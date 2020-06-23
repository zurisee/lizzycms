<?php

// $this->page->addModules('');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	$arg1 = $this->getArg($macroName, /*arg-name*/ '', /*help-text*/ '', /* default-value*/'');

	$args = $this->getArgsArray($macroName);

	$str = '';  // text rendered by macro



	// $this->optionAddNoComment = true;
	// $this->compileMd = true;
	return $str;
});
