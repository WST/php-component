<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

define('PHP_COMPONENT_LOG_SYSLOG', 1);
define('PHP_COMPONENT_LOG_FILE', 2);
define('PHP_COMPONENT_LOG_CONSOLE', 3);

define('PHP_COMPONENT_MESSAGE_INFO', 1);
define('PHP_COMPONENT_MESSAGE_WARNING', 2);
define('PHP_COMPONENT_MESSAGE_ERROR', 3);

//require __DIR__ . '/bsmimage/image.inc.php';
require __DIR__ . '/core/xmltag.inc.php';
require __DIR__ . '/core/jid.inc.php';
require __DIR__ . '/core/vcard.inc.php';
require __DIR__ . '/core/avatar.inc.php';
require __DIR__ . '/core/stanza.inc.php';
require __DIR__ . '/core/form.inc.php';
require __DIR__ . '/core/command.inc.php';
require __DIR__ . '/core/reply.inc.php';

class Component
{
	private $hostname = '';
	private $port = 0;
	private $component_name = '';
	private $password = '';
	
	private $type = 'generic';
	private $category = 'component';
	
	private $socket = NULL;
	private $parser = NULL;
	private $depth = 0;
	private $stack = array();
	
	private $handlers = array();
	private $version = array();
	private $stats = array();
	private $component_title = 'Some component';
	private $registration_form = NULL;
	private $features = array();
	private $logging_mode = 0;
	private $log_filename = '';
	private $log_file = NULL;
	private $i = 0; // used for stanza identifiers
	private $disconnect = false; // Whether to disconnect or not
	private $result = 0; // Execution result
	private $ibstreams = array(); // In-Band data streams
	private $files = array(); // file names
	private $file_handler = NULL; // incoming file handler
	private $signal_handlers_installed = false;
	private $timers = array();
	private $execution_start = 0;
	private $commands = array();
	
	/**
	* Create a component API
	* @param string hostname to connect to
	* @param int port number to connect to
	* @param string Jabber ID of the component
	* @param string password for the handshake
	*/
	public function __construct($hostname, $port, $component_name, $password) {
		$this->hostname = $hostname;
		$this->port = $port;
		$this->component_name = $component_name;
		$this->password = $password;
		
		if(!function_exists('xml_parser_create')) {
			throw new \Exception('PHP XML Parser is not available!');
		}
		
		if(!function_exists('socket_create')) {
			throw new \Exception('PHP Sockets extension is not available!');
		}
		
		$this->version['name'] = 'php-component';
		$this->version['version'] = '0.1';
		$this->version['os'] = php_uname('s') . ' ' . php_uname('r');
		
		$this->handlers['handshake'] = array($this, 'handleSuccessfulHandshake');
		$this->handlers['stream:error'] = array($this, 'handleFailedHandshake');
		
		$this->execution_start = time();
	}
	
	public function __destruct() {
		@ socket_write($this->socket, '</stream:stream>');
		@ socket_close($this->socket);
		@ xml_parser_free($this->parser);
		if(!is_null($this->log_file)) {
			@ flock($this->log_file, LOCK_UN);
			@ fclose($this->log_file);
		}
	}
	
	private function resetParser() {
		@ xml_parser_free($this->parser);
		$this->depth = 0;
		$this->stack = array();
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, 'handleOpenTag', 'handleCloseTag');
		xml_set_character_data_handler($this->parser, 'handleCharacterData');
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parser_set_option($this->parser, XML_OPTION_SKIP_WHITE, 0);
		xml_parser_set_option($this->parser, XML_OPTION_TARGET_ENCODING, 'UTF-8');
	}
	
	private function handleSignal($signo) {
		$this->disconnect();
	}
	
	/**
	* Get an unique id to use in stanzas
	* @return string unique id
	*/
	public function id() {
		return 'pc_' . ++ $this->i;
	}
	
	/**
	* Set component type for the service discovery
	* @param string requested component type
	*/
	public function setComponentType($newtype) {
		$this->type = $newtype;
	}
	
	/**
	* Set component category for the service discovery
	* @param string requested component category
	*/
	public function setComponentCategory($newcategory) {
		$this->category = $newcategory;
	}
	
	/**
	* Get component’s JID given to the constructor
	* @return component’s name (JID)
	*/
	public function componentName() {
		return $this->component_name;
	}
	
	protected function feed($data) {
		if(!xml_parse($this->parser, $data)) {
			throw new \Exception('Failed to parse XML!');
		}
	}
	
	/**
	* Send RAW XML code to the Jabber/XMPP server
	* @param string RAW data to be sent
	* @note you may pass an instance of Stanza or XMLTag as well
	* @return number of bytes written, or false on error
	*/
	public function send($data) {
		return socket_write($this->socket, (string) $data);
	}
	
	public function uptime() {
		return time() - $this->execution_start;
	}
	
	/**
	* Disconnect from the Jabber/XMPP server
	*/
	public function disconnect() {
		$this->log('shutting down requested', PHP_COMPONENT_MESSAGE_WARNING, true);
		$this->disconnect = true;
	}

	/**
	* Run event processing loop
	* @return int execution result (0 = no error)
	*/
	public function run() {
		$this->resetParser();
		if(function_exists('pcntl_signal')) {
			if(! $this->signal_handlers_installed) {
				pcntl_signal(SIGTERM, array($this, 'handleSignal'), false);
				pcntl_signal(SIGINT, array($this, 'handleSignal'), false);
				pcntl_signal(SIGHUP, array($this, 'handleSignal'), false);
				pcntl_signal(SIGUSR1, array($this, 'handleSignal'), false);
			}
			pcntl_signal_dispatch();
			// Следующее нужно, если обработчик сигнала надумал нас отключить
			if($this->disconnect) {
				return $this->result = 0; // Если мы сами отключились, то это можно считать успехом по определению
			}
		}
		
		if(!$this->socket = @ socket_create(AF_INET, SOCK_STREAM, SOL_TCP)) {
			$this->result = socket_last_error($this->socket);
			$errstr = socket_strerror($this->result);
			@ socket_clear_error($this->socket);
			$this->log("error {$this->result}: $errstr", PHP_COMPONENT_MESSAGE_ERROR, true);
			return $this->result;
		}
		
		if(! @ socket_connect($this->socket, $this->hostname, $this->port)) {
			$this->result = socket_last_error($this->socket);
			$errstr = socket_strerror($this->result);
			@ socket_clear_error($this->socket);
			$this->log("error {$this->result}: $errstr", PHP_COMPONENT_MESSAGE_ERROR, true);
			@ socket_close($this->socket);
			return $this->result;
		}
		
		$this->send('<stream:stream xmlns="jabber:component:accept" xmlns:stream="http://etherx.jabber.org/streams" to="' . $this->component_name . '">');
		
		socket_set_nonblock($this->socket);
		
		while(true) {
			$read = array($this->socket);
			$write = $except = NULL;
			
			if(($socket = @ socket_select($read, $write, $except, 1)) === false) {
				if(function_exists('pcntl_signal_dispatch')) {
					pcntl_signal_dispatch();
				}
				@ socket_close($this->socket);
				return $this->result;
			}
			
			if($socket === 0) {
				if(function_exists('pcntl_signal_dispatch')) {
					pcntl_signal_dispatch();
				}
				$this->checkTimers();
				continue;
			}
			
			if(($buf = @ socket_read($this->socket, 1024, PHP_BINARY_READ)) === false) {
				if(function_exists('pcntl_signal_dispatch')) {
					pcntl_signal_dispatch();
				}
				if(($this->result = socket_last_error($this->socket)) === 4) {
					@ socket_clear_error($this->socket);
					$this->result = 0;
					break; // корректное отключение
				}
				if(($this->result = socket_last_error($this->socket)) === 11) {
					continue; // системный вызов прерван по сигналу
				}
				$errstr = socket_strerror($this->result);
				$this->log("error {$this->result}: $errstr", PHP_COMPONENT_MESSAGE_ERROR, true);
				@ socket_clear_error($this->socket);
				@ socket_close($this->socket);
				return $this->result;
			}
			if($buf == '') {
				// Не совсем понятно, почему при закрытии соединения сервером не генерируется никакая ошибка
				// Возможно, это некорректный способ определить, отключили ли нас, надо ещё уточнить
				$this->log('the remote server has dropped the connection', PHP_COMPONENT_MESSAGE_ERROR, true);
				return 104; // код ошибки connection reset by peer
			}
			$this->feed($buf);
			// Следующее сработает, если мы отключились в обработчике некоторой станзы
			if($this->disconnect) {
				$this->result = 0;
				break;
			}
		}
		
		@ socket_close($this->socket);
		return $this->result;
	}

	private function handleStreamStart(XMLTag $tag) {
		$this->send('<handshake>' . sha1($tag->getAttribute('id') . $this->password) . '</handshake>');
	}
	
	private function handleFailedHandshake() {
		$this->log('failed to authenticate', PHP_COMPONENT_MESSAGE_ERROR, true);
	}
	
	private function handleSuccessfulHandshake() {
		$this->log('authenticated', PHP_COMPONENT_MESSAGE_INFO, true);
	}
	
	private function handleStreamEnd($tag_name) {
		@ fclose($this->socket);
	}
	
	public function registerFileHandler($callback) {
		$this->file_handler = $callback;
		$this->handlers['iq/open'] = array($this, 'handleOpenFileStream');
		$this->handlers['iq/close'] = array($this, 'handleCloseFileStream');
		$this->handlers['iq/data'] = array($this, 'handleFileChunk');
		$this->handlers['iq/si'] = array($this, 'handleStreamInitiation');
	}
	
	private function handleStreamInitiation(Stanza $stanza) {
		$si = $stanza->tag()->getChild('si');
		$sid = $si->getAttribute('id');
		$file = $si->getChild('file');
		if(is_null($file)) return false;
		$this->files[$sid]['filename'] = $file->getAttribute('name');
		$this->files[$sid]['description'] = $file->getChildValue('desc');
		$stanza->reply()->fallbackToInBand();
	}
	
	private function handleOpenFileStream(Stanza $stanza) {
		$open = $stanza->tag()->getChild('open');
		$sid = $open->getAttribute('sid', $this->id());
		$container = $open->getAttribute('stanza');
		$this->ibstreams[$sid] = @ fopen($this->files[$sid]['temp'] = tempnam('/tmp', $this->files[$sid]['filename']), 'w');
		$stanza->reply()->agree();
	}
	
	private function handleCloseFileStream(Stanza $stanza) {
		$sid = $stanza->tag()->getChild('close')->getAttribute('sid', $this->id());
		@ fclose($this->ibstreams[$sid]);
		$stanza->reply()->agree();
		$this->log('incoming file saved as ' . $this->files[$sid]['temp'], PHP_COMPONENT_MESSAGE_WARNING, true);
		call_user_func($this->file_handler, $this->files[$sid]['temp'], $this->files[$sid]['filename'], $this->files[$sid]['description'], $stanza->from(), $stanza->to());
		@ unlink($this->files[$sid]['temp']);
		unset($this->files[$sid]);
		unset($this->ibstreams[$sid]);
	}
	
	private function handleFileChunk(Stanza $stanza) {
		@ fputs($this->ibstreams[$stanza->tag()->getChild('data')->getAttribute('sid', $this->id())], base64_decode($stanza->tag()->getChildValue('data')));
		if($stanza->name() == 'iq') {
			$stanza->reply()->agree();
		}
	}
	
	private function handleOpenTag($parser, $tag_name, $attributes) {
		$this->depth ++;
		if($this->depth == 1) {
			return $this->handleStreamStart(new XMLTag($tag_name, $attributes));
		}
		$this->stack[$this->depth] = new XMLTag($tag_name, $attributes);
	}
	
	private function handleCloseTag($parser, $tag_name) {
		if($this->depth == 1) {
			return $this->handleStreamEnd($tag_name);
		}
		$current = array_pop($this->stack);
		isset($this->stack[$this->depth - 1]) ? $this->stack[$this->depth - 1]->insertChildElement($current) : $this->handleStanza(new Stanza($this, $current));
		$this->depth --;
	}
	
	private function handleCharacterData($parser, $data) {
		$this->stack[$this->depth]->insertCharacterData($data);
	}
	
	private function handleComponentIQGet(Stanza $stanza) {
		$query_type = $stanza->queryType();
		switch($query_type) {
			case 'http://jabber.org/protocol/stats':
				return $stanza->reply()->myStats($this->stats);
			break;
			case 'jabber:iq:last':
				return $stanza->reply()->lastActivity($this->uptime());
			break;
			case 'jabber:iq:version':
				return $stanza->reply()->myVersion($this->version['name'], $this->version['version'], $this->version['os']);
			break;
			case 'jabber:iq:register':
				return $stanza->reply()->myRegistrationForm($this->registration_form);
			break;
			case 'http://jabber.org/protocol/disco#info':
				return $stanza->reply()->myInfo($this->component_title, $this->features, $this->category, $this->type);
			break;
			case 'http://jabber.org/protocol/disco#items':
				return $stanza->reply()->myItems();
			break;
			default:
				if($stanza->tag()->hasChild('ping')) {
					return $stanza->reply()->pong();
				}
				if($stanza->tag()->hasChild('vCard')) {
					if(is_callable(@ $this->handlers['iq/vCard'])) {
						return call_user_func($this->handlers['iq/vCard'], $stanza);
					}
					return $stanza->reply()->vCardServiceUnavailable();
				}
				$this->log('unknown information query (' . $query_type . ') from: ' . $stanza->from(), PHP_COMPONENT_MESSAGE_WARNING, true);
				if($stanza->type() != 'error') {
					return $stanza->reply()->serviceUnavailable();
				}
			break;
		}
	}
	
	private function handleComponentIQSet(Stanza $stanza) {
		$query_type = $stanza->queryType();
		switch($query_type) {
			case 'jabber:iq:register':
				return is_callable(@ $this->handlers['iq/query']['jabber:iq:register']) ? call_user_func($this->handlers['iq/query']['jabber:iq:register'], $stanza) : $stanza->reply()->serviceUnavailable();
			break;
		}
	}
	
	private function handleComponentIQResult(Stanza $stanza) {
		if($stanza->tag()->hasChild('vCard')) {
			if(is_callable(@ $this->handlers['iq/vCard'])) {
				return call_user_func($this->handlers['iq/vCard'], $stanza);
			}
		}
	}
	
	private function handleComponentIQ(Stanza $stanza) {
		switch($stanza->type()) {
			case 'set': return $this->handleComponentIQSet($stanza); break;
			case 'get': return $this->handleComponentIQGet($stanza); break;
			case 'result': return $this->handleComponentIQResult($stanza); break;
		}
	}
	
	private function handleIQ(Stanza $stanza) {
		// XEP-0050 command execution
		if(!is_null($command = $stanza->tag()->getChild('command'))) {
			(isset($this->commands[$node = $command->getAttribute('node')]) && is_callable($this->commands[$node][1])) ? call_user_func($this->commands[$node][1], $stanza) : $stanza->reply()->serviceUnavailable();
		}
		
		if(!is_null($query = $stanza->tag()->getChild('query')) && $query->getAttribute('node') == 'http://jabber.org/protocol/commands') {
			return $stanza->reply()->myCommands($this->commands);
		}
		
		if($stanza->to() == $this->component_name) {
			return $this->handleComponentIQ($stanza);
		}
		
		if($query_type = $stanza->queryType()) {
			if(is_callable(@ $this->handlers['iq/query'][$query_type])) {
				return call_user_func($this->handlers['iq/query'][$query_type], $stanza);
			} else {
				$this->log("no IQ query handler for query xmlns = $query_type", PHP_COMPONENT_MESSAGE_WARNING, true);
				if($stanza->type() != 'error') {
					return $stanza->reply()->serviceUnavailable();
				}
			}
		}
		if($stanza->tag()->hasChild('vCard')) {
			return is_callable(@ $this->handlers['iq/vCard']) ? call_user_func($this->handlers['iq/vCard'], $stanza) : $stanza->reply()->vCardServiceUnavailable();
		}
		foreach(array('open', 'close', 'data', 'si') as $k => $v) {
			if($stanza->tag()->hasChild($v)) {
				return is_callable(@ $this->handlers["iq/$v"]) ? call_user_func($this->handlers["iq/$v"], $stanza) : $stanza->reply()->serviceUnavailable();
			}
		}
	}
	
	private function handleStanza(Stanza $stanza) {
		if(!in_array($name = $stanza->name(), array('stream:error', 'handshake'))) {
			$this->log("got <$name> from {$stanza->from()} to {$stanza->to()}", PHP_COMPONENT_MESSAGE_INFO, true);
		}
		if(!is_callable(@ $this->handlers[$stanza->name()])) {
			if($stanza->name() == 'iq') {
				return $this->handleIQ($stanza);
			}
			$this->log('no handler for stanza: ' . $stanza->name(), PHP_COMPONENT_MESSAGE_WARNING, true);
		} else {
			if(!is_null($this->file_handler) && $stanza->name() == 'message' && $stanza->tag()->hasChild('data')) {
				return $this->handleFileChunk($stanza);
			}
			return call_user_func($this->handlers[$stanza->name()], $stanza);
		}
	}
	
	/**
	* Register a callback function that should be called on incoming <message> stanzas
	* @param mixed callback to be registered
	*/
	public function registerMessageHandler($callback) {
		$this->handlers['message'] = $callback;
	}
	
	/**
	* Register a callback function that should be called on incoming <presence> stanzas
	* @param mixed callback to be registered
	*/
	public function registerPresenceHandler($callback) {
		$this->handlers['presence'] = $callback;
	}
	
	/**
	* Register a callback function that should be called on incoming <iq> stanzas with <query> tag inside
	* @param mixed callback to be registered
	* @param string XMLNS of query tags that should be processed with given callback function
	* @note you may register as many callbacks as you want here
	*/
	public function registerIQQueryHandler($callback, $query_xmlns) {
		$this->handlers['iq/query'][$query_xmlns] = $callback;
	}
	
	/**
	* Register a callback function that should be called on incoming <iq> stanzas with <vCard> tag inside
	* @param mixed callback to be registered
	*/
	public function registerIQVCardHandler($callback) {
		$this->handlers['iq/vCard'] = $callback;
	}
	
	/**
	* Register a callback function that should be called after successful connection to the Jabber/XMPP server
	* @param mixed callback to be registered
	* @note you should always use this function in any component
	*/
	public function registerSuccessfulHandshakeHandler($callback) {
		$this->handlers['handshake'] = $callback;
	}
	
	/**
	* Register a callback function that should be called after failure in communication with the Jabber/XMPP server
	* @param mixed callback to be registered
	*/
	public function registerStreamErrorHandler($callback) {
		$this->handlers['stream:error'] = $callback;
	}
	
	public function hasFeature($feature_name) {
		return in_array($feature_name, $this->features);
	}
	
	/**
	* Register a feature supported by the component (for service discovery)
	* @param string feature to be registered
	*/
	public function registerFeature($feature_name) {
		if(!$this->hasFeature($feature_name)) {
			$this->features[] = $feature_name;
		}
	}
	
	/**
	* Register a callback function that should be called on registration requests
	* @param mixed callback to be registered
	*/
	public function registerInbandRegistrationHandler($callback) {
		$this->handlers['iq/query']['jabber:iq:register'] = $callback;
	}
	
	public function registerCommandHandler($node, $command_name, $callback) {
		$this->registerFeature('http://jabber.org/protocol/commands');
		$this->commands[$node] = array($command_name, $callback);
	}
	
	/**
	* Set component software version that will be displayed to anyone who requests it
	* @param string application name
	* @param string application version
	*/
	public function setSoftwareVersion($name, $version) {
		$this->version['name'] = $name;
		$this->version['version'] = $version;
	}
	
	/**
	* Set the title of the component that will be shown in the service discovery
	* @param string component’s title
	*/
	public function setComponentTitle($title) {
		$this->component_title = $title;
	}
	
	/**
	* Add statistic information
	* @param string name of the stats variable
	* @param string value
	* @param string unit
	*/
	public function setStats($stat_name, $value, $units) {
		$this->stats[$stat_name] = array($value, $units);
	}
	
	/**
	* Fetch the component’s registration form
	* @note on the first call this function will create the form and register a 'jabber:iq:register' feature, so, you don’t have to worry about it
	* @return Form a link to a Form instance
	*/
	public function form() {
		if(is_null($this->registration_form)) {
			$this->registration_form = new Form();
			$this->registration_form->registerTemplate();
			$this->registerFeature('jabber:iq:register');
		}
		return $this->registration_form;
	}
	
	public function createCommand($node, $create_form = true) {
		$command = Command::createNew($create_form);
		$command->setNode($node);
		return $command;
	}
	
	/**
	* Set logging mode
	* @param int logging mode
	* @param string filename (only if $mode = 2)
	*/
	public function setLoggingMode($mode, $filename = NULL) {
		$this->logging_mode = $mode;
		$this->log_filename = $filename;
	}
	
	/**
	* Log a message
	* @note you should always use this function if your component generates some output to the console
	* @param string message
	* @param bool internal flag, should NOT be specified in your code!
	*/
	public function log($message, $type = PHP_COMPONENT_MESSAGE_INFO, $internal = false) {
		$message = '[' . date('H:i:s', time()) . ($internal ? '] <core> ' : '] <app> ') . "$message\n";
		switch($this->logging_mode) {
			case PHP_COMPONENT_LOG_FILE:
				if(is_null($this->log_file)) {
					$this->log_file = @ fopen($this->log_filename, 'a');
					@ ftruncate($this->log_file, 0);
					@ flock($this->log_file, LOCK_EX);
				}
				@ fputs($this->log_file, $message);
			break;
			case PHP_COMPONENT_LOG_SYSLOG:
				// TODO
			break;
			case PHP_COMPONENT_LOG_CONSOLE:
				if(strtolower(php_uname('s')) == 'linux') {
					switch($type) {
						case PHP_COMPONENT_MESSAGE_INFO:
							echo "\033[22;37m$message\033[0m";
						break;
						case PHP_COMPONENT_MESSAGE_WARNING:
							echo "\033[01;33m$message\033[0m";
						break;
						case PHP_COMPONENT_MESSAGE_ERROR:
							echo "\033[22;31m$message\033[0m";
						break;
					}
				} else {
					echo $message;
				}
			break;
		}
	}
	
	/**
	* Send a presence stanza to some JID
	* @param string target Jabber ID
	* @param string source JID (only left part). If not provided then component’s JID itself is used as the source JID for the stanza.
	*/
	public function sendPresence($to, $from = NULL, $type = NULL) {
		$type = is_null($type) ? '' : " type=\"{$type}\"";
		return $this->send('<presence to="' . htmlspecialchars($to) . '" from="' . (is_null($from) ? $this->component_name : htmlspecialchars("$from@{$this->component_name}")) . '"' . $type . ' />');
	}
	
	/**
	* Send a message stanza to some JID
	*/
	public function sendMessage($body, $to, $item = NULL, $type = NULL, $subject = NULL, $tobare = false) {
		$to = new JID($to);
		$this->send('<message ' . (is_null($type) ? ' ' : 'type="' . $type . '" ') . 'from="' . (is_null($item) ? ($from = $this->component_name) : ($from = $item . '@' . $this->component_name)) . '" to="' . ($tobare ? htmlspecialchars($to->bare()) : htmlspecialchars($to)) . '">' . (is_null($subject) ? '' : '<subject>' . htmlspecialchars($subject) . '</subject>') . '<body>' . htmlspecialchars($body) . '</body></message>');
		$this->log("sent <message> from {$from} to {$to}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	/**
	* Please note: this function returns nothing! It only requests vCard, because the server may not give reply in some cases.
	* You should process incoming vCards in special handler that may be registered using Component::registerIQVCardHandler
	*/
	public function requestVCard($jid, $from = NULL) {
		$this->send('<iq type="get" to="' . htmlspecialchars($jid) . '" id="' . $this->id() . '" from="' . (is_null($from) ? ($from = $this->component_name) : htmlspecialchars($from = "$from@{$this->component_name}")) . '"><vCard xmlns="vcard-temp"/></iq>');
		$this->log("sent <iq> from {$from} to {$jid}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function setTimeout($timeout, $callback) {
		if(!is_callable($callback)) {
			return false;
		}
		$this->timers[] = array(time() + $timeout, $callback);
	}
	
	private function checkTimers() {
		$now = time();
		foreach($this->timers as $k => $v) {
			if($now >= $v[0]) {
				call_user_func($v[1]);
				unset($this->timers[$k]);
			}
		}
	}
	
	public function parseJID($jid) {
		return new JID($jid);
	}
}

?>