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

    $dataSource = $this->getArg($macroName, 'dataSource', '', '');
    $suppressError = $this->getArg($macroName, 'suppressError', '', false);
    if ($dataSource === 'help') {
        return renderHelp($this, $macroName);
    }

    $file = resolvePath($dataSource, true);
    if ($dataSource && !file_exists($file)) {
        if ($suppressError) {
            return '';
        } else {
            return "<div>Error: Datasource-File '$dataSource' not found for macro 'table()'.</div>\n";
        }
    }

    $options = $this->getArgsArray($macroName, false);
    $options['dataSource'] = $file;

    $dataTable = new HtmlTable($this->lzy, $inx, $options);
	$table = $dataTable->render();
	
	return $table;
});




function renderHelp($trans, $macroName)
{
    $dataTable = new HtmlTable($trans->lzy, 0, 'help');
    $help = $dataTable->render('help');
    foreach ($help as $helpText) {
        $trans->getArg($macroName, $helpText['option'], $helpText['text']);
    }
    return '';
}

