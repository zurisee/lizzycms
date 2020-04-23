<?php

// @info: Renders blocks of content as either accordions or tab panels.


$page->addJQFiles('PANELS');

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $selector = $this->getArg($macroName, 'selector', 'Selector of DIV block(s) to be turned into panels. (Default is".lzy-panels-widget")', false);
    $mode = $this->getArg($macroName, 'mode', '[ tabs | accordion | auto ] (optional) If set, forces a particular mode.', 'auto');
    $background = $this->getArg($macroName, 'background', 'Defines the background color, e.g. #ff0', '');
    $unselectedTabsBackground = $this->getArg($macroName, 'unselectedTabsBackground', '', '');
    $outlineColor = $this->getArg($macroName, 'outlineColor', 'Defines the background color of unselected tabs', '');
    $selectedTextColor = $this->getArg($macroName, 'selectedTextColor', 'Defines text color of selected tabs', '');
    $unselectedTextColor = $this->getArg($macroName, 'unselectedTextColor', 'Defines text color of unselected tabs', '');
    $shadow = $this->getArg($macroName, 'shadow', 'Activates a shadow around the widget (optionally, provide your own CSS styling here)', '');
    $headerBeautifier = $this->getArg($macroName, 'headerBeautifier', 'Activates an improved visual effect on tabs and accordion headers (optionally, provide your own CSS styling here)', false);
    $oneOpenOnly = $this->getArg($macroName, 'oneOpenOnly', 'If true, makes sure that only one panel is open at the time (in accordion mode)', '');
    $tilted = $this->getArg($macroName, 'tilted', 'Activates tilted sides on tabs, providing an improved visual effect', false);
    $preOpen = $this->getArg($macroName, 'preOpen', '[false | number] If set, defines which panel should initially appear opened (numbers starting at 1)', true);
    $omitScript = $this->getArg($macroName, 'omitScript', '[false | number] If set, skips generation of init-script (thus you may invoke it manually before other scripts)', false);
//    $threshold = $this->getArg($macroName, 'threshold', 'defines the width threshold used for switching between tabs- and accordion-mode (default is 480px)', '');
//??? TBD
    // Threshold for switching modes:
//    if ($threshold) {
//        fatalError('panels/threshold not implemented yet - tbd!');
//        $threshold = preg_replace('/\D/', '', $threshold);
//
//        $this->page->addCssFiles("PANELS_CSS?threshold={$threshold}px");
//        $this->page->addJs("window.widthThreshold = '$threshold';");
//    } else {
//        $this->page->addCssFiles('PANELS_CSS');
//    }


    // Where to apply these settings - to this or to all widgets:
    if (!$selector) {
        $widgetSelector = ".lzy-panels-widget";
        $widgetClassSelector = ".lzy-panels-widget";

    } elseif ($selector[0] === '#') {
        $widgetSelector = $selector;
        $widgetClassSelector = ".lzy-panels-widget$inx";

    } elseif ($selector[0] === '.') {
        $widgetSelector = $selector;
        $widgetClassSelector = $selector;

    } else {
        $widgetSelector = ".$selector";
        $widgetClassSelector = ".$selector";
    }

    // Preset modes:
    if ($mode === 'accordion') {
        $this->page->addJq("$('$widgetSelector').addClass('lzy-accordion'); setMode();\n");

    } elseif ($mode === 'tabs') {
        $this->page->addJq("$('$widgetSelector').addClass('lzy-tabs'); setMode();\n");
    }

    if ($tilted) {
        $this->page->addJq("$('$widgetSelector').addClass('lzy-tilted');\n");
    }
    if ($oneOpenOnly) {
        $this->page->addJq("$('$widgetSelector').addClass('one-open-only');\n");
    }
    if ($preOpen === true) {
        $preOpen = 1;
    } elseif ($preOpen === false) {
        $preOpen = 'false';
    }

    if (!$omitScript) {
        $this->page->addJq("initLzyPanel( '$widgetSelector', $preOpen );\n");
    }


    // prepare CSS that depends on settings:
    $css = '';

    if ($unselectedTextColor) {
        $css .= "$widgetClassSelector.lzy-tab-mode .lzy-tabs-mode-panel-header { color: $unselectedTextColor; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header a { color: $unselectedTextColor; }\n";
    }
    if ($selectedTextColor) {
        $css .= "$widgetClassSelector .lzy-tabs-mode-panel-header[aria-selected=true] { color: $selectedTextColor; }\n";
    }
    if ($unselectedTabsBackground) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before { background: $unselectedTabsBackground; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header { background: $unselectedTabsBackground; }\n";
    }
    if ($background) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header[aria-selected=true]::after, $widgetClassSelector .lzy-tabs-mode-panel-header[aria-selected=true]::before, $widgetClassSelector .lzy-panel-inner-wrapper { background: $background; }\n";
    }
    if ($outlineColor) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before, $widgetClassSelector.lzy-tab-mode .lzy-panel-body-wrapper::before { border: 1px solid $outlineColor; outline-color: $outlineColor; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header { border: 1px solid $outlineColor; }\n";
    }
    if (($shadow === true) || ($shadow === 'true')) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before, $widgetClassSelector .lzy-panel-body-wrapper::before { box-shadow: 0 0 15px gray; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header::before { box-shadow: 0 0 15px gray; }\n";
    } elseif ($shadow) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before, $widgetClassSelector .lzy-panel-body-wrapper::before { $shadow; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header::before { $shadow; }\n";
    }
    if (($headerBeautifier === true) || ($headerBeautifier === 'true')) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before { background-image: linear-gradient( hsla( 0,0%, 100%,.6), hsla( 0,0%, 100%, 0)); }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header { background-image: linear-gradient( hsla( 0,0%, 100%,.6), hsla( 0,0%, 100%, 0)); }\n";
    } elseif ($headerBeautifier) {
        $css .= "$widgetClassSelector.lzy-tab-mode.lzy-tilted .lzy-tabs-mode-panel-header::after, $widgetClassSelector .lzy-tabs-mode-panel-header::before { $headerBeautifier; }\n";
        $css .= "$widgetClassSelector .lzy-accordion-mode-panel-header { $headerBeautifier }\n";
    }

    if ($css) {
        $this->page->addCss($css);
    }

	return '';
});
