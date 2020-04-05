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
    $file = $this->getArg($macroName, 'file', 'Defines the data-source from which to retrieve the data element(s)', '');
    $this->getArg($macroName, 'elementName', 'Name of the element(s) to be visualized. Use format "A|B|C" to specify a list of names.', false);
    $this->getArg($macroName, 'id', '(optional) Id of DOM element(s). If not specified, id will be derived from elementName.  Use format "A|B|C" to specify a list of ids.', false);
    $this->getArg($macroName, 'polltime', '(optional) Polling time, i.e. the time the server waits for new data before giving up and responding with "No new data".', false);
    $this->getArg($macroName, 'mode', '[manual] Manual mode: invoke live-data fields manually. Means, HTML code is created outside of this macro, in particular in case when elementName is specified as a list.', false);

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
