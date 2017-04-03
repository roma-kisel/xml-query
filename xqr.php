<?php

/**
 * Author: Roman Kiselevich
 * e-mail: xkisel00@stud.fit.vutbr.cz
 */

require_once 'ipp-cli-args-manager.class.php';
require_once 'ipp-xquery/ipp-xquery.class.php';
require_once 'ipp-xquery/ipp-xquery-element.class.php';
require_once 'ipp-xqr-algorithms.php';

const EXIT_SUCCESS = 0;
const CLI_ARG_ERRCODE = 1;
const FILE_READ_ERRCODE = 2;
const FILE_WRITE_ERRCODE = 3;
const INPUT_FORMAT_ERRCODE = 4;
const BAD_QUERY_ERRCODE = 80;

const TRY_HELP_MESSAGE = "Try '--help' option for more information.";
const HELP_MESSAGE = <<<'HELP_MSG'
    Script apply XMLQuery (xquery) over input xml file. As a result put to
the output xml file with elements which corresponds to this xquery. 
Syntax and semantics of the xquery are very similar to SQL.

  --help            print this message
  --input=filename  input xml file (stdin if option wasn't passed)
  --output=filename output xml file (stdout if option wasn't passed)
  --query='xquery'  specify xquery
  --qf=filename     specify xquery in file (cannot combine with '--query')
  -n                do not generate root element in output xml file
  --root=element    specify name of the root element in output xml file

HELP_MSG;
const XML_HEADER = '<?xml version="1.0" encoding="UTF-8"?>';
const XQUERY_FORMAT_SPEC = <<<'EOT'
	SELECT element FROM element|element.attribute|ROOT|empty
	WHERE condition LIMIT n
EOT;

/* error_log will be print to stderr */
ini_set("display_errors", "stderr");

$cliArgsManager = IPPCLIArgsManager::getInstance();
$cliArgsManager->setArgs($argv);

try {
	$cliArgsManager->parseArgs();
} catch (IPPCLIBadArgumentException $e) {
	error_log($argv[0] . ": " . $e->getMessage());
	error_log(TRY_HELP_MESSAGE);
	exit(CLI_ARG_ERRCODE);
}

if ($cliArgsManager->optionWasPassed("help")) {
	echo HELP_MESSAGE;
	exit(EXIT_SUCCESS);
}

$inputFileName = NULL;
if ($cliArgsManager->optionWasPassed("input")) {
	$inputFileName = $cliArgsManager->input;
} else {
	$inputFileName = "php://stdin";
}

$inputXMLContent = @file_get_contents($inputFileName);
if (FALSE === $inputXMLContent) {
	error_log($argv[0] . ": couldn't open input XML file "
		. "'$inputFileName' for reading or no such file");
	exit(FILE_READ_ERRCODE);
}

libxml_use_internal_errors(true);
$inputXML = @simplexml_load_string($inputXMLContent);
if (FALSE === $inputXML) {
	error_log($argv[0] . ": bad xml file format");
	foreach (libxml_get_errors() as $formatError) {
		fwrite(STDERR, $formatError->message);
	}
	exit(INPUT_FORMAT_ERRCODE);
}

$xmlQueryContent = NULL;
if ($cliArgsManager->optionWasPassed("qf")) {
	$queryFileName = $cliArgsManager->qf;
	$xmlQueryContent = @file_get_contents($queryFileName);
	if (FALSE === $xmlQueryContent) {
		error_log($argv[0] . ": couldn't open XML query file "
			. "'$queryFileName' for reading or no such file");
		exit(FILE_READ_ERRCODE);        
	}
} else {
	$xmlQueryContent = $cliArgsManager->query;
}

try {
	$xmlQuery = new IPPXQuery($xmlQueryContent);
} catch (IPPXQueryBadFormatException $e) {
	error_log($argv[0] . ": bad xml query format: " . $e->getMessage());
	error_log("xml query informal specification:");
	error_log(XQUERY_FORMAT_SPEC);
	error_log(TRY_HELP_MESSAGE);
	exit(BAD_QUERY_ERRCODE);
}


if (isset($xmlQuery["FROM"])) {
	if ($xmlQuery["FROM"]->is_root()) {
		$xmlQuery["FROM"] = new IPPXQueryElement($inputXML->getName());
	}
}

$foundElements = array();
if (isset($xmlQuery["FROM"])) {
	xmlDepthSearchElements(
		$inputXML,
		$xmlQuery["FROM"],
		$foundElements,
		1,
		TRUE
	);
}

if (!empty($foundElements)) {
	if (isset($xmlQuery["FROM"])) {
		$rootElement = $foundElements[0];
		$selectedElements = array();
		xmlDepthSearchElements(
			$rootElement,
			$xmlQuery["SELECT"],
			$selectedElements
		);

		if (isset($xmlQuery["WHERE"])) {
			$selectedElements = 
				array_filter($selectedElements, $xmlQuery["WHERE"]);
		}
	}
}

$outputContent = "";
if (!$cliArgsManager->optionWasPassed("n")) {
	$outputContent .= XML_HEADER . "\n";
}

$allXml = "";
if (!empty($foundElements)) {
	if (isset($xmlQuery["FROM"])) {
		$counter = 0;
		foreach ($selectedElements as $element) {
			if (isset($xmlQuery["LIMIT"])) {
				if ($counter === $xmlQuery["LIMIT"]) {
					break;
				}
			}
			$allXml .= $element->asXML();
			++$counter; 
		}
		$allXml .= "\n";
	}
}

if ($cliArgsManager->optionWasPassed("root")) {
	$rootTag = $cliArgsManager->root;
	$allXml = "<".$rootTag.">\n".$allXml."\n</".$rootTag.">\n";
}

$outputContent .= $allXml;
$outputFileName = NULL;
if ($cliArgsManager->optionWasPassed("output")) {
	$outputFileName = $cliArgsManager->output;
} else {
	$outputFileName = "php://stdout";
}

if (FALSE === @file_put_contents($outputFileName, $outputContent)) {
	error_log($argv[0] . ": couldn't write XML content to the file "
		. "'$outputFileName'");
	exit(FILE_WRITE_ERRCODE);
}

?>