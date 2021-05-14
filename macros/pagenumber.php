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
        $out = "<span class='invisible'>{{ page }} $pageNumber {{ of }} $nPages</span> <span aria-hidden='true'>$pageNumber<span class='lzy-pg-no'></span> / $nPages</span>";

    } else {
        $out = "<span class='invisible'>{{ page }}</span> $pageNumber";
    }
    $this->optionAddNoComment = true;
	return $out; //$pageNumber;
});
