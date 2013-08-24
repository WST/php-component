<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilya I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class JID
{
	private $username = '';
	private $hostname = '';
	private $resource = '';
	private $full = '';
	private $bare = '';
	
	public function __construct($jid) {
		$this->full = $jid;
		$this->bare = $jid;
		if(($p = strpos($jid, '@')) !== false) {
			$this->username = substr($jid, 0, $p);
			$this->bare = $this->username;
			$jid = substr($jid, $p + 1);
		}
		if(($p = strpos($jid, '/')) !== false) {
			$this->hostname = substr($jid, 0, $p);
			$this->resource = substr($jid, $p + 1);
			$this->bare .= '@' . $this->hostname;
		} else {
			$this->hostname = $jid;
			$this->bare .= '@' . $this->hostname;
		}
	}
	
	public function full() {
		return $this->full;
	}
	
	public function bare() {
		return $this->bare;
	}
	
	public function username() {
		return $this->username;
	}
	
	public function hostname() {
		return $this->hostname;
	}
	
	public function resource() {
		return $this->resource;
	}
	
	public function __toString() {
		return (string) $this->full;
	}
}

?>