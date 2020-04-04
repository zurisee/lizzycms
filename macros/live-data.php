<?php
require_once SYSTEM_PATH.'live-data.class.php';

$page->addModules('~sys/js/live_data.js');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

	// typical variables that might be useful:
	$inx = $this->invocationCounter[$macroName] + 1;

	// how to get access to macro arguments:
    $file = $this->getArg($macroName, 'file', 'Defines the data-source from which to retrieve the data element', '');
    $elementName = $this->getArg($macroName, 'elementName', 'Name of the element to be visualized', false);
    $id = $this->getArg($macroName, 'id', '(optional) Id of DOM element.', "lzy-live-data$inx");
    $polltime = $this->getArg($macroName, 'polltime', '(optional) Polling time, i.e. the time server waits for new data before giving up.', false);
    $mode = $this->getArg($macroName, 'mode', '[manual] Manual mode: invoke live-data fields manually.', false);

    $manual = (strpos($mode, 'manual') !== false);

    if ($file === 'help') {
        return '';
    }

    if (!$file) {
        $this->compileMd = true;
        return "**Error**: argument ``file`` not specified.";
    }

    $args = $this->getArgsArray($macroName);
    $ld = new LiveData($this->lzy, $inx, $args);

    $str = $ld->render();

    $this->optionAddNoComment = true;
	return $str;
});
