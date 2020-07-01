<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $id = "lzy-content-from{$this->invocationCounter[$macroName]}";

    $selector = $this->getArg($macroName, 'selector', 'ID or Class selector of page element that shall be imported.', 'default value');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    $jq = "\n\t$('#$id').html( $('$selector').html() );";
    $this->page->addJq($jq);

    $this->optionAddNoComment = true;
    return "\t\t<div id='$id' class='lzy-content-from'></div>\n";
});
