<?php

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');

    $args = $this->getArgsArray($macroName);

    if (@$args[0] === 'help') {
        $pop = new PopupWidget();
        return $pop->renderPopupHelp();
    }

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);
    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

    return $this->page->addPopup($args);
});

