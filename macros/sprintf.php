<?php

// @info: Basically a wrapper to provide access to PHP's sprintf() function.



$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $args = $this->getArgsArray($macroName);
    if (isset($args[0]) && ($args[0] === 'help')) {
        return '';
    }

    $format = array_shift($args);
    foreach ($args as $i => $arg) {
        $s = $this->getVariable($arg);
        if ($s) {
            $args[$i] = $s;
        }
    }
    $out = sprintf($format, $args[0],$args[1]);

    return $out;
});
