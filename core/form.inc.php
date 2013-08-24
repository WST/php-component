<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilya I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class Form
{
	private $tag = NULL;
	
	public function __construct($from_tag = NULL, $type = 'form') {
		if(is_null($from_tag)) {
			$this->tag = new XMLTag('x');
			$this->tag->insertAttribute('type', $type);
			$this->tag->insertAttribute('xmlns', 'jabber:x:data');
		} else {
			$this->tag = $from_tag;
		}
	}
	
	public function __destruct() {
		
	}
	
	public function registerTemplate() {
		$field = new XMLTag('field');
		$value = new XMLTag('value');
		$field->insertAttribute('type', 'hidden');
		$field->insertAttribute('var', 'FORM_TYPE');
		$value->insertCharacterData('jabber:iq:register');
		$field->insertChildElement($value);
		$this->tag->insertChildElement($field);
	}
	
	public function setTitle($title_text) {
		$this->tag->deleteChild('title');
		$title = new XMLTag('title');
		$title->insertCharacterData($title_text);
		$this->tag->insertChildElement($title);
	}
	
	public function setInstructions($instructions_text) {
		$this->tag->deleteChild('instructions');
		$instructions = new XMLTag('instructions');
		$instructions->insertCharacterData($instructions_text);
		$this->tag->insertChildElement($instructions);
	}
	
	public function title() {
		return $this->tag->getChildValue('title');
	}
	
	public function instructions() {
		return $this->tag->getChildValue('instructions');
	}
	
	public static function fromIQStanza(Stanza & $stanza) {
		return new self($stanza->tag()->getChild('query/x'));
	}
	
	public function insertLineEdit($var, $label, $value, $required = false) {
		$field = new XMLTag('field');
		$field->insertAttribute('type', 'text-single');
		$field->insertAttribute('var', $var);
		$field->insertAttribute('label', $label);
		$field->insertChildElement(XMLTag::newWithCharacterData('value', $value));
		
		if($required) {
			$field->insertChildElement(new XMLTag('required'));
		}
		
		$this->tag->insertChildElement($field);
	}
	
	public function insertTextEdit($var, $label, $value, $required = false) {
		$field = new XMLTag('field');
		$field->insertAttribute('type', 'text-multi');
		$field->insertAttribute('var', $var);
		$field->insertAttribute('label', $label);
		$field->insertChildElement(XMLTag::newWithCharacterData('value', $value));
		
		if($required) {
			$field->insertChildElement(new XMLTag('required'));
		}
		
		$this->tag->insertChildElement($field);
	}
	
	public function insertList($var, $label, array $values, $default_value, $required = false, $allow_multiple = false) {
		$field = new XMLTag('field');
		$field->insertAttribute('type', 'list-single');
		$field->insertAttribute('var', $var);
		$field->insertAttribute('label', $label);
		$field->insertChildElement(XMLTag::newWithCharacterData('value', $default_value));
		
		foreach($values as $k => $v) {
			$option = new XMLTag('option');
			$option->insertAttribute('label', $k);
			$option->insertChildElement(XMLTag::newWithCharacterData('value', $v));
			$field->insertChildElement($option);
		}
		
		if($required) {
			$field->insertChildElement(new XMLTag('required'));
		}
		
		$this->tag->insertChildElement($field);
	}
	
	public function insertCheckBox($var, $label, $value, $required = false) {
		$field = new XMLTag('field');
		$field->insertAttribute('type', 'boolean');
		$field->insertAttribute('var', $var);
		$field->insertAttribute('label', $label);
		
		$field->insertChildElement(XMLTag::newWithCharacterData('value', $value ? '1' : '0'));
		
		if($required) {
			$field->insertChildElement(new XMLTag('required'));
		}
		
		$this->tag->insertChildElement($field);
	}
	
	public function value($variable, $default_value = NULL) {
		$field = $this->tag->getChildByAttribute('field', 'var', $variable);
		return is_null($field) ? $default_value : $field->getChildValue('value', $default_value);
	}
	
	public function & tag() {
		return $this->tag;
	}
	
	public function __toString() {
		return (string) $this->tag;
	}
}

?>