<?php

// @info: Renders components of online forms.


require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
	$args = $this->getArgsArray($macroName);
    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

	if ($inx === 1) {
		$this->form = new Forms($this->lzy);

        // Evaluate if form data received
        if (isset($_GET['lizzy_form']) || isset($_POST['lizzy_form'])) {	// we received data:
            $this->form->evaluate();
        }
    }
	
	$str = $this->form->render($args);

    $this->optionAddNoComment = true;

    return $str;
});
