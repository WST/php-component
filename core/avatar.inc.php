<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class Avatar extends Image
{
	private $filename;
	private $tag;
	
	/**
	* Constructs a BSMAvatar instance from a <PHOTO> tag of the vCard
	* @param BSMXMLTag link to an instance of BSMXMLTag representing <PHOTO> element of the vCard
	*/
	public function __construct(XMLTag & $photo_tag) {
		$this->tag = $photo_tag;
		file_put_contents($this->filename = tempnam(sys_get_temp_dir(), 'xmpp_avatar'), base64_decode($photo_tag->getChildValue('BINVAL')));
		parent::__construct($this->filename);
	}
	
	/**
	* Return the avatar image’s MIME type
	* @return string MIME type
	*/
	public function mimeType() {
		return $this->tag->getChildValue('TYPE');
	}
	
	/**
	* Save the original avatar file without any modification
	* @param string filename to save
	* @return bool true if the file was copied successfully, false on failure
	*/
	public function saveAsIs($filename) {
		return @ copy($this->filename, $filename);
	}
	
	public function __destruct() {
		parent::__destruct();
		@ unlink($this->filename);
	}
}

?>