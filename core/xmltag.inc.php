<?php

/**
* Simple PHP Jabber/XMPP blocking component implementation
* Implements XEP-0114 <http://xmpp.org/extensions/xep-0114.html>
* © 2011 Ilja I. Averkov <admin@jsmart.web.id>
* © 2011 Irfan Mahfudz Guntur <ayes@jsmart.web.id>
*/

namespace BSM;

class XMLTag
{
	private $tag_name;
	private $attributes = array();
	private $children = array();
	
	public function __construct($tag_name, $attributes = NULL) {
		$this->tag_name = $tag_name;
		if(is_array($attributes)) {
			$this->attributes += $attributes;
		}
	}
	
	public static function newWithCharacterData($tag_name, $cdata) {
		//$tag = new self($tag_name);
		$tag = new self($tag_name);
		$tag->insertCharacterData($cdata);
		return $tag;
	}
	
	public function __toString() {
		$result = "<{$this->tag_name}";
		foreach($this->attributes as $key => $value) {
			$result .= " $key=\"" . htmlspecialchars($value) . "\"";
		}
		if(!count($this->children)) {
			$result .= " />";
		} else {
			$result .= '>';
			foreach($this->children as $key => $value) {
				$result .= is_object($value) ? (string) $value : htmlspecialchars($value);
			}
			$result .= "</{$this->tag_name}>";
		}
		return $result;
	}
	
	public function name() {
		return $this->tag_name;
	}
	
	public function hasAttribute($attribute_name) {
		return isset($this->attributes[$attribute_name]);
	}
	
	public function getAttribute($attribute_name, $default_value = NULL) {
		return isset($this->attributes[$attribute_name]) ? $this->attributes[$attribute_name] : $default_value;
	}
	
	public function insertAttribute($attribute_name, $value) {
		$this->attributes[$attribute_name] = $value;
	}
	
	public function getChild($tag_name) {
		if(($p = strpos($tag_name, '/')) !== false) {
			$mid = $this->getChild(substr($tag_name, 0, $p));
			if(is_null($mid)) {
				return NULL;
			}
			return $mid->getChild(substr($tag_name, $p + 1));
		}
		foreach($this->children as $key => $value) {
			if(is_object($value) && $value->name() == $tag_name) {
				return $value;
			}
		}
		return NULL;
	}
	
	public function getChildByAttribute($tag_name, $attribute, $attribute_value) {
		if(($p = strpos($tag_name, '/')) !== false) {
			$mid = $this->getChildByAttribute(substr($tag_name, 0, $p), $attribute, $attribute_value);
			if(is_null($mid)) {
				return NULL;
			}
			return $mid->getChildByAttribute(substr($tag_name, $p + 1), $attribute, $attribute_value);
		}
		foreach($this->children as $key => $value) {
			if(is_object($value) && $value->name() == $tag_name && $value->getAttribute($attribute, NULL) == $attribute_value) {
				return $value;
			}
		}
		return NULL;
	}
	
	public function hasChild($tag_name, $delete = false) {
		if(($p = strpos($tag_name, '/')) !== false) {
			$mid = $this->hasChild(substr($tag_name, 0, $p));
			if(!$mid) return false;
			return $mid->hasChild(substr($tag_name, $p + 1));
		}
		foreach($this->children as $key => $value) {
			if(is_object($value) && $value->name() == $tag_name) {
				if($delete) {
					unset($this->children[$key]);
				}
				return true;
			}
		}
		return false;
	}
	
	public function deleteChild($tag_name) {
		return $this->hasChild($tag_name, true);
	}
	
	public function getChildValue($tag_name) {
		return is_object($tag = $this->getChild($tag_name)) ? $tag->getCharacterData() : NULL;
	}
	
	public function insertCharacterData($data) {
		$this->children[] = $data;
	}
	
	public function getCharacterData() {
		$retval = '';
		foreach($this->children as $key => $value) {
			if(is_string($value)) {
				$retval .= $value;
			}
		}
		return $retval;
	}
	
	public function insertChildElement(XMLTag $tag) {
		$this->children[] = $tag;
	}
}

?>