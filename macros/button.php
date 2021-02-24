<?php


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$text = $this->getArg($macroName, 'text', 'Text on button', '');
	$icon = $this->getArg($macroName, 'icon', 'Icon on button', '');
	$callbackCode = $this->getArg($macroName, 'callbackCode', '(optional) Defines JS code that will be executed when user clicks on button.', '');
	$callbackFunction = $this->getArg($macroName, 'callbackFunction', '[name of js-function] If defined, the function with that name is called when the button is activated.', '');
	$id = $this->getArg($macroName, 'id', '(optional) Defines the button\'s ID (default: lzy-button-N', '');
	$class = $this->getArg($macroName, 'class', '(optional) Defines the class applied to the button (default: lzy-button', '');
	$type = $this->getArg($macroName, 'type', '[toggle]', '');

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($text === 'help') {
        return '';
    }

    if ($icon) {
        $text = "<span class='lzy-icon lzy-icon-$icon'></span>$text";
    }

    if (!$id) {
        $id = "lzy-button-$inx";
    } else {
        $id = str_replace(['&#34;', '&#39;', '"', "'"], '', $callbackCode);
    }
    if (!$class) {
        $class = 'lzy-button';
    }
    if ($type === 'toggle') {
        $class .= ' lzy-toggle-button';
        $jq = <<<EOT

$('#$id').click(function() {
    $(this).toggleClass('lzy-button-active');
});

EOT;
        $this->page->addJq($jq);
    }
    if ($class) {
        $class = " class='$class'";
    } elseif ($class !== false) {
        $class = " class='$class'";
    }
	$str = "\t<button id='$id'$class>$text</button>\n";  // text rendered by macro

    if ($callbackCode) {
        $callbackCode = str_replace(['&#34;', '&#39;'], ['"', "'"], $callbackCode);
        $jq = <<<EOT

$('#$id').click(function( e ) {
    $callbackCode
});

EOT;
        $this->page->addJq($jq);
    }

    if ($callbackFunction) {
        $jq = <<<EOT

$('#$id').click(function( e ) {
    if (typeof $callbackFunction === 'function') {
        $callbackFunction( this, e );
    } else {
        mylog('Error: callback function "$callbackFunction" for button is not a function.');
    }
});

EOT;
        $this->page->addJq($jq);
    }

	return $str;
});
