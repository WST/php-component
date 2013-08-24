#!/usr/bin/php
<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

// Ability to start script in background mode
if($daemon = (in_array('--daemonize', $argv) || in_array('-d', $argv))) {
	if(!function_exists('pcntl_fork')) {
		die("PHP should be compiled --with-pcntl to be able to use daemonizing!\n");
	}
	if($pid = pcntl_fork()) {
		die("\n Started with pid: $pid\n\n");
	}
} else {
	echo "\n PHP-Component example bot\n (C) 2011 SmartCommunity <http://jsmart.web.id>\n Tip: use --daemonize or -d flag to run in background.\n\n";
}

require realpath(__DIR__ . '/../../component.inc.php');

// Some environment settings
ini_set('display_errors', $daemon ? 'Off' : 'On');
ini_set('display_startup_errors', 'On');
error_reporting($daemon ? 0 : E_ALL);
set_time_limit(0);
date_default_timezone_set('Asia/Jakarta');

class Bot
{
	private $config = array();
	private $component = NULL;
	private $db = NULL;
	
	public function __construct($config_filename, $silent = false) {
		@ is_readable($f = __DIR__ . "/$config_filename") ? ($this->config = @ parse_ini_file($f, true)) : die("Error: configuration file <$config_filename> cannot be read!\n");
		if(!@ is_writeable($datadir = __DIR__ . '/data/')) {
			die("Error: data directory is not writeable!\n");
		}
		
		if(!class_exists('PDO')) {
			die("Error: PDO is not available!\n");
		}
		
		try {
			$this->db = new PDO('sqlite:' . $datadir . @ $this->config['database']['database_filename']);
			$this->db->exec('CREATE TABLE IF NOT EXISTS rooms (id_room INTEGER PRIMARY KEY AUTOINCREMENT, room_jid VARCHAR(255), room_botnick VARCHAR(255))');
		} catch(PDOException $e) {
			die("PDO error: {$e->getMessage()}\n");
		}
		
		try {
			$this->component = new BSM\Component (
				@ $this->config['xmpp']['hostname'],
				@ $this->config['xmpp']['port'],
				@ $this->config['xmpp']['component_name'],
				@ $this->config['xmpp']['password']
			);
		} catch(Exception $e) {
			die("Fatal error: {$e->getMessage()}\n");
		}
		
		$this->component->setLoggingMode($silent ? PHP_COMPONENT_LOG_FILE : PHP_COMPONENT_LOG_CONSOLE);

		$this->component->setSoftwareVersion('PHP-Component example bot', '0.1');
		$this->component->setComponentType('bot');
		$this->component->setComponentTitle(@ $this->config['xmpp']['component_title']);
		
		$this->component->registerPresenceHandler(array($this, 'myPresenceHandler'));
		$this->component->registerMessageHandler(array($this, 'myMessageHandler'));
		$this->component->registerSuccessfulHandshakeHandler(array($this, 'myHandshakeHandler'));
		$this->component->registerStreamErrorHandler(array($this, 'myErrorHandler'));
		$this->component->registerInbandRegistrationHandler(array($this, 'myRegistrationHandler'));
		$this->component->registerIQVCardHandler(array($this, 'myVCardHandler'));

		$this->component->form()->setTitle('Registration');
		$this->component->form()->setInstructions('You may invite your test bot to a public MUC room with this form.');
		$this->component->form()->insertLineEdit('room', 'Room JID', 'tangerang@conference.jsmart.web.id', true);
		$this->component->form()->insertLineEdit('nick', 'Bot nick', 'myTestBot', true);
	}
	
	public function __destruct() {
		
	}
	
	public function myVCardHandler($stanza) {
	}
	
	public function myHandshakeHandler($stanza) {
		$this->component->log('successfully connected to the server!');
		$statement = $this->db->prepare('SELECT * FROM rooms');
		$statement->execute();
		while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			$this->joinRoom($row['room_jid'], $row['room_botnick'], false);
		}
		$statement->closeCursor();
	}
	
	public function myErrorHandler($stanza) {
		$this->component->log('stream error, check your component password!');
	}
	
	public function myPresenceHandler($stanza) {
		$this->component->log("presence from {$stanza->from()}");
	}
	
	public function myMessageHandler($stanza) {
		$this->component->log("message from {$stanza->from()}");
	}
	
	public function myRegistrationHandler($stanza) {
		$room = @ $stanza->form()->value('room');
		$nick = @ $stanza->form()->value('nick');
		
		if(preg_match('#^([^@:]+)@([^@:<>\\/]+)$#iU', $room)) {
			$this->component->log("successful registration from {$stanza->from()}");
			$this->joinRoom($room, $nick);
			return $stanza->reply()->registrationSuccess();
		}
		
		$stanza->reply()->registrationFailure();
		$this->component->log("failed registration from {$stanza->from()}");
	}
	
	private function joinRoom($jid, $nick, $save = true) {
		if($save) {
			$statement = $this->db->prepare('REPLACE INTO rooms (room_jid, room_botnick) VALUES (:jid, :nick)');
			$statement->bindParam(':jid', $jid);
			$statement->bindParam(':nick', $nick);
			$statement->execute();
		}
		$this->component->sendPresence("$jid/$nick");
	}
	
	public function execute() {
		while(($result = $this->component->run()) !== 0) {
			$this->component->log('disconnected. Trying to connect again in 10 seconds.');
			sleep(10);
		}
		$this->component->log('shutting down on demand');
	}
}

$bot = new Bot('config.ini', $daemon);
$bot->execute();

?>
