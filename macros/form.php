<?php

require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
    $args = $this->getArgsArray($macroName);
    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

    if (@$args[0] === 'help') {
        $this->compileMd = true;
        return renderFormHelp();
    }

    $form = new Forms($this->lzy);
    $str = $form->renderForm( $args );

	return $str;
});



function renderFormHelp()
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
} // renderFormHelp
