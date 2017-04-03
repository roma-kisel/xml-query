<?php

/**
 * Author: Roman Kiselevich
 * e-mail: xkisel00@stud.fit.vutbr.cz
 */

/**
 * class represents XMLQuery element syntax error 
 */
final class IPPXQueryElementException extends Exception {
	public function __construct($msg)
	{
		parent::__construct($msg);
	}
}

/**
 * class represents XMLQuery element
 */
final class IPPXQueryElement {
	private $name = NULL;
	private $attribute = NULL;


	public function is_root() {
		if ("ROOT" === $this->name AND !$this->hasAttribute()) {
			return TRUE;
		} else {
			return FALSE;
		}
	}

	public function __set($property, $value)
	{
		throw new Exception(
			"an attempt to set readonly or nonexistent property"
		);
	}

	/**
	 * construct new object and in case of syntax error throws 
	 * an IPPXQueryElementException
	 * @param string $elemOrAttr - string representation of XQR element
	 */
	public function __construct($elemOrAttr)
	{
		$patternWithoutAttr = "~^[^\.\s]+$~";
		$patternWithAttr = "~^([^\.\s]+)?\.([^\.\s]+)$~";
		if (preg_match($patternWithoutAttr, $elemOrAttr)) {
			$this->name = $elemOrAttr;
		} elseif (preg_match($patternWithAttr, $elemOrAttr, $matches)) {
			if ("" !== $matches[1]) {
				$this->name = $matches[1];
			}

			$this->attribute = $matches[2];
		} else {
			throw new IPPXQueryElementException("bad element format");
		}

		if (!$this->_isValid()) {
			throw new IPPXQueryElementException("bad element format");
		}
	}


	public function getName()
	{
		return $this->name;
	}

	public function getAttribute()
	{
		return $this->attribute;
	}

	/**
	 * test if XQR element has a name
	 * @return boolean
	 */
	public function hasName()
	{
		return isset($this->name);
	}

	/**
	 * test if XQR element has a name
	 * @return boolean
	 */
	public function hasAttribute()
	{
		return isset($this->attribute);
	}

	public function __toString()
	{
		$result = NULL;
		if ($this->hasName()) {
			$result = $this->name;
		}

		if ($this->hasAttribute()) {
			$result .= "." . $this->attribute;
		}
		
		return $result;
	}

	/**
	 * test if XQR element is valid
	 * @return boolean
	 */
	private function _isValid()
	{	
		$xmlContent = NULL;

		$name = "";
		$attr = "";
		if (!$this->hasName()) {
			$name = "some_name";
		} else {
			$name = $this->name;
		}

		if ($this->hasAttribute()) {
			$attr = $this->attribute . '="1"';			
		}

		$xmlContent = "<" . $name . " " . $attr . '/>';

		$xmlElement = @simplexml_load_string($xmlContent);
		if (FALSE === $xmlElement) {
			return FALSE;
		} else {
			return TRUE;
		}
	}
}

?>