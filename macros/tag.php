<?php


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $tagName = $this->getArg($macroName, 'tagName', 'Name of HTML tag to be rendered', 'span');
    $id = $this->getArg($macroName, 'id', 'ID to apply', '');
    $class = $this->getArg($macroName, 'class', 'Class to apply', '');
    $style = $this->getArg($macroName, 'style', 'Style to apply', '');
    $text = $this->getArg($macroName, 'text', 'Text to put between opening and closing tags', '');

    $id = $id ? " id='$id'" : '';
    $class = $class ? " class='$class'" : '';
    $style = $style ? " style='$style'" : '';

    $this->optionAddNoComment = true;

    return "<$tagName$id$class$style>$text</$tagName>";
});
