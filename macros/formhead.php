<?php


require_once SYSTEM_PATH.'forms.class.php';


$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $label = $this->getArg($macroName, 'formName', 'Name of form: used for internal naming and default output filename.', '');

    if ($label === 'help') {
        $this->getArg($macroName, 'id', 'Id applied to the form element. If not supplied it will be derived from argument "formName".', '');

        $this->getArg($macroName, 'class', 'Class applied to the form element.', '');

        $this->getArg($macroName, 'method', '[post|get] Argument applied to the form element\'s "method"-attribute.', '');

        $this->getArg($macroName, 'action', 'Argument applied to the form element\'s "action"-attribute.', '');

        $this->getArg($macroName, 'mailTo', 'If set, an email will be sent to this address each time the form is filled in.', '');

        $this->getArg($macroName, 'mailFrom', 'The address from which above email is sent. (default: "{{ webmaster_email }}").', '');

        $this->getArg($macroName, 'formHeader', 'Text rendered above the form. Will be hidden upon successful completion of form entry.', '');

        $this->getArg($macroName, 'formHint', 'Text rendered above the form buttons. Will be hidden upon successful completion of form entry.', '');

        $this->getArg($macroName, 'formFooter', 'Text rendered below the form buttons. Will be hidden upon successful completion of form entry.', '');

        $this->getArg($macroName, 'customResponseEvaluation', 'Name of a PHP function to be called when user submitted form data.', '');

        $this->getArg($macroName, 'next', 'When a user successfully submits a form, "confirmationText" will be output. '.
            'Default contains a "continue..." link, whose address is defined by this argument.', '');

        $this->getArg($macroName, 'confirmationText', 'The text rendered upon completion of a form entry.', '');
        $this->getArg($macroName, 'file', 'File where to store data submitted by users. E.g. "&#126;data/form.yaml"', '');
        $this->getArg($macroName, 'formDataCaching', 'If the user enters data into the form an leaves the page, '.
            ' without submitting, values will be cached on the server and prefilled upon returning to the page. '.
            'This can be disabled by setting this option to false (Default: true).', true);

        $this->getArg($macroName, 'options', '[nocolor,validate,norequiredcomment] "nocolor" disables default coloring '.
            'of form elements; "validate" enables form validation by browser; "norequiredcomment" suppresses the explation of *=required', '');

        $this->getArg($macroName, 'labelWidth', '[width incl. unit] Defines the default label width, e.g. "8em" (default: 6em)', 'auto');

        $this->getArg($macroName, 'labelPosition', '[left,above,auto] Defines where field labels are positioned (default: auto)', 'auto');
        $this->getArg($macroName, 'labelColons', '[true,false] Defines whether to put a colon after each label (default: leave as is; false: suppress even if contained in label string)', false);

        $this->getArg($macroName, 'translateLabels', 'If true, Lizzy will try to translate all labels in this form (default: false)', '');

        $this->getArg($macroName, 'formTimeout', 'Defines the max time a user can wait between opening the form and '.
            'submitting it. (Default:false)', false);

        $this->getArg($macroName, 'avoidDuplicates', 'If true, Lizzy checks whether identical data-rec already '.
            'exists in DB. If so, skips storing new rec. (default: true).', true);

        $this->getArg($macroName, 'prefill', '[hash,url-arg] Hash corresponds to the key in the form-DB, i.e. where '.
            'previous form entries are stored. The "prefill" arguments lets you render the form prefilled with an '.
            'existing form-data-record. Hash can either be applied directly in this argument or indirectly via an '.
            'URL-argument of given name. E.g. ?key=ABCDEF.', '');

        //$this->getArg($macroName, 'preventMultipleSubmit', 'If true and if a user has started entering data, he/she '.
        //    'will be warned when trying to leave the page without submiting the form. Default: true.', true);

        $this->getArg($macroName, 'replaceQuotes', 'If true, quote characters (\' and ") contained in user\'s '.
            'entries will be converted to lookalikes, which cannot interfere with data-file-formats (yaml, json, csv). Default: true.', true);

        $this->getArg($macroName, 'antiSpam', '[false,field-ID] If true, an invisible "honey-pot" field is added '.
            'to the form. Spam-attacks typically try to fill in data and thus can be identified on the server. '.
            'To use, provide the id of the input field that accepts "last-name" input. The user can then override the '.
            'mechanism by re-entering his/her last name in UPPERCASE letters. You could use any other input field, '.
            'but need to modify the text resource "lzy-form-override-honeypot" accordingly. (default: false)', false);

        $this->getArg($macroName, 'validate', 'If true, the browser\'s validation mechanism is activated, '.
            'e.g. checking for required fields and compliance with field types. Note: this may conflict with '.
            'the "requiredGroup" mechanism. (default: false)', '');

        $this->getArg($macroName, 'showData', '[false, true, loggedIn, privileged, localhost, {group}] '.
            'Defines, to whom previously received data is presented (Default: false).', '');

        $this->getArg($macroName, 'confirmationEmail', '[name-of-email-field] If set to the name of an '.
            'e-mail field within the form, an confirmation mail will be sent (Default: false).', '');

        $this->getArg($macroName, 'confirmationEmailTemplate', '[name-of-template-file,true] This defines '.
            'what to put into the mail. If true, standard variables will be used: "lzy-confirmation-response-subject" '.
            'and "lzy-confirmation-response-message". Alternatively, you can specify the name of a template file. '.
            'All form-inputs are available as variables of the form "&#123;&#123; <strong>var_name</strong>_value }}" '.
            '(Default: false).', '');

        $this->getArg($macroName, 'showDataMinRows', '[integer] If defined, the "showData" table is filled with '.
            'empty rows up to given number. (Default: false)', '');

        $this->getArg($macroName, 'encapsulate', 'If true, activates Lizzy\'s widget encapsulation scheme '.
            '(i.e. adds class "lzy-encapsulated" to the form element).', '');

        $this->getArg($macroName, 'exportStructure', 'If true, Lizzy exports the datastructure corresponding to '.
            'given form data to file "filename_structure.yaml". This is needed when outputting data as a table. '.
            '(Default: true)', '');
        return '';
    }

    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching '.
        '(which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    $args = $this->getArgsArray($macroName);
    $args['type'] = 'form-head';

    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

    // create form head:
    $this->form = new Forms($this->lzy);
    $str = $this->form->render( $args );

    $this->optionAddNoComment = true;
    return $str;
});
