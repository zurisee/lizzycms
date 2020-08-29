<?php
/*
 *	Lizzy - forms rendering module
*/

define('UPLOAD_SERVER', '~sys/_upload_server.php');
define('CSV_SEPARATOR', ',');
define('CSV_QUOTE', 	'"');
define('DATA_EXPIRATION_TIME', false);
define('THUMBNAIL_PATH', 	'_/thumbnails/');

mb_internal_encoding("utf-8");

require_once(SYSTEM_PATH.'form-def.class.php');

// make option available to custom eval code:
$GLOBALS["globalParams"]['preventMultipleSubmit'] = false;


class Forms
{
	private $page = false;
	private $formDescr = [];		// FormDescriptor -> all info about all forms, stored in $_SESSION['lizzy']['formDescr']
    private $errorDescr = [];
	private $currFormIndex = null;	// index of current form within array of forms
	private $currForm = null;		// shortcut to $formDescr[ $currFormIndex ]
	private $currRecIndex = null;	// index of current element within array of form-elements
	private $currRec = null;		// shortcut to $currForm->formElements[ $currRecIndex ]
	private $currFormData = null;	// shortcut to previously entered form data in $formDescr[ <current form> ]->formData
	private $currRecData = null;	// shortcut data for current form-element: $formDescr[ <current form> ]->formData[ $currRecIndex ]

//-------------------------------------------------------------
	public function __construct($lzy)
	{
	    $this->lzy = $lzy;
		$this->transvar = $lzy->trans;
		$this->page = $lzy->page;
		$this->inx = -1;
        $this->addButtonsActions();
    } // __construct

    
//-------------------------------------------------------------
    public function render($args)
    {
        if (isset($args[0]) && ($args[0] === 'help')) {
            return $this->renderHelp();
        }

        $this->inx++;
        $this->parseArgs($args);

        $wrapperClass = 'lzy-form-field-wrapper';
        
        switch ($this->currRec->type) {
            case 'help':
                return $this->renderHelp();

            case 'form-head':
                return $this->renderFormHead($args);
            
            case 'text':
                $elem = $this->renderTextInput();
                break;
            
            case 'password':
                $elem = $this->renderPassword();
                break;
            
            case 'email':
                $elem = $this->renderEMailInput();
                break;
            
            case 'textarea':
                $elem = $this->renderTextarea();
                break;
            
            case 'radio':
                $elem = $this->renderRadio();
                break;
            
            case 'checkbox':
                $elem = $this->renderCheckbox();
                break;
            
            case 'button':
                $elem = $this->renderButtons();
                $wrapperClass = '';
                break;

            case 'url':
                $elem = $this->renderUrl();
                break;

            case 'date':
                $elem = $this->renderDate();
                break;

            case 'time':
                $elem = $this->renderTime();
                break;

            case 'datetime':
                $elem = $this->renderDateTime();
                break;

            case 'month':
                $elem = $this->renderMonth();
                break;

            case 'number':
                $elem = $this->renderNumber();
                break;

            case 'range':
                $elem = $this->renderRange();
                break;

            case 'tel':
                $elem = $this->renderTel();
                break;

            case 'file':
                $elem = $this->renderFileUpload();
                break;

            case 'dropdown':
                $elem = $this->renderDropdown();
                break;

            case 'fieldset':
                return $this->renderFieldsetBegin();

            case 'fieldset-end':
                return "\t\t\t\t</fieldset>\n";

            case 'hidden':
                $elem = $this->renderHidden();
                break;

            case 'bypassed':
                $elem = '';
                break;

            case 'form-tail':
				return $this->formTail();

            default:
                $type = isset($this->type)? $this->type : '';
                $elem = "<p>Error: form type unknown: '$type'</p>\n";
        }

        $type = $this->currRec->type;
        if (($type === 'radio') || ($type === 'checkbox')) {
            $type .= ' lzy-form-field-type-choice';
        } elseif ($type === 'button') {
            $type = 'buttons';
        }

        if (isset($args['layout'])) {
            $layout = $args['layout'];
            if ($layout[0] === 'v') {
                $this->currRec->wrapperClass = isset($this->currRec->wrapperClass)? "{$this->currRec->wrapperClass} lzy-vertical": 'lzy-vertical';
            } elseif ($layout[0] === 'h') {
                $this->currRec->wrapperClass = isset($this->currRec->wrapperClass)? "{$this->currRec->wrapperClass} lzy-horizontal": 'lzy-horizontal';
            }
        }
        if (isset($this->currRec->wrapperClass) && ($this->currRec->wrapperClass)) {
	        $class = "$wrapperClass lzy-form-field-type-$type {$this->currRec->wrapperClass}";
		} else {
            $elemId = $this->currForm->formId.'_'. $this->currRec->elemId;
            $class = "$elemId $wrapperClass lzy-form-field-type-$type";
		}

        // error in supplied data? -> signal to user:
        $error = '';
        $name = $this->currRec->name;
        if (isset($this->errorDescr[$this->formId][$name])) {
            $error = $this->errorDescr[$this->formId][$name];
            $error = "\n\t\t<div class='lzy-form-error-msg'>$error</div>";
            $class .= ' lzy-form-error';
        }
        $class = $this->classAttr($class);
        if (($this->currRec->type !== 'hidden') && ($this->currRec->type !== 'bypassed')) {
            $comment = '';
            if ($this->currRec->comment) {
                $comment = "\t\t\t<span class='lzy-form-elem-comment'>{$this->currRec->comment}\n\t\t</span>";
            }
		    $out = "\t\t<div $class>$error\n$elem\t\t$comment</div><!-- /field-wrapper -->\n\n";
        } else {
            $out = "\t\t$elem";
        }

        // add comment regarding required fields:
        if (($this->currRec->type === 'button') &&
                (stripos($this->currForm->options, 'norequiredcomment') === false)) {
            if (isset($this->currForm->hasRequiredFields) && $this->currForm->hasRequiredFields) {
                $out = "\t<div class='lzy-form-required-comment'>{{ lzy-form-required-comment }}</div>\n$out";
            }
        }

        return $out;
    } // render



    //-------------------------------------------------------------
    private function parseArgs($args)
    {
        if (!$this->currForm) {	// first pass -> must be type 'form-head' -> defines formId
            $label = $this->parseHeadElemArgs($args);

        } else {
            $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form-Elem'.($this->inx + 1);
            $this->translateLabel = (isset($args['translateLabel'])) ? $args['translateLabel'] : true;
            $formId = $this->currForm->formId;
            $this->currForm = &$this->formDescr[ $formId ];
        }


        $inpAttr = '';
        $type = $args['type'] = (isset($args['type'])) ? $args['type'] : 'text';
        if ($args['type'] === 'form-tail') {	// end-element is exception, doesn't need a label
            $label = 'form-tail';
        }
        if ($type === 'form-head') {
            $this->currRec = new FormElement;
            $this->currRec->type = 'form-head';
            return;
        }

        if (isset($args['name'])) {
            $name = str_replace(' ', '_', $args['name']);
        } else {
            $name = translateToIdentifier($label);
        }

        while (isset($this->currForm->formElements[$name])) {
            if (preg_match('/(.*?)(\d+)$/', $name, $m)) {
                $name = $m[1] . (intval($m[2]) + 1);
            } else {
                $name .= '1';
            }
        }
        if (isset($args['id'])) {
            $elemId = $args['id'];
        } else {
            $elemId = translateToIdentifier($name);
        }


        $this->currForm->formElements[$name] = new FormElement;
        $this->currRec = &$this->currForm->formElements[$name];
        $rec = &$this->currRec;

        $rec->type = $type;
        $rec->elemId = $elemId;

        if (strpos($label, '*')) {
            $label = trim(str_replace('*', '', $label));
            $args['required'] = true;
        }
        $rec->label = $label;

        $this->name = $name;
        $rec->name = $name;
        $_name = " name='$name'";


        if (isset($args['required']) && $args['required']) {
            if (preg_match('/(.*)\s*\[(.*)\]/', $args['required'], $m)) {
                $rec->required = false;
                $rec->requiredGroup = explodeTrim(',', $m[2]);
                $marker = $m[1];
                $rec->requiredMarker = $marker ? "<span class='lzy-form-combined-required-marker' aria-hidden='true'>$marker</span>" : '';
                $required = '';
            } else {
                $rec->required = true;
                $rec->requiredMarker = (is_bool($args['required'])) ? '*' : $args['required'];
                if ($rec->requiredMarker) {
                    $rec->requiredMarker = "<span class='lzy-form-required-marker' aria-hidden='true'>$rec->requiredMarker</span>";
                }
                $required = " required aria-required='true'";
            }
        } else {
            $rec->requiredMarker = false;
            $rec->required = false;
            $required = '';
        }

        if (isset($this->userSuppliedData[$name])) {
            if (($type === 'checkbox') || ($type === 'dropdown')) {
                $rec->val = $this->userSuppliedData[$name];
                $rec->value = isset($args['value'])? $args['value']: '';
            } elseif ($type === 'radio') {
                $rec->val = $this->userSuppliedData[$name];
                $rec->value = isset($args['value'])? $args['value']: '';
            } else {
                $rec->value = $this->userSuppliedData[$name];
            }

        } elseif (isset($args['value'])) {
            $rec->value = $args['value'];

        } else {
            $rec->value = '';
        }

        if (isset($args['valueNames'])) {
            $rec->valueNames = $args['valueNames'];
        } else {
            $rec->valueNames = $rec->value;
        }

        $rec->comment =  (isset($args['comment']))? $args['comment']: '';
        $rec->fldPrefix =  (isset($args['elemPrefix']) && ($args['elemPrefix'] !== false))? $args['elemPrefix']: 'fld_';

        if (isset($args['path'])) {
            $rec->uploadPath = $args['path'];
        } else {
            $rec->uploadPath = '~/upload/';
        }

        if ($type === 'form-head') {
            $this->currForm->formData['labels'][0] = 'Date';
            $this->currForm->formData['names'] = [];

        } elseif (($type !== 'button') && ($type !== 'form-tail') && (strpos($type, 'fieldset') === false)) {
            $rec->labelInOutput = (isset($args['labelInOutput'])) ? $args['labelInOutput'] : $label;
            $rec->labelInOutput = $this->transvar->translateVariable($rec->labelInOutput, true);
            $rec->labelInOutput = str_replace(':', '', $rec->labelInOutput);

            if (($type === 'checkbox') || ($type === 'radio') || ($type === 'dropdown')) {
                $checkBoxLabels = ($rec->valueNames) ? preg_split('/\s* [\|,] \s*/x', $rec->valueNames) : [];
                array_unshift($checkBoxLabels, $rec->labelInOutput);
                $this->currForm->formData['labels'][] = $checkBoxLabels;
            } else {
                $this->currForm->formData['labels'][] = $rec->labelInOutput;
            }
            $this->currForm->formData['names'][] = $name;
        }

        $rec->placeholder = (isset($args['placeholder'])) ? $args['placeholder'] : '';
        $rec->class = (isset($args['class'])) ? $args['class'] : '';
        $rec->autocomplete = (isset($args['autocomplete'])) ? $args['autocomplete'] : '';
        $rec->description = (isset($args['description'])) ? $args['description'] : '';
        $rec->errorMsg = (isset($args['errorMsg'])) ? $args['errorMsg'] : '';

        foreach (['min', 'max', 'pattern', 'placeholder'] as $attr) {
            if (isset($args[$attr])) {
                if (($type === 'checkbox') || ($type === 'radio') || ($type === 'dropdown')) {
                    continue;
                }
                if ($type === 'textarea') {
                    continue;
                }
                $inpAttr .= " $attr='{$args[$attr]}'";
            }
        }
        $rec->inpAttr = $_name.$inpAttr.$required;

        foreach($args as $key => $arg) {
            if (!isset($rec->$key)) {
                $rec->$key = $arg;
            }
        }
    } // parseArgs


//-------------------------------------------------------------
    private function parseHeadElemArgs($args)
    {
        if (!isset($args['type'])) {
            fatalError("Forms: mandatory argument 'type' missing.");
        }
        if ($args['type'] !== 'form-head') {
            fatalError("Error: syntax error \nor form field definition encountered without previous element of type 'form-head'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
        }

        // Note: some head arguments are evaluated in renderFormHeader()

        $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form' . ($this->inx + 1);
        $formId = (isset($args['id'])) ? $args['id'] : false;
        if (!$formId) {
            $formElemId = '';
            $formId = (isset($args['class']) && $args['class']) ? $args['class'] : translateToIdentifier($label);
            $formId = str_replace('_', '-', $formId);
        } else {
            $formElemId = $formId;
        }

        $this->formId = $formId;
        $this->formDescr[$formId] = new FormDescriptor;
        $this->currForm = &$this->formDescr[$formId];
        $this->currForm->formId = $formId;
        $this->currForm->formElemId = $formElemId;

        $this->currForm->formName = $label;

        $this->currForm->formData['labels'] = [];
        $this->currForm->formData['names'] = [];
        $this->userSuppliedData = $this->getUserSuppliedData($formId);

        if (!isset($args['warnLeavingPage']) || $args['warnLeavingPage']) {
            $this->page->addModules('~sys/js/forms-leave-warning.js');
        }

        // activate 'prevent multiple submits':
        $this->currForm->preventMultipleSubmit = false;
        if (isset($args['preventMultipleSubmit']) && $args['preventMultipleSubmit']) {
            $this->currForm->preventMultipleSubmit = true;
            $GLOBALS["globalParams"]['preventMultipleSubmit'] = true;
        }
        $this->currForm->replaceQuotes = (isset($args['replaceQuotes'])) ? $args['replaceQuotes'] : true;
        $this->currForm->antiSpam = (isset($args['antiSpam'])) ? $args['antiSpam'] : true;

        $this->currForm->validate = (isset($args['validate'])) ? $args['validate'] : false;
        $this->currForm->showData = (isset($args['showData'])) ? $args['showData'] : false;
        $this->currForm->showDataMinRows = (isset($args['showDataMinRows'])) ? $args['showDataMinRows'] : false;

        // options or option:
        $this->currForm->options = isset($args['options']) ? $args['options'] : (isset($args['option']) ? $args['option'] : '');
        $this->currForm->options = str_replace('-', '', $this->currForm->options);
        return $label;
    } // parseHeadElemArgs




//-------------------------------------------------------------
    private function extractArgs($args)
    {// strip superfluous chars and json-decode data

        $args = trim(html_entity_decode($args));	// translate html special chars
        $args = preg_replace(['/\s*,+$/', '/^"/', '/"$/'], '', $args);// remove blanks, leading and trailing quotes
        $args = preg_replace("/,\s*\}/", '}', $args);	// remove trailing ','
        $args = preg_replace("/'/", '"', $args);	// make sure data is quoted with " (according to json standard)
        $args = $this->args = json_decode($args, true); // json-decode

        return $args;
    } // extractArgs






//-------------------------------------------------------------
    private function renderFormHead($args)
    {
        $defaultClass = 'lzy-form';
        if (stripos($this->currForm->options, 'nocolor') === false) {
            $defaultClass .= ' lzy-form-colored';
        }
		$this->currForm->class = $class = (isset($args['class']) && $args['class']) ? $args['class'] : $defaultClass;
		if ($this->currForm->formName) {
		    $class .= ' '.str_replace('_', '-', translateToIdentifier($this->currForm->formName));
        }
        $this->currForm->class = $class;
        if (!isset($args['encapsulate']) || $args['encapsulate']) {
            $class .= ' lzy-encapsulated';
        }
		$_class = " class='$class'";

		$this->currForm->method = (isset($args['method'])) ? $args['method'] : 'post';
		$_method = " method='{$this->currForm->method}'";

		$this->currForm->action = (isset($args['action'])) ? $args['action'] : '';
		$_action = ($this->currForm->action) ? " action='{$this->currForm->action}'" : '';

		$this->currForm->mailto = (isset($args['mailto'])) ? $args['mailto'] : '';
		$this->currForm->mailfrom = (isset($args['mailfrom'])) ? $args['mailfrom'] : '';
		$this->currForm->postprocess = (isset($args['postprocess'])) ? $args['postprocess'] : '';
		$this->currForm->action = (isset($args['action'])) ? $args['action'] : '';
		$this->currForm->next = (isset($args['next'])) ? $args['next'] : './';
		$this->currForm->file = (isset($args['file'])) ? $args['file'] : '';
		$this->currForm->confirmationText = (isset($args['confirmationText'])) ? $args['confirmationText'] : '{{ lzy-form-data-received-ok }}';

		$time = time();
		if ($this->currForm->preventMultipleSubmit) {
		    $this->activatePreventMultipleSubmit();
        }
		$id = '';
		if ($this->currForm->formElemId) {
		    $id = " id='{$this->currForm->formElemId}'";
        }

		$out = '';
        if (isset($args['legend']) && $args['legend']) {
            $out = "<div class='lzy-form-legend'>{$args['legend']}</div>\n\n";
        }
        $novalidate = (!$this->currForm->validate) ? ' novalidate': '';
        if (!$novalidate) {
            $novalidate = (stripos($this->currForm->options, 'validate') === false) ? ' novalidate' : '';
        }
        $honeyPotRequired = ' required aria-required="true"';
        if (!$novalidate) {
            $novalidate = (stripos($this->currForm->options, 'validate') === false) ? ' novalidate' : '';
            $honeyPotRequired = '';
        }

        $out .= "\t<form$id$_class$_method$_action$novalidate>\n";
		$out .= "\t\t<input type='hidden' name='lizzy_form' value='{$this->currForm->formId}' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_time' name='lizzy_time' value='$time' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_next' name='lizzy_next' value='{$this->currForm->next}' />\n";

		if ($this->currForm->antiSpam) {
            $out .= "\t\t<div class='fld-ch' aria-hidden='true'>\n";
            $out .= "\t\t\t<label for='fld_ch{$this->inx}'>Name:</label><input id='fld_ch{$this->inx}' type='text' class='lzy-form-check' name='lzy-form-name' value=''$honeyPotRequired />\n";
            $out .= "\t\t</div>\n";
        }
		return $out;
	} // renderFormHead


//-------------------------------------------------------------
    private function renderTextInput()
    {
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= $this->getLabel();
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr();
        $autocomplete = $this->currRec->autocomplete? " autocomplete='{$this->currRec->autocomplete}'": '';
        $descrId = "{$this->currRec->fldPrefix}{$this->currRec->elemId}-descr";
        $descrBy = $this->currRec->description? " aria-describedby='$descrId'": '';

        $out .= "<input type='text' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value$autocomplete$descrBy />\n";
        if ($this->currRec->description) {
            $out .= "\t\t\t<div id='$descrId' class='lzy-form-field-description'>{$this->currRec->description}</div>\n";
        }
        return $out;
    } // renderTextInput


//-------------------------------------------------------------
    private function renderPassword()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $input = "<input type='password' class='lzy-form-password' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr} aria-invalid='false' aria-describedby='password-hint'$cls />\n";
        $hint = <<<EOT
            <label class='lzy-form-pw-toggle' for="showPassword"><input type="checkbox" id="lzy-form-showPassword{$this->inx}" class="lzy-form-showPassword"><img src="~sys/rsc/show.png" class="lzy-form-login-form-icon" alt="{{ show password }}" title="{{ show password }}" /></label>
EOT;
        $out = $this->getLabel();
        $out .= $input . $hint;
        return $out;
    } // renderPassword


//-------------------------------------------------------------
    private function renderTextarea()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $out = $this->getLabel();
        $out .= "<textarea id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls>{$this->currRec->value}</textarea>\n";
        return $out;
    } // renderTextarea


//-------------------------------------------------------------
    private function renderEMailInput()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('email');
        $out = $this->getLabel();
        $out .= "<input type='email' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderEMailInput



    //-------------------------------------------------------------
    private function renderRadio()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $values = ($this->currRec->value) ? preg_split('/\s*\|\s*/', $this->currRec->value) : [];
        if (isset($this->currRec->valueNames)) {
            $valueNames = preg_split('/\s*\|\s*/', $this->currRec->valueNames);
        } else {
            $valueNames = $values;
        }
        $groupName = translateToIdentifier($this->currRec->label);
        if ($this->currRec->name) {
            $groupName = $this->currRec->name;
        }
        $checkedElem = isset($this->currRec->val)? $this->currRec->val: false;
        $label = $this->getLabel(false, false);
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-radio-label'><div class='lzy-legend'><legend>{$label}</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";
        foreach($values as $i => $value) {
            $name = str_replace('!', '', $valueNames[$i]);
            $id = "lzy-radio_{$groupName}_$i";

            if (strpos($value, '!') !== false) {
                $value = str_replace('!', '', $value);
                if ($checkedElem === false) {
                    $checkedElem = $value;
                }
            }
            $checked = ($checkedElem && ($value === $checkedElem)) ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-radio-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='radio' name='$groupName' value='$name'$checked$cls /><label for='$id'>$value</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio


//-------------------------------------------------------------
    private function renderCheckbox()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $presetValues = isset($this->currRec->val)? $this->currRec->val: false;
        if ($presetValues) {
            $presetValues = array_map(function($e) { return str_replace('!', '', $e); }, $presetValues);
        }
        $values = ($this->currRec->value) ? preg_split('/\s*\|\s*/', $this->currRec->value) : [];
        if (isset($this->currRec->valueNames)) {
            $valueNames = preg_split('/\s*\|\s*/', $this->currRec->valueNames);
        } else {
            $valueNames = $values;
        }
        $groupName = translateToIdentifier($this->currRec->label);
        $label = $this->getLabel(false, false);
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-checkbox-label'><div class='lzy-legend'><legend>$label</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";

        foreach($values as $i => $value) {
            $preselectedValue = false;
            $name = str_replace('!', '', $valueNames[$i]);
            $id = "lzy-chckb_{$groupName}_$i";
            if (strpos($value, '!') !== false) {
                $value = str_replace('!', '', $value);
                if ($presetValues === false) {
                    $preselectedValue = true;
                }
            }

            $checked = (($presetValues !== false) && in_array($value, $presetValues)) || $preselectedValue ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-checkbox-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='checkbox' name='{$groupName}[]' value='$name'$checked$cls /><label for='$id'>$value</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio



    //-------------------------------------------------------------
    private function renderDropdown()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = isset($this->currRec->val) && $this->currRec->val? $this->currRec->val: '';
        $values = ($this->currRec->value) ? preg_split('/\s*\|\s*/', $this->currRec->value) : [];
        if (isset($this->currRec->valueNames)) {
            $valueNames = preg_split('/\s*\|\s*/', $this->currRec->valueNames);
        } else {
            $valueNames = $values;
        }

        $out = $this->getLabel();
        $out .= "<select id='{$this->currRec->fldPrefix}{$this->currRec->elemId}' name='{$this->currRec->name}'$cls>\n";
        $out .= "\t\t\t\t<option value=''></option>\n";

        foreach ($values as $i => $item) {
            if ($item) {
                $val = str_replace('!', '', $valueNames[$i]);
                $selected = '';
                if ((!$value && (strpos($item, '!') !== false)) || ($value === $val)){
                    $selected = ' selected';
                    $item = str_replace('!', '', $item);
                }
                $out .= "\t\t\t\t<option value='$val'$selected>$item</option>\n";
            }
        }
        $out .= "\t\t\t</select>\n";

        return $out;
    } // renderDropdown



//-------------------------------------------------------------
    private function renderUrl()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('url');
        $out = $this->getLabel();
        $out .= "<input type='url' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderUrl



//-------------------------------------------------------------
    private function renderDate()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('date');
        $out = $this->getLabel();
        $out .= "<input type='date' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderDate


//-------------------------------------------------------------
    private function renderTime()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('time');
        $out = $this->getLabel();
        $out .= "<input type='time' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderTime


//-------------------------------------------------------------
    private function renderDateTime()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('datetime');
        $out = $this->getLabel();
        $out .= "<input type='datetime-local' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderDateTime


//-------------------------------------------------------------
    private function renderMonth()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('month');
        $out = $this->getLabel();
        $out .= "<input type='month' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderMonth

//-------------------------------------------------------------
    private function renderNumber()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        $out .= "<input type='number' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderNumber


//-------------------------------------------------------------
    private function renderRange()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        $out .= "<input type='range' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderRange


//-------------------------------------------------------------
    private function renderTel()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('tel');
        $out = $this->getLabel();
        $out .= "<input type='tel' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderTel


//-------------------------------------------------------------
    private function renderFieldsetBegin()
    {
        if (!isset($this->currRec->legend)) {
            $this->currRec->legend = '';
        }
        if ($this->currRec->legend) {
            $legend = "\t\t\t\t<legend>{$this->currRec->legend}</legend>\n";
        } else {
            $legend = '';
        }
        $autoClass = ($this->currRec->legend) ? translateToIdentifier($this->currRec->legend).' ' : '';

        if ($autoClass || $this->currRec->class) {
            $class = " class='$autoClass{$this->currRec->class} lzy-form-fieldset'";
        } else {
            $class = " class='$autoClass lzy-form-fieldset'";
        }
        $out = "\t\t\t<fieldset$class>\n$legend";
        return $out;
    } // renderTel



//-------------------------------------------------------------
    private function renderFileUpload()
    {
        // While Lizzy's file-manager is active (admin_enableFileManager=true), the upload feature is not working due
        // to an incompatibility. Thus, we render a dummy button containing a warning:
        $e1 = $this->transvar->config->admin_enableFileManager;
        $e2 = !isset($this->transvar->page->frontmatter["admin_enableFileManager"]) || $this->transvar->page->frontmatter["admin_enableFileManager"];
        $e3 = !isset($this->transvar->page->frontmatter["enableFileManager"]) || $this->transvar->page->frontmatter["enableFileManager"];
        if ($e1 && $e2 && $e3) {
            $str = "<button class='lzy-form-file-upload-label lzy-button'><span class='lzy-icon-error' title='Upload() not working while Lizzy&#39;s file-manager is active.'></span>{$this->currRec->label}</button>";
            return $str;
        }


        $inx = $this->inx;
		$id = "lzy-upload-elem$inx";
		$server = isset($this->args['server']) ? $this->args['server'] : UPLOAD_SERVER;
		$multiple = $this->currRec->multiple ? 'multiple' : '';

        $targetPath = fixPath($this->currRec->uploadPath);
        $targetPath = makePathDefaultToPage($targetPath);
        $targetPathHttp = $targetPath;
        $targetFilePath = resolvePath($targetPath);

        $rec = [
            'uploadPath' => $targetFilePath,
            'pagePath' => $GLOBALS['globalParams']['pagePath'],
            'pathToPage' => $GLOBALS['globalParams']['pathToPage'],
            'appRootUrl' => $GLOBALS['globalParams']['absAppRootUrl'],
            'user'      => $_SESSION["lizzy"]["user"],
        ];
        $tick = new Ticketing();
        $this->ticket = $tick->createTicket($rec, 25);


        $thumbnailPath = THUMBNAIL_PATH;
        $list = "\t<div class='lzy-uploaded-files-title'>{{ lzy-uploaded-files-title }}</div>\n";  // assemble list of existing files
        $list .= "<ul>";
        $dispNo = ' style="display:none;"';
		if (isset($this->currRec->showExisting) && $this->currRec->showExisting) {
			$files = getDir($targetFilePath.'*');
			foreach ($files as $file) {
				if (is_file($file) && (fileExt($file) !== 'md')) {
					$file = basename($file);
					if (preg_match("/\.(jpe?g|gif|png)$/i", $file)) {
						$list .= "<li><span>$file</span><span><img src='$targetPathHttp$thumbnailPath$file' alt=''></span></li>";
					} else {
						$list .= "<li><span>$file</span></li>";
					}
				}
                $dispNo = '';
            }
        }
        $list .= "</ul>\n";

		$labelClass = $this->currRec->labelClass;
        $out = <<<EOT
            <div class="lzy-upload-wrapper">
                <input type="hidden" name="lzy-upload" value="{$this->ticket}" />
                <label class="$id lzy-form-file-upload-label $labelClass" for="$id">{$this->currRec->label}</label>
                <input id="$id" class="lzy-form-file-upload-hidden" type="file" name="files[]" data-url="$server" $multiple />
    
                <div class='lzy-form-progress-indicator lzy-form-progress-indicator$inx' style="display: none;">
                    <progress id="lzy-progressBar$inx" class="lzy-form-progressBar" max='100' value='0'>
                        <span id="lzy-form-progressBarFallback1-$inx"><span id="lzy-form-progressBarFallback2-$inx">&#160;</span></span>
                    </progress>
                    <div><span aria-live="polite" id="lzy-form-progressPercent$inx" class="lzy-form-progressPercent"></span></div>
                </div>
            </div> <!-- /lzy-upload-wrapper-->
			<div id='lzy-form-uploaded$inx' class='lzy-form-uploaded'$dispNo >$list</div>

EOT;

        if (!isset($this->uploadInitialized)) {
            $js = <<<EOT
var lzyD = new Date();
var lzyT0 = lzyD.getTime();

EOT;
            $this->page->addJs($js);
            $this->uploadInitialized = true;
        }
		$jq = <<<EOT

	$('#$id').fileupload({
	    url: '$server',
		dataType: 'json',
		
		progressall: function (e, data) {
		    mylog('processing upload');
		    $('.lzy-form-progress-indicator$inx').show();
			var progress = parseInt(data.loaded / data.total * 100, 10);
			$('#lzy-progressBar$inx').val(progress);
			var lzyD = new Date();
			var lzyT1 = lzyD.getTime();
			if (((lzyT1 - lzyT0) > 500) && (progress < 100)) {
				lzyT0 = lzyT1;
				$('#lzy-form-progressPercent$inx').text( progress + '%' );
			}
			if (progress === 100) {
				$('#lzy-form-progressPercent$inx').text( progress + '%' );
			}
		},

		done: function (e, data) {
		    mylog('upload accomplished');
			$.each(data.result.files, function (index, file) {
				if (file.name.match(/\.(jpe?g|gif|png)$/i)) {
					var img = '<img src="$targetPathHttp$thumbnailPath' + file.name + '" alt="" />';
				} else {
					var img = '';
				}
				var line = '<li><span>' + file.name + '</span><span>' + img + '</span></li>';
				$('#lzy-form-uploaded$inx').show();
				$('#lzy-form-uploaded$inx ul').append(line);
			});
		},
		
		error: function (data, textStatus, errorThrown) { 
		    mylog( data.responseText ); 
		},
	});

EOT;
		$this->page->addJq($jq);

		if (!isset($this->fileUploadInitialized)) {
			$this->fileUploadInitialized = true;

			$this->page->addJqFiles([
			    '~sys/third-party/jquery-upload/js/vendor/jquery.ui.widget.js',
                '~sys/third-party/jquery-upload/js/jquery.iframe-transport.js',
                '~sys/third-party/jquery-upload/js/jquery.fileupload.js',
                '~sys/third-party/jquery-upload/js/jquery.fileupload-process.js']);
		}
		
        return $out;
    } // renderFileUpload



//-------------------------------------------------------------
    private function renderButtons()
    {
        $indent = "\t\t";
		$label = $this->currRec->label;
		$value = (isset($this->currRec->value) && $this->currRec->value) ? $this->currRec->value : $label;
		$out = '';

        $class = " class='".trim($this->currRec->class .' lzy-form-button'). "'";
        $types = preg_split('/\s*[,|]\s*/', $value);

		if (!$types) {
			$id = 'btn_'.$this->currForm->formId.'_'.translateToIdentifier($value);
			$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";

		} else {
            $labels = preg_split('/\s*[,|]\s*/', $label);

			foreach ($types as $i => $type) {
			    if (!$type) { continue; }
				$id = 'btn_'.$this->currForm->formId.'_'.translateToIdentifier($type);
				$label = (isset($labels[$i])) ? $labels[$i] : $type;
				if (stripos($type, 'submit') !== false) {
					$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";
					
				} elseif ((stripos($type, 'reset') !== false) ||
                        (stripos($type, 'cancel') !== false)) {
				    if ($type[0] === '(') { // case: show reset button only if data has been supplied before:
				        $type = 'reset';
				        if ($this->userSuppliedData) {
                            $out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
                        }
                    } else {
                        $out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
                    }
					
				} else {
					$out .= "$indent<input type='button' id='$id' value='$label' $class />\n";
				}
			}
		}
        return $out;
    } //renderButtons




//-------------------------------------------------------------
    private function renderHidden()
    {
        $value = $this->getValueAttr();
        $name = " name='{$this->currRec->name}'";
        $value = " value='{$this->currRec->value}'";

        $out = "<input type='hidden' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'$name$value />\n";
        return $out;
    } // renderHidden



//-------------------------------------------------------------
	private function formTail()
    {
		$this->saveFormDescr();
		$out = "\t</form>\n";

        if (isset($this->formEvalResult)) {
			$out .= $this->formEvalResult;
		}
        $out .= $this->renderData();
        return $out;
	} // formTail




//-------------------------------------------------------------
    private function getLabel($id = false, $wrapOutput = true)
    {
		$id = ($id) ? $id : "{$this->currRec->fldPrefix}{$this->currRec->elemId}";
        $requiredMarker =  $this->currRec->requiredMarker;
        if ($requiredMarker) {
            $this->currForm->hasRequiredFields = true;
        }
        $label = $this->currRec->label;
		if ($this->translateLabel) {
		    $hasColon = (strpos($label, ':') !== false);
            $label = trim(str_replace([':', '*'], '', $label));
            $label = $this->transvar->translateVariable( $label, true );
            if ($hasColon) {
                $label .= ':';
            }
            if ($requiredMarker) {
                $label .= ' '.$requiredMarker;
            }
        } else {
            if ($requiredMarker && (strpos($label, ':') !== false)) {
                $label = rtrim($label, ':').' '.$requiredMarker.':';
            } else {
                $label .= ' '.$requiredMarker;
            }
        }

        if ($wrapOutput) {
            return "\t\t\t<label for='$id'>$label</label>";
        } else {
            return $label;
        }
    } // getLabel




//-------------------------------------------------------------
    private function classAttr($class = '')
    {
        $out = " class='".trim($class). "'";
        return trim($out);
    } // classAttr
    


//-------------------------------------------------------------
	private function saveFormDescr($formId = false, $formDescr = false)
	{
		$formId = $formId ? $formId : $this->currForm->formId;
		$formDescr = $formDescr ? $formDescr : $this->formDescr;
		$_SESSION['lizzy']['formDescr'][$formId] = serialize($formDescr);
	} // saveFormDescr


//-------------------------------------------------------------
	private function restoreFormDescr($formId)
	{
		return (isset($_SESSION['lizzy']['formDescr'][$formId])) ? unserialize($_SESSION['lizzy']['formDescr'][$formId]) : null;
	} // restoreFormDescr



//-------------------------------------------------------------
	private function saveUserSuppliedData($formId, $userSuppliedData)
	{
		$_SESSION['lizzy']['formData'][$formId] = serialize($userSuppliedData);
	} // saveUserSuppliedData



//-------------------------------------------------------------
	private function getUserSuppliedData($formId)
	{
		return (isset($_SESSION['lizzy']['formData'][$formId])) ? unserialize($_SESSION['lizzy']['formData'][$formId]) : null;
	} // getUserSuppliedData




//-------------------------------------------------------------
    public function evaluate()
    {
        // returns false on success, error msg otherwise:
        $this->userSuppliedData = $userSuppliedData = (isset($_GET['lizzy_form'])) ? $_GET : $_POST;
		if (isset($userSuppliedData['lizzy_form'])) {
			$this->formId = $formId = $userSuppliedData['lizzy_form'];
		} else {
			$this->clearCache();
			return false;
            //fatalError("ERROR: unexpected value received from browser", 'File: '.__FILE__.' Line: '.__LINE__);
		}
        if (@$userSuppliedData['lizzy_next'] === '_ignore_') { // case reload upon timeout:
            $this->saveUserSuppliedData($formId, $userSuppliedData);
            $_POST = [];
            return;
        }
		$dataTime = (isset($userSuppliedData['lizzy_time'])) ? $userSuppliedData['lizzy_time'] : 0;
		
		if ($dataTime > 0) {
			$this->saveUserSuppliedData($formId, $userSuppliedData);
		} else {
			$this->clearCache();
			return false;
		}
		$formDescr = $this->restoreFormDescr($formId);
		$currFormDescr = &$formDescr[$formId];
		if ($currFormDescr === null) {
            $this->clearCache();
		    return false;
        }

		$next = @$currFormDescr->next;
		
        list($msgToClient, $msgToOwner) = $this->defaultFormEvaluation($currFormDescr);
        $errDescr = @$this->errorDescr[ $this->formId ];
        if ($errDescr) {
            $_POST = [];
            return true;
        }
        $postprocess = isset($currFormDescr->postprocess) ? $currFormDescr->postprocess : false;
        if ($postprocess) {
			$result = $this->transvar->doUserCode($postprocess, null, true);
			if (is_array($result)) {
			    if (!$result[0]) {
                    fatalError($result[1]);
                } else {
                    $this->clearCache();
                    return $result[1];
                }
            }
			if ($result) {
			    if (!function_exists('evalForm')) {
                    fatalError("Warning: trying to execute evalForm(), but not defined in '$postprocess'.", 'File: '.__FILE__.' Line: '.__LINE__);
                }
				$str1 = evalForm($userSuppliedData, $currFormDescr, $msgToOwner, $this->page);
				if (is_string($str1)) {
				    $msgToClient .= $str1;
                }
			} else {
                fatalError("Warning: executing code '$postprocess' has been blocked; modify 'config/config.yaml' to fix this.", 'File: '.__FILE__.' Line: '.__LINE__);
			}
        }

        if (!isset($this->errorDescr[$this->formId]) ||
                    !$this->errorDescr[$this->formId] ) {
            $this->page->addCss(".$formId { display: none; }");
            $msgToClient .= "<div class='lzy-form-continue'><a href='{$next}'>{{ lzy-form-continue }}</a></div>\n";
            $this->clearCache();
            $this->formEvalResult = $msgToClient;

        } elseif ($msgToClient === null) { // error condition:
            return [$msgToOwner];
        }
        return $msgToClient;
    } // evaluate



//-------------------------------------------------------------
	private function defaultFormEvaluation($currFormDescr)
	{
	    // returns: [$msgToClient, $msgToOwner]
        // in error case: [null, null]
		$formName = $currFormDescr->formName;
		$mailTo = $currFormDescr->mailto;
		$mailFrom = $currFormDescr->mailfrom;
		$formData = &$currFormDescr->formData;
		$labels = &$formData['labels'];
		$names = &$formData['names'];
		$userSuppliedData = $this->userSuppliedData;

		// check honey pot field (unless on local host):
		if (($userSuppliedData["lzy-form-name"] !== '') &&
                !$GLOBALS["globalParams"]["localCall"]) {
		    $out = var_export($userSuppliedData, true);
            $out = str_replace("\n", ' ', $out);
            $out .= "\n[{$_SERVER['REMOTE_ADDR']}] {$_SERVER['HTTP_USER_AGENT']}\n";
            $logState = $GLOBALS["globalParams"]["errorLoggingEnabled"];
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = true;
            writeLog($out, 'spam-log.txt');
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = $logState;
            return ['', null]; // silently ignore entry
        }

		// check required entries:
        $this->checkSuppliedDataEntries($currFormDescr, $userSuppliedData);

		$errDescr = @$this->errorDescr[ $this->formId ];
		if ($errDescr) {
            $this->saveErrorDescr($errDescr);
            return [null, $errDescr];
        }

        $msgToOwner = "$formName\n===================\n\n";
		$log = '';
		$labelNames = '';
		$msgToClient = $currFormDescr->confirmationText;
		foreach($names as $i => $name) {
			if (is_array($labels[$i])) {
				$label = $labels[$i][0];
			} else {
				$label = $labels[$i];
			}
            $label = html_entity_decode($label);


            if (@$currFormDescr->formElements[$name]->type === 'bypassed') {
                $value = $currFormDescr->formElements[$name]->value;
                if ($value === '$user') {
                    $value = $GLOBALS["_SESSION"]["lizzy"]["user"];
                    $this->userSuppliedData[$names[$i]] = $value;
                }

            } else {
                $value = (isset($userSuppliedData[$name])) ? $userSuppliedData[$name] : '';
            }
            if (is_array($value)) {
                $value = implode(', ', $value);
            } else {
                $value = str_replace("\n", "\n\t\t\t", $value);
            }
            if (!isset($currFormDescr->replaceQuotes) || $currFormDescr->replaceQuotes) {
                $value = str_replace(['"', "'"], ['ʺ', 'ʹ'], $value);
            }

			$msgToOwner .= mb_str_pad($label, 22, '.').": $value\n\n";
			$log .= "'$value'; ";
            $labelNames .= "'$name'; ";
		}
		$msgToOwner = trim($msgToOwner, "\n\n");
		if ($res = $this->saveCsv($currFormDescr)) { // false = ok
		    $this->saveErrorDescr($res);
		    return [null, $res];
        }

        $msgToClient = "\n<div class='lzy-form-response'>\n$msgToClient\n</div><!-- /lzy-form-response -->\n";

        // send mail if requested:
		if ($mailTo) {
            $subject = $this->transvar->translateVariable('lzy-form-email-notification-subject', true);
            if (!$subject) {
                $subject = 'user data received';
            }
            $subject = "[$formName] ".$subject;

            $this->lzy->sendMail($mailTo, $mailFrom, $subject, $msgToOwner);
        }
        $this->writeLog($labelNames, $log, $formName, $mailTo);
        return [$msgToClient, $msgToOwner];
	} // defaultFormEvaluation()





//-------------------------------------------------------------
	private function saveCsv($currFormDescr)
	{
	    // returns false if ok, err msg otherwise
		$formId = $currFormDescr->formId;
		$errorDescr = $this->errorDescr;
        $errors = (isset($errorDescr[$formId]) && $errorDescr[$formId]) ? sizeof($errorDescr[$formId]) : 0;

		if (isset($currFormDescr->file) && $currFormDescr->file) {
		    $fileName = resolvePath($currFormDescr->file, true);
        } else {
            $fileName = resolvePath("~page/{$formId}_data.csv");
        }

        $userSuppliedData = $this->userSuppliedData;
        $names = $currFormDescr->formData['names'];
        $labels = $currFormDescr->formData['labels'];

        if ($errors) {
            return $errorDescr;
        }

        $db = new DataStorage2($fileName);
        if (!$db->lockDB()) {   // DB locked successfully?
            $_POST = [];
            return 'lzy-forms-error-db-locked'; // ToDo: provide error message
        }
        $data = $db->read();

        if (!$data) {   // no data yet -> prepend header row containing labels:
            $data = $this->prependHeaderRow($currFormDescr, $names, $labels);
        }

        $r = sizeof($data);
        $j = 0;
        $newRec = [];
        $formElements = &$currFormDescr->formElements;
        foreach($names as $i => $name) {
            // skip column if label starts with '_':
            if ($labels[$i][0] === '_') {
                continue;
            }
            $value = (isset($userSuppliedData[$name])) ? $userSuppliedData[$name] : '';
            if (@$currFormDescr->formElements[$name]->type === 'bypassed') {
                if (@$currFormDescr->formElements[$name]->value[0] !== '$') {
                    $newRec[$j++] = $currFormDescr->formElements[$name]->value;
                } else {
                    $newRec[$j++] = $value;
                }

            } elseif (is_array($labels[$i])) { // checkbox returns array of values
                $name = $names[$i];
                $splitOutput = (isset($currFormDescr->formElements[$name]->splitOutput))? $currFormDescr->formElements[$name]->splitOutput: false ;
                if (!$splitOutput) {
                    $newRec[$j++] = implode(', ', $value);

                } elseif($formElements[$name]->type === 'radio') {     // radio:
                    $labs = $labels[$i];
                    for ($k=1; $k<sizeof($labs); $k++) {
                        $l = $labs[$k];
                        $val = ($l === $value) ? '1' : ' ';
                        $newRec[$j++] = $val;
                    }

                } else {    // checkbox:
                    $labs = $labels[$i];
                    for ($k=1; $k<sizeof($labs); $k++) {
                        $l = $labs[$k];
                        $val = (in_array($l, $value)) ? '1' : ' ';
                        $newRec[$j++] = $val;
                    }
                }
            } else {        // normal value
                if (!isset($currFormDescr->replaceQuotes) || $currFormDescr->replaceQuotes) {
                    $value = str_replace(['"', "'"], ['ʺ', 'ʹ'], $value);
                }
                if (isset($formElements[$name])) {
                    $type = $formElements[$name]->type;
                    if (($type === 'email') && $value) {
                        if (!preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,})$/i", $value)) {
                            $errorDescr[$formId][$name] = "{{ lzy-error-in-email-addr }}. {{ lzy-please-correct }}";
                            $errors++;
                        }
                    }
                }
                $newRec[$j++] = $value;
            }
        }
        $newRec[$j] = date('Y-m-d H:i:s');

        // check whether rec already saved:
        $n = sizeof($newRec) - 1;
        for ($k=sizeof($data)-1; $k>0; $k--) {
            $rec = $data[$k];
            $diffFound = false;
            for ($i=0; $i<$n; $i++) {
                if ($rec[$i] !== $newRec[$i]) {
                    $diffFound = true;
                    break;
                }
            }
            if (!$diffFound) {
                // if no diff found in one of the records, we skip saving it again:
                $db->unlockDB();
                return 'lzy-forms-error-data-rec-already-present';   // ToDo: provide error msg?
            }
        }
        $data[$r] = $newRec;
        if ($errors === 0) {
            $db->write($data);
            $db->unlockDB();
            return false;
        }
        $db->unlockDB();
        return $errorDescr;
	} // saveCsv



    private function prependHeaderRow($currFormDescr, $names, $labels)
    {
        $data = [];
        $j = 0;
        foreach ($labels as $l => $label) {
            // skip column if label starts with '_':
            if ($label[0] === '_') {
                continue;
            }
            if (is_array($label)) { // checkbox returns array of values
                $name = $names[$l];
                $splitOutput = (isset($currFormDescr->formElements[$name]->splitOutput)) ? $currFormDescr->formElements[$name]->splitOutput : false;
                if (!$splitOutput) {
                    $data[0][$j++] = $label[0];
                } else {
                    for ($i = 1; $i < sizeof($label); $i++) {
                        $data[0][$j++] = html_entity_decode($label[$i]);
                    }
                }

            } else {        // normal value
                $label = html_entity_decode($label);
                $label = trim($label, ':');
                $data[0][$j++] = $label;
            }
        }
        $data[0][$j] = $this->transvar->translateVariable( 'lzy-timestamp', true );
        return $data;
    } // prependHeaderRow


    private function restoreErrorDescr()
    {
        return isset($_SESSION['lizzy']['formErrDescr']) ? $_SESSION['lizzy']['formErrDescr'] : [];
    }




    private function saveErrorDescr($errDescr)
    {
        $_SESSION['lizzy']['formErrDescr'] = $errDescr;
    }




//-------------------------------------------------------------
	private function sendMail($to, $from, $subject, $message)
	{
		$from  = ($from) ? $from : 'host@domain.com';
		$headers = "From: $from\r\n" .
			'X-Mailer: PHP/' . phpversion();
		
		if (!mail($to, $subject, $message, $headers)) {
            fatalError("Error: unable to send e-mail", 'File: '.__FILE__.' Line: '.__LINE__);
		}
	} // sendMail




//-------------------------------------------------------------
	public function clearCache()
	{
        unset($_SESSION['lizzy']['formDescr']);
        unset($_SESSION['lizzy']['formData']);
        unset($_SESSION['lizzy']['formErrDescr']);
	}





//-------------------------------------------------------------
    public function renderHelp()
    {
        $help = <<<EOT

# Options for macro *form()* end *formelem()*:
## General

Every form must begin with a header element (``type:form-head``) and end with closing element ``type:form-tail``.
In between you place form fields of desired types. (The *form()* macro does this automatically)

## form-head

type: 
: ``form-head ``  
: The first element (required)

label (macro *form()*  only): 
: Text which will be placed in front of the form.

name:
: Name applied to form element. If not supplied, name will be derived from label.

class: 
: Class applied to the form field.

method:
: [post|get] How to send data to the server (default: post).

action:
: (optional) Where to submit entered form data.

preventMultipleSubmit:
: [true|false] Activates prevention of multiple submissions of the form (default: true).

replaceQuotes:
: [true|false] If false, suppresses substitution of single and double quotes in data received from user (default: true). 
: (Substitution is done to prevent data corruption in log and data files.)

next:
: Where to take user after submitting data and receiving a confirmation.

file:
: In which file to store submitted data (in .csv format).  
: May contain a path, e.g. ``~data/mydata.csv``.

mailto:
: Data entered by users will be sent to this address.

mailfrom: 
: The sender address of the mail above.

validate:
: (optional) If true, the browser's validation mechanism is active, e.g. checking for required fields. (default: false)

postprocess:
: (optional) Name of php-script (in folder _code/) that will process submitted data.

showData:
: (optional) [false, true, loggedIn, privileged, localhost, {group}] Defines, to whom previously received data is presented (default: false).

warnLeavingPage:
: If true (=default), user will be warned if attempting to leave the page without submitting entries.

antiSpam:
: If true (=default), honey pot field is embedded in the form as an spam prevention measure.

encapsulate:
: If true, applies Lizzy's CSS encapsulation (i.e. adds lzy-encapsulated class to form element).



## Form Elements
Arguments applicable to regular form element:

type:
: [init, radio, checkbox, date, month, number, range, text, email, password, textarea, hidden, bypassed, button]  
: (mandatory) Defines the type of the form element.

label:
: Some meaningful label used for the form element

class:
: Class identifier that is added to form element.

wrapperClass:
: Applied to the element's wrapper div, if supplied - otherwise class will be applied.

required:
: Forces user input.

placeholder:
: Text displayed in empty field, disappears when user enters data.

labelInOutput:
: text to be used in mail and .csv data file.

value:
: Defines a preset value  
: *Radio and checkbox element only:*  
: {{ space }} -> list of values separated by '|', e.g. "A | BB | CCC"   
: *Button element only:*  
: {{ space }} -> [submit, reset]

splitOutput (Checkbox only):
: [true|false] -> If true, there is one field (i.e column) in data 
: written to the log-file and sent as a notification message.

min:
: Range element only: min value

max:
: Range element only: max value

elemPrefix:
: Prefix applied to input field IDs (default: 'fld_')

comment:
: (optional) Comment added just after the input element (class: 'lzy-form-elem-comment')


## form-tail

form-tail:
: The last element (required)




EOT;
        return compileMarkdownStr($help);
    } // renderHelp




    private function addButtonsActions()
    {
        $jq = <<<'EOT'
		
	$('input[type=reset]').click(function(e) {  // reset: clear all entries
		var $form = $(this).closest('form');
		$('.lizzy_time',  $form ).val(0);
		$form[0].submit();
	});
	
	$('input[type=button]').click(function(e) { // cancel: reload page (or goto 'next' if provided
		var $form = $(this).closest('form');
		var next = $('.lizzy_next', $form ).val();
		window.location.href = next;
	});
    $('.lzy-form-pw-toggle').click(function(e) {
        e.preventDefault();
		var $form = $(this).closest('form');
		var $pw = $('.lzy-form-password', $form);
        if ($pw.attr('type') === 'text') {
            $pw.attr('type', 'password');
            $('.lzy-form-login-form-icon', $form).attr('src', systemPath+'rsc/show.png');
        } else {
            $pw.attr('type', 'text');
            $('.lzy-form-login-form-icon', $form).attr('src', systemPath+'rsc/hide.png');
        }
    });

EOT;
        $this->page->addJq($jq);
    } // renderHelp




    private function activatePreventMultipleSubmit()
    {
        $jq = <<<EOT

jQuery.fn.preventDoubleSubmission = function() {
  $(this).on('submit',function(e){
    var \$form = $(this);
    if (\$form.data('submitted') === true) {
      // Previously submitted - don't submit again
      e.preventDefault();
    } else {
      // Mark it so that the next submit can be ignored
      \$form.data('submitted', true);
    }
  });
  return this;
};
$('form').preventDoubleSubmission();

EOT;
        $this->page->addJq($jq);
    } // activatePreventMultipleSubmit



    private function writeLog($labels, $log, $formName, $mailto)
    {
        writeLog("Form '$formName' response to: '$mailto'; body: '$log'");

        $timeStamp = timestamp();
        $logFile = LOGS_PATH."$formName.csv";
        if (!file_exists($logFile)) {
            file_put_contents($logFile, "{$labels}Timestamp\n");
        }
        file_put_contents($logFile, "$log$timeStamp\n", FILE_APPEND);
    } // writeLog




    private function getValueAttr($type = false)
    {
        $value = $this->currRec->value;
        if ($type === 'tel') {
            if (preg_match('/\D/', $value)) {
                $value = '';
            }
        } elseif ($type === 'number') {
            $value = preg_replace('/[^\d\.\-+]/', '', $value);
        } elseif ($type === 'url') {
            if (!preg_match('/[^@]+ @ [^@]+ \. [^@]{2,10}/x', $value)) {
                $value = '';
            }
        } elseif ($type === 'month') {
            if (!preg_match('/\d{4}-\d{2}/', $value)) {
                $value = '';
            }
        } elseif ($type === 'time') {
            if (!preg_match('/\d{2}:\d{2}(:\d{2})?/', $value)) {
                $value = '';
            }
        } elseif ($type === 'date') {
            if (!preg_match('/\d{4}-\d{2}-\d{2}/', $value)) {
                $value = '';
            }
        } elseif ($type === 'datetime') {
            if (!preg_match('/\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}(:\d{2})?/', $value)) {
                $value = '';
            }
        } if ($value) {
            $this->currRec->inpAttr .= " data-value='$value'";
            $value = $value ? " value='$value'" : '';
        }
        return $value;
    } // getValueAttr



    private function renderData()
    {
        global $globalParams;

        $out = '';
        $formId = $this->currForm->formId;
        $currFormDescr = $this->formDescr[ $formId ];
        $continue = true;
        if (!$currFormDescr->showData) {
            return '';

        } elseif ($currFormDescr->showData !== true) {
            // showData options: false, true, loggedIn, privileged, localhost, {group}
            switch ($currFormDescr->showData) {
                case 'logged-in':
                case 'loggedin':
                case 'loggedIn':
                    $continue = (bool)$_SESSION["lizzy"]["user"] || $globalParams["isAdmin"];
                    break;

                case 'privileged':
                    $continue = $this->lzy->config->isPrivileged;
                    break;

                case 'localhost':
                    $continue = $globalParams["localCall"];
                    break;

                default:
                    $continue = $this->lzy->auth->checkGroupMembership($currFormDescr->showData);
            }
        }

        if (!$continue) {
            return '';
        }

        if (isset($currFormDescr->file) && $currFormDescr->file) {
            $fileName = resolvePath($currFormDescr->file, true);
        } else {
            $fileName = resolvePath("~page/{$formId}_data.csv");
        }

        $out .= <<<EOT
<div class="lzy-forms-preview">
<h2>{{ lzy-form-data-preview-title }}</h2>

EOT;

        require_once SYSTEM_PATH.'htmltable.class.php';
        $ds = new DataStorage2($fileName);
        $data = $ds->read();
        if (!$data) {
            return '';
        }
        if ($currFormDescr->showDataMinRows) {
            $nCols = @sizeof($data[0]);
            $emptyRow = array_fill(0, $nCols, '&nbsp;');
            $max = intval($currFormDescr->showDataMinRows) + 1;
            for ($i = sizeof($data); $i < $max; $i++) {
                $data[$i] = $emptyRow;
            }
            for ($i=0; $i < $max; $i++) {
                $index = $i ? $i : '#';
                $rec = $data[$i];
                array_splice($rec, 0,0, [$index]);
                $data[$i] = $rec;
            }
            $options = [
                'dataSource' => $data,
                'headers' => true,
            ];
        } else {
            $options = [
                'dataSource' => $fileName,
                'headers' => true,
            ];
        }
        $tbl = new HtmlTable($this->lzy, 0, $options);
        $out .= $tbl->render();
        $out .= "</div>\n";
        return $out;
    } // renderData




    private function checkSuppliedDataEntries($currFormDescr, $userSuppliedData)
    {
        foreach ($currFormDescr->formElements as $name => $rec) {
            $type = $rec->type;
            $value = isset($userSuppliedData[$name])? $userSuppliedData[$name]: '';

            if ($rec->requiredMarker) {
                if ($g = isset($rec->requiredGroup) ? $rec->requiredGroup : []) {
                    $found = false;
                    foreach ($g as $n) {
                        if ($userSuppliedData[$n]) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $this->errorDescr[$this->formId][$name] = '{{ lzy-combined-required-value-missing }}';
                    }
                } elseif (!isset($userSuppliedData[$name]) || !$userSuppliedData[$name]) {
                    $this->errorDescr[$this->formId][$name] = '{{ lzy-required-value-missing }}';
                }
            }
            if ($value) {
                if ($type === 'email') {
                    if (!is_legal_email_address($value)) {
                        $this->errorDescr[$this->formId][$name] = '{{ lzy-invalid-email-address }}';
                    }
                } elseif ($type === 'number') {
                    if (preg_match('/[^\d.-]]/', $value)) {
                        $this->errorDescr[$this->formId][$name] = '{{ lzy-invalid-number }}';
                    }
                } elseif ($type === 'url') {
                    if (!isValidUrl($value)) {
                        $this->errorDescr[$this->formId][$name] = '{{ lzy-invalid-url }}';
                    }
// ToDo:
//            } else { // 'date','time','datetime','month','range','tel'
                }
            }
        }
    } // checkSuppliedDataEntries





} // Forms


