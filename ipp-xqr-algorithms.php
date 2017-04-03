<?php

/**
 * Author: Roman Kiselevich
 * e-mail: xkisel00@stud.fit.vutbr.cz
 * 
 * module encapsulates some algorithms for 
 * working with xml format files
 */

/**
 * if SimpleXMLElement value is integer (as [+-]?\d+) 
 * or float ([+-]?\d+(?:\.\d+)) funciton will return it's value as integer
 * if SimpleXMLElement value is not integer NULL will be returned
 * 
 * @param  SimpleXMLElement $elem - instance of SimpleXMLElement class
 * @return int, NULL
 */
function xmlSimpleGetValue(SimpleXMLElement $elem)
{
	$val = (string) $elem;
	if (isset($val)) {
		if (preg_match('~^[+-]?\d+(?:\.\d+)?~', $val)) {
			return (int)$val;
		} else {
			return $val;
		}		
	}

	return NULL;
}

/**
 * if SimpleXMLElement attribute is integer (as [+-]?\d+) 
 * or float ([+-]?\d+(?:\.\d+)) function will return 
 * it's attribute as integer
 * if SimpleXMLElement attribute is not integer NULL will be returned
 * 
 * @param  SimpleXMLElement $elem - instance of SimpleXMLElement class
 * @param  string 			$attr - SimpleXMLElement attribute
 * @return int, NULL
 */
function xmlSimpleGetAttribute(SimpleXMLElement $elem, $attr)
{
	$val = (string)$elem->attributes()->$attr;
	if (isset($val)) {
		if (preg_match('~^[+-]?\d+(?:\.\d+)?~', $val)) {
			return (int)$val;
		} else {
			return $val;
		}
	}

	return NULL;
}

/**
 * compare to values relative to XML query operator (>, =, CONTAINS etc.)
 * return (int)1 
 *
 * @param  string, int $val1 - left value
 * @param  string, int $val2 - right value
 * @param  string  $operator - XML query operator
 * @return int     - 1 if the result of comaprison is true
 *         int     - 0 if the result of comparison is false
 *         boolean - FALSE if the operation cannot be applied
 */
function xmlQueryComparison($val1, $val2, $operator)
{
	switch ($operator) {
	case ">":
		if (is_integer($val1) AND is_integer($val2)) {
			if ($val1 > $val2) {
				return 1;
			} else {
				return 0;
			}
		} elseif (is_string($val1) AND is_string($val2)) {
			if ($val1 > $val2) {
				return 1;
			} else {
				return 0;
			}
		} else {
			return FALSE;
		}
	case "<":
		if (is_integer($val1) AND is_integer($val2)) {
			if ($val1 < $val2) {
				return 1;
			} else {
				return 0;
			}
		} elseif (is_string($val1) AND is_string($val2)) {
			if ($val1 < $val2) {
				return 1;
			} else {
				return 0;
			}
		} else {
			return FALSE;
		}
	case "=":
		if (is_integer($val1) AND is_integer($val2)) {
			if ($val1 === $val2) {
				return 1;
			} else {
				return 0;
			}
		} elseif (is_string($val1) AND is_string($val2)) {
			if ($val1 === $val2) {
				return 1;
			} else {
				return 0;
			}
		} else {
			return FALSE;
		}
	case "CONTAINS":
		if (is_string($val1) AND is_string($val2)) {
			if (FALSE === strpos($val1, $val2)) {
				return 0;
			} else {
				return 1;
			}
		} else {
			return FALSE;
		}
	default:
		return FALSE;
	}
}

/**
 * test for equality SimpleXMLElement and IPPXQueryElement objects
 * equality means equal names of elements and attributes
 * 
 * @param  SimpleXMLElement $xmlElem   - instance of 
 *                                     SimpleXMLElement class
 * @param  IPPXQueryElement $queryElem - instance of 
 *                                     IPPXQueryElement class
 * @return boolean - if elements are equal
 */
function xmlSimpleQueryElementsEqual(
	SimpleXMLElement $xmlElem,
	IPPXQueryElement $queryElem
) {
	$namesAreEqual = $xmlElem->getName() === $queryElem->getName();
	$queryAttr = $queryElem->getAttribute();
	$attrsAreEqual = isset($xmlElem->attributes()->$queryAttr);

	if ($queryElem->hasName() AND $queryElem->hasAttribute()) {
		if ($namesAreEqual AND $attrsAreEqual) {
			return TRUE;
		}
		return FALSE;
	} elseif ($queryElem->hasName() AND !$queryElem->hasAttribute()) {
		if ($namesAreEqual) {
			return TRUE;
		}
		return FALSE;
	} else {
		if ($attrsAreEqual) {
			return TRUE;
		}
		return FALSE;
	}
}


/**
 * finds all SimpleXMLElement specified by $needle in $inputXML element
 * using recursive Depth Search algorithm and store it 
 * consistently into an array $foundElements
 * number of stored elements can be limited by $numberOfElements
 * parametr
 * 
 * @param  SimpleXMLElement $inputXML         
 * @param  IPPXQueryElement $needle           
 * @param  array            $foundElements    
 * @param  int              $numberOfElements - [optional]
 * @param  boolean          $includeRoot      - [optional] if set 
 *                                            root element 
 *                                            will not be ignored
 */
function xmlDepthSearchElements(
	SimpleXMLElement $inputXML,
	IPPXQueryElement $needle,
	array & $foundElements,
	$numberOfElements = NULL,
	$includeRoot = FALSE
) {
	if ($includeRoot === TRUE) {
		if (xmlSimpleQueryElementsEqual($inputXML, $needle)) {
			array_push($foundElements, $inputXML);
			return ;
		}
	}

	$children = $inputXML->children();
	if (empty($children)) {
		return ;
	}

	foreach ($children as $child) {
		if (count($foundElements) === $numberOfElements) {
			break;
		}

		if (xmlSimpleQueryElementsEqual($child, $needle)) {
			array_push($foundElements, $child);
		} else {
			xmlDepthSearchElements(
				$child, 
				$needle, 
				$foundElements,
				$numberOfElements
			);
		}
	}
}

?>