<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class VCard
{
	private $tag = NULL;
	private $empty = NULL;
	
	/**
	* vCard class constructor.
	* Accepts a link to an instance of BSMXMLTag that represents <vCard> element of an iq query
	* @param BSMXMLTag vCard tag
	*/
	public function __construct(XMLTag & $vcard_tag) {
		$this->tag = & $vcard_tag;
	}
	
	/**
	* Fetch the character data contained in some element.
	* This function is for internal usage only.
	* @param string the name of the tag whose value is needed
	* @retval string the value or NULL if the value is absent
	*/
	private function getValue($tag_name) {
		return is_null($this->tag) ? $this->empty : $this->tag->getChildValue($tag_name);
	}
	
	public function setEmptyFieldValue($value) {
		$this->empty = $value;
	}
	
	/**
	* Get the avatar from the vCard.
	* Avatar is represented by an instance of BSMAvatar class.
	* @return BSMAvatar the avatar contained in the vCard or NULL if the avatar is absent
	*/
	public function getPhoto() {
		if(is_null($photo = $this->tag->getChild('PHOTO'))) {
			return NULL;
		} else {
			try {
				return new Avatar($photo);
			} catch(Exception $e) {
				return NULL;
			}
		}
	}
	
	/**
	* Get the user’s full name.
	* @return string user’s full name or NULL if it’s not provided
	*/
	public function fullname() {
		return $this->getValue('FN');
	}
	
	/**
	* Get the user’s nick.
	* @return string user’s nick or NULL if it’s not provided
	*/
	public function nickname() {
		return $this->getValue('NICKNAME');
	}
	
	/**
	* Get the user’s birthday.
	* Please note: the birthday should be specified in special format described by RFC 2426, but absolutely most of the
	* Jabber/XMPP client programs ignore it and save unformatted text here.
	* @return string user’s birthday or NULL if it’s not provided
	*/
	public function birthDay() {
		return $this->getValue('BDAY');
	}
	
	/**
	* Get the user’s home address.
	* @return string user’s home address or NULL if it’s not provided
	*/
	public function homeAddress() {
		return $this->getValue('ADR/HOME');
	}
	
	/**
	* Get the user’s additional address line.
	* @return string user’s address or NULL if it’s not provided
	*/
	public function extAddress() {
		return $this->getValue('ADR/EXTADR');
	}
	
	/**
	* Get the user’s street.
	* @return string user’s street or NULL if it’s not provided
	*/
	public function street() {
		return $this->getValue('ADR/STREET');
	}
	
	/**
	* Get the user’s locality (city).
	* @return string user’s locality or NULL if it’s not provided
	*/
	public function locality() {
		return $this->getValue('ADR/LOCALITY');
	}
	
	/**
	* Get the user’s region (state).
	* @return string user’s region or NULL if it’s not provided
	*/
	public function region() {
		return $this->getValue('ADR/REGION');
	}
	
	/**
	* Get the user’s post office code (zip code).
	* @return string user’s post office code or NULL if it’s not provided
	*/
	public function postCode() {
		return $this->getValue('ADR/PCODE');
	}
	
	public function country() {
		return $this->getValue('ADR/CTRY');
	}
	
	public function phoneNumber() {
		return $this->getValue('TEL/NUMBER');
	}
	
	public function email() {
		return $this->getValue('EMAIL/USERID');
	}
	
	public function title() {
		return $this->getValue('TITLE');
	}
	
	public function role() {
		return $this->getValue('ROLE');
	}
	
	public function orgName() {
		return $this->getValue('ORG/ORGNAME');
	}
	
	public function orgUnit() {
		return $this->getValue('ORG/ORGUNIT');
	}
	
	public function url() {
		return $this->getValue('URL');
	}
	
	public function desc() {
		return $this->getValue('DESC');
	}
}

?>