<?php

$page->addModules('TOOLTIPSTER');
$page->addJq('$(".tooltip").tooltipster();');
//$page->addJq('$(".lzy-tooltip").tooltipster();');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$text = $this->getArg($macroName, 'text', 'Defines the visible text which shall get a tooltip.', '');
	$description = $this->getArg($macroName, 'description', 'Defines the text that shall be shown inside the tooltip bubble.', '');
	$contentFrom = $this->getArg($macroName, 'contentFrom', '[selector] If set, text from targeted element will be used as tooltip content.', '');
	$id = $this->getArg($macroName, 'id', 'If defined, that identifier is applied to the DOM element as ID attribute.', '');
	$class = $this->getArg($macroName, 'class', 'If defined, that identifier is added to the element class.', '');
	$arrow = $this->getArg($macroName, 'arrow', 'If true, an arrow points from the tooltip to the anker text.', '');
	$interactive = $this->getArg($macroName, 'interactive', 'If true, user can interact with the tooltip.', '');
	$trigger = $this->getArg($macroName, 'trigger', '[hover,click,custom] Sets when the tooltip should open and close.', '');
    $tooltipsterArgs = $this->getArg($macroName, 'tooltipsterArgs', 'String handed over to tooltipster() module as is.', '');

	if ($text === 'help') {
	    return '';
    }

	if ($arrow) {
        $tooltipsterArgs .= ", arrow:true,";
    }
	if ($interactive) {
        $tooltipsterArgs .= ", interactive:true,";
    }
	if ($trigger) {
        $tooltipsterArgs .= ", trigger:'$trigger',";
    }
	if ($contentFrom) {
        $tooltipsterArgs .= ", content: $('$contentFrom').html(), contentAsHTML: true,";
    }

	if ($tooltipsterArgs || $id || $class || $contentFrom) {
        $tooltipsterArgs = trim($tooltipsterArgs, ',');
        $tooltipsterArgs = str_replace(',,', ',', $tooltipsterArgs);
	    if (!preg_match('/^ { .*? } $/x', $tooltipsterArgs )) {
            $tooltipsterArgs = "{ $tooltipsterArgs }";
        }
	    if ($id) {
	        if ($id[0] === '#') {
	            $id = substr($id, 1);
            }
            $this->page->addJq("$('#$id').tooltipster( $tooltipsterArgs );");
	        $id = " id='$id'";

        } elseif ($class) {
            if ($class[0] === '.') {
                $class = substr($class,1);
            }
            $this->page->addJq("$('.$class').tooltipster( $tooltipsterArgs );");

        } else {
	        $id = "lzy-tooltip-$inx";
            $this->page->addJq("$('#$id').tooltipster( $tooltipsterArgs );");
            $id = " id='$id'";
        }
        $class = $class? " $class": '';
    }
	$str = "<span$id class='lzy-tooltip lzy-tooltip-$inx$class' title='$description'>$text</span>";

	$this->optionAddNoComment = true;
	// $this->compileMd = true;
	return $str;
});
