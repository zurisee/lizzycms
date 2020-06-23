<?php
// @info: -> one line description of macro <-

/*
 *
 * Definition of a Macro
 * =====================
 *
 * The context at this point is class Transvar.
 *
  * Note: to use this feature it must be enabled in config/config.yaml: custom_permitUserCode: true
 */
$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name

/*
 * The code in the preamble (up to "$this->addMacro()") is executed once during the rendering process.
 * It's place to load libraries etc.
 * E.g.
    require_once SYSTEM_PATH.'gallery.class.php';
 *
 * For manipulating the current page, use:

	$this->page->addOverlay();
	$this->page->addOverride();
	$this->page->addPageSubstitution();
	$this->page->addMessage();
	$this->page->addDebugMsg();

	$this->page->addBodyEndInjections();
	$this->page->addCssFiles();
	$this->page->addCss();
	$this->page->addJsFiles();
	$this->page->addJs();
	$this->page->addJqFiles();
	$this->page->addJq();

	$this->page->addHead();
	$this->page->addKeywords();
	$this->page->addDescription();

    $this->addVariable();
*/


$this->addMacro($macroName, function () {
	// the actual macro code follows here.
	// it will be executed for each invocation of this macro within the current page:

	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

	// typical variables that might be useful:
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

	// how to get access to macro arguments:
	$arg1 = $this->getArg($macroName, 'argName1', 'Help-text', 'default value');
	$arg2 = $this->getArg($macroName, 'argName2', 'Help-text', 'default value');

	// alternatively, get all arguments at once as an array:
	$args = $this->getArgsArray($macroName);


	// how to manipulate page-related objects, e.g.:
	// $this->page->addOverlay('');

	// how to define additional variables within a macro:
	// $this->addVariable('myvar', 'value');

	// $this->optionAddNoComment = true;
	// $this->compileMd = true;

	$str = "<!-- macro $macroName() loaded -->\n";
	return $str;
});
