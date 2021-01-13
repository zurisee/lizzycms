<?php

// @info: Create a Table From Data.


define('ORD_A', 	ord('a'));
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'htmltable.class.php';

global $tableCounter;
$tableCounter = 0;

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $dataSource = $this->getArg($macroName, 'dataSource', '(optional if nCols is set) Name of file containing data. Format may be .cvs or .yaml and is expected be local to page folder.', '');
    if ($dataSource === 'help') {
        return renderTableHelp($this, $macroName);
    }

    $suppressError = $this->getArg($macroName, 'suppressError', '', false);
    $file = resolvePath($dataSource, true);
    if ($dataSource && !file_exists($file)) {
        if ($suppressError) {
            return '';
        } else {
            preparePath($file);
            touch($file);
//            return "<div>Error: Datasource-File '$dataSource' not found for macro 'table()'.</div>\n";
        }
    }
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);


    $options = $this->getArgsArray($macroName, false);
    if (isset($options['disableCaching'])) {
        unset($options['disableCaching']);
    }
    $options['dataSource'] = $file;

    $dataTable = new HtmlTable($this->lzy, $inx, $options);
	$table = $dataTable->render();
	
	return $table;
});




function renderTableHelp($trans, $macroName)
{
    $dataTable = new HtmlTable($trans->lzy, 0, 'help');
    $help = $dataTable->render('help');
    array_shift($help);
    usort( $help, function($a, $b) {
        return ($a['option'] > $b['option']);
    });
    foreach ($help as $rec) {
        $trans->getArg($macroName, $rec['option'], $rec['text']);
    }
    return '';
} // renderTableHelp

