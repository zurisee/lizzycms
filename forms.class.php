<?php
/*
 *	Lizzy - forms rendering module
*/

define('UPLOAD_SERVER', '~sys/_upload_server.php');
define('CSV_SEPARATOR', ',');
define('CSV_QUOTE', 	'"');
define('DATA_EXPIRATION_TIME', false);
define('THUMBNAIL_PATH', 	'_/thumbnails/');
define('DEFAULT_EXPORT_FILE', 	'~page/form-export.csv');
define('SPAM_LOG_FILE', 	'spam-log.txt');

mb_internal_encoding("utf-8");


$GLOBALS["globalParams"]['lzyFormsCount'] = 1;
$GLOBALS["globalParams"]['warnLeavingPageInitialized'] = false;

class Forms
{
	private $page;
    private $inx;
	private $currForm = null;		// shortcut to $formDescr[ $currFormIndex ]
	private $currRec = null;		// shortcut to $currForm->formElements[ $currRecIndex ]
    public  $errorDescr = [];
    private $responseToClient = false;
    protected $skipRenderingForm = false;

    //-------------------------------------------------------------
	public function __construct($lzy, $inx)
	{
	    $this->lzy = $lzy;
		$this->trans = $lzy->trans;
		$this->page = $lzy->page;
		$this->inx = -1;
		$this->formInx = $inx;
		$this->formsCount = $GLOBALS["globalParams"]['lzyFormsCount']++;
        $this->currForm = new FormDescriptor; // object as will be saved in DB

        $this->tck = new Ticketing([
            'defaultType' => 'lzy-form',
            'defaultMaxConsumptionCount' => 100,
            'defaultValidityPeriod' => 86400,
        ]);

        if (isset($_POST['_lizzy-form'])) {	// we received data:
            $this->evaluateUserSuppliedData();
        }
	} // __construct

    
    //-------------------------------------------------------------
    public function render($args)
    {
        $this->args = $args;
        $this->inx++;
        if (($this->inx > 0) && (@$args['type'] !== 'form-tail')) {
            $this->currRec = &$this->currForm->formElements[ $this->inx ];
        }

        $wrapperClass = 'lzy-form-field-wrapper';

        $type = $this->parseArgs();
        if ($this->skipRenderingForm && ($type !== 'form-tail')) {
            return '';
        }
        switch ($type) {
            case 'form-head':
                return $this->renderFormHead();
            
            case 'text':
                $elem = $this->renderText();
                break;
            
            case 'password':
                $elem = $this->renderPassword();
                break;
            
            case 'email':
                $elem = $this->renderEMail();
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

            case 'dropdown':
                $elem = $this->renderDropdown();
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

            case 'fieldset':
                return $this->renderFieldsetBegin();

            case 'fieldset-end':
                return "\t\t\t\t</fieldset>\n";

            case 'reveal':
                $elem = $this->renderReveal();
                break;

            case 'hidden':
                $elem = $this->renderHidden();
                break;

            case 'bypassed':
                $elem = '';
                $this->bypassedValues[ $this->currRec->name ] = $this->currRec->value;
                break;

            case 'form-tail':
				return $this->renderFormTail();

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
        $out = '';
        $name = $this->currRec->name;
        if (isset($this->errorDescr[ $this->currForm->formId ][$name] )) {
            $error = $this->errorDescr[ $this->currForm->formId ][$name];
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
        } elseif ($this->currRec->type !== 'bypassed') {
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
    private function parseArgs()
    {
        if ($this->inx === 0) {    // first pass -> must be type 'form-head' -> defines formId
            return $this->parseHeadElemArgs();

        } else {
            return $this->parseElemArgs();
        }
    } // parseArgs



    //-------------------------------------------------------------
    private function parseHeadElemArgs()
    {
        $args = $this->args;

        if (!isset($args['type'])) {
            fatalError("Forms: mandatory argument 'type' missing.");
        }
        if ($args['type'] !== 'form-head') {
            fatalError("Error: syntax error \nor form field definition encountered without previous element of type 'form-head'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
        }

        // Note: some head arguments are evaluated in renderFormHeader()

        $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form' . $this->formsCount;
        $formId = (isset($args['id'])) ? $args['id'] : false;
        if (!$formId) {
            $formId = translateToClassName($label);
        }
        $this->formId = $formId;

        $currForm = &$this->currForm;
        $currForm->formId = $formId;

        $currForm->formName = $label;
        $currForm->translateLabels = (isset($args['translateLabels'])) ? $args['translateLabels'] : false;

        $currForm->class = (isset($args['class'])) ? $args['class'] : 'lzy-form';
        $currForm->method = (isset($args['method'])) ? $args['method'] : 'post';
        $currForm->action = (isset($args['action'])) ? $args['action'] : '';
        $currForm->mailTo = (isset($args['mailto'])) ? $args['mailto'] : ((isset($args['mailTo'])) ? $args['mailTo'] : '');
        $currForm->mailFrom = (isset($args['mailfrom'])) ? $args['mailfrom'] : ((isset($args['mailFrom'])) ? $args['mailFrom'] : '');
        $currForm->legend = (isset($args['formHeader'])) ? $args['formHeader'] : ''; // synonyme for 'legend'
        $currForm->legend = (isset($args['legend'])) ? $args['legend'] : $currForm->legend;
        $currForm->customResponseEvaluation = (isset($args['customResponseEvaluation'])) ? $args['customResponseEvaluation'] : '';
        $currForm->next = (isset($args['next'])) ? $args['next'] : './';
        $currForm->file = (isset($args['file'])) ? $args['file'] : '';
        $currForm->confirmationText = (isset($args['confirmationText'])) ? $args['confirmationText'] : '{{ lzy-form-data-received-ok }}';
        $currForm->warnLeavingPage = (isset($args['warnLeavingPage'])) ? $args['warnLeavingPage'] : true;
        $currForm->encapsulate = (isset($args['encapsulate'])) ? $args['encapsulate'] : true;
        $currForm->formTimeout = (isset($args['formTimeout'])) ? $args['formTimeout'] : false;
        $currForm->avoidDuplicates = (isset($args['avoidDuplicates'])) ? $args['avoidDuplicates'] : true;
        $currForm->export = (isset($args['export'])) ? $args['export'] : false;
        $currForm->confirmationEmail = (isset($args['confirmationEmail'])) ? $args['confirmationEmail'] : false;
        $currForm->confirmationEmailTemplate = (isset($args['confirmationEmailTemplate'])) ? $args['confirmationEmailTemplate'] : false;
        if ($currForm->export === true) {
            $currForm->export = DEFAULT_EXPORT_FILE;
        }

        if (!$currForm->file) {
            $currForm->file = "~data/form-$formId.yaml";
        }
        $currForm->file = resolvePath($currForm->file, true);

        $currForm->prefill = (isset($args['prefill'])) ? $args['prefill'] : false;
        if ($currForm->prefill) {
            if (preg_match('/^[A-Z0-9]{5,10}$/', $currForm->prefill)) {
                $hash = $currForm->prefill;
            } else {
                $hash = getUrlArg($currForm->prefill, true);
            }
            if ($hash) {
                $ds = new DataStorage2($currForm->file);
                $rec = $ds->readRecord($hash);
                if ($rec) {
                    $currForm->prefillRec = $rec;
                }
            }
        }


        // activate 'prevent multiple submits':
        $currForm->preventMultipleSubmit = (@$args['preventMultipleSubmit'] !== null)? $args['preventMultipleSubmit'] : true;
        $GLOBALS["globalParams"]['preventMultipleSubmit'] = $currForm->preventMultipleSubmit;

        $currForm->replaceQuotes = (isset($args['replaceQuotes'])) ? $args['replaceQuotes'] : true;
        $currForm->antiSpam = (isset($args['antiSpam'])) ? $args['antiSpam'] : false;
        if ($currForm->antiSpam && $this->lzy->localCall && !$_SESSION["lizzy"]["debug"]) {    // disable antiSpam on localhost for convenient testing of forms
            $currForm->antiSpam = false;
            $this->page->addDebugMsg('"antiSpam" disabled on localhost');
        }

        $currForm->validate = (isset($args['validate'])) ? $args['validate'] : false;
        $currForm->showData = (isset($args['showData'])) ? $args['showData'] : false;
        if (($currForm->showData) && !$currForm->export) {
            $currForm->export = DEFAULT_EXPORT_FILE;
        }
        $currForm->export = resolvePath($currForm->export, true);

        $currForm->showDataMinRows = (isset($args['showDataMinRows'])) ? $args['showDataMinRows'] : false;

        // options or option:
        $currForm->options = isset($args['options']) ? $args['options'] : (isset($args['option']) ? $args['option'] : '');
        $currForm->options = str_replace('-', '', $currForm->options);

        return 'form-head';
    } // parseHeadElemArgs




    //-------------------------------------------------------------
    private function parseElemArgs()
    {
        $args = $this->args;

        $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form-Elem'.($this->inx + 1);

        $inpAttr = '';
        $type = $args['type'] = (isset($args['type'])) ? $args['type'] : 'text';
        if ($type === 'form-tail') {	// end-element is exception, doesn't need a label
            return 'form-tail';
        }

        $rec = &$this->currRec;
        $rec = new FormElement;
        $rec->type = $type;

        if (isset($args['name'])) {
            $name = str_replace(' ', '_', $args['name']);
        } else {
            $name = translateToIdentifier($label);
        }
        $name = $name . '_' . $this->inx;   // add elem id
        $rec->name = $name;
        $_name = " name='$name'";

        // whether to translate a label: form-wide translateLabels or per-element translateLabel or '-' in front of label:
        $rec->translateLabel = $this->currForm->translateLabels || ($label[0] === '-') || (@$args['translateLabel'] ? $args['translateLabel'] : false);

        if (isset($args['id'])) {
            $elemId = $args['id'];
        } else {
            $elemId = translateToIdentifier($name) . '_' . $this->formsCount;
        }

        $rec->elemId = $elemId;
        $rec->elemInx = $this->inx . '_' . $this->formsCount;

        if (strpos($label, '*')) {
            $label = trim(str_replace('*', '', $label));
            $args['required'] = true;
        }
        $rec->label = $label;

        $rec->class = @$args['class'] ? $args['class'] : '';
        $rec->wrapperClass = @$args['wrapperClass']? $args['wrapperClass']: '';

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

        if (@$this->currForm->prefillRec) {
            if (isset($this->currForm->prefillRec[ $name ])) {
                $value = $this->currForm->prefillRec[ $name ];
                if (is_array($value)) {
                    $rec->prefill = str_replace(',', '|', $value[0]);
                } else {
                    $rec->prefill = $value;
                }
            }
        }
        if (@$this->userSuppliedData[$name]) {
            $rec->prefill = $this->userSuppliedData[$name];
        }

        $rec->target = @$args['target']? $args['target']: '';
        $rec->value = @$args['value']? $args['value']: '';

        // for radio, checkbox and dropdown: options define the values available
        //  optionLabels are optional and used in the page, e.g. if you need a longish formulation to describe the option
        //  (for backward compatibility, value is accepted in place of options)
        $rec->options = @$args['options']? $args['options']: $rec->value;
        $rec->optionLabels = @$args['optionLabels']? $args['optionLabels']: $rec->options;

        // for backward compatibility:
        //  valueNames used to have the meaning of today's option
        if (@$args['valueNames']) { // if supplied, swap arguments:
            $rec->optionLabels = $rec->options;
            $rec->options = $args['valueNames'];
        }

        // for radio, checkbox and dropdown:
        if (($type === 'radio') || ($type === 'checkbox') || ($type === 'dropdown')) {
            $rec->optionNames = explodeTrim('|,', $rec->options);
            $rec->optionLabels = explodeTrim('|,', $rec->optionLabels);
            foreach ($rec->optionLabels as $key => $oLabel) {
                if ((@$oLabel[0] === '-') || $this->currForm->translateLabels) {
                    $rec->optionLabels[$key] = $this->trans->translateVariable($oLabel, true);
                }
                if (!@$rec->optionNames[$key]) {
                    $s = (@$oLabel[0] === '-')? substr($oLabel,1): $oLabel;
                    $rec->optionNames[$key] = str_replace('!', '', $s);
                }
            }
        }

        // initialize "info-icon" feature:
        $rec->info =  (isset($args['info']))? $args['info']: '';
        if ($rec->info && !@$this->infoInitialized) {
            $this->infoInitialized = true;
            $jq = <<<EOT
$('.lzy-formelem-show-info').tooltipster({
    trigger: 'click',
    contentCloning: true,
    animation: 'fade',
    delay: 200,
    animation: 'grow',
    maxWidth: 420,
});

EOT;
            $this->page->addJq( $jq );
            $this->page->addModules( 'TOOLTIPSTER' );
        }
        $rec->comment =  (isset($args['comment']))? $args['comment']: '';
        $rec->fldPrefix =  (isset($args['elemPrefix']) && ($args['elemPrefix'] !== false))? $args['elemPrefix']: 'fld_';

        if (isset($args['path'])) {
            $rec->uploadPath = $args['path'];
        } else {
            $rec->uploadPath = '~/upload/';
        }

        if (($type !== 'button') && ($type !== 'form-tail') && (strpos($type, 'fieldset') === false)) {
            $rec->labelInOutput = (isset($args['labelInOutput'])) ? $args['labelInOutput'] : $label;
            if ($rec->translateLabel) {
                $rec->labelInOutput = $this->trans->translateVariable($rec->labelInOutput, true);
            }
            $rec->labelInOutput = str_replace(':', '', $rec->labelInOutput);

            if (($type === 'checkbox') || ($type === 'radio') || ($type === 'dropdown')) {
                array_unshift($rec->optionLabels, $rec->labelInOutput);
                array_unshift($rec->optionNames, $rec->labelInOutput);
            }
        }

        $rec->placeholder = (isset($args['placeholder'])) ? $args['placeholder'] : '';
        $rec->splitOutput = (isset($args['splitOutput'])) ? $args['splitOutput'] : false;
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

        return $type;
    } // parseElemArgs




    //-------------------------------------------------------------
    private function renderFormHead()
    {
        $formId = $this->formId;
        $currForm = $this->currForm;

        $this->initButtonHandlers();

        $this->userSuppliedData = $this->getUserSuppliedDataFromCache($formId);
        $currForm->creationTime = time();

        $ticketHash = getStaticVariable( 'forms.'.$currForm->formId );
        if ($ticketHash) {
            if ($this->tck->ticketExists($ticketHash)) {
                $currForm->ticketHash = $ticketHash;
            } else {
                $ticketHash = false;
            }
        }
        if (!$ticketHash) {
            $currForm->ticketHash = $this->tck->createTicket([]);
            setStaticVariable( 'forms.'.$currForm->formId, $currForm->ticketHash );
        }

        if ($currForm->warnLeavingPage && !$GLOBALS["globalParams"]['warnLeavingPageInitialized']) {
            $GLOBALS["globalParams"]['warnLeavingPageInitialized'] = true;
            $js = <<<EOT
function onUnloadPage() {
    if ( lzyFormUnsaved ) {
        return true;
    }
}
var lzyFormUnsaved = false;
window.onbeforeunload = onUnloadPage;

EOT;
            $this->page->addJs( $js );
            $js = <<<EOT
$(":input").change(function () {
    lzyFormUnsaved = true;
});

EOT;
            $this->page->addJq( $js );
        }

        $id = " id='{$this->formId}'";

        $legendClass = 'lzy-form-legend';
        $announcementClass = 'lzy-form-announcement';
        if (stripos($currForm->options, 'nocolor') === false) {
            $currForm->class .= ' lzy-form-colored';
            $legendClass .= ' lzy-form-colored';
            $announcementClass .= ' lzy-form-colored';
        }
        $class = &$currForm->class;
        $class = "$formId $class";

        if ($currForm->encapsulate) {
            $class .= ' lzy-encapsulated';
        }
        $_class = " class='$class'";

		$_method = " method='{$currForm->method}'";
		$_action = ($currForm->action) ? " action='{$currForm->action}'" : '';

		if ($currForm->preventMultipleSubmit) {
		    $this->activatePreventMultipleSubmit();
        }

        $novalidate = (!$currForm->validate) ? ' novalidate': '';
        if (!$novalidate) { // also possible as option:
            $novalidate = (stripos($currForm->options, 'novalidate') !== false) ? ' novalidate' : '';
        }
        $honeyPotRequired = ' required aria-required="true"';
        if (!$novalidate) {
            $novalidate = (stripos($currForm->options, 'novalidate') !== false) ? ' novalidate' : '';
            $honeyPotRequired = '';
        }



        // now assemble output, i.e. <form> element:
		$out = '';
        if ($currForm->legend) {
            $out = "<div class='$legendClass {$currForm->formId}'>{$currForm->legend}</div>\n\n";
        }

        // handle general error feedback:
        if (@$this->errorDescr[$this->currForm->formId]['_announcement_']) {
            $msg = $this->errorDescr[$this->currForm->formId]['_announcement_'];
            $this->errorDescr[$this->currForm->formId]['_announcement_'] = false;
            $out .= "\t<div class='$announcementClass'>$msg</div>\n";
        }

        $out .= "\t<form$id$_class$_method$_action$novalidate>\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form-id' value='{$this->formInx}' />\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form-label' value='{$currForm->formName}' />\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form' value='{$currForm->ticketHash}' />\n";
		$out .= "\t\t<input type='hidden' class='lzy-form-cmd' name='_lizzy-form-cmd' value='{$currForm->next}' />\n";

		if ($currForm->antiSpam) {
            $out .= "\t\t<div class='fld-ch' aria-hidden='true'>\n";
            $out .= "\t\t\t<label for='fld_ch{$this->formsCount}{$this->inx}'>Name:</label><input id='fld_ch{$this->formsCount}{$this->inx}' type='text' class='lzy-form-check' name='_lizzy-form-name' value=''$honeyPotRequired />\n";
            $out .= "\t\t</div>\n";
        }
		return $out;
	} // renderFormHead



    //-------------------------------------------------------------
    private function renderText()
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
    } // renderText



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
    private function renderEMail()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('email');
        $out = $this->getLabel();
        $out .= "<input type='email' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderEMail



    //-------------------------------------------------------------
    private function renderRadio()
    {
        $rec = $this->currRec;
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $groupName = translateToIdentifier($this->currRec->label);
        if ($this->currRec->name) {
            $groupName = $this->currRec->name;
        }
        $checkedElem = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        $label = $this->getLabel(false, false);
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-radio-label'><div class='lzy-legend'><legend>{$label}</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";
        foreach($rec->optionLabels as $i => $optionLabel) {
            if ($i === 0) { continue; } // skip group name
            $preselectedValue = false;
            $name = @$rec->optionNames[$i]? $rec->optionNames[$i]: $optionLabel;
            $id = "lzy-radio_{$groupName}_$i";

            if (strpos($optionLabel, '!') !== false) {
                $optionLabel = str_replace('!', '', $optionLabel);
                if ($checkedElem === false) {
                    $preselectedValue = true;
                }
            }
            $checked = ($checkedElem && $checkedElem[$i+1]) || $preselectedValue ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-radio-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='radio' name='$groupName' value='$name'$checked$cls /><label for='$id'>$optionLabel</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio



    //-------------------------------------------------------------
    private function renderCheckbox()
    {
        $rec = $this->currRec;
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $presetValues = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        $groupName = translateToIdentifier($this->currRec->label) . '_' . $this->inx;
        $label = $this->getLabel(false, false);
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-checkbox-label'><div class='lzy-legend'><legend>$label</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";

        foreach($rec->optionLabels as $i => $optionLabel) {
            if ($i === 0) { continue; } // skip group name
            $preselectedValue = false;
            $name = @$rec->optionNames[$i]? $rec->optionNames[$i]: $optionLabel;
            $id = "lzy-chckb_{$groupName}_$i";
            if (strpos($optionLabel, '!') !== false) {
                $optionLabel = str_replace('!', '', $optionLabel);
                if ($presetValues === false) {
                    $preselectedValue = true;
                }
            }

            $checked = (($presetValues !== false) && @$presetValues[$i+1]) || $preselectedValue ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-checkbox-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='checkbox' name='{$groupName}[]' value='$name'$checked$cls /><label for='$id'>$optionLabel</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderCheckbox



    //-------------------------------------------------------------
    private function renderDropdown()
    {
        $rec = $this->currRec;
        $cls = $rec->class? " class='{$rec->class}'": '';

        $selectedElem = isset($rec->prefill)? $rec->prefill: [];
        $selectedElem = @$selectedElem[0];
        $out = $this->getLabel();
        $out .= "<select id='{$rec->fldPrefix}{$rec->elemId}' name='{$rec->name}'$cls>\n";

        foreach ($rec->optionLabels as $i => $optionLabel) {
            if ($i === 0) { continue; } // skip group name
            $preselectedValue = false;
            $val = @$rec->optionNames[$i]? $rec->optionNames[$i]: $optionLabel;
            $selected = '';
            if ($optionLabel) {
                if (strpos($optionLabel, '!') !== false) {
                    $preselectedValue = true;
                    $optionLabel = str_replace('!', '', $optionLabel);
                }
                $selected = ($val === $selectedElem) || $preselectedValue ? ' selected' : '';
            }
            $out .= "\t\t\t\t<option value='$val'$selected>$optionLabel</option>\n";
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
        $e1 = $this->trans->config->admin_enableFileManager;
        $e2 = !isset($this->trans->page->frontmatter["admin_enableFileManager"]) || $this->trans->page->frontmatter["admin_enableFileManager"];
        $e3 = !isset($this->trans->page->frontmatter["enableFileManager"]) || $this->trans->page->frontmatter["enableFileManager"];
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
    private function renderHidden()
    {
        $name = " name='{$this->currRec->name}'";
        $value = " value='{$this->currRec->value}'";
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';

        $out = "<input type='hidden' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'$cls$name$value />\n";
        return $out;
    } // renderHidden



    //-------------------------------------------------------------
    private function renderReveal()
    {
        $id = "lzy-form-reveal_{$this->currRec->elemInx}";
        $label = $this->getLabel(false, false);
        $target = $this->currRec->target;
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= "\t\t\t\t<input id='$id' class='lzy-reveal-checkbox' type='checkbox' data-reveal-target='$target' /><label for='$id'>$label</label>\n";

        $out = "\t\t\t<div class='lzy-reveal-controller'>$out</div>\n";

        $jq = <<<'EOT'

    $('.lzy-reveal-checkbox').each(function() {
        $(this).attr('aria-expanded', 'false');
        var $target = $( $( this ).attr('data-reveal-target') );
        if ( !$target.parent().hasClass('lzy-reveal-container') ) {
            $target.wrap("<div class='lzy-reveal-container'></div>").show();
            var boundingBox = $target[0].getBoundingClientRect();
            $target.css('margin-top', (boundingBox.height * -1 - 20) + 'px');
        }
    });
    
    $('.lzy-reveal-checkbox').change(function() {
        var $target = $( $( this ).attr('data-reveal-target') );
        var boundingBox = $target[0].getBoundingClientRect();
        $target.css('margin-top', (boundingBox.height * -1 - 20) + 'px');
        var $container = $target.parent();
        if ( $( this ).prop('checked') ) {
            $(this).attr('aria-expanded', 'true');
            $container.addClass('lzy-elem-revealed');
        } else {
            $(this).attr('aria-expanded', 'false');
            $container.removeClass('lzy-elem-revealed');
        }
    });

EOT;
        $this->page->addJq($jq);

        return $out;
    } // renderReveal



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
                $class = " class='".trim($this->currRec->class ." lzy-form-button lzy-form-button-$type"). "'";
				$id = 'btn_'.$this->currForm->formId.'_'.translateToIdentifier($type);
				$label = $labels[$i];
				if (isset($label) && $label) {
				    if ($label[0] === '-') {
                        $label = $this->trans->translateVariable(substr($label,1), true);
                    }
                } else {
                    $label = $type;
                }
				if (stripos($type, 'submit') !== false) {
					$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";
					
				} elseif (stripos($type, 'reset') !== false) {
				    if (($label[0] === '(') || ($type[0] === '(')) { // case: show reset button only if data has been supplied before:
				        if ($this->userSuppliedData) {
				            $label = str_replace(['(', ')'], '', $label);
                            $out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
                        }
                    } else {
                        $out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
                    }
					
				} else { // $type === cancel
					$out .= "$indent<input type='button' id='$id' value='$label' $class />\n";
				}
			}
		}
        return $out;
    } //renderButtons




    //-------------------------------------------------------------
	private function renderFormTail()
    {
        $formId = $this->currForm->formId;
        if ($this->currForm->antiSpam) {
            $this->initAntiSpam();
        }
        $out = "\t</form>\n";

        // save form data to DB:
        $this->saveFormDescr();

        // append possible text from user-data evaluation:
        $msgToClient = '';
        $msgToClientClass = '';
        if (stripos($this->currForm->options, 'nocolor') === false) {
            $msgToClientClass = 'lzy-form-colored ';
        }

        // check _announcement_ and responseToClient, inject msg if present:
        if (@$this->errorDescr[$formId]['_announcement_']) { // is errMsg, takes precedence over responseToClient
            $msgToClient = $this->errorDescr[$formId]['_announcement_'];
            $this->errorDescr[$formId]['_announcement_'] = false;
            $msgToClientClass .= 'lzy-form-announcement';

        } elseif (@$this->responseToClient) {
            $msgToClient = $this->responseToClient;
            $msgToClientClass .= 'lzy-form-response';
        }
        if ($msgToClient) {
            // append 'continue...' if form was omitted:
            if ($this->skipRenderingForm) {
                $next = @$this->currForm->next ? $this->currForm->next : './';
                $msgToClient .= "<div class='lzy-form-continue'><a href='{$next}'>{{ lzy-form-continue }}</a></div>\n";
            }
            $out .= "\t<div class='$msgToClientClass'>$msgToClient</div>\n";
        }

        // refresh export if necessary:
        if ($this->currForm->export) {
            $this->export();
        }

        // present previously received data to form owner:
        if ($this->currForm->showData) {
            $out .= $this->renderData();
        }
        return $out;
	} // renderFormTail




    //-------------------------------------------------------------
    private function getLabel($id = false, $wrapOutput = true)
    {
		$id = ($id) ? $id : "{$this->currRec->fldPrefix}{$this->currRec->elemId}";
        $requiredMarker =  $this->currRec->requiredMarker;
        if ($requiredMarker) {
            $this->currForm->hasRequiredFields = true;
        }
        $label = $this->currRec->label;
        $hasColon = (strpos($label, ':') !== false);
        $label = trim(str_replace([':', '*'], '', $label));
        if ($this->currRec->translateLabel) {
            $label = $this->trans->translateVariable($label, true);
        }
        if ($hasColon) {
            $label .= ':';
        }
        if ($requiredMarker) {
            $label .= ' '.$requiredMarker;
        }

        $infoIcon = $infoText = $infoIconText = '';
        if ($this->currRec->info) {
            $elemInx = $this->currRec->elemInx;
            $icon = '<span class="lzy-icon-info"></span>';
            $infoIcon = <<<EOT
        <a href="#" class="lzy-formelem-show-info" aria-label="{{ lzy-formelem-info-title }}" data-tooltip-content="#lzy-formelem-info-text-$elemInx">{$icon}</a> 

EOT;
            $infoIconText = <<<EOT
    <span  class="sr-only">
		<span id="lzy-formelem-info-text-$elemInx" class="lzy-formelem-info-text lzy-formelem-info-text-$elemInx">{$this->currRec->info}</span>
	</span>

EOT;

        }

        if ($wrapOutput) {
            return "\t\t\t<label for='$id'>$label$infoIcon$infoIconText</label>";
        } else {
            return "$label$infoIcon$infoIconText";
        }
    } // getLabel




    //-------------------------------------------------------------
    private function classAttr($class = '')
    {
        $out = " class='".trim($class). "'";
        return trim($out);
    } // classAttr
    


    //-------------------------------------------------------------
	private function saveFormDescr()
	{
	    $form = $this->currForm;
	    $form->bypassedValues = @$this->bypassedValues;
        $str = base64_encode( serialize( $form) );
        $this->tck->updateTicket( $this->currForm->ticketHash, ['form' => $str] );
	} // saveFormDescr


    //-------------------------------------------------------------
    public function restoreFormDescr($ticketHash)
	{
        $rec = $this->tck->consumeTicket($ticketHash);
        if (isset($rec['form'])) {
            return unserialize(base64_decode($rec['form']));
        } else {
            return null;
        }
	} // restoreFormDescr



    //-------------------------------------------------------------
	private function cacheUserSuppliedData($formId, $userSuppliedData)
	{
		$_SESSION['lizzy']['formData'][$formId] = serialize($userSuppliedData);
	} // cacheUserSuppliedData



    //-------------------------------------------------------------
	private function getUserSuppliedDataFromCache($formId)
	{
		return (isset($_SESSION['lizzy']['formData'][$formId])) ? unserialize($_SESSION['lizzy']['formData'][$formId]) : null;
	} // getUserSuppliedDataFromCache




    //-------------------------------------------------------------
    public function evaluateUserSuppliedData()
    {
        $msgToClient = '';

        // returns false on success, error msg otherwise:
        $this->userSuppliedData = $_POST;
        $this->userSuppliedData0 = $_POST;
        $userSuppliedData = &$this->userSuppliedData;
		if (!isset($userSuppliedData['_lizzy-form-id'])) {
			$this->clearCache();
			return false;
		}
		if (intval($userSuppliedData['_lizzy-form-id']) !== $this->formInx) {
		    return false;
        }

        $ticketHash = $this->ticketHash = $userSuppliedData['_lizzy-form'];
        $this->currForm = $this->restoreFormDescr( $ticketHash );
        $currForm = $this->currForm;
        if ($currForm === null) {   // ticket timed out:
            $this->clearCache();
            return false;
        }

        // anti-spam check:
        if ($this->checkHoneyPot()) {
            $this->clearCache();
            return false;
        }

        $this->formId = $formId = $currForm->formId;

        $cmd = @$userSuppliedData['_lizzy-form-cmd'];
        if ($cmd === '_ignore_') {     // _ignore_
            $this->cacheUserSuppliedData($formId, $userSuppliedData);
            return;

        } elseif ($cmd === '_reset_') { // _reset_
            $this->clearCache();
            reloadAgent();

        } elseif ($cmd === '_log_') { // _log_
            $out = @$userSuppliedData['_lizzy-form-log'];
            writeLog($out, SPAM_LOG_FILE);
            exit;
        }

        $this->prepareUserSuppliedData(); // handles radio and checkboxes

        // check required entries:
        $this->checkSuppliedDataEntries();
        if ( @$this->errorDescr[$formId] ) {
            $this->cacheUserSuppliedData($formId, $userSuppliedData);
            return false;
        }

        // check whether form data timed out:
        if ($currForm->formTimeout) {
            $dataTime = $currForm->creationTime;
            if ($dataTime < (time() - $currForm->formTimeout)) {
                $this->page->addPopup( '{{ lzy-form-expired }} [form timed out]' );
                return false;
            }
        }

        // save user supplied data to SESSION:
        $this->cacheUserSuppliedData($formId, $userSuppliedData);

        $errDescr = @$this->errorDescr[ $this->formId ];
        if ($errDescr) {
            $_POST = [];
            return true;
        }

        $msgToOwner = $this->assembleResponses();


        $customResponseEvaluation = @$currForm->customResponseEvaluation;
        if ($customResponseEvaluation) {
			$result = $this->trans->doUserCode('-'.$customResponseEvaluation, null, true);
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
                    fatalError("Warning: trying to execute evalForm(), but not defined in '$customResponseEvaluation'.", 'File: '.__FILE__.' Line: '.__LINE__);
                }
				$res = evalForm( $this);
                if (is_array($res)) {
                    $msgToClient = $res[0];
                    if (is_array($res[1])) {
                        $msgToOwner = $res[1];
                    } else {
                        $msgToOwner['body'] = $res[1];
                    }
                } else {
                    $msgToClient = $res;
                }
			} else {
                fatalError("Warning: executing code '$customResponseEvaluation' has been blocked; modify 'config/config.yaml' to fix this.", 'File: '.__FILE__.' Line: '.__LINE__);
			}
        }

        $noError = !@$this->errorDescr[ $this->formId ];
        if ($noError ) {
            $cont = $this->saveAndWrapUp($msgToOwner);
        }

        if ($cont && $this->currForm->confirmationEmail) {
            $msgToClient .= $this->sendConfirmationMail( $userSuppliedData );
        }

        if ($noError ) {
            if (!$msgToClient) {
                $msgToClient = $this->currForm->confirmationText;
            }
            $this->responseToClient = $msgToClient . $this->responseToClient;
            $this->skipRenderingForm = true;
        }
    } // evaluateUserSuppliedData



    //-------------------------------------------------------------
	private function saveAndWrapUp($msgToOwner)
	{
        if (!$this->saveUserSuppliedDataToDB()) {
            return false;
        }
        if ($this->currForm->export) {
            $this->export();
        }
        $this->clearCache();

        if ($msgToOwner && $this->currForm->mailTo) {
            $this->lzy->sendMail($this->currForm->mailTo, $msgToOwner['subject'], $msgToOwner['body'], $this->currForm->mailFrom);
        }
        return true;
    } // saveAndWrapUp


    //-------------------------------------------------------------
	private function assembleResponses()
	{
	    // returns: [$msgToClient, $msgToOwner]
        $currForm = $this->currForm;
        $msgToOwner = "{$currForm->formName}\n===================\n\n";

		foreach ($currForm->formElements as $element) {
		    if (($element->type === 'reveal') || ($element->type === 'button')) {
                continue;
            }
            $label = $element->labelInOutput;
            if ($element->translateLabel) {
                $label = $this->trans->translateVariable( $label, true );
            }
            $label = html_entity_decode($label);

            $name = $element->name;
            $value = $this->userSuppliedData[ $name ];
            if (is_array($value)) {
                $value = $value[0];
            }
            if (@$currForm->replaceQuotes) {
                $value = str_replace(['"', "'"], ['ʺ', 'ʹ'], $value);
            }
            $msgToOwner .= mb_str_pad($label, 22, '.') . ": $value\n\n";
        }
        $msgToOwner = trim($msgToOwner, "\n\n");

        // prepare default mail to owner if arg 'mailTo' is defined:
        if ($currForm->mailTo) {
            $subject = $this->trans->translateVariable('lzy-form-email-notification-subject', true);
            if (!$subject) {
                $subject = 'user data received';
            }
            $subject = "[$currForm->formName] ".$subject;

            $msgToOwner = ['subject' => $subject, 'body' => $msgToOwner];
        }
        return $msgToOwner;
	} // assembleResponses




    //-------------------------------------------------------------
    private function checkHoneyPot()
    {
        if (!$this->currForm->antiSpam) {
            return false;
        }
        // check honey pot field (unless on local host):
        if ($this->userSuppliedData["_lizzy-form-name"] !== '') {
            $out = var_export($this->userSuppliedData, true);
            $out = str_replace("\n", ' ', $out);
            $out .= "\n[{$_SERVER['REMOTE_ADDR']}] {$_SERVER['HTTP_USER_AGENT']}\n";
            $logState = $GLOBALS["globalParams"]["errorLoggingEnabled"];
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = true;
            writeLog($out, 'spam-log.txt');
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = $logState;
            return true;
        }
        return false;
    } // checkHoneyPot



    //-------------------------------------------------------------
	private function saveUserSuppliedDataToDB()
	{
        $currForm = $this->currForm;
        $recKey = $currForm->ticketHash;
        $ds = new DataStorage2( $currForm->file );

        if ($currForm->avoidDuplicates) {
            $data = $ds->read();
            foreach ($data as $hash => $rec) {
                if ($hash === '_meta_') { continue; }
                $identical = true;
                foreach ($rec as $key => $value) {
                    $v1 = $this->userSuppliedData[ $key ];
                    $v2 = $rec[ $key ];
                    if ($v1 !== $v2) {
                        $identical = false;
                        break;
                    }
                }
                if ($identical) {
                    $this->clearCache();
                    $this->errorDescr[$this->formId]['_announcement_'] = '{{ lzy-form-duplicate-data }}';
                    $this->skipRenderingForm = true;
                    return false;
                }
            }
        }

        // prepend meta data if DB is empty:
        $n = $ds->getNoOfRecords();
        if ($n === 0) {
            $meta = base64_encode( serialize( $currForm ) );
            $ds->writeRecord('_meta_', $meta);
        }

        // add new record:
        $ds->writeRecord($recKey, $this->userSuppliedData);
        return true;
	} // saveUserSuppliedDataToDB



    //-------------------------------------------------------------
    private function prepareUserSuppliedData()
    {
        // add missing elements, remove meta-elements, convert radio&checkboxes to internal format:
        $currForm = $this->currForm;
        $userSuppliedData = &$this->userSuppliedData;
        $elemDefs = $currForm->formElements;

        // skip system fields starting with _:
        foreach ($userSuppliedData as $key => $value) {
            if (!$key || ($key[0] === '_')) {
                unset($userSuppliedData[ $key ]);
            }
        }

        foreach ($elemDefs as $key => $elemDef) {
            if (!$elemDef) {
                continue;
            }
            $key = $elemDef->name;
            $type = $elemDef->type;
            if ($type === 'reveal') { continue; }
            if (!isset($userSuppliedData[ $key ])) {
                if ($type === 'bypassed') {
                    $userSuppliedData[$key] = $elemDef->value;
                } else {
                    $userSuppliedData[$key] = '';
                }
            }

            if (($type === 'checkbox') || ($type === 'radio') || ($type === 'dropdown')) {
                $value = $userSuppliedData[$key];
                $userSuppliedData[$key] = [];
                if (is_array($value)) {
                    $userSuppliedData[$key][0] = implode(',', $value);
                } else {
                    $userSuppliedData[$key][0] = $value;
                }
                for ($i=1; $i<sizeof($elemDef->optionNames); $i++) {
                    $option = $elemDef->optionNames[$i];
                    if (!$option) { continue; }
                    if (is_array($value)) {
                        $userSuppliedData[$key][] = (bool) in_array($option, $value);
                    } else {
                        $userSuppliedData[$key][] = ($option === $value);
                    }
                }

            } elseif ($type === 'button') {
                unset($userSuppliedData[ $key ]);
            }
        }
    } // prepareUserSuppliedData



    //-------------------------------------------------------------
	private function export()
	{
        $currForm = $this->currForm;

        // check whether export is required:
        $t1 = @filemtime($currForm->file);
        $t2 = @filemtime($outFile = $currForm->export);
        if (file_exists($currForm->export) && ($t1 !== false) && ($t1 < $t2)) {
            return;
        }

        $ds = new DataStorage2( $currForm->file );
        $srcData = $ds->read();
        unset($srcData['_meta_']);

        // export header row:
        $data = $this->exportHeaderRow();

        $r = 1;
        foreach ($srcData as $row) {
            $c = 0;
            foreach ($currForm->formElements as $fldDescr) {
                if (!$fldDescr) { continue; }

                $fldName = @$fldDescr->name;
                $fldType = @$fldDescr->type;
                if (!$fldType || ($fldType === 'reveal') || ($fldType === 'button')) {
                    continue;
                } elseif (($fldType === 'checkbox') || ($fldType === 'radio') || ($fldType === 'dropdown')) {
                    if ($fldDescr->splitOutput) {
                        for ($i=1; $i<(sizeof($row[$fldName]) - 1); $i++) {
                            $value = $row[$fldName][$i];
                            $data[$r][$c++] = $value? '1': ' ';
                        }
                        $value = $row[$fldName][$i]? '1': ' ';
                    } else {
                        $value = $row[$fldName][0];
                    }

                } else {
                    $value = @$row[$fldName];
                }
                $data[$r][$c++] = $value;
            }
            $r++;
        }
        $outFile = $currForm->export;
        $ds2 = new DataStorage2($outFile);
        $ds2->write( $data );
    } // export



    //-------------------------------------------------------------
    private function exportHeaderRow()
    {
        $row = [];
        $c = 0;
        foreach ($this->currForm->formElements as $fldDescr) {
            if (!$fldDescr) { continue; }
            $fldType = @$fldDescr->type;
            if (!$fldType || ($fldType === 'reveal') || ($fldType === 'button')) {
                continue;
            } elseif (($fldType === 'checkbox') || ($fldType === 'radio') || ($fldType === 'dropdown')) {
                if ($fldDescr->splitOutput) {
                    $hdrs = $fldDescr->optionNames;
                    for ($i = 1; $i < (sizeof($hdrs) - 1); $i++) {
                        if ($hdrs[$i]) {
                            $row[$c++] = $hdrs[$i];
                        }
                    }
                    $value = $hdrs[$i];
                } else {
                    $value = $fldDescr->optionNames[0];
                }

            } else {
                $value = $fldDescr->labelInOutput;
            }
            if ($fldDescr->translateLabel) {
                $value = $this->trans->translateVariable( $value, true );
            }
            $row[$c++] = $value;
        }
        return [ $row ];
    } // exportHeaderRow





    //-------------------------------------------------------------
    private function restoreErrorDescr()
    {
        return isset($_SESSION['lizzy']['formErrDescr']) ? $_SESSION['lizzy']['formErrDescr'] : [];
    } // restoreErrorDescr




    //-------------------------------------------------------------
    private function saveErrorDescr($errDescr)
    {
        $_SESSION['lizzy']['formErrDescr'] = $errDescr;
    } // saveErrorDescr



    //-------------------------------------------------------------
	public function clearCache()
	{
	    $formId = @$this->currForm->formId;
	    if ($formId) {
            unset($_SESSION['lizzy']['formData'][$formId]);
            unset($_SESSION['lizzy']['formErrDescr'][$formId]);
            unset($_SESSION["lizzy"]['forms'][$formId]);
        } else {
            unset($_SESSION['lizzy']['formData']);
            unset($_SESSION['lizzy']['formErrDescr']);
            unset($_SESSION["lizzy"]['forms']);
        }
        $this->errorDescr = null;
	    @$this->tck->deleteTicket( @$this->ticketHash );
	} // clearCache




    private function initAntiSpam()
    {
        $id = "fld_ch{$this->inx}";
        if ($this->currForm->antiSpam !== true) {   // field for antiSpam explicitly defined:
            $nameFldId = str_replace('#fld_', '', $this->currForm->antiSpam);
            $found = false;
            foreach ($this->currForm->formElements as $rec) {
                if ($rec->elemId === $nameFldId) {
                    $nameFldId = '#fld_' . $nameFldId;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                die("AntiSpam: field '{$this->currForm->antiSpam}' not found for using in antiSpam.");
            }
        } else {        // no field for antiSpam check given, find first text field:
            $nameFldId = false;
            foreach ($this->currForm->formElements as $rec) {
                if ($rec->type === 'text') {
                    $nameFldId = '#fld_' . $rec->elemId;
                    break;
                }
            }
            if (!$nameFldId) {
                die("AntiSpam: no text field found that could be used for antiSpam-mechanism.");
            }
        }

        $html = <<<EOT
        <div id="lzy-ch-wrapper-{$this->formInx}" class="lzy-ch-wrapper">
            {{ lzy-form-override-honeypot }}
            <div class="lzy-ch-input-wrapper">
                <label for="$id">{{ lzy-form-override-honeypot-label }}</label>
                <input id="$id" type="text" name="lzy-ch-name" />
            </div>
        </div>

EOT;
        $html = str_replace(["\n", '  '], ' ', $html);

        // submit: check honey pot
        $js = <<<EOT

function lzyChContinue( i, btn, callbackArg ) {
    lzyFormUnsaved = false;
    var val = $( '#$id' ).val();
    var origFld = $( '$nameFldId' ).val();
    if (!origFld) {
        origFld = '';
    }
    if (val === origFld.toUpperCase()) {
        var \$form = $( '#' + callbackArg );
        $( '.lzy-form-check', \$form ).val('');
        \$form[0].submit();
    } else {
        lzyPopupClose();
        setTimeout(function() {
            initAntiSpamPopup();
        }, 800);
    }
}

function lzyChAbort() {
    return false;
}

function initAntiSpamPopup() {
    lzyPopup({
        text: '$html',
        trigger: true,
        class: 'lzy-popup-leftaligned',
        buttons: '{{ Continue }},{{ Cancel }}',
        callbacks: 'lzyChContinue,lzyChAbort',
        callbackArg: '{$this->currForm->formId}',
        buttonClass: 'lzy-button lzy-button-submit, lzy-button ',
        closeButton: false,
    });
    $( '#$id' ).focus();
}

EOT;
        $this->page->addJs($js);
    } // initAntiSpam




    private function initButtonHandlers()
    {
        $id = "fld_ch{$this->inx}";
        $js = <<<EOT

function noInputPopup() {
    lzyPopup({
        text: '{{ lzy-form-empty-form-warning }}',
        trigger: true,
        buttons: '{{ Continue }}',
        closeButton: false,
    });
    $( '#$id' ).focus();
}

EOT;
        $this->page->addJs($js);

        $formId = '#'.$this->currForm->formId;

        $logFile = SPAM_LOG_FILE;
        $jq = <<<EOT

$('$formId input[type=submit]').click(function(e) {
    var \$form = $('$formId');
    if (!$('.lzy-form-check', \$form ).val()) {
        var s = '';
        $( 'input,textarea', \$form).each(function() {
            var type = $(this).attr('type');
            if ((type === 'hidden') || (type === 'submit') || (type === 'reset') || (type === 'button')) {
                return;
            } else if ((type === 'radio') || (type === 'checkbox')) {
                if ($(this).prop('checked') || $(this).prop('selected')) {
                    s = 'X';
                }
            } else {
                s += $(this).val();
            }
        });
        $( 'option', \$form).each(function() {
            if ( $(this).prop('selected') && $(this).val() ) {
                s += 'Y';
            }
        });
        if (!s) {
            e.preventDefault();
            noInputPopup();
            return;
        }

        lzyFormUnsaved = false;
        \$form[0].submit();
        return;
    }
    e.preventDefault();
    var data = JSON.stringify( \$form.serializeArray() );
    serverLog('suspicious form data: ' + data, '$logFile');
    initAntiSpamPopup();
});

EOT;

        $jq .= <<<EOT

$('$formId input[type=reset]').click(function(e) {  // reset: clear all entries
    var \$form = $('$formId');
    $('.lzy-form-cmd', \$form ).val('_reset_');
    lzyFormUnsaved = false;
    \$form[0].submit();
});

$('$formId input[type=button]').click(function(e) { // cancel: reload page (or goto 'next' if provided
    var \$form = $('$formId');
    var next = $('.lzy-form-cmd', \$form ).val();
    window.location.href = next;
});

$('$formId .lzy-form-pw-toggle').click(function(e) {
    e.preventDefault();
    var \$form = $('$formId');
    var \$pw = $('.lzy-form-password', \$form);
    if (\$pw.attr('type') === 'text') {
        \$pw.attr('type', 'password');
        $('.lzy-form-login-form-icon', \$form).attr('src', systemPath+'rsc/show.png');
    } else {
        \$pw.attr('type', 'text');
        $('.lzy-form-login-form-icon', \$form).attr('src', systemPath+'rsc/hide.png');
    }
});

EOT;
        $this->page->addJq($jq);
    } // initButtonHandlers




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



    private function getValueAttr($type = false)
    {
        $value = $this->currRec->value;
        if (($type !== 'radio') && ($type !== 'checkbox') && ($type !== 'dropdown')) {
            if (@$this->currRec->prefill) {
                $value = $this->currRec->prefill;
            }
        }

        if ($type === 'tel') {
            if (preg_match('/\D/', $value)) {
                $value = preg_replace('/[^\d.\-+()]/', '', $value);
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
        $currForm = $this->currForm;
        $continue = true;

        if ($currForm->showData !== true) {
            // showData options: false, true, loggedIn, privileged, localhost, {group}
            switch ($currForm->showData) {
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
                    $continue = $this->lzy->auth->checkGroupMembership($currForm->showData);
            }
        }

        if (!$continue) {
            return '';
        }

        $fileName = $currForm->export;

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
        if ($currForm->showDataMinRows) {
            $nCols = @sizeof($data[0]);
            $emptyRow = array_fill(0, $nCols, '&nbsp;');
            $max = intval($currForm->showDataMinRows) + 1;
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




    private function checkSuppliedDataEntries()
    {
        $currForm = $this->currForm;
        $userSuppliedData = $this->userSuppliedData;
        $formEmpty = true;

        foreach ($currForm->formElements as $rec) {
            if (!$rec) {
                continue;
            }
            $name = $rec->name;
            $type = $rec->type;
            $value = isset($userSuppliedData[$name])? $userSuppliedData[$name]: '';

            if ($rec->requiredMarker) {
                if ($g = isset($rec->requiredGroup) ? $rec->requiredGroup : []) {
                    $found = false;
                    foreach ($g as $n) {
                        foreach ($userSuppliedData as $k => $v) {
                            $k = preg_replace('/_\d+$/', '', $k);
                            if (($k === $n) && $v) {
                                $found = true;
                                break 2;
                            }
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
                $formEmpty = false;
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

        if ($formEmpty) {
            $this->errorDescr[$this->formId]['_announcement_'] = '{{ lzy-form-empty-rec-received }}';
        }

        $log = str_replace(["\n", '  '], ' ', var_export($this->userSuppliedData0, true));
        if (@$this->errorDescr[$this->formId]) {
            $log .= "\nError Msg: ".str_replace(["\n", '  '], ' ', var_export($this->errorDescr[$this->formId], true));
        }
        writeLog("New form data [{$currForm->formName}]:\n$log\n");
    } // checkSuppliedDataEntries



    private function sendConfirmationMail( $rec )
    {
        $isHtml = false;
        $emailFieldName = $this->currForm->confirmationEmail;
        $to = false;
        foreach ($rec as $key => $value) {
            $key1 = preg_replace('/_\d+$/', '', $key);
            if ($emailFieldName === $key1) {
                $to = $value;
            }
            if (is_array($value)) {
                $value = $value[0];
            }
            $this->trans->addVariable("{$key1}_value", $value);
        }
        if ($this->currForm->confirmationEmailTemplate === true) {
            $subject = '{{ lzy-confirmation-response-subject }}';
            $message = '{{ lzy-confirmation-response-message }}';

        } else {
            $file = resolvePath($this->currForm->confirmationEmailTemplate, true);
            if (!file_exists($file)) {
                $this->responseToClient = 'lzy-reservation-email-template-not-found';
                return '';
            }

            $ext = fileExt($file);
            $tmpl = file_get_contents($file);

            if (stripos($ext, 'md') !== false) {
                $page = new Page();
                $tmpl = $page->extractFrontmatter($tmpl);
                $css = $page->get('css');
                $fm = $page->get('frontmatter');
                $subject = isset($fm['subject']) ? $fm['subject'] : '';
                if ($css) {
                    $css = <<<EOT
	<style>
$css
	</style>

EOT;
                }
                $message = compileMarkdownStr($tmpl);
                $message = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
$css
</head>
<body>
$message
</body>
</html>

EOT;
                $message = translateUmlauteToHtml( ltrim($message) );
                $isHtml = true;

            } elseif ((stripos($ext, 'htm') !== false)) {
                $page = new Page();
                $tmpl = $this->lzy->page->extractHtmlBody($tmpl);
                $css = $page->get('css');
                $fm = $page->get('frontmatter');
                $subject = isset($fm['subject']) ? $fm['subject'] : '';
                $message = extractHtmlBody($tmpl);
                $message = translateUmlauteToHtml( ltrim($message) );
                $isHtml = true;

            } else {    // .txt
                if (preg_match('/^subject: (.*?)\n(.*)/ims', $tmpl, $m)) {
                    $subject = $m[1];
                    $message = $m[2];
                } else {
                    $this->responseToClient = 'lzy-reservation-email-template-syntax-error';
                    return '';
                }
            }
        }

        $subject = $this->trans->translate($subject);
        $message = $this->trans->translate($message);
        if ($to) {
            $mailfrom = @$this->mailfrom? $this->mailfrom: $this->trans->getVariable('webmaster_email');
            $this->lzy->sendMail($to, $subject, $message, $mailfrom, $isHtml);
            $this->responseToClient = '{{ lzy-reservation-email-sent }}';
        }
    } // sendConfirmationMail




    private function elementInx($name)
    {
        return preg_replace('/^.*_(\d+)$/', "$1", $name);
    } // elementInx
} // Forms


class FormDescriptor
{
    public $formId = '';
    public $formName = '';
    public $formElements = [];    // fields contained in this form

    public $mailto = '';        // data entered by users will be sent to this address
    public $process = '';
    public $method = '';
    public $action = '';
    public $class = '';
    public $options = '';
    public $ticketHash = '';
    public $preventMultipleSubmit = false;
    public $validate = false;
    public $antiSpam = true;
    public $showData = true;
    public $replaceQuotes = true;
}


class FormElement
{
    public $type = '';        // init, radio, checkbox, date, month, number, range, text, email, password, textarea, button
    public $label = '';        // some meaningful label used for the form element
    public $labelInOutput = '';// some meaningful short-form of label used in e-mail and .cvs data-file
    public $name = '';
    public $required = '';    // enforces user input
    public $placeholder = '';// text displayed in empty field, disappears when user enters input field
    public $min = '';
    public $max = '';        // for numerical entries -> defines lower and upper boundry
    public $value = '';        // defines a preset value
    public $class = '';        // class identifier that is added to the surrounding div
    public $inpAttr = '';
} // class FormElement

