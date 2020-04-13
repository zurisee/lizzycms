<?php


$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $dataSrc = $this->getArg($macroName, 'dataSrc', 'Filename of datasource', '');
    $return = $this->getArg($macroName, 'return', '[size|recNo] ', false);

    if (!$dataSrc || ($dataSrc === 'help')) {
        return '';
    }
    $dataSrc = makePathRelativeToPage($dataSrc);
    $db = new DataStorage2( $dataSrc);

    $str = '';
    if ($return === 'size') {
        $str = $db->getNoOfRecords();
    }

    $this->optionAddNoComment = true;
	return $str;
});
