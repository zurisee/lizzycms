<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $text = $this->getArg($macroName, 'text', 'Text to be displayed in the Popup', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($text === 'help') {
        $this->getArg($macroName, 'contentFrom', 'Text to be optained from the selected element (e.g. \'#box\')', '');
        $this->getArg($macroName, 'fromFile', 'Text to be optained from given file', '');
        $this->getArg($macroName, 'closable', 'Defines whether the Popup can be closed.', '');
        $this->getArg($macroName, 'id', '(optional) Defines the ID applied to the element containing the overlay.', '');
        $this->getArg($macroName, 'trigger', '[auto|none|identifier] Defines what will open the overlay.', '');
        $this->getArg($macroName, 'onOpen', '(optional) Name of a function to call upon opening the overlay.', '');
        $this->getArg($macroName, 'mdCompile', 'Defines whether the provided text shall be md-compiled', '');

        return '';
    }

    $args = $this->getArgsArray($macroName);

    $this->page->addOverlay($args);

	return '';
});
