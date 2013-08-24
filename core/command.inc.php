<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilya I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class Command
{
	private $form = NULL;
	private $cmdtag = NULL;
	
	public function __construct(XMLTag $command) {
		$this->cmdtag = $command;
		$this->form = is_null($form = $command->getChildByAttribute('x', 'xmlns', 'jabber:x:data')) ? NULL : new Form($form);
	}
	
	public function createNew($create_form = true) {
		$command = new self(new XMLTag('command', array('xmlns' => 'http://jabber.org/protocol/commands')));
		if($create_form) {
			$command->createForm('result');
		}
		return $command;
	}
	
	public function action() {
		return $this->tag->getAttribute('action');
	}
	
	public function hasForm() {
		return !is_null($this->form);
	}
	
	public function node() {
		return $this->tag->getAttribute('node');
	}
	
	public function sessionId() {
		return $this->tag->getAttribute('sessionid');
	}
	
	public function status() {
		return $this->tag->getAttribute('status');
	}
	
	public function & form() {
		return $this->form;
	}
	
	public function setNode($node) {
		return $this->cmdtag->insertAttribute('node', $node);
	}
	
	public function setAction($action) {
		return $this->cmdtag->insertAttribute('action', $action);
	}
	
	public function setSessionId($session_id) {
		return $this->cmdtag->insertAttribute('sessionid', $session_id);
	}
	
	public function setStatus($status) {
		return $this->cmdtag->insertAttribute('status', $status);
	}
	
	public function & createForm($type = 'result') {
		$this->form = new Form(NULL, $type);
		$this->cmdtag->insertChildElement($this->form->tag());
		return $this->form;
	}
	
	public function __toString() {
		return (string) $this->cmdtag;
	}
	
	public function resetFields($type = 'result') {
		unset($this->form);
		$this->createForm($type);
	}
	
	public function cancel() {
		$this->setStatus('cancelled');
		return $this;
	}
	
	public function cancelled() {
		return ($this->cmdtag->getAttribute('action') == 'cancel');
	}
	
	public function done($title, $instructions) {
		$this->setStatus('completed');
		$this->resetFields();
		$this->form->setTitle($title);
		$this->form->setInstructions($instructions);
		return $this;
	}
}

?>