<?php
/*
 *	Lizzy - forms rendering module
*/

define('UPLOAD_SERVER',         '~sys/_upload_server.php');
define('THUMBNAIL_PATH', 	    '_/thumbnails/');
define('FORM_LOG_FILE', 	    LOG_PATH.'form-log.txt');
define('SPAM_LOG_FILE', 	    LOG_PATH.'spam-log.txt');
define ('FORMS_DEFAULT_TICKET_VALIDITY_TIME', 259200); // 3 days
define ('DEFAULT_FORMS_TIMEOUT', 900); // 15 minutes

define('HEAD_ATTRIBUTES', 	    ',label,id,translateLabels,class,method,action,mailto,mailfrom,mailTo,mailFrom,keyType,export,'.
    'legend,customResponseEvaluation,customResponseEvaluationFunction,next,file,confirmationText,formDataCaching,'.
    'encapsulate,formTimeout,avoidDuplicates,confirmationEmail,dynamicFormSupport,labelColons,'.
    'confirmationEmailTemplate,prefill,preventMultipleSubmit,replaceQuotes,antiSpam,'.
    'validate,novalidate,showData,showDataMinRows,options,encapsulate,disableCaching,labelWidth,'.
    'translateLabel,labelPosition,formName,formHeader,formHint,formFooter,showSource,splitChoiceElemsInDb,'.
    'responseViaSideChannels,reportErrorByPopup,useRecycleBin,');

define('ELEM_ATTRIBUTES', 	    ',label,type,id,class,wrapperClass,name,required,value,'.
	'option,options,optionLabels,layout,info,comment,translateLabel,'.
	'labelInOutput,splitOutput,placeholder,autocomplete,'.
	'description,pattern,min,max,path,target,'.
    'defaultValue,derivedValue,liveValue,postProcess,');

define('UNARY_ELEM_ATTRIBUTES', ',required,translateLabel,splitOutput,autocomplete,');

define('SUPPORTED_TYPES', 	    ',text,readonly,password,email,textarea,radio,checkbox,'.
    'dropdown,button,url,date,time,datetime,month,number,range,tel,toggle,file,hash,'.
    'fieldset,fieldset-end,reveal,hidden,literal,bypassed,');

 // types to ignore in output:
define('PSEUDO_TYPES', ',form-head,form-tail,reveal,literal,fieldset,fieldset-end,');


mb_internal_encoding("utf-8");


$GLOBALS['lizzy']['lzyFormsCount'] = 0;
$GLOBALS['lizzy']['formDataCachingInitialized'] = false;
$GLOBALS['lizzy']['formTooltipsInitialized'] = false;
$GLOBALS['lizzy']['forms_skipRenderingForm'] = [];
$GLOBALS['lizzy']['forms_responseToClient'] = [];

class Forms
{
	private   $page;
    protected $inx;
	private   $currForm;		// shortcut to $formDescr[ $currFormIndex ]
	private   $currRec;		    // shortcut to $currForm->formElements[ $currRecIndex ]
    private   $submitButtonRendered = false;
    protected $errorDescr = [];
    protected $responseToClient;
    protected $args;
    protected $skipRenderingForm;
    protected $formId = false;
    public    $file = false;
    private   $db = false;
    private   $recKey;
    private   $isNewRec;
    private   $bypassedValues;
    private   $infoInitialized, $mailfrom, $dataKeyOverride;
    private   $inhibitSavingUserData;
    protected $userSuppliedData, $userSuppliedData0, $repeatEventTaskPending, $dataKeyOverrideHash;


	public function __construct( $lzy )
	{
	    $this->lzy = $lzy;
		$this->trans = $lzy->trans;
		$this->page = $lzy->page;
		$this->inx = -1;    // = elemInx
        $GLOBALS['lizzy']['lzyFormsCount']++;
		$this->formInx = $GLOBALS['lizzy']['lzyFormsCount'];
        $this->currForm = new FormDescriptor; // object as will be saved in DB
        $this->infoInitialized = &$GLOBALS['lizzy']['formTooltipsInitialized'];

        $GLOBALS['lizzy']['forms_responseToClient'][$this->formInx] = false;
        $this->responseToClient = &$GLOBALS['lizzy']['forms_responseToClient'][$this->formInx];
        $GLOBALS['lizzy']['forms_skipRenderingForm'][$this->formInx] = false;
        $this->skipRenderingForm = &$GLOBALS['lizzy']['forms_skipRenderingForm'][$this->formInx];

        $this->page->addModules('POPUPS');

        if (isset($_POST['_lzy-form-ref'])) {
            $this->evaluateUserSuppliedData();    // we received data for curr form
        }
        $this->formHeadInfoVariableName = "lzy-form-head-placeholder-{$this->formInx}";
	} // __construct

    

    public function render( $args )
    {
        $type = @$args['type'];
        if (($type === 'form-head') || ($type === 'form-tail')) {
            return $this->_render($args);

        } else {
            // check for implicit syntax:
            $keys = array_keys($args);
            $out = '';
            $buttons = [ 'label' => '', 'type' => 'button', 'value' => '' ];
            foreach ($keys as $key) {
                if (is_int($key)) {
                    unset($args[ $key ]);
                    continue;
                }
                if (!$this->isElementAttribute($key)) {
                    if ($key === 'submit') {
                        $buttons["label"] .= isset($args[$key]['label']) ? $args[$key]['label'].',': 'Submit,';
                        $buttons["value"] .= 'submit,';
                        continue;

                    } elseif (($key === 'reset') || ($key === 'cancel')) {
                        $buttons["label"] .= isset($args[$key]['label']) ? $args[$key]['label'].',': 'Cancel,';
                        $buttons["value"] .= 'cancel,';
                        continue;

                    } else {
                        $args1 = $args[$key];
                        $args1['label'] = $key;
                    }
                    $out .= $this->_render($args1);

                } else {
                    $out .= $this->_render($args);
                    return $out;
                }
            }
            if ($buttons['value'] !== '') {
                $buttons['label'] = rtrim($buttons['label'], ',');
                $buttons['value'] = rtrim($buttons['value'], ',');
                $out .= $this->_render($buttons);
            }
        }
        return $out;
    } // render



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

        $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form' . $this->formInx;
        $formId = (isset($args['id'])) ? $args['id'] : false;
        if (!$formId) {
            $formId = translateToClassName($label);
        }
        $this->formId = $formId;

        if (!$this->currForm) {
            $this->currForm = new FormDescriptor();
        }
        $currForm = &$this->currForm;
        $currForm->formId = $formId;
        $currForm->formInx = $this->formInx;

        $currForm->dynamicFormSupport = (isset($args['dynamicFormSupport'])) ? $args['dynamicFormSupport'] : false;
        $currForm->lockRecWhileFormOpen = (isset($args['lockRecWhileFormOpen'])) ? $args['lockRecWhileFormOpen'] : false;

        // in rare cases (e.g. config/users.yaml) we need choice elements to be stored in plain form, not as an array:
        $currForm->splitChoiceElemsInDb = (isset($args['splitChoiceElemsInDb'])) ? $args['splitChoiceElemsInDb'] : false;

        $currForm->formName = $label;
        $currForm->translateLabels = (isset($args['translateLabels'])) ? $args['translateLabels'] : false;
        $currForm->labelPosition = (isset($args['labelPosition'])) ? $args['labelPosition'] : 'auto';

        $currForm->class = (isset($args['class'])) ? $args['class'] : 'lzy-form';
        $currForm->wrapperClass = (isset($args['wrapperClass'])) ? $args['wrapperClass'] : '';
        $currForm->method = (isset($args['method'])) ? $args['method'] : 'post';
        $currForm->action = (isset($args['action'])) ? $args['action'] : '';
        $currForm->keyType = (isset($args['keyType'])) ? $args['keyType'] : false;
        $currForm->mailTo = (isset($args['mailto'])) ? $args['mailto'] : ((isset($args['mailTo'])) ? $args['mailTo'] : '');
        $currForm->mailFrom = (isset($args['mailfrom'])) ? $args['mailfrom'] : ((isset($args['mailFrom'])) ? $args['mailFrom'] : '');
        $currForm->formHeader = (isset($args['formHeader'])) ? $args['formHeader'] : ''; // synonyme for 'legend'
        $currForm->formHeader = (isset($args['legend'])) ? $args['legend'] : $currForm->formHeader;
        $currForm->formHint = (isset($args['formHint'])) ? $args['formHint'] : '';
        $currForm->formFooter = (isset($args['formFooter'])) ? $args['formFooter'] : '';
        $currForm->responseViaSideChannels = (isset($args['responseViaSideChannels'])) ? $args['responseViaSideChannels'] : false;

        $currForm->customResponseEvaluation = (isset($args['customResponseEvaluation'])) ? $args['customResponseEvaluation'] : '';
        $currForm->customResponseEvaluationFunction = (isset($args['customResponseEvaluationFunction'])) ? $args['customResponseEvaluationFunction'] : '';
        $currForm->next = (isset($args['next'])) ? $args['next'] : './';
        $currForm->file = (isset($args['file'])) ? $args['file'] : '';
        $currForm->useRecycleBin = (isset($args['useRecycleBin'])) ? $args['useRecycleBin'] : false;
        $currForm->confirmationText = (isset($args['confirmationText'])) ? $args['confirmationText'] : '{{ lzy-form-data-received-ok }}';
        $currForm->formDataCaching = (isset($args['formDataCaching'])) ? $args['formDataCaching'] : true;
        $currForm->encapsulate = (isset($args['encapsulated'])) ? $args['encapsulated'] : true;
        $currForm->encapsulate = (isset($args['encapsulate'])) ? $args['encapsulate'] : $currForm->encapsulate;
        $currForm->formTimeout = (isset($args['formTimeout'])) ? $args['formTimeout'] : false;
        $currForm->avoidDuplicates = (isset($args['avoidDuplicates'])) ? $args['avoidDuplicates'] : true;
        $currForm->submitButtonCallback = (isset($args['submitButtonCallback'])) ? $args['submitButtonCallback'] : 'auto';
        $currForm->cancelButtonCallback = (isset($args['cancelButtonCallback'])) ? $args['cancelButtonCallback'] : 'auto';
        $currForm->confirmationEmail = (isset($args['confirmationEmail'])) ? $args['confirmationEmail'] : false;
        $currForm->confirmationEmailTemplate = (isset($args['confirmationEmailTemplate'])) ? $args['confirmationEmailTemplate'] : false;
        $currForm->labelColons = (isset($args['labelColons'])) ? $args['labelColons'] : null; // default: colon if explicitly provided
        $currForm->labelWidth = (isset($args['labelWidth'])) ? $args['labelWidth'] : false;

        $this->exportStructure = (isset($args['exportStructure'])) ? $args['exportStructure'] : true;

        if (!$currForm->file) {
            $currForm->file = "~data/form-$formId.yaml";
        }
        $currForm->file = resolvePath($currForm->file, true);

        $currForm->skipConfirmation = (isset($args['skipConfirmation'])) ? $args['skipConfirmation'] : false;

        $currForm->prefill = (isset($args['prefill'])) ? $args['prefill'] : false;
        if ($currForm->prefill) {
            if (preg_match('/^[A-Z0-9]{5,10}$/', $currForm->prefill)) {
                $hash = $currForm->prefill;
                $currForm->wrapperClass .= ' lzy-form-revisited';
            } else {
                $hash = getUrlArg($currForm->prefill, true);
            }
            if ($hash) {
                $ds = $this->openDB();
                $rec = $ds->readRecord($hash);
                if ($rec) {
                    $rec['dataKey'] = $hash;
                    $currForm->prefillRec = $rec;
                    $currForm->wrapperClass .= ' lzy-form-revisited';
                }
            }
        }


        // activate 'prevent multiple submits':
        $currForm->preventMultipleSubmit = isset($args['preventMultipleSubmit'])? $args['preventMultipleSubmit'] : false;
        $GLOBALS['lizzy']['preventMultipleSubmit'] = $currForm->preventMultipleSubmit;

        $currForm->replaceQuotes = (isset($args['replaceQuotes'])) ? $args['replaceQuotes'] : true;
        $currForm->antiSpam = (isset($args['antiSpam'])) ? $args['antiSpam'] : false;
        if ($currForm->antiSpam && $this->lzy->localHost && !@$_SESSION['lizzy']['debug']) {    // disable antiSpam on localhost for convenient testing of forms
            $currForm->antiSpam = false;
            $this->page->addDebugMsg('"antiSpam" disabled on localhost');
        }

        $currForm->validate = (isset($args['validate'])) ? $args['validate'] : ((isset($args['novalidate'])) ? !$args['novalidate'] : false);
        $currForm->showData = (isset($args['showData'])) ? $args['showData'] : false;

        $currForm->showDataMinRows = (isset($args['showDataMinRows'])) ? $args['showDataMinRows'] : false;

        // options or option:
        $currForm->options = isset($args['options']) ? $args['options'] : (isset($args['option']) ? $args['option'] : '');
        $currForm->options = str_replace('-', '', $currForm->options);

        $currForm->recModifyCheck = (isset($args['recModifyCheck'])) ? $args['recModifyCheck'] : false;

        if (stripos('above', $currForm->labelPosition) !== false) {
            $currForm->class .= ' lzy-form-labels-above';
        }

        return 'form-head';
    } // parseHeadElemArgs



    private function parseElemArgs()
    {
        $args = $this->args;

        if (!is_array($args)) {
            $args = [];
        }
        foreach ($args as $key => $value) {
            if (is_int($key)) {
                if (strpos(SUPPORTED_TYPES, ",$value,") !== false) {
                    $args[ 'type' ] = $value;
                } elseif (strpos(UNARY_ELEM_ATTRIBUTES, ",$value,") !== false) {
                    $args[ $value ] = true;
                }
                unset( $args[ $key ]);
            }
        }

        $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form-Elem'.($this->inx + 1);

        $inpAttr = '';
        $type = $args['type'] = (isset($args['type'])) ? $args['type'] : 'text';
        if ($type === 'form-tail') {	// end-element is exception, doesn't need a label
            return 'form-tail';
        }

        $rec = &$this->currRec;
        $rec = new FormElement;
        $rec->type = $type;

        if (strpos($label, '<') !== false) {
            $rec->labelHtml = $label;
            $label = stripHtml( $label );
        }

        if (isset($args['name'])) {
            $name = $args['name'];
        } else {
            $name = $label;
        }

        if (($type === 'button') && (strpos(@$args['options'], 'submit') !== false)) {
            $name = '';
        }
        $name = trim( str_replace(['*', ':'], '', $name) );
        $name = str_replace(' ','_', $name);
        $name = preg_replace("/[^[:alnum:]_-]/m", '', $name);	// remove any non-letters, except _ and -

        // check that $name is unique:
        $ii = 0;
        for ($i=0; $i<$this->inx; $i++) {
            $n = @$this->currForm->formElements[$i]->name;
            if ($n === $name) {
                $ii++;
            }
        }
        if ($ii) {
            $name = $name . '_' . ($ii+1);   // add elem id
        }
        $rec->name = $name;
        $_name = " name='$name'";
        if (isset($args['dataKey'])) {
            $rec->dataKey = $args['dataKey'];
        } else {
            $rec->dataKey = $name;
        }

        // whether to translate a label: form-wide translateLabels or per-element translateLabel or '-' in front of label:
        $rec->translateLabel = false;
        if ($this->currForm->translateLabels || (@$args['translateLabel'])) {
            $rec->translateLabel = true;
        } elseif ($label && ($label[0] === '-')) {
            $label = substr($label, 1);
            $rec->translateLabel = true;
        }

        if (isset($args['id'])) {
            $elemId = $args['id'];
        } else {
            $elemId = translateToIdentifier($name) . '_' . $this->formInx;
        }

        $rec->elemId = $elemId;
        $rec->elemInx = $this->inx . '_' . $this->formInx;

        if (strpos($label, '*')) {
            $label = trim(str_replace('*', '', $label));
            $args['required'] = true;
        }
        $rec->label = $label;

        $rec->class = @$args['class'] ? $args['class'].' ' : '';
        $rec->wrapperClass = @$args['wrapperClass']? $args['wrapperClass']: '';

        if (isset($args['required']) && $args['required']) {
            if (preg_match('/(.*)\s*\[(.*)]/', $args['required'], $m)) {
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
            if (isset($this->currForm->prefillRec[ $rec->dataKey ])) {
                $value = $this->currForm->prefillRec[ $rec->dataKey ];
                if (is_string($value) && strpbrk($value, ',|')) {
                    $rec->prefill = explodeTrim(',|', "|$value");
                } else {
                    $rec->prefill = $value;
                }
            }
        }
        if (@$this->userSuppliedData[$name]) {
            $rec->prefill = $this->userSuppliedData[$name];
        }

        $rec->target = @$args['target']? $args['target']: '';
        if (strpos('radio,checkbox,dropdown,toggle', $type) !== false) {
            $rec->target = @$args['reveal']? $args['reveal']: $rec->target;
        }

        $rec->value = @$args['value']? $args['value']: '';
        if (isset($args['defaultValue'])) {
            $this->currForm->dynamicFormSupport = true;
            $rec->defaultValue = $args['defaultValue'];
        } elseif ($rec->value) {
            $rec->defaultValue = $rec->value;
        } else {
            $rec->defaultValue = null;
        }
        $rec->derivedValue = @$args['derivedValue']? $args['derivedValue']: '';
        if ($rec->derivedValue) {
            $this->currForm->dynamicFormSupport = true;
        }
        $rec->liveValue = @$args['liveValue']? $args['liveValue']: '';
        if ($rec->liveValue) {
            $this->currForm->dynamicFormSupport = true;
        }
        $rec->postProcess = (isset($args['postProcess'])) ? $args['postProcess'] : false;

        // for radio, checkbox and dropdown: options define the values available
        //  optionLabels are optional and used in the page, e.g. if you need a longish formulation to describe the option
        //  (for backward compatibility, value is accepted in place of options)
        $rec->option = @$args['option']? $args['option']: $rec->value;
        $rec->options = @$args['options']? $args['options']: $rec->value;
        $rec->optionLabels = @$args['optionLabels']? $args['optionLabels']: $rec->options;

        // for backward compatibility:
        //  valueNames used to have the meaning of today's option
        if (@$args['valueNames']) { // if supplied, swap arguments:
            $rec->optionLabels = $rec->options;
            $rec->options = $args['valueNames'];
        }

        if ($type === 'button') {
            $rec->optionNames = explodeTrim('|,', $rec->options);
            if (strpos($value, 'submit') !== false) {
                $rec->name = '_' . $rec->name;
            }
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
        if ($rec->info && !$this->infoInitialized) {
            $this->infoInitialized = true;
            $jq = <<<EOT
$('.lzy-formelem-show-info').tooltipster({
    trigger: 'custom',
    contentCloning: true,
    animation: 'fade',
    delay: 200,
    animation: 'grow',
    maxWidth: 420,
}).focus(function(){ // show on focus
    $(this).tooltipster('show');
}).mouseover(function(){ // show on hover
    $(this).tooltipster('show');
}).blur(function(){ // on click it'll focuses, and will hide only on blur
    $(this).tooltipster('hide');
}).mouseout(function(){ // if user doesnt click, will hide on mouseout
    if (document.activeElement !== this) {
        $(this).tooltipster('hide');
    }
});

EOT;
            $this->page->addJq( $jq );
            $this->page->addModules( 'TOOLTIPSTER' );
        }

        if (@$args['shortLabel']) {
            $rec->class = $rec->class? "$rec->class lzy-form-short-field ": 'lzy-form-short-field ';
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
        $rec->autoGrow = (isset($args['autoGrow'])) ? $args['autoGrow'] : true;
        $rec->fieldWrapperAttr = (isset($args['fieldWrapperAttr'])) ? ' '.$args['fieldWrapperAttr'] : '';
        $cutomImpAttr = (isset($args['inputAttr'])) ? ' '.$args['inputAttr'] : '';
        $cutomImpAttr = replaceCodesByQuotes( $cutomImpAttr );

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
        $rec->inpAttr = $_name.$inpAttr.$required.$cutomImpAttr;

        foreach($args as $key => $arg) {
            if (!isset($rec->$key)) {
                $rec->$key = $arg;
            }
        }

        return $type;
    } // parseElemArgs



    public function renderForm( $args )
    {
        $headArgs = ['type' => 'form-head'];
        $formElems = [];
        foreach ($args as $key => $value) {
            if ($this->isHeadAttribute( $key )) {
                $headArgs[$key] = $value;

            } else {
                $formElems[$key] = $value;
            }
        }
        $out  = $this->_render( $headArgs );
        $out .= $this->render( $formElems );
        $out .= $this->_render([ 'type' => 'form-tail' ]);

        return $out;
    } // renderForm



    private function _render($args)
    {
        $this->args = $args;
        $this->inx++;
        if (($this->inx > 0) && (@$args['type'] !== 'form-tail')) {
            $this->currRec = &$this->currForm->formElements[ $this->inx ];
        }

        $wrapperClass = "lzy-form-field-wrapper lzy-form-field-wrapper-{$this->inx}";

        $type = $this->parseArgs();
        if ($this->skipRenderingForm && (strpos('form-head,form-tail', $type) === false)) {
            return '';
        }
        $elem = $preElem = '';
        switch ($type) {
            case 'form-head':
                return $this->renderFormHead();
            
            case 'text':
            case 'string':
                $elem = $this->renderText();
                break;
            
            case 'readonly':
                $elem = $this->renderReadonly();
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

            case 'toggle':
                $elem = $this->renderToggle();
                break;

            case 'button':
                list($elem, $preElem) = $this->renderButtons();
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

            case 'numeric':
            case 'number':
                $elem = $this->renderNumber();
                break;

            case 'range':
                $elem = $this->renderRange();
                break;

            case 'tel':
                $elem = $this->renderTel();
                break;

            case 'fieldset':
                return $this->renderFieldsetBegin();

            case 'fieldset-end':
                return "\t\t\t\t</fieldset>\n";

            case 'hash':
                $elem = $this->renderHash();
                break;

            case 'reveal':
                $elem = $this->renderReveal();
                break;

            case 'hidden':
                $elem = $this->renderHidden();
                break;

            case 'literal':
                $elem = $this->renderLiteral();
                break;

            case 'bypassed':
                $this->bypassedValues[ $this->currRec->name ] = $this->currRec->value;
                break;

            case 'form-tail':
				return $this->renderFormTail();

            default:
                $type = isset($this->type)? $this->type : '';
                if ($type) {
                    $elem = "<p>Error: form type unknown: '$type'</p>\n";
                }
        }

        $type = $this->currRec->type;
        if (($type === 'radio') || ($type === 'checkbox') || ($type === 'toggle')) {
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
        $this->currRec->layout = @$args['layout']? $args['layout']: '';

        if (isset($this->currRec->wrapperClass) && ($this->currRec->wrapperClass)) {
	        $class = "$wrapperClass lzy-form-field-type-$type {$this->currRec->wrapperClass}";
		} else {
            $class = "$wrapperClass lzy-form-field-type-$type";
		}

        // error in supplied data? -> signal to user:
        $error = '';
        $out = '';
        $name = $this->currRec->name;
        if (isset($this->errorDescr[ $this->currForm->formInx ][$name] )) {
            $error = $this->errorDescr[ $this->currForm->formInx ][$name];
            $error = "\n\t\t<div class='lzy-form-error-msg'>$error</div>";
            $class .= ' lzy-form-error';
        }
        $class = $this->classAttr($class);
        if (($this->currRec->type !== 'hidden') &&
                ($this->currRec->type !== 'bypassed') &&
                ($this->currRec->type !== 'literal')) {
            $comment = '';
            if ($this->currRec->comment) {
                $comment = "\t<span class='lzy-form-elem-comment'><span>{$this->currRec->comment}</span>\n\t\t</span>";
            }
            $out = "\n\t\t<!-- [{$this->currRec->label}] -->\n";
		    $out .= "\t\t<div $class{$this->currRec->fieldWrapperAttr}>$error\n$elem\t\t$comment</div><!-- /field-wrapper -->\n\n";
        } elseif ($this->currRec->type !== 'bypassed') {
            $out = "\t\t$elem";
        }

        // add comment regarding required fields:
        if ($this->submitButtonRendered &&
                (stripos($this->currForm->options, 'norequiredcomment') === false)) {
            $this->submitButtonRendered = false;
            $out = $preElem . $out;
            if ($this->currForm->formHint) {
                $out = "\t\t<div class='lzy-form-hint'>{$this->currForm->formHint}</div>\n$out";

            } elseif (isset($this->currForm->hasRequiredFields) && $this->currForm->hasRequiredFields) {
                $out = "\t\t<div class='lzy-form-required-comment'>{{ lzy-form-required-comment }}</div>\n$out";
            }
        }

        return $out;
    } // render



    private function parseArgs()
    {
        if ($this->inx === 0) {    // first pass -> must be type 'form-head' -> defines formId
            return $this->parseHeadElemArgs();

        } else {
            return $this->parseElemArgs();
        }
    } // parseArgs



    private function renderFormHead()
    {
        if ($this->skipRenderingForm && $this->currForm->skipConfirmation) {
            $this->skipRenderingForm = false;
        }

        if ($this->skipRenderingForm) {
            $this->page->addCss("\n.lzy-form-hide-when-completed { display: none; };");
            return "\t<div class='lzy-form-hide-when-completed'>\n";
        }
        $formId = $this->formId;
        $currForm = $this->currForm;

        $this->initButtonHandlers();

        if ($currForm->labelWidth) {
            if (!preg_match('/\D/', $currForm->labelWidth)) {
                $currForm->labelWidth .= 'px';
            }
            $this->page->addCss("\n#$formId { --lzy-label-width: {$currForm->labelWidth}; };");
        }

        $this->userSuppliedData = $this->getUserSuppliedDataFromCache( $currForm->formInx );
        $currForm->creationTime = time();

        $id = " id='{$this->formId}'";

        $legendClass = 'lzy-form-header';
        $announcementClass = 'lzy-form-announcement';

        $wrapperClass = $currForm->wrapperClass;
        if (stripos($currForm->options, 'nocolor') === false) {
            $wrapperClass .= ' lzy-form-colored';
        }
        $class = &$currForm->class;
        $class = "$formId $class";

        if ($currForm->encapsulate && (strpos($class, 'lzy-encapsulated') === false)) {
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
		$out = "\n\t<div class='lzy-form-wrapper$wrapperClass'>\n";
        if ($currForm->formHeader) {
            $out .= "\t  <div class='$legendClass {$currForm->formId}'>{$currForm->formHeader}</div>\n\n";
        }

        // add DOM-elem in which FormFooter can later inject (error-)message:
        // (note: does not work using variable, therefore we use the include() macro)
        $out .= "{{ include(contentFrom:'#{$this->formHeadInfoVariableName}', id:'lzy-form-msg-{$currForm->formInx}') }}";

        $this->formHash = $this->getFormHash();

        $out .= "\t  <form$id$_class$_method$_action$novalidate>\n";
		$out .= "\t\t<input type='hidden' name='_lzy-form-ref' value='{$this->formHash}' />\n";
		$out .= "\t\t<input type='hidden' name='_lzy-form-cmd' value='' class='lzy-form-cmd' />\n";
		$out .= "\t\t<input type='hidden' name='_lzy-form-pg' value='{$GLOBALS['lizzy']['pagePath']}' />\n";

		if ($currForm->antiSpam) {
            $out .= "\t\t<div class='fld-ch' aria-hidden='true'>\n";
            $out .= "\t\t\t<label for='fld_ch{$this->formInx}{$this->inx}'>Name:</label><input id='fld_ch{$this->formInx}{$this->inx}' type='text' class='lzy-form-check' name='_lzy-form-name' value=''$honeyPotRequired />\n";
            $out .= "\t\t</div>\n";
        }
		return $out;
	} // renderFormHead



    private function renderReadonly()
    {
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= $this->getLabel(); // includes infoIcon
        $cls = " class='{$this->currRec->class}lzy-form-input-elem lzy-form-readonly-elem'";
        $value = $this->getValueAttr();
        list($descrBy, $description) = $this->renderElemDescription();

        $out .= "\t\t\t<div id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'$cls>{$this->currRec->value}</div>\n";
        $out .= "\t\t\t<input type='hidden' class='lzy-readonly' {$this->currRec->inpAttr}$value />\n";
        $out .= $description;
        return $out;
    } // renderText



    private function renderText()
    {
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= $this->getLabel(); // includes infoIcon
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr();
        $autocomplete = $this->currRec->autocomplete? " autocomplete='{$this->currRec->autocomplete}'": '';
        list($descrBy, $description) = $this->renderElemDescription();

        $out .= "\t\t\t<input type='text' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value$autocomplete$descrBy />\n";
        $out .= $description;
        return $out;
    } // renderText



    private function renderPassword()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $label = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $input = "<input type='password' class='lzy-form-password' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr} aria-invalid='false'$cls$descrBy />\n";
        $id = "lzy-form-show-pw-{$this->formInx}-{$this->inx}";
        $showPw = <<<EOT
                    <label class='lzy-form-pw-toggle' for="$id">
                        <input type="checkbox" id="$id" class="lzy-form-show-pw">
                        <img src="~sys/rsc/show.png" class="lzy-form-show-pw-icon" alt="{{ show password }}" title="{{ show password }}" />
                    </label>

EOT;
        $out = str_replace("\n", "\n\t\t", $label);
        if (@$this->currRec->{"reverse-order"}) {
            $out = <<<EOT
            <div class='lzy-form-pw-wrapper'>
                <div class='lzy-form-input-elem'>
                    $input$showPw
                </div><!-- /lzy-form-input-elem -->
$out
            </div><!-- /lzy-form-pw-wrapper -->

EOT;

        } else {
            $out .= <<<EOT
            <div class='lzy-form-input-elem'>
                $input$showPw
            </div>

EOT;

        }
        $out .= $description;
        return $out;
    } // renderPassword



    private function renderTextarea()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = @$this->currRec->prefill;
        $label = $this->getLabel();
        $out = '';
        list($descrBy, $description) = $this->renderElemDescription();
        if ($this->currRec->autoGrow) {
            $out .= <<<EOT
            <div class='lzy-textarea-autogrow lzy-form-input-elem'>
                <textarea id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy>$value</textarea>
            </div><!-- /lzy-form-input-elem -->
EOT;

        } else {
            $out .= "<textarea id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy>$value</textarea>\n";
        }
        $out .= $description;

        if (!@$this->currRec->reveal) {
            $out = "$label$out";

        // option "reveal: this" -> wrap textarea into a reveal-box:
        } else {
            $this->addRevealJs();
            $out = <<<EOT
        <div class='lzy-reveal-controller'>
            <input id='lzy-reveal-controller-{$this->currRec->elemInx}' class='lzy-reveal-controller-elem lzy-reveal-icon' type='checkbox' data-reveal-target='#lzy-reveal-container-{$this->currRec->elemInx}' />
            <label for='lzy-reveal-controller-{$this->currRec->elemInx}'>{$this->currRec->label}</label>
        </div>
		<div  id='lzy-reveal-container-{$this->currRec->elemInx}' style="display:none;">
		    <div>
$out
            </div>
        </div>
EOT;
        }
        return $out;
    } // renderTextarea



    private function renderEMail()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('email');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "\t\t\t<input type='email' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value$descrBy />\n";
        $out .= $description;
        return $out;
    } // renderEMail



    private function renderRadio()
    {
        $rec = $this->currRec;
        $class = $this->currRec->class;
        $groupName = translateToIdentifier($this->currRec->label);
        if ($this->currRec->name) {
            $groupName = $this->currRec->name;
        }
        $checkedElem = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        if ($rec->defaultValue) {
            $checkedElem = $rec->defaultValue;
        }
        $defaultValueAttr = '';
        if ($checkedElem) {
            if (is_array($checkedElem) && isset($checkedElem[0])) {
                $checkedElem = $checkedElem[0];
            }
            $defaultValueAttr = " data-default-value='$checkedElem'";
        }
        if ( $rec->liveValue ) {
            $defaultValueAttr .= " data-live-value='$rec->liveValue'";
        }

        $label = $this->getLabel(false, false);
        list($descrBy, $description) = $this->renderElemDescription();

        $target = $this->currRec->target;
        if ($target) {
            $this->addRevealJs();
            $target = explodeTrim(',|', "|$target||||||||||||||||");
        }

        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-radio-label'><legend class='lzy-legend'>{$label}</legend>\n\t\t\t  <div class='lzy-fieldset-body'$defaultValueAttr>\n";
        foreach($rec->optionLabels as $i => $optionLabel) {
            if ($i === 0) { continue; } // skip group name
            $preselectedValue = false;
            $name = @$rec->optionNames[$i]? $rec->optionNames[$i]: $optionLabel;
            $id = "lzy-radio_{$groupName}_{$this->formInx}-$i";

            if (strpos($optionLabel, '!') !== false) {
                $optionLabel = str_replace('!', '', $optionLabel);
                if ($checkedElem === false) {
                    $preselectedValue = true;
                }
            }
            $attr = '';
            if (@$target[$i]) {
                $cls = " class='$class lzy-reveal-controller-elem'";
                $attr = " data-reveal-target='{$target[$i]}'";
            } else {
                $cls = $class ? " class='$class'" : '';
            }

            $checked = ($checkedElem || $preselectedValue) ? ' checked' : '';
            $out .= "\t\t\t\t<div class='$id lzy-form-radio-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t\t<input id='$id' type='radio' name='$groupName' value='$name'$checked$cls$attr$descrBy /><label for='$id'>$optionLabel</label>\n";
            $out .= "\t\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        $out .= $description;
        return $out;
    } // renderRadio



    private function renderCheckbox()
    {
        $rec = $this->currRec;
        if (($rec->options === ' ') || ($rec->options === '_')) {
            $rec->optionLabels[1] = '&nbsp;';
            $rec->optionNames[1] = 'true';
        }
        $class = $this->currRec->class;
        $presetValues = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        $groupName = translateToIdentifier($rec->name, false, true, false);
        $label = $this->getLabel(false, false);
        list($descrBy, $description) = $this->renderElemDescription();
        $wrapperId = @$rec->wrapperId ? " id='$rec->wrapperId'": '';

        $checkedElem = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        if ($rec->defaultValue) {
            $checkedElem = $rec->defaultValue;
        }
        $defaultValueAttr = '';
        if ($checkedElem) {
            if (is_array($checkedElem) && isset($checkedElem[0])) {
                $checkedElem = $checkedElem[0];
            }
            $defaultValueAttr = " data-default-value='$checkedElem'";
        }
        if ( $rec->liveValue ) {
            $defaultValueAttr .= " data-live-value='$rec->liveValue'";
        }


        $aria = '';
        $revealTarget = $this->currRec->target;
        if ($revealTarget) {
            $rec->wrapperClass .= 'lzy-reveal-controller lzy-form-reveal-controller';
            $this->addRevealJs();
            $revealTarget = explodeTrim(',|', "|$revealTarget||||||||||||||||");
            $aria = ' aria-expanded="false"';
            $label .= " <span class='lzy-invisible'>{{ lzy-reveal-action-explanation }}</span>";
        }

        $legendId = "lzy-chckb_legend-{$groupName}_{$this->formInx}";
        $out = "\t\t\t<fieldset$wrapperId class='lzy-form-label lzy-form-checkbox-label'><legend id='$legendId' class='lzy-legend'>$label</legend>\n\t\t\t  <div class='lzy-fieldset-body'$defaultValueAttr>\n";

        foreach($rec->optionLabels as $i => $optionLabel) {
            if ($i === 0) { continue; } // skip group name
            $preselectedValue = false;
            $name = @$rec->optionNames[$i]? $rec->optionNames[$i]: $optionLabel;
            $id = "lzy-chckb_{$groupName}_{$this->formInx}-$i";
            if (strpos($optionLabel, '!') !== false) {
                $optionLabel = str_replace('!', '', $optionLabel);
                if ($presetValues === false) {
                    $preselectedValue = true;
                }
            }
            $attr = '';
            if (@$revealTarget[$i]) {
                $cls = " class='$class lzy-reveal-controller-elem'";
                $attr = " data-reveal-target='{$revealTarget[$i]}'";
            } else {
                $cls = $class ? " class='$class'" : '';
            }

            $checked = ($presetValues || $preselectedValue) ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-checkbox-elem lzy-form-choice-elem'>\n";

            // special case: $optionLabel is empty -> instead of label, apply aria-labelledby referring to legend:
            if ($optionLabel === '&nbsp;') {
                $out .= "\t\t\t\t<input id='$id' type='checkbox' name='${groupName}[]' value='$name'$checked$cls$attr$descrBy$aria aria-labelledby='$legendId' aria-expanded='false' />\n";
            } else {
            $out .= "\t\t\t\t<input id='$id' type='checkbox' name='${groupName}[]' value='$name'$checked$cls$attr /><label for='$id' class='lzy-form-choice-elem-label'>$optionLabel</label>\n";
            }
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        $out .= $description;
        return $out;
    } // renderCheckbox



    private function renderDropdown()
    {
        $rec = $this->currRec;
        $class = $this->currRec->class;


        $revealTarget = $this->currRec->target;
        if ($revealTarget) {
            $this->addRevealJs();
            $revealTarget = explodeTrim(',|', "|$revealTarget||||||||||||||||");
            $class .= ' lzy-reveal-controller-elem';
        }
        $cls = $class? " class='$class'": '';

        $selectedElem = isset($rec->prefill)? $rec->prefill: [];
        $selectedElem = @$selectedElem[0];
        if ($rec->defaultValue) {
            $selectedElem = $rec->defaultValue;
        }
        $defaultValueAttr = '';
        if ($selectedElem) {
            $defaultValueAttr = " data-default-value='$selectedElem'";
        }
        if ( $rec->liveValue ) {
            $defaultValueAttr .= " data-live-value='$rec->liveValue'";
        }

        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "\t\t\t<select id='{$rec->fldPrefix}{$rec->elemId}' name='{$rec->name}'$cls$descrBy$defaultValueAttr>\n";

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
            $attr = '';
            if (@$revealTarget[$i]) {
                $attr = " data-reveal-target='{$revealTarget[$i]}'";
            }

            $out .= "\t\t\t\t<option value='$val'$selected$attr>$optionLabel</option>\n";
        }
        $out .= "\t\t\t</select>\n";
        $out .= $description;

        return $out;
    } // renderDropdown



    private function renderToggle()
    {
        $label = $this->getLabel( false, false);
        $value = $this->getValueAttr('toggle');
        if ($value) {
            $stateOn = ' checked';
            $stateOff = '';
        } else {
            $stateOff = ' checked';
            $stateOn = '';
        }
        $reveal = $revealCls = '';
        $revealTarget = $this->currRec->target;
        if ($revealTarget) {
            $this->addRevealJs();
            $reveal = " data-reveal-target='$revealTarget'";
            $revealCls = ' lzy-reveal-controller-elem';
        }

        // optionLabels = small text in background of widget -> defined by arg 'optionLabels':
        $optionLabels = explodeTrim( ',|', $this->currRec->optionLabels );
        if ($optionLabels) {
            $lblOff = "<span class='lzy-toggle-text'>{$optionLabels[0]}</span>";
            $lblOn = "<span class='lzy-toggle-text'>{$optionLabels[1]}</span>";
        } else {
            $lblOff = "<span class='lzy-toggle-text lzy-invisible'>{{ lzy-off }}</span>";
            $lblOn = "<span class='lzy-toggle-text lzy-invisible'>{{ lzy-on }}</span>";
        }

        if (!@$this->currRec->layout) {
            $this->currRec->wrapperClass .= ' lzy-horizontal';
        }

        list($descrBy, $description) = $this->renderElemDescription();

        $out = <<<EOT
          <fieldset class='lzy-label-wrapper lzy-toggle-widget-label{$this->currRec->class}'><legend class='lzy-legend'>$label</legend>
            <div class="lzy-fieldset-body lzy-formelem-toggle-wrapper$revealCls" $reveal>
              <input type="radio" name="{$this->currRec->name}" value="false" class="lzy-toggle-input-off" id='{$this->currRec->fldPrefix}{$this->currRec->elemId}-off' $stateOff$descrBy />
              <label class="lzy-form-label lzy-toggle-off" for="{$this->currRec->fldPrefix}{$this->currRec->elemId}-off">$lblOff</span></label>

              <input type="radio" name="{$this->currRec->name}" value="true" class="lzy-toggle-input-on" id='{$this->currRec->fldPrefix}{$this->currRec->elemId}-on' $stateOn$descrBy$reveal />
              <label class="lzy-form-label lzy-toggle-on" for="{$this->currRec->fldPrefix}{$this->currRec->elemId}-on">$lblOn</label>
              
              <div class="lzy-toggle-handle"></div>
            </div>
          </fieldset>

EOT;

        $out .= $description;
        return $out;
    } // renderToggle



    private function renderUrl()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('url');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='url' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderUrl



    private function renderDate()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('date');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='date' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderDate



    private function renderTime()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('time');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='time' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderTime



    private function renderDateTime()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('datetime');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='datetime-local' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderDateTime



    private function renderMonth()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('month');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='month' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderMonth



    private function renderNumber()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='number' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderNumber



    private function renderRange()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='range' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderRange



    private function renderTel()
    {
        $cls = " class='{$this->currRec->class}lzy-form-input-elem'";
        $value = $this->getValueAttr('tel');
        $out = $this->getLabel();
        list($descrBy, $description) = $this->renderElemDescription();
        $out .= "<input type='tel' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$descrBy$value />\n";
        $out .= $description;
        return $out;
    } // renderTel



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
    } // renderFieldsetBegin



    private function renderHash()
    {
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= $this->getLabel(); // includes infoIcon
        $value = $this->getValueAttr();

        $out .= "\t\t\t<input type='text' class='lzy-input-hash {$this->currRec->class}' {$this->currRec->inpAttr}$value />\n";
        $out .= "\t\t\t<button id='lzy-input-hash-{$this->inx}' class='lzy-button lzy-generate-hash lzy-button-lean' type='button'>{{ lzy-generate-hash }}</button>\n";

        if (isset($this->currRec->option)) {
            $unambiguous = (strpos($this->currRec->option, 'unambiguous') !== false) ? 'true' : 'false';
        } else {
            $unambiguous = 'false';
        }
        $length = isset($this->currRec->length) ? $this->currRec->length : '8';

        $jq = <<<EOT
$('#lzy-input-hash-{$this->inx}').click(function() {
    let \$wrapper = $(this).closest('.lzy-form-field-wrapper');
    let \$input = $('.lzy-input-hash', \$wrapper);
    const newHash = createHash( $length, $unambiguous ); // unambiguous
    \$input.val( newHash );
});
EOT;
        $this->page->addJq($jq);
        return $out;
    } // renderHash



    private function renderHidden()
    {
        $value = $this->getValueAttr();
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';

        $out = "<input type='hidden' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderHidden



    private function renderLiteral()
    {
        $out = '';
        if (isset($this->currRec->html)) {
            $out = $this->currRec->html;
            $out = str_replace(['&#39;', '&#34;'], ["'", '"'], $out);
        }
        if (isset($this->currRec->md)) {
            $mdStr = $this->currRec->md;
            $md = new LizzyMarkdown( $this->lzy );
            $out .= $md->compileStr( $mdStr );
        }
        return $out;
    } // renderLiteral



    private function renderReveal()
    {
        $id = "lzy-form-reveal_{$this->formInx}-{$this->currRec->elemInx}";
        $label = $this->getLabel(false, false);
        $target = $this->currRec->target;
        $out = '';
        if ($this->currRec->errorMsg) {
            $out .= "\t\t\t<div class='lzy-form-field-errorMsg' aria-hidden='true'>{$this->currRec->errorMsg}</div>\n";
        }
        $out .= "\n\t\t\t\t<input id='$id' class='lzy-reveal-controller-elem lzy-reveal-icon' type='checkbox' data-reveal-target='$target' />\n";
        $out .= "\t\t\t\t<label for='$id'>$label</label>\n";

        $out = "\t\t\t<div class='lzy-reveal-controller lzy-form-reveal-controller'>$out\t\t\t</div>\n";

        $this->addRevealJs();
        return $out;
    } // renderReveal



    private function addRevealJs()
    {
        $this->page->addModules('REVEAL');
    } // addRevealJs



    private function renderButtons()
    {
        $out = '';
        $indent = "\t\t";
		$label = $this->currRec->label;
		$options = (isset($this->currRec->options) && $this->currRec->options) ? $this->currRec->options : $label;
		$value = (isset($this->currRec->value) && $this->currRec->value) ? $this->currRec->value : $options;

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
                    } elseif ($this->currRec->translateLabel) {
                        $label = $this->trans->translateVariable($label, true);
                    }
                } else {
                    $label = $type;
                }
				if (stripos($type, 'submit') !== false) {
				    $this->submitButtonRendered = true;
					$out .= "$indent<input type='submit' id='$id' value='$label' $class />\n";

                } elseif ((stripos($type, 'reset') !== false) || (stripos($type, 'cancel') !== false)) {
                    $out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";

				} elseif ($type === 'delete') { // delete button
                    $out .= "$indent<button type='submit' id='$id' type='submit' data-delete='' $class>$label</button>\n";

                } else { // custome button
					$out .= "$indent<button id='$id' $class type='button'>$label</button>\n";
				}
			}
		}

        $preElem = $this->renderDeleteRec();

        return [$out, $preElem];
    } //renderButtons



    private function renderDeleteRec()
    {
        if (strpos(','.$this->currRec->option, ',delete-rec') === false) {
            return '';
        }
        if (@$GLOBALS['lizzy']['formsDeleteRecRendered']) {
            return '';
        }
        $GLOBALS['lizzy']['formsDeleteRecRendered'] = true;

        $out = <<<EOT

        <!-- === form delete rec ----------------- -->
		<div class='lzy-form-delete-rec lzy-form-field-wrapper lzy-form-field-wrapper-2 lzy-form-field-type-checkbox lzy-form-field-type-choice'>
			<fieldset class='lzy-form-label lzy-form-checkbox-label'>
			  <div class='lzy-fieldset-body'>
                <div class='lzy-form-checkbox-elem lzy-form-choice-elem'>
                    <input id='lzy-form-delete-rec-{$this->inx}' type='checkbox' name='_lzy-delete-rec' value='true' /><label for='lzy-form-delete-rec-{$this->inx}'>{{ lzy-delete-rec-label }}</label>
                </div>
			  </div><!--/lzy-fieldset-body -->
			</fieldset>
			<span class="lzy-form-delete-rec-btn-lbl dispno">{{ lzy-form-delete-rec-btn-lbl }}</span>
		</div><!-- /field-wrapper -->
        <!-- === form delete rec ----------------- -->


EOT;
        $jq = <<<EOT
let \$submitBtn = $('.lzy-form-button-submit');
let \$deleteRecCheckbox = $('[name=_lzy-delete-rec]');
let \$labelText = $('.lzy-form-delete-rec-btn-lbl');
\$deleteRecCheckbox.change(function() {
    if (\$deleteRecCheckbox.prop('checked')) {
        let origLabel = \$submitBtn.val();
        \$labelText.data('orig-text', origLabel);
        \$submitBtn.val( \$labelText.text() );
    } else {
        \$submitBtn.val( \$labelText.data('orig-text') );
    }
});
EOT;
        $this->page->addJq($jq);
        return $out;
    } // renderDeleteRec


	private function renderFormTail()
    {
        $currForm = $this->currForm;
        $this->initFormJs();

        $formInx = $this->currForm->formInx;
        if (!$this->skipRenderingForm) {
            $out = '';
            if ($currForm->antiSpam) {
                $this->initAntiSpam();
            }
            if ($this->bypassedValues) {
                $tck = new Ticketing([
                    'defaultType' => 'form-bypass-hash',
                    'defaultMaxConsumptionCount' => 10]
                );
                $tickHash = $tck->createTicket($this->bypassedValues);
                $out .= "\t\t<input type='hidden' name='_form-bypass-hash' value='$tickHash' />\n";
            }
            $out .= "\t  </form>\n";

            if ($currForm->formFooter) {
                $out .= "\t  <div class='lzy-form-footer'>{$currForm->formFooter}</div>\n";
            }
        } else {
            $out = "\t</div><!-- /lzy-form-hide-when-completed -->\n";
        }

        // save form data to DB:
        $this->saveFormDescr();

        // update db-structure file:
        $this->updateDbStructure();

        // append possible text from user-data evaluation:
        $msgToClient = '';

        // check _announcement_ and responseToClient, inject msg if present:
        if (@$this->errorDescr[$formInx]['_announcement_']) { // is errMsg, takes precedence over responseToClient
            if ($currForm->responseViaSideChannels) {
                $this->page->addPopup($this->errorDescr[$formInx]['_announcement_']);
            } else {
                $msgToClient = $this->errorDescr[$formInx]['_announcement_'];
            }
            $this->errorDescr[$formInx]['_announcement_'] = false;

        } elseif (@$this->errorDescr[ 'generic' ]['_announcement_']) { // is errMsg, takes precedence over responseToClient
            if ($currForm->responseViaSideChannels) {
                $this->page->addPopup($this->errorDescr['generic']['_announcement_']);
            } else {
                $msgToClient = $this->errorDescr['generic']['_announcement_'];
            }
            $this->errorDescr[ 'generic' ]['_announcement_'] = false;

        } elseif (@$this->responseToClient) {
            if ($currForm->responseViaSideChannels) {
                $this->page->addMessage($this->responseToClient);
            } else {
                $msgToClient = $this->responseToClient;
            }
        }

        if ($msgToClient) {
            // append 'continue...' if form was omitted:
            if ($this->skipRenderingForm) {
                if (!isset($this->errorDescr[ 'generic' ]['_override_']) || !$this->errorDescr[ 'generic' ]['_override_']) {
                    $next = @$this->currForm->next ? $this->currForm->next : './';
                    $msgToClient .= "<div class='lzy-form-continue'><a href='{$next}'>{{ lzy-form-continue }}</a></div>\n";
                } else {
                    $msgToClient = "<div class='lzy-form-override-msg'>{$this->errorDescr[ 'generic' ]['_override_']}</div>\n";
                }
                $out .= "\t  <div class='lzy-form-response'>$msgToClient</div>\n";
                $out .= "\t<div id='{$this->formHeadInfoVariableName}' class='dispno'>$msgToClient\t</div>\n";

            } else {
                $msgToClient = "\t  <div class='lzy-form-response lzy-error-message'>$msgToClient</div>\n";
                $out .= "\t<div id='{$this->formHeadInfoVariableName}' class='dispno'>$msgToClient\t</div>\n";
            }
        }

        // check whether request has been properly processed, display an error message if not:
        if (@$_SESSION['lizzy']['formRequestPending']) {
            $errMsg = "\t  <div class='lzy-form-response lzy-error-message'>{{ lzy-form-general-error }}</div>\n";
            $out .= "\t<div id='{$this->formHeadInfoVariableName}' class='dispno'>$errMsg</div>\n";
            $this->hideForm();
            $_SESSION['lizzy']['formRequestPending'] = false;
            $this->sendMailToWebmaster('Error while processing form input');
        }

        if (!$this->skipRenderingForm) {
            $out .= "\t</div><!-- /lzy-form-wrapper -->\n\n";
        }
        if ($this->errorDescr) {
            $jq = <<<'EOT'

$('.lzy-form-error:first').each(function () {
    const $this = $( this );
    $('main, html').animate({
        scrollTop: $this.offset().top - 50
    }, 500);
});

EOT;
            $this->page->addJq( $jq );
        }

        // present previously received data to form owner:
        if ($this->currForm->showData) {
            $out .= $this->renderDataTable();
        }
        return $out;
	} // renderFormTail



    private function getLabel($id = false, $wrapOutput = true)
    {
		$id = ($id) ? $id : "{$this->currRec->fldPrefix}{$this->currRec->elemId}";
        $requiredMarker =  $this->currRec->requiredMarker;
        if ($requiredMarker) {
            $this->currForm->hasRequiredFields = true;
        }
        if (@$this->currRec->formLabel) {
            $label = $this->currRec->formLabel;
        } elseif ($this->currRec->labelHtml) {
            $label = $this->currRec->labelHtml;
        } else {
            $label = $this->currRec->label;
        }

        $hasColon = (strpos($label, ':') !== false);
        $label = trim(str_replace([':', '*'], '', $label));
        if ($label && ($label[0] === '-')) {
            $label = $this->trans->translateVariable(substr($label,1), true);
        } elseif ($this->currRec->translateLabel) {
            $label = $this->trans->translateVariable($label, true);
        }
        if ($this->currForm->labelColons || $hasColon) {
            $label = str_replace(':', '', $label) . ':';
        }
        if ($requiredMarker) {
            $label .= ' '.$requiredMarker;
        }

        $infoIcon = $this->renderInfoIcon();

        if ($wrapOutput) {
            return <<<EOT
                <span class='lzy-label-wrapper'>
                    <label for='$id'>$label</label>
                    $infoIcon
                </span><!-- /lzy-label-wrapper -->

EOT;
        } else {
            return "$label$infoIcon";
        }
    } // getLabel



    private function renderInfoIcon()
    {
        if (!$this->currRec->info) {
            $this->currRec->infoId = '';
            return '';
        }

        $elemInx = "{$this->formInx}-{$this->currRec->elemInx}";
        $this->currRec->infoId = "lzy-formelem-info-text-$elemInx";
        $infoIconText = $this->currRec->info;
        if (@$infoIconText[0] === '-') {
            $infoIconText = $this->trans->translateVariable( substr($infoIconText,1) );
        }

        $infoIconText = <<<EOT

                    <span  class="lzy-dispno">
                        <span id="{$this->currRec->infoId}" class="lzy-formelem-info-text lzy-formelem-info-text-$elemInx" aria-live="polite">$infoIconText</span>
                    </span>
            
EOT;

        $icon = '<span class="lzy-icon lzy-icon-info"></span>';
        $infoIcon = <<<EOT

                <button class="lzy-formelem-show-info" data-tooltip-content="#{$this->currRec->infoId}" type="button">
                    $icon$infoIconText
                </button>

EOT;
        return "$infoIcon";
    } // renderInfoIcon



    private function renderElemDescription()
    {
        $descrBy = '';
        if ($this->currRec->description) {
            $descrId = "{$this->currRec->fldPrefix}{$this->currRec->elemId}-descr";
            $descrBy = " aria-describedby='$descrId'";

        } elseif ($this->currRec->info) {
            $descrId = $this->currRec->infoId;
        }
        $description = '';
        if ($this->currRec->description) {
            $description = "\t\t\t<div id='$descrId' class='lzy-form-field-description'>{$this->currRec->description}</div>\n";
        }
        return [$descrBy, $description];
    } // renderElemDescription



    private function classAttr($class = '')
    {
        $out = " class='".trim($class). "'";
        return trim($out);
    } // classAttr
    


	private function saveFormDescr()
	{
	    $form = $this->currForm;
	    $form->formHash = $this->formHash;
	    $form->bypassedValues = @$this->bypassedValues;
	    if (isset( $form->prefillRec['dataKey'] )) {
            $form->dataKey = $form->prefillRec['dataKey'];
        }

        $cacheFile = SYSTEM_CACHE_PATH . $GLOBALS['lizzy']['pagePath'] . "form-descr-{$this->formInx}.txt";
        
        // cache form-description for max 24hours:
        if (file_exists($cacheFile) && (filemtime($cacheFile) > (time() - 86400))) {
            return;
        }
        $str = $this->formHash . ':'. base64_encode( serialize( $form ) );
        preparePath($cacheFile);
        file_put_contents($cacheFile, $str);

        // for debugging only:
        if (@$_SESSION['lizzy']['debug']) {
            $cacheFile = SYSTEM_CACHE_PATH . $GLOBALS['lizzy']['pagePath'] . "form-descr-{$this->formInx}.yaml";
            writeToYamlFile($cacheFile, $form);
        }
    } // saveFormDescr



    protected function getFormHash()
    {
        $cacheFile = SYSTEM_CACHE_PATH . $GLOBALS['lizzy']['pagePath'] . "form-descr-$this->formInx.txt";
        if (file_exists($cacheFile)) {
            $str = file_get_contents($cacheFile);
            $formHash = substr($str, 0, 8);
        } else {
            $formHash = createHash(8, false, true);
        }
        return $formHash;
    } // getFormHash



    protected function restoreFormDescr( $formHash )
	{
        for ($formInx=1; true; $formInx++) {
            $cacheFile = SYSTEM_CACHE_PATH . $GLOBALS['lizzy']['pagePath'] . "form-descr-$formInx.txt";
            if (!file_exists($cacheFile)) {
                return null;
            }
            $str = file_get_contents($cacheFile);
            if (strpos($str, $formHash) === 0) {
                break;
            }
        }
        $this->currForm = unserialize(base64_decode( substr($str, 9 )));
        if (@$this->userSuppliedData0['_form-bypass-hash']) {
            $tickHash = $this->userSuppliedData0['_form-bypass-hash'];
            $tck = new Ticketing();
            $bypassed = $tck->consumeTicket($tickHash);
            if ($bypassed) {
                $this->currForm->bypassedValues = $bypassed;
            }
        }

        // enroll can set admin_mode, if so, skip recModifyCheck:
        if (@$this->admin_mode) {
            $this->currForm->recModifyCheck = false;
        }
        return $this->currForm;
	} // restoreFormDescr



	private function cacheUserSuppliedData($formInx, $userSuppliedData)
	{
        $pathToPage = $GLOBALS['lizzy']['pathToPage'];
        $_SESSION['lizzy']['formData'][ $pathToPage ][ $formInx ] = serialize($userSuppliedData);
	} // cacheUserSuppliedData



	private function getUserSuppliedDataFromCache( $formInx )
	{
        $pathToPage = $GLOBALS['lizzy']['pathToPage'];
		return (isset($_SESSION['lizzy']['formData'][ $pathToPage ][ $formInx ])) ?
            unserialize($_SESSION['lizzy']['formData'][ $pathToPage ][ $formInx ]) : null;
	} // getUserSuppliedDataFromCache




    public function evaluateUserSuppliedData()
    {
        $msgToClient = '';
        $this->inhibitSavingUserData = false;

        // returns false on success, error msg otherwise:
        $this->userSuppliedData = $_POST;
        $this->userSuppliedData0 = $_POST;

        $userSuppliedData = &$this->userSuppliedData;
        $_SESSION['lizzy']['formRequestPending'] = true;

        if ($this->lzy->config->debug_debugLogging) {
            $log = json_encode($userSuppliedData);
            writeLogStr("Form data received: $log", FORM_LOG_FILE);
        }

		if (!isset($userSuppliedData['_lzy-form-ref'])) {
			$this->clearCache();
            $_SESSION['lizzy']['formRequestPending'] = false;
			return false;
		}

        $formHash = $userSuppliedData['_lzy-form-ref'];
        $currForm = $this->restoreFormDescr( $formHash );

        // ticket timed out or formInx not matching:
        if (($currForm === null) || ($currForm->formHash !== $formHash)) {
            $this->clearCache();
            $_SESSION['lizzy']['formRequestPending'] = false;
            return false;
        }
        if ($this->formInx !== $currForm->formInx) {
            return false;
        }
        $formInx = $currForm->formInx;
        $this->formHash = $formHash;

        // anti-spam check:
        if ($this->checkHoneyPot()) {
            $this->errorDescr[$currForm->formInx]['_announcement_'] = "{{ lzy-form-antispam-notice }}";
            $this->clearCache();
            $_SESSION['lizzy']['formRequestPending'] = false;
            $this->hideForm($currForm->formId);
            $this->inhibitSavingUserData = true;
        }

        $cmd = @$userSuppliedData['_lzy-form-cmd'];
        if ($cmd === '_ignore_') {     // _ignore_
            $this->cacheUserSuppliedData($formInx, $userSuppliedData);
            $_SESSION['lizzy']['formRequestPending'] = false;
            return false;

        } elseif ($cmd === '_reset_') { // _reset_
            $this->clearCache();
            $_SESSION['lizzy']['formRequestPending'] = false;
            reloadAgent();

        } elseif ($cmd === '_cache_') { // _cache_
            $this->prepareUserSuppliedData(); // handles radio and checkboxes
            $this->cacheUserSuppliedData($formInx, $userSuppliedData);
            exit;

        } elseif ($cmd === '_log_') {   // _log_
            $out = @$userSuppliedData['_lizzy-form-log'];
            writeLogStr($out, SPAM_LOG_FILE);
            exit;
        }

        $this->prepareUserSuppliedData(); // handles radio and checkboxes

        // check required entries:
        if (!$this->checkSuppliedDataEntries()) {
            $this->cacheUserSuppliedData($formInx, $userSuppliedData);
            $_SESSION['lizzy']['formRequestPending'] = false;
            return false;
        }
        if (@$this->errorDescr[$formInx] ) {
            $this->cacheUserSuppliedData($formInx, $userSuppliedData);
            $_SESSION['lizzy']['formRequestPending'] = false;
            return false;
        }

        // check whether form data timed out:
        if ($currForm->formTimeout) {
            $dataTime = $currForm->creationTime;
            if ($dataTime < (time() - $currForm->formTimeout)) {
                $this->page->addPopup( '{{ lzy-form-expired }} [form timed out]' );
                $_SESSION['lizzy']['formRequestPending'] = false;
                return false;
            }
        }

        // save user supplied data to SESSION:
        $this->cacheUserSuppliedData($formInx, $userSuppliedData);

        $errDescr = @$this->errorDescr[ $this->formInx ];
        if ($errDescr) {
            $_POST = [];
            $_SESSION['lizzy']['formRequestPending'] = false;
            return false;
        }

        $msgToOwner = $this->assembleResponses();


        $customResponseEvaluation = @$currForm->customResponseEvaluation;
        if ($customResponseEvaluation) {
            if (function_exists($customResponseEvaluation)) {
                $res =  $customResponseEvaluation( $userSuppliedData );
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
                $result = $this->trans->doUserCode($customResponseEvaluation, null, true);
                if (is_array($result)) {
                    if (!$result[0]) {
                        fatalError($result[1]);
                    } else {
                        $this->clearCache();
                        $_SESSION['lizzy']['formRequestPending'] = false;
                        return $result[1];
                    }
                }
                if ($result) {
                    if (!function_exists('evalForm')) {
                        fatalError("Warning: trying to execute evalForm(), but not defined in '$customResponseEvaluation'.", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                    }
                    $res = evalForm($this);
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
                    fatalError("Warning: executing code '$customResponseEvaluation' has been blocked; modify 'config/config.yaml' to fix this.", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                }
            }
        }

        $customResponseEvaluationFunction = @$currForm->customResponseEvaluationFunction;
        if ($customResponseEvaluationFunction && function_exists( $customResponseEvaluationFunction )) {
            $res = $customResponseEvaluationFunction($this->lzy, $this, $userSuppliedData); // $res = false -> everything ok
            if (is_string($res)) {
                $this->clearCache();
                $GLOBALS['lizzy']['forms_skipRenderingForm'][$this->formInx] = true;
                $this->responseToClient = $res;
                $_SESSION['lizzy']['formRequestPending'] = false;
                return '';
            } elseif(is_array($res)) {
                // second elem of $res set => means skip rendering form and override output:
                if (isset($res[1])) {
                    $this->errorDescr[ 'generic' ]['_override_'] = $res[1];
                    $GLOBALS['lizzy']['forms_skipRenderingForm'][$this->formInx] = true;
                    writeLogStr("{$res[1]}", FORM_LOG_FILE);

                } else {
                    $this->errorDescr[$currForm->formInx]['_announcement_'] = $res[0];
                    writeLogStr("{$res[0]}", FORM_LOG_FILE);
                }
            }
        }

        $noError = !@$this->errorDescr[ $currForm->formInx ] && !isset($this->errorDescr[ 'generic' ]);
        $cont = false;
        if ($noError ) {
            $cont = $this->saveAndWrapUp($msgToOwner);
        }
        $_POST['__lzy-form-ref'] = $_POST['_lzy-form-ref'];
        unset($_POST['_lzy-form-ref']);

        if ($cont && $this->currForm->confirmationEmail) {
            $this->sendConfirmationMail( $userSuppliedData );
        }

        if ($noError && ($this->currForm->confirmationText !== false)) {
            if (!$msgToClient) {
                $msgToClient = $this->currForm->confirmationText;
            }
            if ($msgToClient && $this->responseToClient) {
                $this->responseToClient = $msgToClient . '<br>' . $this->responseToClient;
            } else {
                $this->responseToClient = $msgToClient;
            }
            if (!$this->currForm->responseViaSideChannels) {
                $this->skipRenderingForm = true;
            }
            $_SESSION['lizzy']['formRequestPending'] = false;

        } elseif (@$this->errorDescr[ 'generic' ]['_override_']) {
            $this->responseToClient = $this->errorDescr[ 'generic' ]['_override_'];
            if (!$this->currForm->responseViaSideChannels) {
                $this->skipRenderingForm = true;
            }
            $_SESSION['lizzy']['formRequestPending'] = false;
        }
        return true;
    } // evaluateUserSuppliedData



	private function saveAndWrapUp($msgToOwner)
	{
	    if (!$this->saveUserSuppliedDataToDB()) {
            return false;
        }

        if ($this->checkDataWritten()) {
            writeLogStr("Forms data processed successfully", FORM_LOG_FILE);
        }

        $this->clearCache();

        if ($msgToOwner && $this->currForm->mailTo) {
            $this->lzy->sendMail($this->currForm->mailTo, $msgToOwner['subject'], $msgToOwner['body'], $this->currForm->mailFrom);
        }
        return true;
    } // saveAndWrapUp



    private function checkDataWritten()
    {
        $filename = $this->db->close();
        $rawData = file_get_contents($filename);
        $data = $this->userSuppliedData;
        do {
            if (!$data) {
                return false;
            }
            $testStr = array_shift($data);
        } while (!$testStr);
        if ($testStr && (strpos($rawData, $testStr) === false)) {
            $this->sendMailToWebmaster("Error in DB-write-check.", "Missing pattern:\n\n$testStr");
            return false;
        }
        return true;
    } // checkDataWritten



	private function assembleResponses()
	{
	    // returns: [$msgToClient, $msgToOwner]
        $currForm = $this->currForm;
        $msgToOwner = "{$currForm->formName}\n===================\n\n";

		foreach ($currForm->formElements as $element) {
		    if ((strpos(PSEUDO_TYPES, $element->type) !== false) || (@$element->name[0] === '_')) {
                continue;
            }
            $label = $element->labelInOutput;
            if ($element->translateLabel) {
                $label = $this->trans->translateVariable( $label, true );
            }
            $label = html_entity_decode($label);

            $name = $element->name;
            if (!isset($this->userSuppliedData[ $name ])) {
                continue;
            }
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



    private function checkHoneyPot()
    {
        if (!$this->currForm->antiSpam) {
            return false;
        }

        // skip if on localhost and NOT in debug mode:
        if ($GLOBALS['lizzy']['isLocalhost'] && !$GLOBALS['_SESSION']['lizzy']['debug']) {
            return false;
        }
        // check honey pot field (unless on local host):
        if (@$this->userSuppliedData['_lzy-form-name'] !== '') {
            $out = var_export($this->userSuppliedData, true);
            $out = str_replace("\n", ' ', $out);
            $out .= "\n[{$_SERVER['REMOTE_ADDR']}] {$_SERVER['HTTP_USER_AGENT']}\n";
            $logState = $GLOBALS['lizzy']['errorLoggingEnabled'];
            $GLOBALS['lizzy']['errorLoggingEnabled'] = true; // temp override errorLoggingEnabled setting
            writeLog($out, SPAM_LOG_FILE);
            writeLogStr("Honeypot entry detected", FORM_LOG_FILE);
            $GLOBALS['lizzy']['errorLoggingEnabled'] = $logState;
            return true;
        }
        return false;
    } // checkHoneyPot



	private function saveUserSuppliedDataToDB()
	{
        $currForm = $this->currForm;
        $userSuppliedData = $this->userSuppliedData;
        $recKey = $recKey0 = &$this->recKey;
        if ($recKey === 'new-rec') {
            $recKey = '';
        }

        $ds = $this->openDB();

        $struc = $ds->getStructure();
        if (!@$struc['key']) {
            $struc['key'] = 'index';
        }

        $oldRec = false;
        $skipSaving = $this->inhibitSavingUserData;

        if (isset($this->dataKeyOverride)) {
            $recKey = $this->getOverrideKey( $recKey );
            $recKey0 = preg_replace('/^.*,/', '', $recKey);
        }

        // handle special case: structure[key] contains pattern '=xy', if so use that element as key:
        if ($struc['key'][0] === '=') {
            $useAsKey = substr($struc['key'], 1);
            if (isset($userSuppliedData[ $useAsKey ])) {
                $recKey = $userSuppliedData[ $useAsKey ];
            }
        }

        if ($this->isNewRec) {
            writeLogStr("New data: [{$currForm->formName}:$recKey] ".json_encode($userSuppliedData), FORM_LOG_FILE);
        } else {
            writeLogStr("Data modified: [{$currForm->formName}:$recKey] ".json_encode($userSuppliedData), FORM_LOG_FILE);
        }

        // check whether data already present in DB, if not disabled:
        if ($currForm->avoidDuplicates) {
            $keys0 = array_keys($struc['elements'] ?? []);
            if ($this->checkDuplicates($ds, $keys0, $currForm, $userSuppliedData)) {
                $skipSaving = true;
            }
        }

        // prepare user supplied data for DB:
        $newRec = [];
        foreach ($currForm->formElements as $i => $rec) {
            $usrDataFldName = $rec->name;
            $elementKey = $rec->dataKey;
            if (($usrDataFldName && ($usrDataFldName[0] === '_')) || !isset($userSuppliedData[$usrDataFldName])) {
                continue;
            }

            if (!$this->isNewRec && ($rec->type === 'password')) {
                $newPw = $userSuppliedData[ $usrDataFldName ];
                if (!$newPw || ($newPw === PASSWORD_PLACEHOLDER)) {
                    if (!$oldRec) {
                        $oldRec = $ds->readRecord($recKey);
                    }
                    $userSuppliedData[ $usrDataFldName ] = @$oldRec[ $elementKey ];
                }
            }
            $newRec[$elementKey] = $userSuppliedData[$usrDataFldName];
        }

        $newRec[ TIMESTAMP_KEY_ID ] = date('Y-m-d H:i:s');
        if ($recKey !== '') {
            $newRec[REC_KEY_ID] = $recKey0;
        } else {
            $newRec[REC_KEY_ID] = createHash();
        }

        if (!$skipSaving) {
            // special case: external directive re. dataKay (e.g. from enroll):
            if (isset($this->dataKeyOverride)) {
                $ds->writeElement($recKey, $newRec, true, true, true, $this->dataKeyOverrideHash);
                if (!$this->currForm->responseViaSideChannels) {
                    $this->skipRenderingForm = true;
                }
            } else {
                if ($this->isNewRec) {
                    // add new record:
                    if ($struc['key'] === 'index') {
                        $recKey = false;
                    }
                    $res = $ds->addRecord($newRec, $recKey, true, true, true);

                } else {
                    $res = $ds->writeRecord($recKey, $newRec, true, true, true);
                }
                if ($res) {
                    if (!$this->currForm->responseViaSideChannels) {
                        $this->skipRenderingForm = true;
                    }
                } else {
                    $this->errorDescr[$currForm->formInx]['_announcement_'] = 'Error writing to DB';
                    writeLogStr("forms:saveUserSuppliedDataToDB(): Error writing to DB", FORM_LOG_FILE);
                    $this->sendMailToWebmaster('Error while writing form data to DB');
                }
            }
        }

        if ($this->repeatEventTaskPending) {
            $this->executeEventDuplicating($newRec, $ds);
        }
        return true;
	} // saveUserSuppliedDataToDB



    private function checkDuplicates($ds, $keys0, $currForm, $userSuppliedData)
    {
        $doubletFound = false;
        if (isset($this->dataKeyOverride)) {
            $data = $ds->readElement($this->dataKeyOverride);
        } else {
            $data = $ds->read();
        }
        // loop over records:
        foreach ($data as $dataRecKey => $rec) {
            // generally ignore all data keys starting with '_':
            if (@$dataRecKey[0] === '_') {
                continue;
            }

            // loop over fields in rec, look for differences:
            $identical = true;
//??? modif for user-admin
// -> need to check whether works in other situations, e.g if struct derived from (incomplete) data
//                foreach ($rec as $dbFldKey => $value) {
//            $keys = array_unique( array_merge(array_keys($struc['elements']), array_keys($rec)));
            $keys = array_unique( array_merge( $keys0, array_keys($rec)));
            foreach ($keys as $dbFldKey) {
                // ignore all meta attributes:
                if (@$dbFldKey[0] === '_') {
                    continue;
                }

                $usrDataFldName = false;
                foreach ($currForm->formElements as $i => $formElemDescr) {
                    if (($formElemDescr->name === $dbFldKey) ||
                        ($formElemDescr->dataKey === $dbFldKey)) {
                        $usrDataFldName = $formElemDescr->name;
                        break;
                    }
                }
                if (!$usrDataFldName) {
                    continue;
                }

                if (is_array($userSuppliedData[$usrDataFldName])) {
                    $v1 = strtolower(str_replace(' ', '', @$userSuppliedData[$usrDataFldName][0]));
                } else {
                    $v1 = strtolower(str_replace(' ', '', @$userSuppliedData[$usrDataFldName]));
                }
                if (isset($rec[$dbFldKey]) && is_array($rec[$dbFldKey])) {
                    $v2 = strtolower(str_replace(' ', '', @$rec[$dbFldKey][0]));
                } else {
                    $v2 = strtolower(str_replace(' ', '', @$rec[$dbFldKey]));
                }
                if (($v1 !== $v2) && ($v1 !== PASSWORD_PLACEHOLDER)) {
                    $identical = false;
                    break;
                }
            }
            $doubletFound |= $identical;
        }

        if ($doubletFound) {
            $this->clearCache();
            // if repeatEventTaskPending we need to continue without sending warning:
            if (!$this->repeatEventTaskPending) {
                $this->errorDescr[$this->formInx]['_announcement_'] = '{{ lzy-form-duplicate-data }}';
                if (!@$this->currForm->responseViaSideChannels) {
                    $this->skipRenderingForm = true;
                }
                writeLogStr("Unchanged data: ignored [{$currForm->formName}:$this->recKey] " . json_encode($userSuppliedData), FORM_LOG_FILE);
            }
        }
        return $doubletFound;
    } // checkDuplicates



    private function deleteDataRecord()
    {
        if (!@$this->userSuppliedData0['_rec-key']) {
            return false;
        }

        $recKey = $this->userSuppliedData0['_rec-key'];
        $ds = $this->openDB();
        if (isset($this->dataKeyOverride)) {
            $recKey = $this->getOverrideKey( $recKey );
        }
        $res = $ds->deleteElement($recKey);
        if (!$res) {
            return '{{ lzy-forms-delete-rec-failed }}';
        }
        $this->clearCache();
        return false;
    } // deleteDataRecord



    private function prepareUserSuppliedData()
    {
        // add missing elements, remove meta-elements, convert radio&checkboxes to internal format:
        $currForm = $this->currForm;
        $this->repeatEventTaskPending = false;

        // determine recKey:
        $this->recKey = @$this->userSuppliedData0['_rec-key'];

        $this->isNewRec = true;
        if ((@$this->currForm->dataKey !== null) && (@$this->currForm->dataKey !== '')) {
            $this->recKey = $currForm->dataKey;
            $this->isNewRec = false;
        } elseif (!$this->recKey) {
            $this->recKey = createHash();
        } else {
            $this->isNewRec = false;
        }

        $this->prepareDataRec();
    } // prepareUserSuppliedData



    private function prepareDataRec()
    {
        $elemDefs = $this->currForm->formElements;
        $rec = &$this->userSuppliedData;

        // drop elements from userSuppliedData whose keys start with '_':
        foreach ($rec as $key => $label) {
            if ($key[0] === '_') {
                unset($rec[ $key ]);
            }
        }

        foreach ($elemDefs as $inx => $elemDef) {
            $key = $elemDef->name;
            $type = $elemDef->type;

            // skip elements of pseudo type:
            if ((strpos(PSEUDO_TYPES, $type) !== false) ||
                ($key && ($key[0] === '_') && (strpos($key, '_repeat') === false))) {
                continue;
            }

            // handle missing values:
            if (!isset($rec[ $key ])) {
                // handle elements of type beypassed, retrieve value from $elemDef:
                if ($type === 'bypassed') {
                    $rec[$key] = $this->currForm->bypassedValues[ $key ];
                } else {
                    $rec[$key] = '';
                }
            }

            // execute postProcess instructions:
            if (isset($elemDef->postProcess) && $elemDef->postProcess) {
                // skip special case '_repeat-event' -> excute later:
                if (strpos($elemDef->postProcess, '%repeatEvent') !== 0) {
                    if (preg_match('/=\(\( (.*?) \)\)/x', $elemDef->postProcess, $m)) {
                        $expr = $m[1];
                        if (preg_match_all('/\$(\w+)/', $expr, $mm)) {
                            foreach ($mm[1] as $i => $elem) {
                                $varName = $mm[1][$i];
                                if (isset($this->userSuppliedData0[$varName])) {
                                    $val = $this->userSuppliedData0[$varName];
                                    $expr = str_replace($mm[0][$i], $val, $expr);
                                }
                            }
                        }
                        $rec[$key] = $expr;
                    }
                } elseif (@$this->userSuppliedData0['_repeat-event'] && preg_match('/%repeatEvent\( (.*?) \)/x', $elemDef->postProcess, $m)) {
                    $this->repeatEventTaskPending = $m[1];
                }
            }

            // handle element types 'radio,checkbox,dropdown':
            if (strpos('radio,checkbox,dropdown', $type) !== false) {
                $value = $rec[$key];
                if (isset($elemDef->splitOutput)) {
                    $splitChoiceElemsInDb = $elemDef->splitOutput;

                } elseif (isset($this->currForm->formElements[$inx]->splitChoiceElemsInDb)) {
                    $splitChoiceElemsInDb = $this->currForm->formElements[$inx]->splitChoiceElemsInDb;
                } else {
                    $splitChoiceElemsInDb = $this->currForm->splitChoiceElemsInDb;
                }
                if ($splitChoiceElemsInDb) {
                    $rec[$key] = [];
                    if (is_array($value)) {
                        $rec[$key][0] = implode(',', $value);
                    } else {
                        $rec[$key][0] = $value;
                    }
                    for ($i = 1; $i < sizeof($elemDef->optionNames); $i++) {
                        $option = $elemDef->optionNames[$i];
                        $label = $elemDef->optionLabels[$i];
                        if (!$label) {
                            continue;
                        } elseif (is_array($value)) {
                            $rec[$key][$option] = (bool)in_array($option, $value);
                        } else {
                            $rec[$key][$option] = ($option === $value);
                        }
                    }
                } else {
                    if (is_array($value)) {
                        $rec[$key] = implode(',', $value);
                    }
                }

            // handle element type 'password':
            } elseif ($type === 'password') {
                if ($rec[$key] && ($rec[$key] !== PASSWORD_PLACEHOLDER)) {
                    $rec[$key] = password_hash($rec[$key], PASSWORD_DEFAULT);
                } else {
                    $rec[$key] = '';
                }

            // toggle switch:
            } elseif ($type === 'toggle') {
                $rec[$key] = ($rec[$key] !== 'false'); // save as boolean

            // skip buttons
            } elseif ($type === 'button') {
                unset($rec[ $key ]);
            }
        }

        foreach ($elemDefs as $key => $elemDef) {
            // drop user-defined elements that have dataKey === false or dataKey starts with '_':
            if (!$elemDef || !@$elemDef->dataKey || ($elemDef->dataKey[0] === '_')) {
                if (isset($rec[ $elemDef->name ])) {
                    unset( $rec[$elemDef->name ]);
                }
                continue;
            }
        }
    } // prepareDataRec



    private function restoreErrorDescr()
    {
        return isset($_SESSION['lizzy']['formErrDescr']) ? $_SESSION['lizzy']['formErrDescr'] : [];
    } // restoreErrorDescr



    private function saveErrorDescr($errDescr)
    {
        $_SESSION['lizzy']['formErrDescr'] = $errDescr;
    } // saveErrorDescr



	public function clearCache()
	{
	    $formInx = @$this->currForm->formInx;
        $pathToPage = $GLOBALS['lizzy']['pathToPage'];
	    if ($formInx) {
            unset($_SESSION['lizzy']['formData'][ $pathToPage ][$formInx]);
            unset($_SESSION['lizzy']['formErrDescr'][$formInx]);
            unset($_SESSION["lizzy"]['forms'][$formInx]);
        } else {
            unset($_SESSION['lizzy']['formData'][ $pathToPage ]);
            unset($_SESSION['lizzy']['formErrDescr']);
            unset($_SESSION["lizzy"]['forms']);
        }
	} // clearCache



    private function initFormJs()
    {
        $this->page->addModules('MOMENT,~sys/js/forms.js');
        $lockRecWhileFormOpen = $this->currForm->lockRecWhileFormOpen ? 'true': 'false';
        $jq = "lzyForms.init( $lockRecWhileFormOpen );";
        $this->page->addJq($jq);
    } // initFormJs



    private function initAntiSpam()
    {
        if ($this->currForm->antiSpam !== true) {   // field for antiSpam explicitly defined:
            $nameFldId = preg_replace('/^ (\#fld_)? (.*?) $/x', "$2", $this->currForm->antiSpam);
            $found = false;
            foreach ($this->currForm->formElements as $rec) {
                if (strpos($rec->elemId, $nameFldId) === 0) {
                    $nameFldId = '#fld_' . $rec->elemId;
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
                <label for="lzy-popup-as-input">{{ lzy-form-override-honeypot-label }}</label>
                <input id="lzy-popup-as-input-{$this->formInx}" type="text" name="lzy-ch-name" />
            </div>
        </div>

EOT;
        $html = str_replace(["\n", '  '], ' ', $html);

        if ($this->currForm->antiSpam) {
            // submit: check honey pot

            $js = <<<EOT

function lzyChContinue( callbackArg ) {
    lzyFormUnsaved = false;
    let val = $( '#lzy-popup-as-input-{$this->formInx}' ).val();
    let origFld = $( '$nameFldId' ).val();
    if (!origFld) {
        origFld = '';
    }
    if (val.trim() === origFld.toUpperCase().trim()) {
        let \$form = $( '#' + callbackArg );
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
        buttons: '{{ Cancel }},{{ Continue }}',
        buttonClass: 'lzy-button lzy-button-cancel, lzy-button lzy-button-submit',
        closeButton: false,
    });
    setTimeout(function() {
        $( '#lzy-popup-as-input-{$this->formInx}' ).focus();
    }, 800);
}

EOT;
            $this->page->addJs($js);
            $jq = <<<EOT
    $('body')
        .on('click', '.lzy-button-submit', function() {
            lzyChContinue('{$this->currForm->formId}');
        })
        .on('keyup', '.lzy-popup-container', function(e) {
            const key = e.which;
            if (key === 13) {
                lzyChContinue('{$this->currForm->formId}');
            }
        });

EOT;
            $this->page->addJq($jq);
            $this->page->addModules('POPUPS');
        }
    } // initAntiSpam



    private function initButtonHandlers()
    {
        if (@$GLOBALS['lizzy']['formsButtonHandlersInitialized']) {
            return;
        }
        $GLOBALS['lizzy']['formsButtonHandlersInitialized'] = true;

        $js = <<<EOT

function noInputPopup() {
    lzyPopup({
        text: '{{ lzy-form-empty-form-warning }}',
        trigger: true,
        buttons: '{{ Continue }}',
        closeButton: false,
    });
}

EOT;
        $this->page->addJs($js);

        $jq = '';
        if ($this->currForm->submitButtonCallback === 'auto') {
            if ($this->currForm->antiSpam) {
                $jq .= <<<'EOT'

$('.lzy-form input[type=submit]').click(function(e) {
    let $form = $(this).closest('.lzy-form');
    if (!$('.lzy-form-check', $form ).val()) {
        let s = '';
        $( 'input,textarea', $form).each(function() {
            let type = $(this).attr('type');
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
        $( 'option', $form).each(function() {
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
        $form[0].submit();
        return;
    }
    e.preventDefault();
    e.stopImmediatePropagation();
    let data = JSON.stringify( $form.serializeArray() );
    // serverLog('suspicious form data: ' + data, 'form-log.txt');
    initAntiSpamPopup();
});

EOT;
            }
        }


        if ($this->currForm->cancelButtonCallback === 'auto') {
            $jq .= <<<'EOT'

$('.lzy-form input[type=reset]').click(function(e) {  // reset: clear all entries
    let $form = $(this).closest('.lzy-form');
    $('.lzy-form-cmd', $form ).val('_reset_');
    lzyFormUnsaved = false;
    $form[0].submit();
});
EOT;
        }

        $jq .= <<<'EOT'
       
$('.lzy-form .lzy-form-pw-toggle').click(function(e) {
    e.preventDefault();
    let $form = $(this).closest('.lzy-form');
    let $pw = $('.lzy-form-password', $form);
    let rcsPath = systemPath + 'rsc/';
    if ($pw.attr('type') === 'text') {
        $pw.attr('type', 'password');
        $('.lzy-form-show-pw-icon', $form).attr('src', rcsPath + 'show.png');
    } else {
        $pw.attr('type', 'text');
        $('.lzy-form-show-pw-icon', $form).attr('src', rcsPath +'hide.png');
    }
});

EOT;

        $jq .= <<<EOT
$('.lzy-textarea-autogrow textarea').on('input', function() {
    this.parentNode.dataset.replicatedValue = this.value;
});
EOT;

        // initiate window-freeze if form is open for too long:
        $jq .= "\nfreezeWindowAfter('" . (DEFAULT_FORMS_TIMEOUT - 10) ."s');\n";

        $this->page->addJq($jq);
    } // initButtonHandlers



    private function activatePreventMultipleSubmit()
    {
        $jq = <<<EOT

jQuery.fn.preventDoubleSubmission = function() {
  $(this).on('submit',function(e){
    let \$form = $(this);
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
        // 1) value
        // 2) default value
        // 3) derived value
        // 4) live value
        $value = $this->_getValue( 'value' );

        $defaultValue = $this->_getValue( 'defaultValue' );
        if ($defaultValue !== null) {
            if ($defaultValue && !(is_string($defaultValue) && ($defaultValue[0] === '='))) {
                $value = $defaultValue;
            }
            $this->currRec->inpAttr .= " data-default-value='$defaultValue'";
        }

        $derivedValue = $this->_getValue( 'derivedValue' );
        if ($derivedValue) {
            if (!(is_string($derivedValue) && ($derivedValue[0] === '='))) {
                $value = $derivedValue;
            }
            $this->currRec->inpAttr .= " data-derived-value='$derivedValue'";
        }

        $liveValue = $this->_getValue( 'liveValue' );
        if ($liveValue) {
            $liveValue = str_replace("'", "´", $liveValue);
            $this->currRec->inpAttr .= " data-live-value='$liveValue'";
        }

        if (@$this->currRec->prefill) {
            $prefill = $this->currRec->prefill;
            $this->currRec->inpAttr .= " data-prefill='$prefill'";
        }

        $value = $this->parseValue($type, $value);
        return $value;
    } // getValueAttr



    private function parseValue($type, $value)
    {
        if (($type !== 'radio') && ($type !== 'checkbox') && ($type !== 'dropdown') && ($type !== 'toggle')) {
            if (@$this->currRec->prefill) {
                $value = $this->currRec->prefill;
            }
        } elseif (@$this->currRec->prefill) {
            $value = $this->currRec->prefill;
        }

        // skip non-strings and acive values (which start with '='):
        if ($value && is_string($value) && ($value[0] !== '=')) {
            // perform some basic format checks:
            if ($type === 'tel') {
                if (preg_match('/\D/', $value)) {
                    $value = preg_replace('/[^\d.\-+()]/', '', $value);
                }
            } elseif ($type === 'number') {
                $value = preg_replace('/[^\d.\-+]/', '', $value);
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
            }
        }

        if ($value && ($type !== 'toggle')) {
            $value = $value ? " value='$value'" : '';
        }
        return $value;
    } // parseActiveValue
    
    
    
    private function _getValue( $which )
    {
        $value = null;
        if (isset($this->currRec->$which)) {
            $value = $this->currRec->$which;
        }
        if ($value !== null) {
            if (is_string($value)) {
                $value = str_replace("'", "´", $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
        }
        return $value;
    } // _getValue



    protected function isHeadAttribute( $attr )
    {
        if (!$attr) {
            return false;
        }
        return (strpos(HEAD_ATTRIBUTES, ",$attr,") !== false);
    } // isHeadAttribute



    protected function isElementAttribute( $attr )
    {
        if (!$attr || !is_string($attr)) {
            return false;
        }
        return (strpos(ELEM_ATTRIBUTES, ",$attr,") !== false);
    } // isElementAttribute



    protected function renderDataTable()
    {
        global $lizzy;

        $out = '';
        $currForm = $this->currForm;
        $continue = true;
        $showData = $currForm->showData? $currForm->showData: ($this->currRec->showData? $this->currRec->showData: true);

        if ($showData !== true) {
            // showData options: false, true, loggedIn, privileged, localhost, {group}
            switch ($showData) {
                case 'logged-in':
                case 'loggedin':
                case 'loggedIn':
                    $continue = (bool)$_SESSION["lizzy"]["user"] || $lizzy["isAdmin"];
                    break;

                case 'privileged':
                    $continue = $this->lzy->config->isPrivileged;
                    break;

                case 'localhost':
                    $continue = $lizzy["localHost"];
                    break;

                default:
                    $continue = $this->lzy->auth->checkGroupMembership($showData);
            }
        }

        if (!$continue) {
            return '';
        }

        $out .= <<<EOT
<div class="lzy-forms-preview">
<h2>{{ lzy-form-data-preview-title }}</h2>

EOT;

        require_once SYSTEM_PATH.'htmltable.class.php';
        $options = [
            'dataSource' => $currForm->file,
            'headers' => true,
        ];
        $tbl = new HtmlTable($this->lzy, $options);
        $out .= $tbl->render();
        $out .= "</div>\n";
        return $out;
    } // renderDataTable



    private function checkSuppliedDataEntries()
    {
        // modify record if requested:
        if (@$this->currForm->recModifyCheck && @$this->userSuppliedData0['_rec-key']) {

            $recKey = $this->userSuppliedData0['_rec-key'];
            $ds = $this->openDB();
            if (isset($this->dataKeyOverride)) {
                $recKey = $this->getOverrideKey( $recKey );
            }
            $rec = $ds->readElement($recKey);
            $checkKey = $this->currForm->recModifyCheck;
            $checkValue = strtolower(trim( @$rec[ $checkKey ] ));
            $suppliedValue = strtolower(trim( @$this->userSuppliedData0[ $checkKey ] ));
            if ($checkValue !== $suppliedValue) {
                $this->page->addPopup('{{ lzy-form-rec-modify-failed }}');
                return false;
            }
        }

        // delete record if requested:
        if (isset($this->userSuppliedData0['_lzy-delete-rec']) ||
            ($this->userSuppliedData0['_lzy-form-cmd'] === 'delete')) {
            $res = $this->deleteDataRecord();
            if (!$res) {
                writeLogStr("Forms: data-rec '{$this->userSuppliedData0['_rec-key']}' deleted", FORM_LOG_FILE);
                $this->page->addMessage('{{ lzy-form-rec-deleted }}');
                unset($_POST['_lzy-delete-rec']);
                unset($_POST['_lzy-form-ref']);
            } else {
                $this->page->addPopup( $res );
                writeLogStr("Forms:checkSuppliedDataEntries(): Delete rec '{$this->userSuppliedData0['_rec-key']}' failed", FORM_LOG_FILE);
            }
            return false;
        }

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
                        $n = strtolower($n);
                        foreach ($userSuppliedData as $k => $v) {
                            $k = strtolower($k);
                            if (($k === $n) && $v) {
                                $found = true;
                                break 2;
                            }
                        }
                    }
                    if (!$found) {
                        $this->errorDescr[$this->formInx][$name] = '{{ lzy-combined-required-value-missing }}';
                    }
                } elseif (!isset($userSuppliedData[$name]) || !$userSuppliedData[$name]) {
                    $this->errorDescr[$this->formInx][$name] = '{{ lzy-required-value-missing }}';
                }
            }
            if ($value) {
                $formEmpty = false;
                if ($type === 'email') {
                    if (!is_legal_email_address($value)) {
                        $this->errorDescr[$this->formInx][$name] = '{{ lzy-invalid-email-address }}';
                    }
                } elseif ($type === 'number') {
                    if (preg_match('/[^\d.-]]/', $value)) {
                        $this->errorDescr[$this->formInx][$name] = '{{ lzy-invalid-number }}';
                    }
                } elseif ($type === 'url') {
                    if (!isValidUrl($value)) {
                        $this->errorDescr[$this->formInx][$name] = '{{ lzy-invalid-url }}';
                    }
                // ToDo:
                //            } else { // 'date','time','datetime','month','range','tel'
                }
            }
        }

        if ($formEmpty) {
            $this->errorDescr[$this->formInx]['_announcement_'] = '{{ lzy-form-empty-rec-received }}';
        }

        $log = str_replace(["\n", '  '], ' ', var_export($this->userSuppliedData0, true));
        if (@$this->errorDescr[$this->formInx]) {
            $log .= "\nError Msg: ".str_replace(["\n", '  '], ' ', var_export($this->errorDescr[$this->formInx], true));
            writeLogStr("Form-Error [{$currForm->formName}]:\n$log\n", FORM_LOG_FILE);

            // skipConfirmation means user doesn't get error feedback within form, so inform by message:
            if ($this->currForm->skipConfirmation) {
                $errMsg = '';
                foreach ($this->errorDescr[$this->formInx] as $key => $value) {
                    $errMsg .= "$value $key<br>";
                }
                $errMsg = substr($errMsg, 0, -4);
                $this->page->addMessage("$errMsg");
                unset($_POST['_lzy-form-ref']);
            }
            return false;
        }
        return true;
    } // checkSuppliedDataEntries



    private function sendConfirmationMail( $rec )
    {
        $isHtml = false;
        if ($this->currForm->confirmationEmail === true) {
            $to = $this->getUserSuppliedValue( 'E-mail', true );
            if (!$to) {
                $to = $this->getUserSuppliedValue('e-mail', true);
            }
        } else {
            $to = $this->getUserSuppliedValue( $this->currForm->confirmationEmail );
        }
        if (!$to) {
            return;
        }

        // add variables for all form values, so they can be used in mail-template:
        foreach ($rec as $key => $value) {
            if (is_array($value)) {
                $value = $value[0];
            }
            $value = $value? $value: '{{ lzy-confirmation-response-element-empty }}';
            $this->trans->addVariable("{$key}-value", $value);
            $this->trans->addVariable("{$key}_value", $value);
        }
        if ($this->currForm->confirmationEmailTemplate === true) {
            $subject = '{{ lzy-confirmation-response-subject }}';
            $message = '{{ lzy-confirmation-response-message }}';

        } else {
            $file = resolvePath($this->currForm->confirmationEmailTemplate, true);
            if (!file_exists($file)) {
                $this->responseToClient = '';
                if ($this->lzy->localHost || $GLOBALS['lizzy']['isLoggedin']) {
                    $this->lzy->page->addPopup("{{ lzy-form-response-email-template-not-found }}:<br>$file");
                }
                return;
            }

            $ext = fileExt($file);
            $tmpl = file_get_contents($file);

            if (stripos($ext, 'md') !== false) {
                $page = new Page();
                $tmpl = $page->extractFrontmatter( $tmpl );
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
                $md = new LizzyMarkdown( $this->lzy );
                $message = $md->compileStr( $tmpl );
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
                $message = $this->page->extractHtmlBody($tmpl);
                $message = translateUmlauteToHtml( ltrim($message) );
                $isHtml = true;

            } else {    // .txt
                if (preg_match('/^subject: (.*?)\n(.*)/ims', $tmpl, $m)) {
                    $subject = $m[1];
                    $message = $m[2];
                } else {
                    $this->responseToClient = 'lzy-reservation-email-template-syntax-error';
                    return;
                }
            }
        }

        $subject = $this->trans->translate($subject);
        $message = $this->trans->translate($message);
        if ($to) {
            $mailfrom = @$this->currForm->mailFrom? $this->currForm->mailFrom: $this->trans->getVariable('webmaster_email');
            $this->lzy->sendMail($to, $subject, $message, $mailfrom, $isHtml);
            $this->responseToClient = '{{ lzy-form-confirmation-email-sent }}';
        }
    } // sendConfirmationMail



    protected function getUserSuppliedValue( $fieldName, $relaxed = false )
    {
        if ($relaxed) {
            foreach ($this->userSuppliedData as $key => $value) {
                if (strpos($key, $fieldName) !== false) {
                    return $value;
                }
            }

        } else {
            foreach ($this->userSuppliedData as $key => $value) {
            if (strpos($key, $fieldName) === 0) {
                    return $value;
                }
            }
        }
        return '';
    } // getUserSuppliedValue



    protected function getFormProp( $propName ) {
	    return $this->currForm->$propName;
    } // getFormProp



    private function updateDbStructure() {
	    if (!$this->exportStructure) {
	        return false;
        }
	    $formElements = $this->currForm->formElements;
	    $dbFile = $this->currForm->file;
	    $dbStructFile = fileExt($dbFile, true) . '_structure.yaml';
	    if (file_exists($dbStructFile)) {
	        return false;
        }

        $structure = [];
        $structure['key'] = 'hash';
	    foreach ($formElements as $key => $rec) {
	        if (!$rec->dataKey || !$rec->name || ($rec->name[0] === '_')) {
	            continue;
            }
            $newRec = [];
            $recKey = $rec->dataKey;
            $newRec['name'] = $rec->dataKey;
            $newRec['type'] = $rec->type;
            $newRec['formLabel'] = $rec->label;
            if ($rec->splitOutput) {
                $newRec['splitOutput'] = true;
            }
            $structure['elements'][$recKey] = $newRec;
        }

	    preparePath($dbStructFile);
        writeToYamlFile($dbStructFile, $structure);
	    return true;
    } // updateDbStructure



    private function openDB()
    {
        if ($this->db) {
            return $this->db;
        }
        if (!$this->currForm) {
            return null;
        }
        $file = $this->currForm->file;

        // url-arg 'ds' may override file -> used by table, reservation etc.:
        //   (if used, 'ds' contains a ticket hash, possibly including a :setX specifier)
        $dataRef = getRequestData('ds');
        if (!$dataRef) {
            // if not found, try form hidden field _lizzy-data-ref:
            $dataRef = @$_POST['_lizzy-data-ref'];
        }
        if ($dataRef) {
            $setId = '';
            if (preg_match('/ ([A-Z0-9]{4,20}) : (.*) /x', $dataRef, $m)) {
                $dataRef = $m[1];
                $setId = $m[2];
            }
            $tck = new Ticketing();
            $ticketRec = $tck->consumeTicket( $dataRef );
            if ($ticketRec) {      // corresponding ticket found
                if ($setId && isset($ticketRec[$setId])) {
                    $ticketRec = $ticketRec[$setId];
                }
                if (isset($ticketRec['_dataSource'])) {
                    $file = PATH_TO_APP_ROOT . $ticketRec['_dataSource'];
                }
                if (isset($ticketRec['_dataKey'])) {
                    $this->dataKeyOverride = $ticketRec['_dataKey'];
                }
            }
        }

        $this->db = new DataStorage2([
            'dataFile' => $file,
            'useRecycleBin' => $this->currForm->useRecycleBin,
            'includeKeys' => true,
            'includeTimestamp' => true,
        ]);
        return $this->db;
    } // openDB



    private function executeEventDuplicating(array $newRec, $ds): void
    {
        $keys = explodeTrim(',', $this->repeatEventTaskPending);
        $every = intval(@$this->userSuppliedData0['_repeat-every']);
        $count = intval(@$this->userSuppliedData0['_repeat-count']);
        $newRec[REC_KEY_ID] = false;
        if ($every && $count) {
            for ($i = 1; $i <= $count; $i++) {
                foreach ($keys as $key) {
                    $t = strtotime($newRec[$key]);
                    $t = strtotime("+$every days", $t);
                    $newRec[$key] = date('Y-m-d H:i', $t);
                }
                $ds->addRecord($newRec, false, true, true, true);
            }
        }
    } // executeEventDuplicating



    private function getOverrideKey( $recKey )
    {
        $dataKeyOverride = $this->dataKeyOverride;
        if (!$recKey) {
            $recKey = createHash();
        } elseif (strpos($recKey, ',') !== false) {
            return $recKey;
        }

        if (strpos($dataKeyOverride, '*') !== false) {
            $recKey = str_replace('*', $recKey, $this->dataKeyOverride);
            $this->dataKeyOverrideHash = true;
        } elseif (strpos($dataKeyOverride, '#') !== false) {
            $recKey = str_replace('#', $recKey, $this->dataKeyOverride);
            $this->dataKeyOverrideHash = false;
        }
        return $recKey;
    } // getOverrideKey



    public function getNext()
    {
        return $this->currForm->next;
    } // getNext



    private function hideForm($formId = false)
    {
        if (!$formId) {
            $formId = $this->formId;
        }
        $this->page->addCss("#$formId { display: none; }");
    } // hideForm



    private function sendMailToWebmaster($subject, $message = '')
    {
        $webmasterEmail = $this->trans->getVariable('webmaster_email');
        $domain = $this->trans->getVariable('site_title');
        sendMail($webmasterEmail, $webmasterEmail, "[$domain] $subject", $message);
    } // sendMailToWebmaster

} // Forms





class FormDescriptor
{
    public $formId = '';
    public $formName = '';
    public $formElements = [];    // fields contained in this form

    public $mailto;        // data entered by users will be sent to this address
    public $process = '';
    public $method = '';
    public $action = '';
    public $class = '';
    public $options = '';
    public $dataKey = '';
    public $bypassedValues = []; // dynamic per user -> treated separately in restoreFormDescr()
    public $prefill = false;
    public $prefillRec = [];
    public $formHash = '';
    public $preventMultipleSubmit = false;
    public $validate = false;
    public $antiSpam = true;
    public $showData = true;
    public $replaceQuotes = true;
    public $translateLabels;
    public $formInx;
    public $file;
    public $formHint;
    public $skipConfirmation;
    public $responseViaSideChannels;
    public $labelWidth;
    public $wrapperClass;
    public $encapsulate, $recModifyCheck, $dynamicFormSupport, $lockRecWhileFormOpen;
    public $formHeader, $formFooter, $next, $avoidDuplicates, $splitChoiceElemsInDb;
    public $labelColons, $confirmationEmail, $confirmationText, $mailTo;
    public $cancelButtonCallback, $confirmationEmailTemplate, $keyType, $useRecycleBin, $mailFrom;
    public $submitButtonCallback;
}




class FormElement
{
    public $type = '';        // init, radio, checkbox, date, month, number, range, text, email, password, textarea, button
    public $label = '';        // some meaningful label used for the form element
    public $labelHtml = '';        // some meaningful label used for the form element
    public $labelInOutput = '';// some meaningful short-form of label used in e-mail and .cvs data-file
    public $name = '';          // name in form and transmitted 'userSuppliedData'
    public $dataKey = '';       // element-key in data-source / file
    public $required = '';    // enforces user input
    public $placeholder = '';// text displayed in empty field, disappears when user enters input field
    public $min = '';
    public $max = '';        // for numerical entries -> defines lower and upper boundry
    public $value = '';        // defines a preset value
    public $class = '';        // class identifier that is added to the surrounding div
    public $inpAttr = '';
    public $timestamp = '';
} // class FormElement
