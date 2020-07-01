<?php

// @info: Renders or hides some text based on time.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $text = $this->getArg($macroName, 'text', 'Text to be displayed', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', 'CSS-Selector from which to import text', '');
    $variable = $this->getArg($macroName, 'variable', 'Text from variable to be displayed', '');
    $showFrom = $this->getArg($macroName, 'showFrom', 'Content will be visible after this time (format: YYYY-MM-DD HH:MM)', false);
    $showTill = $this->getArg($macroName, 'showTill', 'Content will be visible until this time (format: YYYY-MM-DD HH:MM)', false);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($variable) {
        $text .= $this->getVariable($variable);
    }

    if ($contentFrom) {
        $text .= "<div id='lzy-scheduled-wrapper$inx'></div>\n";
        $jq = <<<EOT
var html = $('$contentFrom').html();
$('#lzy-scheduled-wrapper$inx').append( html );
EOT;
        $this->page->addJq($jq);
    }

    $from = 0;
    if ($showFrom) {
        $from = strtotime($showFrom);
    }
    $till = PHP_INT_MAX;
    if ($showTill) {
        $till = strtotime($showTill);
    }
    $now = time();
    if (($now < $from) || ($now > $till)) {
        $text = '';
    }
	return $text;
});
