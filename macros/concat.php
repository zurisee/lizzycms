<?php

// @info: Just concatenates the arguments. But before it does so, it tries to translate each argument.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $help = $this->getArg($macroName, 'help', '(string) Any number of strings - if they correspond to the name of a variable, the content of that variable is rendered.', false);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($help === 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $out = '';
    foreach ($args as $key => $arg) {
        if (!is_int($key)) {
            continue;
        }
        $s = $this->getVariable($arg);
        if ($s) {
            $arg = $s;
        }
        $out .= $arg;
    }

	return $out;
});
