<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Page Switcher: Links and Keyboard Events
*/

if (!$this->config->isLegacyBrowser) {
    $this->page->addJsFiles("HAMMERJS");
    if ($this->config->feature_touchDeviceSupport) {
        $this->page->addJqFiles(["HAMMERJQ", "TOUCH_DETECTOR", "PAGE_SWITCHER", "JQUERY"]);
    } else {
        $this->page->addJqFiles(["HAMMERJQ", "PAGE_SWITCHER", "JQUERY"]);
    }
}

$nextLabel = $this->trans->getVariable('lzy-next-page-label');
$prevLabel = $this->trans->getVariable('lzy-prev-page-label');

$str = <<<EOT

    <div class='lzy-next-prev-page-links'>
        <div class='lzy-prev-page-link'><a href='~/{$this->siteStructure->prevPage}'>$prevLabel</a></div>
        <div class='lzy-next-page-link'><a href='~/{$this->siteStructure->nextPage}'>$nextLabel</a></div>
    </div>

EOT;
$this->page->addBodyEndInjections($str);
