<?php
/*
 * Table Macro
 * takes a data-source containing 2 dimensional array data, wraps it in HTML
 * Data-sources formats:
 * 		- Yaml
 * 		- Json
 * 		- CVS (standard and Microsoft)
 *
 * DataTables Plugin:
 * -> activate by added table-class = 'datatables'
 * for options see https://datatables.net/manual/index
*/

define('ORD_A', 	ord('a'));
require_once SYSTEM_PATH.'datastorage.class.php';
require_once SYSTEM_PATH.'htmltable.class.php';

global $tableCounter;
$tableCounter = 0;

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $options = $this->getArgsArray($macroName, false);

    $dataTable = new HtmlTable($this->page, $inx, $options);
	$table = $dataTable->render();
	
	return $table;
});

