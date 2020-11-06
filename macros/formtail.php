<?php


require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $s = $this->getArg($macroName, 'noArguments', '', '');
    if ($s === 'help') {
        return "Requires no arguments.<br /> Renders form end element &lt;/form>.<br />Executes internal wrap-up process of Form module.";
    }

    // create form tail:
    $str = $this->form->render( ['type' => 'form-tail'] );

    $this->optionAddNoComment = true;
	return $str;
});
