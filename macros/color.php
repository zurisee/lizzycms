<?php

// @info: Renders text in the color defined in the second argument.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $text = $this->getArg($macroName, 'text', '', '');
    $color = $this->getArg($macroName, 'color', '', 'black');

    $str = "<span style='color:var(--lzy-color, $color);'>$text</span>";
    $this->optionAddNoComment = true;
	return $str;
});
