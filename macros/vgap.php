<?php

// @info: Renders vertical space of given height.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $gap_size = $this->getArg($macroName, 'gap_size', 'Height of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if (trim($gap_size) === '') {
		$gap_size = '1em';
	} elseif (preg_match('/^[\d\.]+$/', $gap_size)) {
		$gap_size = ($gap_size / 2).'px';
	} elseif (preg_match('/^([\d\.]+)(.*)$/', $gap_size, $m)) {
		$gap_size = ($m[1] / 2).$m[2];
	}
	$str = "\n<div id='$macroName$inx' class='lzy-vgap' style='margin:$gap_size 0;'>&nbsp;</div>\n";
	return $str;
});
