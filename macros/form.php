<?php

require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
    $headArgs = $this->getArgsArray($macroName);
    if (isset($headArgs['disableCaching'])) {
        unset($headArgs['disableCaching']);
    }

    if (@$headArgs[0] === 'help') {
        $this->compileMd = true;
        return renderHelp();
    }


    if ($inx === 1) {
        $this->form = new Forms($this->lzy, $inx);
    }

    $headElements = ['formName', 'id', 'mailto', 'mailfrom', 'mailTo', 'mailFrom', 'legend', 'customResponseEvaluation', 'next',
        'confirmationText', 'file', 'warnLeavingPage', 'options', 'formTimeout', 'export', 'prefill',
        'preventMultipleSubmit', 'replaceQuotes', 'antiSpam', 'validate', 'showData',
        'showDataMinRows', 'encapsulate', 'disableCaching'];

    // separate arguments for header and fields:
    $headArgs = $this->getArgsArray($macroName);
    $formElems = [];
    foreach ($headArgs as $key => $value) {
        if (!in_array($key, $headElements)) {
            $formElems[$key] = $value;
            unset($headArgs[$key]);
        }
    }


    // create form head:
    $headArgs['type'] = 'form-head';
    $str = $this->form->render( $headArgs );

    // create form buttons:
    $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];

    // parse further arguments, interpret as form field definitions:
    foreach ($formElems as $label => $arg) {
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

        } elseif (strpos('formName,mailto,mailfrom,legend,showData', $label) !== false) {
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



function renderHelp()
{
    return <<<EOT
## Help on macro form()
form() is a short-hand for invoking form-head() and multiple form-elem(), terminated by form-tail().

All arguments that do not apply to the form-head() element will be interpreted as further form-elements.  
Use the following syntax:

    label: { argName: value, ... }

{{ vgap }}
Example:

	'Name:': { type:text, required:true },
	'E-Mail:': { email },    &#47;/ 'type' may be omitted

{{ vgap }}

Use pseudo-labels "submit" and "cancel" to define buttons like this:

    submit: { label:"Submit" },
    cancel: { label:"Cancel" },

{{ vgap }}

Find details about arguments of form-head() and form-elem() below:

{{ formhead( help ) }}
{{ vgap }}
{{ formelem( help ) }}
EOT;

}