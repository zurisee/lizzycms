<?php

// @info: Renders components of online forms.


require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
    $args = $this->getArgsArray($macroName);


    if ($inx === 1) {
        $this->form = new Forms($this->lzy);

        // Evaluate if form data received
        if (isset($_GET['lizzy_form']) || isset($_POST['lizzy_form'])) {	// we received data:
            $this->form->evaluate();
        }
    }


    if (isset($args[0]) && ($args[0] === 'help')) {
        return $this->form->renderHelp();
    }
    $label = $this->getArg($macroName, 'formName', '', '');
    $file = ($label) ? translateToFilename($label, 'csv') : '';
    $mailfrom = $this->getArg($macroName, 'mailfrom', '', '');
    $mailto = $this->getArg($macroName, 'mailto', '', '');
    $legend = $this->getArg($macroName, 'legend', '', '');

    // create form head:
    $str = $this->form->render([
        'type' => 'form-head',
        'label' => $label,
        'mailto' => $mailto,
        'mailfrom' => $mailfrom,
        'file' => $file,
        'legend' => $legend,
    ]);

    // create form buttons:
    $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];

    // parse further arguments, interpret as form field definitions:
    foreach ($args as $label => $arg) {
        if (is_string($arg)) {
            $arg = ['type' => $arg ? $arg : 'text'];
        }
        if (isset($arg[0])) {
            if ($arg[0] === 'required') {
                $arg['required'] = true;
                unset($arg[0]);
            } else {
                $arg['type'] = $arg[0];
            }
        }
        if ($label === 'submit') {
            $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Submit }},';
            $buttons["value"] .= 'submit,';
            $arg['type'] = 'button';

        } elseif ($label === 'cancel') {
            $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Cancel }},';
            $buttons["value"] .= 'cancel,';
            $arg['type'] = 'button';

        } elseif (strpos('formName,mailto,mailfrom,legend', $label) !== false) {
        } else {
            $arg['label'] = $label;
            $str .= $this->form->render($arg);
        }
    }
    if ($buttons["label"]) {
        $str .= $this->form->render($buttons);
    }

    $str .= $this->form->render([ 'type' => 'form-tail' ]);

	return $str;
});
