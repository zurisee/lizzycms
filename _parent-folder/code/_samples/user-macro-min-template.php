<?php

// $page->addModules('');

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	$arg1 = $this->getArg($macroName, '', '', '');
    // $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

	$args = $this->getArgsArray($macroName);

	$str = '';  // text rendered by macro

    // $this->page->addCss( $css );
    // $this->page->addJs( $js );
    // $this->page->addJq( $jq );
    // etc., see more in page.class.php

	// $this->optionAddNoComment = true;
	// $this->compileMd = true;
	return $str;
});
