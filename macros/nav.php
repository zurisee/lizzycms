<?php

// @info: Renders a navigation menu.
require_once SYSTEM_PATH.'nav-renderer.class.php';

$this->page->addModules('NAV');


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $type = $this->getArg($macroName, 'type', '[top, side, side-accordion, sitemap, sitemap-accordion, in-page] Specifies the type of output to be rendered.', '');
    $class = $this->getArg($macroName, 'class', 'Class to be applied to the surrounding NAV tag. (alias for \'navClass\')');
    $this->getArg($macroName, 'layout', '[horizontal, vertical] Specifies direction of top-level items.', '');
    $this->getArg($macroName, 'animation', '[dropdown, slidedown, collapsible] Defines the type of animation applied to the rendered tree.', '');
    $this->getArg($macroName, 'options', '[top-level, curr-branch] These are filters that render a subset of items.', '');
    $this->getArg($macroName, 'theme', '[dark] Selects coloring defaults.', '');

    $this->getArg($macroName, 'navClass', 'Class to be applied to the surrounding NAV tag.');
    $this->getArg($macroName, 'ulClass', 'Class to be applied to UL tags.');
    $this->getArg($macroName, 'liClass', 'Class to be applied to LI tags.');
    $this->getArg($macroName, 'hasChildrenClass', 'Class to be applied to elements that have children.');
    $this->getArg($macroName, 'aClass', 'Class to be applied to A tags.');
    $this->getArg($macroName, 'title', 'A title to be inserted just after the NAV tag. If it doesn\'t contain HTML, the title will be wrapped in H1 tags.');
    $this->getArg($macroName, 'ariaLabel', 'A label that describes the role, e.g. "Main Menu" (&rarr; needed by assistive technologies)');
    $this->getArg($macroName, 'arrow', 'The symbol to be placed in front of nav elements that have children.');

    $this->getArg($macroName, 'primary', '[true|false] If true, applies class "lzy-primary-nav". By default, the first instance of nav() gets the "lzy-primary-nav" class. This options permits to override the mechanism.', null);

    $this->getArg($macroName, 'depth', 'The max depth of headers which shall be included', 6);
    $this->getArg($macroName, 'targetElement', 'For "in-page": specifies the html tag that shall be targeted', false);
    $this->getArg($macroName, 'listTag', '[ol, ul, div] Specifies type of list. Default is OL.', 'ol');
    $this->getArg($macroName, 'listWrapper', 'Specifies whether sub-lists get a wrapper.', 'div');
    $this->getArg($macroName, 'smallScreenHeaderText', 'Text in small-screen-header, typically the app name', '');
    $this->getArg($macroName, 'inPageSizeThreshold', 'If less that this number of H-tags are found, the tag containing in-page nav is hidden', 0);

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($type === 'help') {
        return '';
    }

    $options = $this->getArgsArray($macroName);

    // -------- in-page
    if (($type === 'in-page') || ($type === 'inpage')) {
        return inPageNav($this->page, $options, $inx, $options["inPageSizeThreshold"]);

    // -------- small-screen-header
    } elseif ($type === 'small-screen-header') {
        return renderSmallScreenHeader($this, $options);
    }

    // make 'class' synonym for 'navClass'
    if ($class) {
        $options['navClass'] .= ' '.$class;
    }

    // case of one-pager -> has no site structure, so skip:
    if (!isset($this->siteStructure) || !$this->siteStructure) {
        return '';
    }

    $nav = new NavRenderer($this->lzy, $inx);
    return $nav->render($options);
});





function renderSmallScreenHeader($trans, $options)
{
    $smallScreenHeaderText = $options['smallScreenHeaderText'];
    if (!$smallScreenHeaderText) {
        $smallScreenHeaderText = $trans->getVariable('site_title');
    }
    if ($smallScreenHeaderText && (strpos($smallScreenHeaderText, '<') === false)) {
        $smallScreenHeaderText = "<h1>$smallScreenHeaderText</h1>";
    }

    $out =  "<div class='lzy-mobile-page-header'>".
                "$smallScreenHeaderText".
                "<button id='lzy-nav-menu-icon' class='lzy-nav-menu-icon' tabindex='1' aria-label='{{ lzy-operate-menu }}'><span class='lzy-icon lzy-icon-menu'></span></button>".
            "</div>";

    return $out;
}




function inPageNav($page, $options, $inx, $inPageSizeThreshold)
{

    $depth = $options['depth'];
    $targetElem = $options['targetElement'];
    $listTag = $options['listTag'];
    $title = $options['title'];

    if ($title && (strpos($title, '<') === false)) {
        $title = "<h1>$title</h1>";
    }

    $listElemTag = ($listTag === 'div') ? 'div' : 'li';
    renderInPageNav($page, $inx, $targetElem, $depth, $listElemTag, $inPageSizeThreshold);

    $out = "\t<nav class='lzy-in-page-nav' aria-label='{{ InPage TOC }}'>$title<$listTag id='lzy-in-page-nav$inx' class='lzy-in-page-nav'></$listTag></nav>\n";
    return $out;
} // inPageNav




function renderInPageNav($page, $inx, $targetElems, $depth, $listElemTag, $inPageSizeThreshold)
{
    $depth = intval($depth);
    $hMax = "H$depth";
    if ($targetElems) {
        $targetElems = ',' . strtoupper(str_replace(' ', '', $targetElems)) . ',';
    }
    $jq = <<<EOT

    var str = '';
    var nElems = 0;
    var targetElems = '$targetElems';
    $(':header').each(function() {
        var \$this = $(this);
        if (\$this.closest('.lzy-mobile-page-header').length) {
            return;
        }
        var tagName = this.tagName;
        if (targetElems) {  // skip tags if not in targetElems
            if (!targetElems.match(tagName)) {
                return;
            }
        }
        var hdrText = \$this.text();
        var id = hdrText.replace(/[^a-z0-9]/gi, '_');
        \$this.attr('id', id);
        var hdrId =  \$this.attr('id');
        if (tagName <= '$hMax') {
            str += '<$listElemTag class="'+tagName+'"><a href="#'+hdrId+'">'+hdrText+'</a></$listElemTag>';
            nElems++;
       }
    });
    $('#lzy-in-page-nav$inx').append(str);
    if (nElems < $inPageSizeThreshold) {
        $('#lzy-in-page-nav$inx').closest('nav.lzy-in-page-nav').parent().hide();
    }

EOT;

    $page->addJq($jq);
} // renderInPageNav

