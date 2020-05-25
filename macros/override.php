<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $text = $this->getArg($macroName, 'text', 'Text to be displayed overriding the original page content.', '');
    if ($text === 'help') {
        $this->getArg($macroName, 'fromFile', 'Text to be optained from given file', '');
        $this->getArg($macroName, 'mdCompile', 'Defines whether the provided text shall be md-compiled', '');

        return '';
    }

    $args = $this->getArgsArray($macroName);

    $this->page->addOverride($args);

	return '';
});
