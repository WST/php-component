<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class Stanza
{
	private $tag;
	private $component;
	
	public function __construct(Component & $component, XMLTag & $tag) {
		$this->tag = & $tag;
		$this->component = & $component;
	}
	
	public function & tag() {
		return $this->tag;
	}
	
	public function name() {
		return $this->tag->name();
	}
	
	public function __toString() {
		return (string) $this->tag;
	}
	
	public function from() {
		return new JID($this->tag->getAttribute('from'));
	}
	
	public function to() {
		return new JID($this->tag->getAttribute('to'));
	}
	
	public function type() {
		return $this->tag->getAttribute('type');
	}
	
	public function id() {
		return $this->tag->getAttribute('id');
	}
	
	public function queryType() {
		return $this->tag->hasChild('query') ? $this->tag->getChild('query')->getAttribute('xmlns') : false;
	}
	
	public function sendBack($data) {
		return $this->component->send($data);
	}
	
	public function reply() {
		return new Reply($this->component, $this);
	}
	
	public function form() {
		return new Form($this->tag->getChild('query/x'));
	}
	
	public function body() {
		return $this->tag->getChildValue('body');
	}
	
	public function vCard() {
		return new VCard($this->tag->getChild('vCard'));
	}
	
	public function command() {
		return (is_null($command = $this->tag->getChild('command'))) ? NULL : new Command($command);
	}
}

?>