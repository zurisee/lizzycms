<?php

// @info: Wraps given text in a span and applies given styles.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $text = $this->getArg($macroName, 'text', 'Text to be wrapped in a span', '');
    $style = $this->getArg($macroName, 'style', 'CSS code to be applied to the given text.', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($text === 'help') {
        return '';
    }
    $str = "<span style='$style'>$text</span>";
    $this->optionAddNoComment = true;
	return $str;
});
