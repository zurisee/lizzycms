<?php

// @info: Inserts some (horizontal) space of given width.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $width = $this->getArg($macroName, 'width', 'Width of inserted space. Use any form allowed in CSS, e.g. 3em, 20px or 1cm', '');
    if ($width === 'help') {
        return '';
    }
    $width = ($width) ? " style='width:$width'" : '';
	$str = "<span class='lzy-h-space'$width></span>";
	return $str;
});
