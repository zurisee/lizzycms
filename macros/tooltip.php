<?php

$page->addModules('TOOLTIPSTER');
$page->addJq('$(".lzy-tooltip").tooltipster();');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$text = $this->getArg($macroName, 'text', 'Defines the visible text which shall get a tooltip.', '');
	$description = $this->getArg($macroName, 'description', 'Defines the text that shall be shown inside the tooltip bubble.', '');

	if ($text === 'help') {
	    return '';
    }

	$str = "<span class='lzy-tooltip lzy-tooltip-$inx' title='$description'>$text</span>";

	$this->optionAddNoComment = true;
	// $this->compileMd = true;
	return $str;
});
