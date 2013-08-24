<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilya I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class Reply
{
	private $component = NULL;
	private $stanza = NULL;
	
	public function __construct(Component & $component, Stanza & $stanza) {
		$this->component = & $component;
		$this->stanza = & $stanza;
	}
	
	private function iq($type, $data) {
		return '<iq id="' . htmlspecialchars($this->stanza->id()) . '" to="' . htmlspecialchars($this->stanza->from()) . '" from="' . htmlspecialchars($this->stanza->to()) . '" type="' . htmlspecialchars($type) . '">' . $data . '</iq>';
	}
	
	public function serviceUnavailable() {
		$this->stanza->sendBack($this->iq('error', '<error type="cancel"><service-unavailable xmlns="urn:ietf:params:xml:ns:xmpp-stanzas"/></error>'));
		$this->component->log("sent <iq> (service-unavailable) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function notAcceptable() {
		$this->stanza->sendBack($this->iq('error', '<error type="modify" code="406"><not-acceptable xmlns="urn:ietf:params:xml:ns:xmpp-stanzas"/></error>'));
		$this->component->log("sent <iq> (not-acceptable) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function vCardServiceUnavailable() {
		$this->stanza->sendBack($this->iq('error', '<error type="cancel"><vCard xmlns="vcard-temp"/><service-unavailable xmlns="urn:ietf:params:xml:ns:xmpp-stanzas"/></error>'));
		$this->component->log("sent <iq> (service-unavailable) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myInfo($name, & $features, $category = 'gateway', $type = '') {
		$reply = '<query xmlns="http://jabber.org/protocol/disco#info"><identity category="' . htmlspecialchars($category) . '" name="' . htmlspecialchars($name) . '" type="' . htmlspecialchars($type) . '"/><feature var="http://jabber.org/protocol/disco#info"/><feature var="http://jabber.org/protocol/disco#items"/><feature var="jabber:iq:version"/><feature var="urn:xmpp:ping"/><feature var="vcard-temp"/>';
		foreach($features as $key => $value) {
			$reply .= '<feature var="' . htmlspecialchars($value) . '" />';
		}
		$reply .= '</query>';
		$this->stanza->sendBack($this->iq('result', $reply));
		$this->component->log("sent <iq> (disco#info) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myItems() {
		// TODO: my items
		$this->stanza->sendBack($this->iq('result', '<query xmlns="http://jabber.org/protocol/disco#items" />'));
		$this->component->log("sent <iq> (disco#items) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myCommands(& $commands) {
		$items = '';
		foreach($commands as $node => $command) {
			$items .= '<item jid="' . $this->stanza->to() . '" node="' . $node . '" name="' . $command[0] . '" />';
		}
		$this->stanza->sendBack($this->iq('result', '<query xmlns="http://jabber.org/protocol/disco#items" node="http://jabber.org/protocol/commands">' . $items . '</query>'));
		$this->component->log("sent <iq> (ad-hoc command list) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myVersion($component_name, $version, $os) {
		$this->stanza->sendBack($this->iq('result', '<query xmlns="jabber:iq:version"><name>' . htmlspecialchars($component_name) . '</name><version>' . htmlspecialchars($version) . '</version><os>' . htmlspecialchars($os) . '</os></query>'));
		$this->component->log("sent <iq> (version) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myStats(& $stats_info, $stat_type_requested = NULL) {
		$reply = '<query xmlns="http://jabber.org/protocol/stats">';
		foreach($stats_info as $key => $value) {
			if(is_null($stat_type_requested)) {
				$reply .= '<stat name="' . $key . '" value="' . htmlspecialchars(@ $value[0]) . '" units="' . htmlspecialchars(@ $value[1]) . '" />';
			} elseif($key == $stat_type_requested) {
				$reply .= '<stat name="' . $key . '" value="' . htmlspecialchars(@ $value[0]) . '" units="' . htmlspecialchars(@ $value[1]) . '" />';
			}
		}
		$reply .= '</query>';
		$this->stanza->sendBack($this->iq('result', $reply));
		$this->component->log("sent <iq> (stats) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function myRegistrationForm(Form & $form) {
		// TODO: регистрация для клиентов, не поддерживающих XEP-0004, как в Spectrum
		$reply = '<query xmlns="jabber:iq:register">';
		$reply .= (string) $form;
		$reply .= '</query>';
		$this->stanza->sendBack($this->iq('result', $reply));
		$this->component->log("sent <iq> (registration) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function agree() {
		return $this->pong();
	}
	
	public function pong() {
		$this->stanza->sendBack('<iq from="' . htmlspecialchars($this->stanza->to()) . '" to="' . htmlspecialchars($this->stanza->from()) . '" id="' . htmlspecialchars($this->stanza->id()) . '" type="result" />');
		$this->component->log("sent <iq> (empty result) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function registrationSuccess() {
		return $this->pong();
	}
	
	public function registrationFailure() {
		return $this->notAcceptable();
	}
	
	public function subscribedPresence($item) {
		$this->stanza->sendBack('<presence from="' . $item . '@' . $this->component->componentName() . '" type="subscribed" to="' . htmlspecialchars($this->stanza->from()) . '" />');
		$this->component->log("sent <presence> (subscribed) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function sendNewRosterItem($item) {
		$this->stanza->sendBack('<presence from="' . $item . '@' . $this->component->componentName() . '" type="subscribe" to="' . htmlspecialchars($this->stanza->from()) . '" />');
		$this->component->log("sent <presence> (subscribe) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function sendPresence($item) {
		$this->stanza->sendBack('<presence from="' . $item . '@' . $this->component->componentName() . '" to="' . htmlspecialchars($this->stanza->from()) . '"><status></status></presence>');
		$this->component->log("sent <presence> from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function sendMessage($body, $item = NULL, $type = NULL, $subject = NULL, $tobare = false) {
		$this->stanza->sendBack('<message ' . (is_null($type) ? ' ' : 'type="' . $type . '" ') . 'from="' . (is_null($item) ? $this->component->componentName() : $item . '@' . $this->component->componentName()) . '" to="' . ($tobare ? htmlspecialchars($this->stanza->from()->bare()) : htmlspecialchars($this->stanza->from())) . '">' . (is_null($subject) ? '' : '<subject>' . htmlspecialchars($subject) . '</subject>') . '<body>' . htmlspecialchars($body) . '</body></message>');
		$this->component->log("sent <message> from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function fallbackToInBand() {
		$this->stanza->sendBack($this->iq('result', "<si xmlns='http://jabber.org/protocol/si'><file xmlns='http://jabber.org/protocol/si/profile/file-transfer'/><feature xmlns='http://jabber.org/protocol/feature-neg'><x xmlns='jabber:x:data' type='submit'><field var='stream-method'><value>http://jabber.org/protocol/ibb</value></field></x></feature></si>"));
		$this->component->log("sent <iq> (use ibb) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function sendVCard($data) {
		$this->stanza->sendBack('<iq from="' . htmlspecialchars($this->stanza->to()) . '" to="' . htmlspecialchars($this->stanza->from()) . '" id="' . htmlspecialchars($this->stanza->id()) . '" type="result">' . $data . '</iq>');
		$this->component->log("sent <iq> (vcard) from {$this->stanza->to()} to {$this->stanza->from()}", true, PHP_COMPONENT_MESSAGE_INFO);
	}
	
	public function lastActivity($seconds) {
		$this->stanza->sendBack($this->iq('result', '<query xmlns="jabber:iq:last" seconds="' . $seconds . '"/>'));
	}
	
	public function sendCommand(Command $command) {
		$this->stanza->sendBack($this->iq('result', (string) $command));
	}
}

?>