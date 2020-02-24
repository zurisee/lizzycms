<?php

// @info: Renders components of online forms.


require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');

// Evaluate if form data received
if (isset($_GET['lizzy_form']) || isset($_POST['lizzy_form'])) {	// we received data:
	$this->form = new Forms($page, $this);
	$page->formEvalResult = $this->form->evaluate();
	unset($this->form);
}

$jsFloatPath = "~sys/third-party/floatlabel/";

$page->addCssFiles( [ "{$jsFloatPath}jquery.FloatLabel.css", "{$jsFloatPath}main.css" ]);
$page->addJqFiles("{$jsFloatPath}jquery.FloatLabel.js");



$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
    $args = $this->getArgsArray($macroName);


    if ($inx === 1) {
        $this->form = new Forms($this->page, $this);
    }
    if (isset($args[0]) && ($args[0] === 'help')) {
        return $this->form->renderHelp();
    }
    $mailto = $this->getArg($macroName, 'mailto', '', '');
    $legend = $this->getArg($macroName, 'legend', '', '');

    $str = $this->form->render([ 'type' => 'form-head', 'mailto' => $mailto ]);
    $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];
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

        } elseif (($label === 'mailto') || ($label === 'legend')) {
        } else {
            $arg['label'] = $label;
            $str .= $this->form->render($arg);
        }
    }
    if ($buttons["label"]) {
        $str .= $this->form->render($buttons);
    }

    $str .= $this->form->render([ 'type' => 'form-tail' ]);

    if ($legend) {
        $str = "<div class='lzy-form-legend'>$legend</div>$str";
    }

	return $str;
});
