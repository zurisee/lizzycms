<?php


require_once SYSTEM_PATH.'forms.class.php';

$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $args = $this->getArgsArray($macroName);

    $label = $this->getArg($macroName, 'label', 'Label in front of the form field (mandatory).', '');

    if ($label === 'help') {
        $this->getArg($macroName, 'type', '[radio, checkbox, date, month, number, range, text, email, password, '.
            'textarea, hidden, bypassed, button] Type of the form field.', '');

        $this->getArg($macroName, 'id', 'Id applied to the form element.', '');

        $this->getArg($macroName, 'class', 'Class applied to the form element, to "&lt;input>.', '');

        $this->getArg($macroName, 'wrapperClass', 'Class applied to the &lt;div> which serves as a container '.
            'around label, form element etc.', '');

        $this->getArg($macroName, 'name', 'Defines how a data element is named when it is sent to the server. '.
            '(Default derived from label)', '');

        $this->getArg($macroName, 'required', 'If true, user is forced to provide input. Alternative: add "*" '.
            'to label.<br>Note: it is possible to apply "required" to a group in the sense that not all but '.
            'some must be filled in. E.g. "1 [tel,email]" meaning "either telefon or email must be provided".', '');

        $this->getArg($macroName, 'value', 'Defines a preset value.', '');

        $this->getArg($macroName, 'options', '[x|y|z] For radio, checkbox and dropdown type: string containing '.
            'list of options separated by "|", e.g. "A | Bb | Ccc"', '');

        $this->getArg($macroName, 'optionLabels', '[x|y|z] like "options" but values are used as labels in the page. '.
            'E.g. if they should be more explicit than the values stored. Note: optionLabels may undergo '.
            'translation to other languages, options will not.', '');

        $this->getArg($macroName, 'layout', 'For radio and checkbox: If true, options will be dispayed in one row, '.
            'rather than below each other.', '');

        $this->getArg($macroName, 'info', '[string] If defined, an info-icon will appear next to the field label. Clickiing on that icon will show given text in a tooltip.', '');

        $this->getArg($macroName, 'comment', 'Text that will be rendered below the form element.', '');

        $this->getArg($macroName, 'translateLabel', 'If true, label will be translated. E.g. "First name".'.
            '<br>Note: labels will be translated in any case if they start with \'-\'. (Default: false)', false);

        $this->getArg($macroName, 'labelInOutput', 'Text to be used as label in mail or as header in exported file.', '');

        $this->getArg($macroName, 'splitOutput', 'For radio, checkbox and dropdown type: If true, there is '.
            'one field (i.e column) in data written to the export file. If false, only one column will be '.
            'exported containing a textual summary of the selection submitted.', '');

        $this->getArg($macroName, 'placeholder', 'Text displayed in empty field, disappears when user enters data.', '');

        $this->getArg($macroName, 'autocomplete', 'If true, adds the "autocomplete" attribute to the '.
            'input element (see HTML).', '');

        $this->getArg($macroName, 'description', 'If defined, adds the "aria-describedby=" attribute to '.
            'the input element. Then adds a div below the input element containing the description itself. '.
            '(see HTML reps. WAI-ARIA).', '');

        $this->getArg($macroName, 'pattern', 'If defined, adds a "pattern" attribute to the input field, '.
            'e.g. pattern="[A-Za-z]{3}" (see HTML). ', '');

        $this->getArg($macroName, 'min', 'For number and range type: the min value (see HTML).', '');

        $this->getArg($macroName, 'max', 'For number and range type: the max value (see HTML).', '');

        $this->getArg($macroName, 'path', 'For upload type', '');

        $this->getArg($macroName, 'target', '[selector] For type "reveal": specifies the DOM element that shall be manipulated.', '');

//        $this->getArg($macroName, 'prefill', '', '');

        return 'A form must start with a <code>form-head()</code>, followed by any number of <code>form-elem()'.
        '</code> fields and must end with a <code>form-tail()</code> element.<br>'.
            'For the form to work properly the last element before <code>form-tail()</code> should be of type "button".';
    }

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is '.
        'disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }
    $type = @$args['type'];

	if ($type === 'form-head') {
		$this->form = new Forms($this->lzy);
    } elseif (!isset($this->form)) {
	    die('Error: forms must be initiated with type:"form-head"');
    }
	
	$str = $this->form->render($args);

    $this->optionAddNoComment = true;

    return $str;
});
