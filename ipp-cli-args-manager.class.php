<?php

/**
 * Author: Roman Kiselevich
 * e-mail: xkisel00@stud.fit.vutbr.cz
 */

/**
 * class represents a logical or parsing error
 * which is connected with cli arguments
 */
final class IPPCLIBadArgumentException extends Exception {
	public function __construct($msg)
	{
		parent::__construct($msg);
	}
}

/**
 * class for storing information about CLI Arguments
 * implements Singleton pattern
 */
final class IPPCLIArgsManager {
	private static $manager = NULL;

	private static $SHORT_OPTS = "n";
	private static $LONG_OPTS = array(
		"help::",
		"input::",
		"output::",
		"query::",
		"qf::",
		"root::",
	);
	
	private $cliArgs = NULL;
	private $cliOptions = NULL;

	private function __construct()
	{
		/* nothing to do */
	}

	private function __clone()
	{
		/* nothing to do */
	}

	public static function getInstance()
	{
		if (self::$manager == NULL) {
			self::$manager = new IPPCLIArgsManager();
		}

		return self::$manager;
	}

	/**
	 * set CLI Args to CLI Manager object
	 * @param array $args - CLI arguments 
	 */
	public function setArgs(array $args)
	{
		$this->cliArgs = $args;
	}

	/**
	 * Overloading property access methods for accessing program
	 * arguments as CLI Manager object properties
	 * 
	 * __set method just throw an exception because cli arguments
	 * cannot be changed
	 */
	public function __set($option, $value)
	{
		throw new Exception("an attempt to set "
			. "readonly or nonexistent option");
	}

	/**
	 * @param  string $option - arument name
	 * @return string         - argument value
	 */
	public function __get($option)
	{	
		$needle = $option . "::";
		if (!in_array($needle, self::$LONG_OPTS)) {
			throw new Exception("an attempt to read invalid option");
		}

		if (isset($this->cliOptions[$option])) {
			return $this->cliOptions[$option];
		} else {
			return NULL;
		}
	}

	/**
	 * check if short program option is valid. In case of invalid option 
	 * throw an IPPCLIBadArgument Exception
	 * @param  string $shortopt - short cli option
	 */
	private function _checkShortOption($shortopt)
	{
		if (!array_key_exists($shortopt, $this->cliOptions)) {
			foreach (str_split($shortopt) as $opt) {
				if ($opt !== "n") {
					throw new IPPCLIBadArgumentException("unrecognized "
						. "option -- '$opt'");
				}
			} // foreach
		}
	}

	/**
	 * check if long program option is valid. In case of invalid option 
	 * throw an IPPCLIBadArgument Exception
	 * @param  string $longopt - long cli option
	 */
	private function _checkLongOption($longopt)
	{
		if (!array_key_exists($longopt, $this->cliOptions)) {
			throw new IPPCLIBadArgumentException("unrecognized "
				. "option '--$longopt'");
		}
		
		$optionArg = $this->cliOptions[$longopt];

		if (FALSE == $optionArg AND $longopt !== "help") {
			throw new IPPCLIBadArgumentException("option '--$longopt' "
				. "requires an argument");
		} elseif (TRUE == $optionArg AND $longopt === "help") {
			throw new IPPCLIBadArgumentException("option '--$longopt' "
				. "doesn't allow an argument");
		}
	}

	/**
	 * check if program options are valid. In case of invalid option 
	 * throw an IPPCLIBadArgument Exception
	 */
	private function _checkOptions()
	{
		array_shift($this->cliArgs);
		foreach ($this->cliArgs as $arg) {
			$cliArgPattern = '~^\-\-?(\w+)(=.*)?$~';
			if (!preg_match($cliArgPattern, $arg, $matches)) {
				throw new IPPCLIBadArgumentException("invalid "
					. "argument -- '$arg'");
			}

			if (preg_match('~^\-\w.+~', $matches[0])) {
				if (isset($matches[2])) {
					$matches[1] .= $matches[2];
				}
				$this->_checkShortOption($matches[1]);
			} elseif (preg_match('~^\-\-\w.+~', $matches[0])) {
				if (isset($matches[2]) AND 1 == strlen($matches[2])) {
					$this->cliOptions[$matches[1]] = FALSE;
				}
				$this->_checkLongOption($matches[1]);
			}
		}
	}

	/**
	 * check if cli arguments are valid in logical sense. 
	 * In case of invalid argument throw an IPPCLIBadArgument Exception
	 */
	public function parseArgs()
	{
		$this->cliOptions = getopt(self::$SHORT_OPTS, self::$LONG_OPTS);
		foreach ($this->cliOptions as $option => $arg) {
			if (is_array($arg)) {
				throw new IPPCLIBadArgumentException("cannot combine "
					. "two or more same options '$option'");
			}
		}

		$this->_checkOptions();

		$queryFile = $this->optionWasPassed("qf");
		$query = $this->optionWasPassed("query");
		if ($queryFile AND $query) {
			throw new IPPCLIBadArgumentException("cannot combine "
				. "options '--qf' and '--query'");
		}

		if (!$queryFile AND !$query) {
			if (!$this->optionWasPassed("help")) {
				throw new IPPCLIBadArgumentException("xml query "
					. "wasn't passed");
			}
		}
	}

	/**
	 * test if cli option was passed 
	 * @param  string $option cli option
	 * @return bool
	 */
	public function optionWasPassed($option)
	{
		return array_key_exists($option, $this->cliOptions);
	}
} // class

?>