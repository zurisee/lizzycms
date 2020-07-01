<?php
// @info: Creates a popup widget.



$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);
    $args = $this->getArgsArray($macroName);
    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }
    return $this->page->addPopup($args);
});

