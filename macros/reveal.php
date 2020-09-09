<?php


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $label = $this->getArg($macroName, 'label', 'Text that prepresents the controlling element.', '');
    $target = $this->getArg($macroName, 'target', '[css selector] CSS selector of the DIV that shall be revealed, e.g. "#box"', '');
    $class = $this->getArg($macroName, 'class', '(optional) A class that will be applied to the controlling element.', '');
    $symbol = $this->getArg($macroName, 'symbol', '(triangle) If defined, the symbol on the left hand side of the label will be modified. (currently just "triangle" implemented.)', '');

    if ($label === 'help') {
        return '';
    }

    $id = "lzy-reveal-controller-$inx";

    if (stripos($symbol, 'tri') !== false) {
        $class .= ' lzy-reveal-triangle';
    }

    $class = $class? " $class": '';
    $out = '';
    $out .= "\t\t\t\t<input id='$id' class='lzy-reveal-checkbox' type='checkbox' data-reveal-target='$target' /><label for='$id'>$label</label>\n";

    $out = "\t\t\t<div class='lzy-reveal-controller$class'>$out</div>\n";

    $jq = <<<'EOT'

    $('.lzy-reveal-checkbox').each(function() {
        $(this).attr('aria-expanded', 'false');
        var $target = $( $( this ).attr('data-reveal-target') );
        if ( !$target.parent().hasClass('lzy-reveal-container') ) {
            $target.wrap("<div class='lzy-reveal-container'></div>").show();
            var boundingBox = $target[0].getBoundingClientRect();
            $target.css('margin-top', (boundingBox.height * -1 - 10) + 'px');
        }
    });
    
    $('.lzy-reveal-checkbox').change(function() {
        var $target = $( $( this ).attr('data-reveal-target') );
        var boundingBox = $target[0].getBoundingClientRect();
        $target.css('margin-top', (boundingBox.height * -1 - 10) + 'px');
        var $container = $target.parent();
        if ( $( this ).prop('checked') ) {
            $(this).attr('aria-expanded', 'true');
            $container.addClass('lzy-elem-revealed');
        } else {
            $(this).attr('aria-expanded', 'false');
            $container.removeClass('lzy-elem-revealed');
        }
    });

EOT;
    $this->page->addJq($jq);

    return $out;
});
