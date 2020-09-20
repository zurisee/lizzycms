<?php

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

    $form = new Form($this->lzy, $inx);

    if (@$args[0] === 'help') {
        $this->compileMd = true;
        return $form->renderHelp();
    }
    $str = $form->render( $args );

	return $str;
});




class Form extends Forms
{
    public function __construct($lzy, $inx)
    {
        parent::__construct($lzy, $inx);
    }



    public function render( $headArgs )
    {
        $headElements = ',label,id,translateLabels,class,method,action,mailto,mailfrom,formHeader,'.
            'legend,customResponseEvaluation,next,file,confirmationText,warnLeavingPage,'.
            'encapsulate,formTimeout,avoidDuplicates,export,confirmationEmail,'.
            'confirmationEmailTemplate,prefill,preventMultipleSubmit,replaceQuotes,antiSpam,'.
            'validate,showData,showDataMinRows,options,encapsulate,disableCaching,'.
            'translateLabel,';

        // separate arguments for header and fields:
        $formElems = [];
        $formHint = '';
        $formFooter = '';
        foreach ($headArgs as $key => $value) {
            if ($key === 'formHint') {
                $formHint = $value;
                unset($headArgs[$key]);

            } elseif ($key === 'formFooter') {
                $formFooter = $value;
                unset($headArgs[$key]);

            } elseif (strpos($headElements, ",$key,") === false) {
                $formElems[$key] = $value;
                unset($headArgs[$key]);
            }
        }

        if ($formHint) {
            $headArgs['options'] = @$headArgs['options']? $headArgs['options'].' norequiredcomment': 'norequiredcomment';
        }

        // create form head:
        $headArgs['type'] = 'form-head';
        $str = parent::render( $headArgs );

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

            } elseif ($label === 'reset') {
                $buttons["label"] .= isset($arg['label']) ? $arg['label'].',': '{{ Reset }},';
                $buttons["value"] .= 'reset,';
                $arg['type'] = 'button';

            } elseif (strpos('formName,mailto,mailfrom,legend,showData', $label) !== false) {
                // nothing to do
            } else {
                $arg['label'] = $label;
                $str .= parent::render($arg);
            }
        }

        // inject formHint:
        if ($formHint && !$this->skipRenderingForm) {
            if (!preg_match('/\<.+\>/', $formHint)) {
                $formHint = "\t<div class='lzy-form-footer'>$formHint</div>\n";
            }
            $str .= str_replace(['&#39;','&#34;'], ['"', "'"], $formHint );
        }

        if ($buttons["label"]) {
            $str .= parent::render($buttons);
        }

        // inject formFooter:
        if ($formFooter && !$this->skipRenderingForm) {
            if (!preg_match('/\<.+\>/', $formFooter)) {
                $formFooter = "\t<div class='lzy-form-footer'>$formFooter</div>\n";
            }
            $str .= str_replace(['&#39;','&#34;'], ['"', "'"], $formFooter );
        }

        $str .= parent::render([ 'type' => 'form-tail' ]);

        return $str;
    } // render



    public function renderHelp()
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
    'E-Mail:': { email },    &#47;/ 'type': may be omitted

{{ vgap }}

Use pseudo-labels "submit" and "cancel" to define buttons like this:

    submit: { label:"Submit" },
    cancel: { label:"Cancel" },

{{ vgap }}

###  Arguments specific to form():

-> These arguments are supported in addition to those normally accepted by form().

formHint:
: (string) If supplied, the given text will be inserted just before the submit buttons.  
: Note: the automatic comment regarding required fields will be omitted, so you may need to provide explicitly.

formTail:
: (string) If supplied, the given text will be inserted after the submit buttons.  


Find details about arguments of form-head() and form-elem() below:

{{ formhead( help ) }}
{{ vgap }}
{{ formelem( help ) }}
EOT;
    } // renderHelp

} // Class Form
