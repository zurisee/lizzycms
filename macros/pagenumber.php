<?php

// @info: Renders a page number (i.e. the page's position within the sitemap.)


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ( ) {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $offset = $this->getArg($macroName, 'offset', '(optional) offset value to be added to the page-number', 0);
    $addNumberOfPages = $this->getArg($macroName, 'addNumberOfPages', '(optional) number of all pages &rarr; overrides the automatically determined number of pages', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($offset === 'help') {
        return '';
    }

    $pageNumber = $this->siteStructure->currPageRec['listInx'] + 1 + $offset;

    if ( $addNumberOfPages ) {
        $nPages = $this->siteStructure->getNumberOfPages() + $offset;

        // inject understandable text for screen-readers:
        $out = "<span class='invisible'>{{ page }} $pageNumber {{ of }} $nPages</span> <span aria-hidden='true'><span class='lzy-page-nr-prefix'>$pageNumber<span class='lzy-page-nr-postfix'></span> / $nPages</span>";

    } else {
        $out = "<span class='invisible'>{{ page }}</span> <span class='lzy-page-nr-prefix'>$pageNumber<span class='lzy-page-nr-postfix'>";
    }
    $out = "<div class='lzy-pagenumber'>$out</div>";

    $this->optionAddNoComment = true;
	return $out; //$pageNumber;
});
