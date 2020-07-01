<?php

// $this->page->addModules('');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	$arg1 = $this->getArg($macroName, /*arg-name*/ '', /*help-text*/ '', /* default-value*/'');
//    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);


	$args = $this->getArgsArray($macroName);
//    if (isset($args['disableCaching'])) {
//        unset($args['disableCaching']);
//    }

	$str = '';  // text rendered by macro



	// $this->optionAddNoComment = true;
	// $this->compileMd = true;
	return $str;
});
