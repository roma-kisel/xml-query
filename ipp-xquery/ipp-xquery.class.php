<?php

/**
 * Author: Roman Kiselevich
 * e-mail: xkisel00@stud.fit.vutbr.cz
 */

require_once 'ipp-xquery-element.class.php';
require_once 'ipp-xqr-algorithms.php';

/**
 * class represents XQuery syntax error 
 */
final class IPPXQueryBadFormatException extends Exception {
	public function __construct($msg)
	{
		parent::__construct($msg);
	}
}

/**
 * class represents XML Query
 */
final class IPPXQuery implements ArrayAccess {
	private $splitedQRContent = NULL;

	private $whereFilter = NULL;

	private $query = array(
		"SELECT" => NULL, /* element from SELECT clause */
		"FROM"	=> NULL,  /* element from FROM clause   */
		"LIMIT"	=> NULL,  /* number from LIMIT clause   */
		/**
		 * function which represents WHERE clause predicate and has one 
		 * parametr of type SimpleXMLElement
		 * return TRUE or FALSE 
		 */
		"WHERE" => NULL 
	);

	public static $KEYWORDS = array(
		"SELECT", "FROM", "ROOT", "WHERE", 
		"CONTAINS", "LIMIT", "NOT"
	);

	/**
	 * test if specified $string is XQuery keyword
	 * @param  string  $string
	 * @return boolean
	 */
	public static function is_keyword($string)
	{
		return in_array($string, self::$KEYWORDS, TRUE);
	}

	/**
	 * 
	 * @param string $XQRContent - XQuery string representation
	 */
	public function __construct($XQRContent)
	{
		$this->splitedQRContent = 
			preg_split("~\s+~", $XQRContent, NULL, PREG_SPLIT_NO_EMPTY);

		if (FALSE === $this->splitedQRContent) {
			throw new IPPXQueryBadFormatException("some parsing error");
		}
		
		$this->_queryStartParsing();

	}

	/**
	 * function is part of ArrayAccess interface
	 */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->query[] = $value;
        } else {
            $this->query[$offset] = $value;
        }
    }

    /**
     * function is part of ArrayAccess interface
     */
    public function offsetExists($offset)
    {
        return isset($this->query[$offset]);
    }

    /**
     * function is part of ArrayAccess interface
     */
    public function offsetUnset($offset)
    {
        unset($this->query[$offset]);
    }

    /**
     * function is part of ArrayAccess interface
     */
    public function offsetGet($offset)
    {
    	if (isset($this->query[$offset])) {
    		return $this->query[$offset];
    	} else {
    		return NULL;
    	}
    }

    /**
     * semantic test for IPPXQueryElement. In case of error throw an
     * IPPXQueryBadFormatException
     * @param  IPPXQueryElement $element
     */
	private function _checkXQRElement(IPPXQueryElement $element)
	{
		if ($element->hasName()) {
			if (self::is_keyword($element->getName())) {
				throw new IPPXQueryBadFormatException(
					"element name cannot be query keyword\n\t"
						. "such as SELECT, ROOT etc."
				);
			}
		}

		if ($element->hasAttribute()) {
			if (self::is_keyword($element->getAttribute())) {
				throw new IPPXQueryBadFormatException(
					"attribute name cannot be query keyword\n\t"
						. "such as SELECT, ROOT etc."
				);
			}
		}
	}

	/**
	 * function try to create IPPXQueryElement object and return it.
	 * In case of some error throw an IPPXQueryBadFormatException
	 * 
	 * @param  string $elementContent [description]
	 * @return IPPXQueryElement       new element
	 */
	private function _tryToCreateElement($elementContent)
	{
		try {
			$element = new IPPXQueryElement($elementContent);
		} catch (IPPXQueryElementException $e) {
			throw new IPPXQueryBadFormatException($e->getMessage());
		}

		return $element;
	}
 	
 	/**
 	 * set WHERE predicate in query array as ["WHERE"] key 
 	 * @param IPPXQueryElement $queryElement 
 	 * @param string           $operator     
 	 * @param string, int      $literal      
 	 * @param boolean          $negate
 	 */
	public function setWhereFilter(
		IPPXQueryElement $queryElement,
		$operator,
		$literal,
		$negate
	) {
		$cb = function (SimpleXMLelement $xmlElement) use (
			$queryElement,
			$operator,
			$literal,
			$negate
		) {
			$foundElements = array();
			xmlDepthSearchElements(
				$xmlElement,
				$queryElement,
				$foundElements,
				NULL,
				TRUE
			);

			foreach ($foundElements as $element) {
				$val = NULL;
				if ($queryElement->hasAttribute()) {
					$attrName = $queryElement->getAttribute();
					$val = xmlSimpleGetAttribute($element, $attrName);
				} else {
					$val = xmlSimpleGetValue($element);
				}

				$res = xmlQueryComparison($val, $literal, $operator);
				if (FALSE === $res) {
					continue;
				} elseif (TRUE == $res) {
					return $negate ? FALSE : TRUE;
				} else {
					return $negate ? TRUE : FALSE;
				}
			}
			return FALSE;
		};

		$this->query["WHERE"] = $cb;
	}

	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class. In case of syntax error
	 * that kind of functions throws an IPPXQueryBadFormatException
	 */
	private function _queryStartParsing()
	{
		$selectKeyword = current($this->splitedQRContent);
		if (!preg_match("~^SELECT$~", $selectKeyword)) {
			throw new IPPXQueryBadFormatException(
				"expected 'SELECT' keyword"
			);
		}

		if (FALSE === next($this->splitedQRContent)) {
			throw new IPPXQueryBadFormatException(
				"expected element name after 'SELECT' keyword"
			);
		}

		$element = 
			$this->_tryToCreateElement(current($this->splitedQRContent));

		if ($element->hasAttribute()) {
			throw new IPPXQueryBadFormatException(
				"an attempt to SELECT attribute"
			);
		}

		$this->_checkXQRElement($element);
		$this->query["SELECT"] = $element;

		$this->_fromClause();
	}

	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class.
	 * represents FROM clause in XMLQuery
	 */
	private function _fromClause()
	{
		if ("FROM" !== next($this->splitedQRContent)) {
			throw new IPPXQueryBadFormatException(
				"expected 'FROM' keyword"
			);
		}

		if (FALSE === next($this->splitedQRContent)) {
			/* empty FROM clause */
			return;
		}

		if ("WHERE" === current($this->splitedQRContent)) {
			$this->_whereClauseCondition();
			return;
		} elseif ("LIMIT" === current($this->splitedQRContent)) {
			$this->_limitClause();
			return;
		}

		$element = 
			$this->_tryToCreateElement(current($this->splitedQRContent));

		if (!$element->is_root()) {
			$this->_checkXQRElement($element);
		}

		$this->query["FROM"] = $element;
		
		$this->_whereOrLimit();	
	}

	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class.
	 * represents WHERE clause in XMLQuery
	 */
	private function _whereOrLimit()
	{
		if (FALSE === next($this->splitedQRContent)) {
			return;
		}

		if ("WHERE" === current($this->splitedQRContent)) {
			$this->_whereClauseCondition();
		} elseif ("LIMIT" === current($this->splitedQRContent)) {
			$this->_limitClause();
		} else {
			throw new IPPXQueryBadFormatException(
				"expected 'WHERE' or 'LIMIT' keyword"
			);
		}
	}

	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class.
	 */
	private function _whereClauseCondition()
	{
		if (FALSE === next($this->splitedQRContent)) {
			throw new IPPXQueryBadFormatException(
				"expected condition after 'WHERE' keyword"
			);
		}

		$negate = FALSE;
		if ("NOT" === current($this->splitedQRContent)) {
			$negate = TRUE;
			while ("NOT" === next($this->splitedQRContent)) {
				$negate = !$negate;
			}
		}

		$this->_extractCondition($elementContent, $operator, $literal);
		
		$element = $this->_tryToCreateElement($elementContent);
		if (!$this->_parseLiteral($literal)) {
			throw new IPPXQueryBadFormatException(
				"bad literal in 'WHERE' condition"
			);
		}

		if ($operator === 'CONTAINS' AND !is_string($literal)) {
			throw new IPPXQueryBadFormatException(
				"operator 'CONTAINS' can be applied only to string"
			);
		}

		$this->setWhereFilter(
			$element,
			$operator,
			$literal,
			$negate
		);

		if (FALSE === next($this->splitedQRContent)) {
			return ; /* no LIMIT clause */
		}

		if ("LIMIT" === current($this->splitedQRContent)) {
			$this->_limitClause();
		} else {
			throw new IPPXQueryBadFormatException(
				"expected 'LIMIT' keyword"
			);
		}
	}

	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class.
	 * @param  &string $el 
	 * @param  &string $op 
	 * @param  &string $lit 
	 */
	private function _extractCondition(& $el, & $op, & $lit)
	{
		$condition = current($this->splitedQRContent);
		$pattern = "~^(.+)(>|<|=|CONTAINS)(.*)?$~";

		$matches = NULL;
		if (preg_match($pattern, $condition, $matches)) {
			$el = $matches[1];
			$op = $matches[2];

			if ("" === $matches[3]) {
				if (FALSE === next($this->splitedQRContent)) {
					throw new IPPXQueryBadFormatException(
						"expected literal in 'WHERE' condition"
					);
				}

				$lit = current($this->splitedQRContent);
			} else {
				$lit = $matches[3];
			}
		} else {
			$el = current($this->splitedQRContent);

			if (FALSE === next($this->splitedQRContent)) {
				throw new IPPXQueryBadFormatException(
					"exptected operator in 'WHERE' condition"
				);
			}

			$opOrLit = current($this->splitedQRContent);
			$pattern = '~^(>|<|=|CONTAINS)(.*)?$~';
			if (preg_match($pattern, $opOrLit, $matches)) {
				$op = $matches[1];

				if ("" === $matches[2]) {
					if (FALSE === next($this->splitedQRContent)) {
						throw new IPPXQueryBadFormatException(
							"expected literal in 'WHERE' condition"
						);
					}

					$lit = current($this->splitedQRContent);
				} else {
					$lit = $matches[2];
				}
			} else {
				throw new IPPXQueryBadFormatException(
					"exptected operator in 'WHERE' condition"
				);				
			}
		}
	}

	/**
	 * tests if XMLQuery literal is valid and cast it to correspond type. 
	 * In case of illegal literal return FALSE, otherwise TRUE will be 
	 * returned 
	 * @param  &string $literal
	 * @return boolean
	 */
	private function _parseLiteral(& $literal)
	{
		if (preg_match('~^"(.*)"$~', $literal, $matches)) {
			$literal = $matches[1];
			return TRUE;
		} elseif (preg_match('~^[+-]?\d+$~', $literal, $matches)) {
			$literal += 0; // cast to int
			return TRUE;
		} else {
			return FALSE;
		}
	}
	/**
	 * function is part of top-down recursive parser which is represent
	 * as some private functions of this class.
	 */
	private function _limitClause()
	{
		if (FALSE === next($this->splitedQRContent)) {
			throw new IPPXQueryBadFormatException(
				"expected number in LIMIT clause"
			);
		}

		$literal = current($this->splitedQRContent);
		if (FALSE === $this->_parseLiteral($literal)) {
			throw new IPPXQueryBadFormatException(
				"bad literal in LIMIT clause"
			);
		}

		if (is_string($literal)) {
			throw new IPPXQueryBadFormatException(
				"literal in LIMIT clause should be a positive int"
			);
		}

		if (is_integer($literal) AND $literal < 0) {
			throw new IPPXQueryBadFormatException(
				"literal in LIMIT clause should be a positive int"
			);
		}

		$this->query["LIMIT"] = $literal;
	}
}

?>