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
	$id = $this->getArg($macroName, 'id', '(optional) Defines the button\'s ID (default: lzy-button-N)', '');
	$class = $this->getArg($macroName, 'class', '(optional) Defines the class applied to the button (default: lzy-button)', '');
	$type = $this->getArg($macroName, 'type', '[toggle] Defines the button\'s type-attribute.<br>Special case "toggle": in '.
        'this case JS is added to toggle class "lzy-button-active" and aria-attributes.<br>'.
        '<strong>Note</strong>: you can provide an alternative label for the active state via "text" option, e.g. type:"Off-State|On-State".', 'button');

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($text === 'help') {
        return '';
    } elseif (!$text) {
        $text = 'Button';
    }

    if ($icon) {
        $text = "<span class='lzy-icon lzy-icon-$icon'></span>$text";
    }

    if (!$id) {
        $id = "lzy-button-$inx";
    } elseif ($id[0] === '#') {
        $id = substr($id, 1);
//        $id = str_replace(['&#34;', '&#39;', '"', "'"], '', $callbackCode); //???
    }
    if (!$class) {
        $class = 'lzy-button';
    }
    $aria = '';
    if ($type === 'toggle') {
        $textActive = $text;
        if (strpos($text, '|') !== false) {
            list($text, $textActive) = explodeTrim('|', $text);
        }
        $class .= ' lzy-toggle-button';
        $aria = ' aria-pressed="false"';
        $jq = <<<EOT

$('#$id').click(function() {
    let \$this = $(this);
    if (\$this.hasClass('lzy-button-active')) {
        \$this.removeClass('lzy-button-active').text('$text').attr('aria-pressed', 'false');
    } else {
        \$this.addClass('lzy-button-active').text('$textActive').attr('aria-pressed', 'true');
    }  
});

EOT;
        $this->page->addJq($jq);
        $type = 'button';
    }

    if ($class) {
        $class = " class='$class'";
    } elseif ($class !== false) {
        $class = " class='$class'";
    }
	$str = "\t<button id='$id'$class type='$type'$aria>$text</button>\n";  // text rendered by macro

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
