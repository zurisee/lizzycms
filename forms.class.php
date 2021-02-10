<?php
/*
 *	Lizzy - forms rendering module
*/

define('UPLOAD_SERVER',         '~sys/_upload_server.php');
define('THUMBNAIL_PATH', 	    '_/thumbnails/');
define('DEFAULT_EXPORT_FILE', 	'~page/form-export.csv');
define('FORM_LOG_FILE', 	    LOG_PATH.'form-log.txt');
define('SPAM_LOG_FILE', 	    LOG_PATH.'spam-log.txt');

define('HEAD_ATTRIBUTES', 	    ',label,id,translateLabels,class,method,action,mailto,mailfrom,'.
    'legend,customResponseEvaluation,next,file,confirmationText,formDataCaching,'.
    'encapsulate,formTimeout,avoidDuplicates,export,exportKey,confirmationEmail,'.
    'confirmationEmailTemplate,prefill,preventMultipleSubmit,replaceQuotes,antiSpam,'.
    'validate,showData,showDataMinRows,options,encapsulate,disableCaching,'.
    'translateLabel,formName,formHeader,formHint,formFooter,');

define('ELEM_ATTRIBUTES', 	    ',label,type,id,class,wrapperClass,name,required,value,'.
	'options,optionLabels,layout,info,comment,translateLabel,'.
	'labelInOutput,splitOutput,placeholder,autocomplete,'.
	'description,pattern,min,max,path,target,');

define('UNARY_ELEM_ATTRIBUTES', ',required,translateLabel,splitOutput,autocomplete,');

define('SUPPORTED_TYPES', 	    ',text,password,email,textarea,radio,checkbox,'.
    'dropdown,button,url,date,time,datetime,month,number,range,tel,file,'.
    'fieldset,fieldset-end,reveal,hidden,literal,bypassed,');

 // types to ignore in output:
define('PSEUDO_TYPES', ',form-head,form-tail,reveal,literal,fieldset,fieldset-end,');


mb_internal_encoding("utf-8");


$GLOBALS["globalParams"]['lzyFormsCount'] = 0;
$GLOBALS["globalParams"]['formDataCachingInitialized'] = false;
$GLOBALS["globalParams"]['formTooltipsInitialized'] = false;

class Forms
{
	private $page;
    protected $inx;
	private $currForm = null;		// shortcut to $formDescr[ $currFormIndex ]
	private $currRec = null;		// shortcut to $currForm->formElements[ $currRecIndex ]
    private $submitButtonRendered = false;
    protected $errorDescr = [];
    protected $responseToClient = false;
    private $revealJsAdded = false;
    protected $skipRenderingForm = false;
    protected $formId = false;
    public  $file = false;
    private  $db = false;
    private  $dbExport = false;
    private  $exportFileImported = false;


	public function __construct($lzy, $userDataEval = null)
	{
	    $this->lzy = $lzy;
		$this->trans = $lzy->trans;
		$this->page = $lzy->page;
		$this->inx = -1;    // = elemInx
        $GLOBALS["globalParams"]['lzyFormsCount']++;
		$this->formInx = $GLOBALS["globalParams"]['lzyFormsCount'];
        $this->currForm = new FormDescriptor; // object as will be saved in DB
        $this->infoInitialized = &$GLOBALS["globalParams"]['formTooltipsInitialized'];

        $this->tck = new Ticketing([
            'defaultType' => 'lzy-form',
            'defaultMaxConsumptionCount' => 100,
            'defaultValidityPeriod' => 259200, // 3 days
        ]);

        // $userDataEval===false suppresses user-data-evaluation (because client does it on its own)
        if ($userDataEval !== false) {
            if (isset($_POST['_lizzy-form'])) {    // we received data:
                $this->evaluateUserSuppliedData();
                unset( $_POST['_lizzy-form'] );
            }
            // $userDataEval===true means client just wants to eval data, not instantiate an object:
            if ($userDataEval === true) {
                $GLOBALS["globalParams"]['lzyFormsCount']--;
            }
        }
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
                $buttons["label"] = rtrim($buttons["label"], ',');
                $buttons["value"] = rtrim($buttons["value"], ',');
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

        $currForm->formName = $label;
        $currForm->translateLabels = (isset($args['translateLabels'])) ? $args['translateLabels'] : false;

        $currForm->class = (isset($args['class'])) ? $args['class'] : 'lzy-form';
        $currForm->wrapperClass = (isset($args['wrapperClass'])) ? $args['wrapperClass'] : '';
        $currForm->method = (isset($args['method'])) ? $args['method'] : 'post';
        $currForm->action = (isset($args['action'])) ? $args['action'] : '';
        $currForm->mailTo = (isset($args['mailto'])) ? $args['mailto'] : ((isset($args['mailTo'])) ? $args['mailTo'] : '');
        $currForm->mailFrom = (isset($args['mailfrom'])) ? $args['mailfrom'] : ((isset($args['mailFrom'])) ? $args['mailFrom'] : '');
        $currForm->formHeader = (isset($args['formHeader'])) ? $args['formHeader'] : ''; // synonyme for 'legend'
        $currForm->formHeader = (isset($args['legend'])) ? $args['legend'] : $currForm->formHeader;
        $currForm->formHint = (isset($args['formHint'])) ? $args['formHint'] : '';
        $currForm->formFooter = (isset($args['formFooter'])) ? $args['formFooter'] : '';
        $currForm->customResponseEvaluation = (isset($args['customResponseEvaluation'])) ? $args['customResponseEvaluation'] : '';
        $currForm->next = (isset($args['next'])) ? $args['next'] : './';
        $currForm->file = (isset($args['file'])) ? $args['file'] : '';
        $currForm->useRecycleBin = (isset($args['useRecycleBin'])) ? $args['useRecycleBin'] : false;
        $currForm->confirmationText = (isset($args['confirmationText'])) ? $args['confirmationText'] : '{{ lzy-form-data-received-ok }}';
        $currForm->formDataCaching = (isset($args['formDataCaching'])) ? $args['formDataCaching'] : true;
        $currForm->encapsulate = (isset($args['encapsulated'])) ? $args['encapsulated'] : true;
        $currForm->encapsulate = (isset($args['encapsulate'])) ? $args['encapsulate'] : $currForm->encapsulate;
        $currForm->formTimeout = (isset($args['formTimeout'])) ? $args['formTimeout'] : false;
        $currForm->avoidDuplicates = (isset($args['avoidDuplicates'])) ? $args['avoidDuplicates'] : true;
        $currForm->export = (isset($args['export'])) ? $args['export'] : false;
        $currForm->exportedDataIsMaster = (isset($args['exportedDataIsMaster'])) ? $args['exportedDataIsMaster'] : false;
        $currForm->exportMetaElements = (isset($args['exportMetaElements'])) ? $args['exportMetaElements'] : false;
        $currForm->submitButtonCallback = (isset($args['submitButtonCallback'])) ? $args['submitButtonCallback'] : 'auto';
        $currForm->cancelButtonCallback = (isset($args['cancelButtonCallback'])) ? $args['cancelButtonCallback'] : 'auto';
        $currForm->confirmationEmail = (isset($args['confirmationEmail'])) ? $args['confirmationEmail'] : false;
        $currForm->confirmationEmailTemplate = (isset($args['confirmationEmailTemplate'])) ? $args['confirmationEmailTemplate'] : false;
        $currForm->labelColons = (isset($args['labelColons'])) ? $args['labelColons'] : true;
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
        $GLOBALS["globalParams"]['preventMultipleSubmit'] = $currForm->preventMultipleSubmit;

        $currForm->replaceQuotes = (isset($args['replaceQuotes'])) ? $args['replaceQuotes'] : true;
        $currForm->antiSpam = (isset($args['antiSpam'])) ? $args['antiSpam'] : false;
        if ($currForm->antiSpam && $this->lzy->localCall && !@$_SESSION["lizzy"]["debug"]) {    // disable antiSpam on localhost for convenient testing of forms
            $currForm->antiSpam = false;
            $this->page->addDebugMsg('"antiSpam" disabled on localhost');
        }

        $currForm->validate = (isset($args['validate'])) ? $args['validate'] : false;
        $currForm->showData = (isset($args['showData'])) ? $args['showData'] : false;
        if (($currForm->showData) && !$currForm->export) {
            $currForm->export = '~page/' . $this->formId . '_export.csv';
        }
        if ($currForm->export) {
            $currForm->export = resolvePath($currForm->export, true);
        }

        $currForm->showDataMinRows = (isset($args['showDataMinRows'])) ? $args['showDataMinRows'] : false;

        // options or option:
        $currForm->options = isset($args['options']) ? $args['options'] : (isset($args['option']) ? $args['option'] : '');
        $currForm->options = str_replace('-', '', $currForm->options);

        $currForm->ticketPayload = (isset($args['ticketPayload'])) ? $args['ticketPayload'] : null;
        $currForm->ticketHash = (isset($args['ticketHash'])) ? $args['ticketHash'] : false;

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
            $name = translateToIdentifier($args['name'], false, true, false);
        } else {
            $name = translateToIdentifier($label);
        }

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
        $rec->dataKey = @$args['dataKey']? $args['dataKey']: $name;

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
                if (is_string($value) && strpbrk($value, ',|')) {
                    $rec->prefill = explodeTrim( "|$value");
                } else {
                    $rec->prefill = $value;
                }
            }
        }
        if (@$this->userSuppliedData[$name]) {
            $rec->prefill = $this->userSuppliedData[$name];
        }

        $rec->target = @$args['target']? $args['target']: '';
        if (strpos('radio,checkbox,dropdown', $type) !== false) {
            $rec->target = @$args['reveal']? $args['reveal']: $rec->target;
        }

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
        $rec->autoGrow = (isset($args['autoGrow'])) ? $args['autoGrow'] : true;
        $rec->fieldWrapperAttr = (isset($args['fieldWrapperAttr'])) ? ' '.$args['fieldWrapperAttr'] : '';
        $cutomImpAttr = (isset($args['inpAttr'])) ? ' '.$args['inpAttr'] : '';

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
        $elem = '';
        switch ($type) {
            case 'form-head':
                return $this->renderFormHead();
            
            case 'text':
            case 'string':
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

            case 'literal':
                $elem = $this->renderLiteral();
                break;

            //case 'render-data':
            //    $elem = $this->renderData();
            //    break;

            case 'bypassed':
                $elem = '';
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
            $class = "$wrapperClass lzy-form-field-type-$type";
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
        if (($this->currRec->type !== 'hidden') &&
                ($this->currRec->type !== 'bypassed') &&
                ($this->currRec->type !== 'literal')) {
            $comment = '';
            if ($this->currRec->comment) {
                $comment = "\t<span class='lzy-form-elem-comment'><span>{$this->currRec->comment}</span>\n\t\t</span>";
            }
		    $out = "\t\t<div $class{$this->currRec->fieldWrapperAttr}>$error\n$elem\t\t$comment</div><!-- /field-wrapper -->\n\n";
        } elseif ($this->currRec->type !== 'bypassed') {
            $out = "\t\t$elem";
        }

        // add comment regarding required fields:
        if ($this->submitButtonRendered &&
                (stripos($this->currForm->options, 'norequiredcomment') === false)) {
            $this->submitButtonRendered = false;
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
        if ($this->skipRenderingForm) {
            return "\t<div class='lzy-form-hide-when-completed'>\n";
        }
        $formId = $this->formId;
        $currForm = $this->currForm;

        $this->initButtonHandlers();

        $this->userSuppliedData = $this->getUserSuppliedDataFromCache($formId);
        $currForm->creationTime = time();

        if (!$currForm->ticketHash || !$this->tck->ticketExists($currForm->ticketHash)) {
            $this->formHash = $this->tck->createTicket($currForm->ticketPayload, false, null, false, $currForm->ticketHash);
        } else {
            $this->formHash = $currForm->ticketHash; // if ticket provided by caller
        }

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

        // handle general error feedback:
        if (@$this->errorDescr[$this->currForm->formId]['_announcement_']) {
            $msg = $this->errorDescr[$this->currForm->formId]['_announcement_'];
            $this->errorDescr[$this->currForm->formId]['_announcement_'] = false;
            $out .= "\t<div class='$announcementClass'>$msg</div>\n";

        } elseif (@$this->errorDescr[ 'generic' ]['_announcement_']) {
            $msg = $this->errorDescr[$this->currForm->formId]['_announcement_'];
            $out .= "\t<div class='$announcementClass'>$msg</div>\n";
            $this->errorDescr[ 'generic' ]['_announcement_'] = false;
        }

        $out .= "\t  <form$id$_class$_method$_action$novalidate>\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form-id' value='{$this->formInx}' />\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form-label' value='{$currForm->formName}' />\n";
		$out .= "\t\t<input type='hidden' name='_lizzy-form' value='{$this->formHash}:form{$this->formInx}' class='lzy-form-hash' />\n";
		$out .= "\t\t<input type='hidden' class='lzy-form-cmd' name='_lizzy-form-cmd' value='{$currForm->next}' />\n";

		if ($currForm->antiSpam) {
            $out .= "\t\t<div class='fld-ch' aria-hidden='true'>\n";
            $out .= "\t\t\t<label for='fld_ch{$this->formInx}{$this->inx}'>Name:</label><input id='fld_ch{$this->formInx}{$this->inx}' type='text' class='lzy-form-check' name='_lizzy-form-name' value=''$honeyPotRequired />\n";
            $out .= "\t\t</div>\n";
        }
		return $out;
	} // renderFormHead




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




    private function renderPassword()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $input = "<input type='password' class='lzy-form-password' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr} aria-invalid='false' aria-describedby='password-hint'$cls />\n";
        $id = "lzy-form-showPassword-{$this->formInx}-{$this->inx}";
        $hint = <<<EOT
            <label class='lzy-form-pw-toggle' for="$id"><input type="checkbox" id="$id" class="lzy-form-showPassword"><img src="~sys/rsc/show.png" class="lzy-form-show-pw-icon" alt="{{ show password }}" title="{{ show password }}" /></label>

EOT;
        $out = $this->getLabel();
        $out .= $input . $hint;
        return $out;
    } // renderPassword




    private function renderTextarea()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = @$this->currRec->prefill;
        $out = $this->getLabel();
        if ($this->currRec->autoGrow) {
            $out .= "\n\t\t\t<div class='lzy-textarea-autogrow'>\n\t\t\t\t<textarea id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls onInput='this.parentNode.dataset.replicatedValue = this.value'>$value</textarea>\n\t\t\t</div>\n";
        } else {
            $out .= "<textarea id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls>$value</textarea>\n";
        }
        return $out;
    } // renderTextarea




    private function renderEMail()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('email');
        $out = $this->getLabel();
        $out .= "<input type='email' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
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
        $label = $this->getLabel(false, false);

        $target = $this->currRec->target;
        if ($target) {
            $this->addRevealJs();
            $target = explodeTrim(',|', "|$target||||||||||||||||");
        }

        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-radio-label'><legend class='lzy-legend'>{$label}</legend>\n\t\t\t  <div class='lzy-fieldset-body'>\n";
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

            $checked = ($checkedElem && @$checkedElem[$name]) || $preselectedValue ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-radio-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='radio' name='$groupName' value='$name'$checked$cls$attr /><label for='$id'>$optionLabel</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderRadio




    private function renderCheckbox()
    {
        $rec = $this->currRec;
        $class = $this->currRec->class;
        $presetValues = isset($this->currRec->prefill)? $this->currRec->prefill: false;
        $groupName = translateToIdentifier($rec->name, false, true, false);
        $label = $this->getLabel(false, false);
        $wrapperId = @$rec->wrapperId ? " id='$rec->wrapperId'": '';

        $target = $this->currRec->target;
        if ($target) {
            $rec->wrapperClass .= 'lzy-reveal-controller';
            $this->addRevealJs();
            $target = explodeTrim(',|', "|$target||||||||||||||||");
        }

        $out = "\t\t\t<fieldset$wrapperId class='lzy-form-label lzy-form-checkbox-label'><legend class='lzy-legend'>$label</legend>\n\t\t\t  <div class='lzy-fieldset-body'>\n";

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
            if (@$target[$i]) {
                $cls = " class='$class lzy-reveal-controller-elem'";
                $attr = " data-reveal-target='{$target[$i]}'";
            } else {
                $cls = $class ? " class='$class'" : '';
            }

            $checked = (($presetValues !== false) && @$presetValues[$name]) || $preselectedValue ? ' checked' : '';
            $out .= "\t\t\t<div class='$id lzy-form-checkbox-elem lzy-form-choice-elem'>\n";
            $out .= "\t\t\t\t<input id='$id' type='checkbox' name='${groupName}[]' value='$name'$checked$cls$attr /><label for='$id'>$optionLabel</label>\n";
            $out .= "\t\t\t</div>\n";
        }
        $out .= "\t\t\t  </div><!--/lzy-fieldset-body -->\n\t\t\t</fieldset>\n";
        return $out;
    } // renderCheckbox




    private function renderDropdown()
    {
        $rec = $this->currRec;
        $class = $this->currRec->class;


        $target = $this->currRec->target;
        if ($target) {
            $this->addRevealJs();
            $target = explodeTrim(',|', "|$target||||||||||||||||");
            $class .= ' lzy-reveal-controller-elem';
        }
        $cls = $class? " class='$class'": '';

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
            $attr = '';
            if (@$target[$i]) {
                $attr = " data-reveal-target='{$target[$i]}'";
            }

            $out .= "\t\t\t\t<option value='$val'$selected$attr>$optionLabel</option>\n";
        }
        $out .= "\t\t\t</select>\n";

        return $out;
    } // renderDropdown




    private function renderUrl()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('url');
        $out = $this->getLabel();
        $out .= "<input type='url' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderUrl




    private function renderDate()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('date');
        $out = $this->getLabel();
        $out .= "<input type='date' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderDate




    private function renderTime()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('time');
        $out = $this->getLabel();
        $out .= "<input type='time' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderTime




    private function renderDateTime()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('datetime');
        $out = $this->getLabel();
        $out .= "<input type='datetime-local' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderDateTime




    private function renderMonth()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('month');
        $out = $this->getLabel();
        $out .= "<input type='month' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderMonth




    private function renderNumber()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        $out .= "<input type='number' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderNumber




    private function renderRange()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('number');
        $out = $this->getLabel();
        $out .= "<input type='range' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
        return $out;
    } // renderRange




    private function renderTel()
    {
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr('tel');
        $out = $this->getLabel();
        $out .= "<input type='tel' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
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
    } // renderTel




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
        $id = "lzy-upload-elem-{$this->formInx}-$inx";
		$server = isset($this->args['server']) ? $this->args['server'] : UPLOAD_SERVER;
		$multiple = $this->currRec->multiple ? 'multiple' : '';

        $targetPath = fixPath($this->currRec->uploadPath);
        $targetPath = makePathDefaultToPage($targetPath);
        $targetPathHttp = $targetPath;
        $targetFilePath = resolvePath($targetPath);

        $rec = [
            'uploadPath' => $targetFilePath,
            'pagePath' => $GLOBALS['globalParams']['pageFolder'], //??? -> rename subsequently
            'pathToPage' => $GLOBALS['globalParams']['pathToPage'],
            'appRootUrl' => $GLOBALS['globalParams']['absAppRootUrl'],
            'user'      => $_SESSION["lizzy"]["user"],
        ];
        $tick = new Ticketing(['defaultType' => 'lzy-upload']);
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




    private function renderHidden()
    {
        $name = " name='{$this->currRec->name}'";
        $value = " value='{$this->currRec->value}'";
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';

        $out = "<input type='hidden' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'$cls$name$value />\n";
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
            $md = $this->currRec->md;
            $out .= compileMarkdownStr( $md );
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
        $out .= "\t\t\t\t<input id='$id' class='lzy-reveal-controller-elem lzy-reveal-icon' type='checkbox' data-reveal-target='$target' /><label for='$id'>$label</label>\n";

        $out = "\t\t\t<div class='lzy-reveal-controller'>$out</div>\n";

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

				} else { // custome button
					$out .= "$indent<button id='$id' $class>$label</button>\n";
				}
			}
		}
        return $out;
    } //renderButtons





	private function renderFormTail()
    {
        $formId = $this->currForm->formId;
        if (!$this->skipRenderingForm) {
            if ($this->currForm->antiSpam) {
                $this->initAntiSpam();
            }
            $out = "\t  </form>\n";

            if ($this->currForm->formFooter) {
                $out .= "\t  <div class='lzy-form-footer'>{$this->currForm->formFooter}</div>\n";
            }
        } else {
            $out = "\t</div><!-- /lzy-form-hide-when-completed -->\n";
            $this->page->addJq("$('.lzy-form-hide-when-completed').css('display', 'none');");
        }

        // save form data to DB:
        $this->saveFormDescr();

        // append possible text from user-data evaluation:
        $msgToClient = '';

        // check _announcement_ and responseToClient, inject msg if present:
        if (@$this->errorDescr[$formId]['_announcement_']) { // is errMsg, takes precedence over responseToClient
            $msgToClient = @$this->errorDescr[$formId]['_announcement_'];
            $this->errorDescr[$formId]['_announcement_'] = false;

        } elseif (@$this->errorDescr[ 'generic' ]['_announcement_']) { // is errMsg, takes precedence over responseToClient
            $msgToClient = @$this->errorDescr[ 'generic' ]['_announcement_'];
            $this->errorDescr[ 'generic' ]['_announcement_'] = false;

        } elseif (@$this->responseToClient) {
            $msgToClient = $this->responseToClient;
        }
        if ($msgToClient) {
            // append 'continue...' if form was omitted:
            if ($this->skipRenderingForm) {
                $next = @$this->currForm->next ? $this->currForm->next : './';
                $msgToClient .= "<div class='lzy-form-continue'><a href='{$next}'>{{ lzy-form-continue }}</a></div>\n";
            }
            $out .= "\t<div class='lzy-form-response'>$msgToClient</div>\n";
        } else {
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

        // Special case: exported file is master -> import first if changed:
        if ($this->currForm->exportedDataIsMaster) {
            $this->syncBackExportedData();
        }


        // refresh export if necessary:
        if ($this->currForm->export) {
            $this->export();
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
        $label = $this->currRec->labelHtml? $this->currRec->labelHtml: $this->currRec->label;

        $hasColon = (strpos($label, ':') !== false);
        $label = trim(str_replace([':', '*'], '', $label));
        if ($label && ($label[0] === '-')) {
            $label = $this->trans->translateVariable(substr($label,1), true);
        } elseif ($this->currRec->translateLabel) {
            $label = $this->trans->translateVariable($label, true);
        }
        if ($this->currForm->labelColons || $hasColon) {
            $label .= ':';
        }
        if ($requiredMarker) {
            $label .= ' '.$requiredMarker;
        }

        $infoIcon = $this->renderInfoIcon();

        if ($wrapOutput) {
            return "\t\t\t<label for='$id'>$label$infoIcon\n\t\t\t</label>";
        } else {
            return "$label$infoIcon";
        }
    } // getLabel




    private function renderInfoIcon()
    {
        if (!$this->currRec->info) {
            return '';
        }

        $elemInx = "{$this->formInx}-{$this->currRec->elemInx}";

        $infoIconText = <<<EOT

              <span  class="lzy-invisible">
                <span id="lzy-formelem-info-text-$elemInx" class="lzy-formelem-info-text lzy-formelem-info-text-$elemInx">{$this->currRec->info}</span>
              </span>
            
EOT;

        $icon = '<span class="lzy-icon-info"></span>';
        $infoIcon = <<<EOT

            <button class="lzy-formelem-show-info" aria-label="{{ lzy-formelem-info-title }}" data-tooltip-content="#lzy-formelem-info-text-$elemInx">$icon$infoIconText</button>

EOT;
        if (@$infoIconText[0] === '-') {
            $infoIconText = $this->trans->translateVariable( substr($infoIconText,1) );
        }
        return "$infoIcon";
    } // renderInfoIcon




    private function classAttr($class = '')
    {
        $out = " class='".trim($class). "'";
        return trim($out);
    } // classAttr
    



	private function saveFormDescr()
	{
	    $form = $this->currForm;
	    $form->bypassedValues = @$this->bypassedValues;
	    if (isset( $form->prefillRec["dataKey"] )) {
            $form->dataKey = $form->prefillRec["dataKey"];
        }
        $formObj = base64_encode( serialize( $form) );
        $formDescr = [];
        $i = 0;
        foreach ($form->formElements as $elem) {
            if ($elem->name && ($elem->name[0] === '_')) {
                continue;
            }
            $formDescr[$i]['label'] = $elem->labelInOutput;
            $formDescr[$i]['name'] = $elem->name;
            $formDescr[$i++]['type'] = $elem->type;
        }
        if (!$this->formHash) {
            $this->errorDescr['generic']['_announcement_'] = '{{ lzy-form-error-formhash-lost }}';
            return;
        }
        $dataKeys = [];
        foreach ($form->formElements as $element) {
            if ($element->type !== 'button') {
                $dataKeys[$element->dataKey] = $element->labelInOutput;
            }
        }
        $this->tck->updateTicket( $this->formHash, [
            "form$this->formInx" => [
                'form'          => $formObj,
                'formDescr'     => $formDescr,
                'dataSrc'       => $this->currForm->file,
                'dataKeys'      => $dataKeys,
                ]
        ] );
	} // saveFormDescr




    public function restoreFormDescr($formHash = false, $formInx = false)
	{
	    if (!$formHash) {
	        if (!$this->formHash) {
                return null;
            }
	        $formHash = $this->formHash;
        }
        $rec = $this->tck->consumeTicket($formHash);
        $formInx = $formInx? $formInx : $this->formInx;
	    $fInx = "form$formInx";
        if (isset($rec[$fInx])) {
            return unserialize(base64_decode($rec[$fInx]['form']));
        } else {
            return null;
        }
	} // restoreFormDescr




	private function cacheUserSuppliedData($formId, $userSuppliedData)
	{
        $pathToPage = $GLOBALS["globalParams"]["pathToPage"];
        $_SESSION['lizzy']['formData'][ $pathToPage ][$formId] = serialize($userSuppliedData);
	} // cacheUserSuppliedData




	private function getUserSuppliedDataFromCache($formId)
	{
        $pathToPage = $GLOBALS["globalParams"]["pathToPage"];
		return (isset($_SESSION['lizzy']['formData'][ $pathToPage ][$formId])) ?
            unserialize($_SESSION['lizzy']['formData'][ $pathToPage ][$formId]) : null;
	} // getUserSuppliedDataFromCache




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
        $this->formId = $formId = $userSuppliedData['_lizzy-form-id'];

        $formHash = $this->formHash = $userSuppliedData['_lizzy-form'];
        $formHash = preg_replace('/:.*/', '', $formHash);
        $this->currForm = $this->restoreFormDescr( $formHash, $formId );
        $currForm = $this->currForm;

        // ticket timed out or formId not matching:
        if (($currForm === null) || ($formId !== $this->formId)) {
            $this->clearCache();
            return false;
        }

        // anti-spam check:
        if ($this->checkHoneyPot()) {
            $this->clearCache();
            return false;
        }

        $cmd = @$userSuppliedData['_lizzy-form-cmd'];
        if ($cmd === '_ignore_') {     // _ignore_
            $this->cacheUserSuppliedData($formId, $userSuppliedData);
            return;

        } elseif ($cmd === '_reset_') { // _reset_
            $this->clearCache();
            reloadAgent();

        } elseif ($cmd === '_cache_') { // _cache_
            $this->prepareUserSuppliedData(); // handles radio and checkboxes
            $this->cacheUserSuppliedData($formId, $userSuppliedData);
            exit;

        } elseif ($cmd === '_log_') {   // _log_
            $out = @$userSuppliedData['_lizzy-form-log'];
            writeLogStr($out, SPAM_LOG_FILE);
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
            $this->sendConfirmationMail( $userSuppliedData );
        }

        if ($noError && ($this->currForm->confirmationText !== false)) {
            if (!$msgToClient) {
                $msgToClient = $this->currForm->confirmationText;
            }
            $this->responseToClient = $msgToClient . '<br>' . $this->responseToClient;
            $this->skipRenderingForm = true;
        }
    } // evaluateUserSuppliedData




	private function saveAndWrapUp($msgToOwner)
	{
	    if (isset($this->userSuppliedData0['_delete'])) {
            writeLogStr("Delete data: [$this->recKey]", FORM_LOG_FILE);
            $this->deleteDataRecord();

        } elseif (!$this->saveUserSuppliedDataToDB()) {
            return false;
        }

        $this->clearCache();

        if ($msgToOwner && $this->currForm->mailTo) {
            $this->lzy->sendMail($this->currForm->mailTo, $msgToOwner['subject'], $msgToOwner['body'], $this->currForm->mailFrom);
        }
        return true;
    } // saveAndWrapUp




	private function assembleResponses()
	{
	    // returns: [$msgToClient, $msgToOwner]
        $currForm = $this->currForm;
        $msgToOwner = "{$currForm->formName}\n===================\n\n";

		foreach ($currForm->formElements as $element) {
		    if ((strpos(PSEUDO_TYPES, $element->type) !== false) || ($element->name[0] === '_')) {
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
        // check honey pot field (unless on local host):
        if ($this->userSuppliedData["_lizzy-form-name"] !== '') {
            $out = var_export($this->userSuppliedData, true);
            $out = str_replace("\n", ' ', $out);
            $out .= "\n[{$_SERVER['REMOTE_ADDR']}] {$_SERVER['HTTP_USER_AGENT']}\n";
            $logState = $GLOBALS["globalParams"]["errorLoggingEnabled"];
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = true;
            writeLog($out, SPAM_LOG_FILE);
            $GLOBALS["globalParams"]["errorLoggingEnabled"] = $logState;
            return true;
        }
        return false;
    } // checkHoneyPot




	private function saveUserSuppliedDataToDB()
	{
        $currForm = $this->currForm;
        $userSuppliedData = $this->userSuppliedData;

        $ds = $this->openDB();

        // check meta-fields '_timestamp' and '_key', add if necessary:
        $this->updateDbStructure();

        $struc = $ds->getStructure();

        $isNewRec = false;
        $oldRec = false;
        $origKey = $this->recKey;
        if (@$struc['key'][0] === '=') {
            $useAsKey = substr($struc['key'], 1);
            if (isset($userSuppliedData[ $useAsKey ])) {
                $recKey = $userSuppliedData[ $useAsKey ];
            }
            if ($recKey !== $origKey) {
                $oldRec = $ds->readRecord($origKey);
                $ds->deleteRecord( $origKey );
                $isNewRec = true;
            }
        } else {
            $recKey = $this->recKey;
        }

        if (!$recKey || ($recKey === 'new-rec') || ($recKey === 'unknown')) {
            $isNewRec = true;
            writeLogStr("New data: [{$currForm->formName}:$recKey] ".json_encode($userSuppliedData), FORM_LOG_FILE);
        } else {
            writeLogStr("Data modified: [{$currForm->formName}:$recKey] ".json_encode($userSuppliedData), FORM_LOG_FILE);
        }

        // check whether data already present in DB, if not disabled:
        if ($currForm->avoidDuplicates) {
            $doubletFound = false;
            $data = $ds->read();
            // loop over records:
            foreach ($data as $dataRecKey => $rec) {
                // generally ignore all data keys starting with '_':
                if (@$dataRecKey[0] === '_') {
                    continue;
                }

                // loop over fields in rec, look for differences:
                $identical = true;
                foreach ($rec as $dbFldKey => $value) {
                    // ignore all meta attributes:
                    if (@$dbFldKey[0] === '_') {
                        continue;
                    }
                    $usrDataFldName = false;
                    foreach ($currForm->formElements as $i => $formDescr) {
                        if ($formDescr->dataKey === $dbFldKey) {
                            $usrDataFldName = $formDescr->name;
                            break;
                        }
                    }
                    if (!$usrDataFldName) {
                        die("Error in saveUserSuppliedDataToDB(): inconsistancy in rec structure");
                    }
                    if (is_array($userSuppliedData[$usrDataFldName])) {
                        $v1 = @strtolower(str_replace(' ', '', $userSuppliedData[$usrDataFldName][0]));
                    } else {
                        $v1 = @strtolower(str_replace(' ', '', $userSuppliedData[$usrDataFldName]));
                    }
                    if (is_array($rec[$dbFldKey])) {
                        $v2 = @strtolower(str_replace(' ', '', $rec[$dbFldKey][0]));
                    } else {
                        $v2 = @strtolower(str_replace(' ', '', $rec[$dbFldKey]));
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
                $this->errorDescr[$this->formId]['_announcement_'] = '{{ lzy-form-duplicate-data }}';
                $this->skipRenderingForm = true;
                writeLogStr("Unchanged data: ignored [{$currForm->formName}:$this->recKey] ".json_encode($userSuppliedData), FORM_LOG_FILE);
                return false;
            }
        }

        // prepare user supplied data for DB:
        $newRec = [];
        foreach ($currForm->formElements as $i => $rec) {
            $usrDataFldName = $rec->name;
            $elementKey = $rec->dataKey;
            if (($usrDataFldName[0] === '_') || !isset($userSuppliedData[$usrDataFldName])) {
                continue;
            }

            if (!$isNewRec && ($rec->type === 'password')) {
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

        // add new record:
        $newRec[ TIMESTAMP_KEY_ID ] = date('Y-m-d H:i:s');
        $newRec[ REC_KEY_ID ] = $recKey;
        if ($isNewRec) {
            $ds->addRecord($newRec, $recKey);

        } else {
            $ds->writeRecord($recKey, $newRec);
        }
        return true;
	} // saveUserSuppliedDataToDB




    private function deleteDataRecord()
    {
        if (!@$this->userSuppliedData0['_rec-key']) {
            return false;
        }
        $recKey = $this->userSuppliedData0['_rec-key'];
        $ds = $this->openDB();
        $res = $ds->deleteRecord($recKey);
        return $res;
    } // deleteDataRecord




    private function prepareUserSuppliedData()
    {
        // add missing elements, remove meta-elements, convert radio&checkboxes to internal format:
        $currForm = $this->currForm;

        // determine recKey:
        $this->recKey = @$this->userSuppliedData0['_rec-key'];
        if ((@$this->currForm->dataKey !== null) && (@$this->currForm->dataKey !== '')) {
            $this->recKey = $currForm->dataKey;
        } elseif ($this->recKey === null) {
            $this->recKey = 'unknown';
        }

        $userSuppliedData = &$this->userSuppliedData;

        $userSuppliedData = $this->prepareDataRec($currForm->formElements, $userSuppliedData);
    } // prepareUserSuppliedData




    private function prepareDataRec($elemDefs, $rec)
    {
        foreach ($rec as $key => $label) {
            if ($key[0] === '_') {
                unset($rec[ $key ]);
            }
        }

        foreach ($elemDefs as $key => $elemDef) {
            if (!$elemDef || (@$key[0] === '_')) {
                continue;
            }
            // skip system fields starting with _:
            $key = $elemDef->name;
            if ($key[0] === '_') {
                continue;
            }
            $type = $elemDef->type;
            if (strpos(PSEUDO_TYPES, $type) !== false) {
                continue;
            }
            if (!isset($rec[ $key ])) {
                if ($type === 'bypassed') {
                    $rec[$key] = $elemDef->value;
                } else {
                    $rec[$key] = '';
                }
            }

            if (strpos('radio,checkbox,dropdown', $type) !== false) {
                $value = $rec[$key];
                $rec[$key] = [];
                if (is_array($value)) {
                    $rec[$key][0] = implode(',', $value);
                } else {
                    $rec[$key][0] = $value;
                }
                for ($i=1; $i<sizeof($elemDef->optionNames); $i++) {
                    $option = $elemDef->optionNames[$i];
                    $label = $elemDef->optionLabels[$i];
                    if (!$label) {
                        continue;
                    } elseif (is_array($value)) {
                        $rec[$key][$label] = (bool) in_array($option, $value);
                    } else {
                        $rec[$key][$label] = ($option === $value);
                    }
                }

            } elseif ($type === 'password') {
                if ($rec[$key] && ($rec[$key] !== PASSWORD_PLACEHOLDER)) {
                    $rec[$key] = password_hash($rec[$key], PASSWORD_DEFAULT);
                }

            } elseif ($type === 'button') {
                unset($rec[ $key ]);
            }
        }
        return $rec;
    } // prepareDataRec




	private function export()
    {
        if ($this->exportFileImported) {
            return;
        }
        $currForm = $this->currForm;
        $outFile = $currForm->export;

        $ds = $this->openDB();
        $srcData = $ds->read( true );
        if (!$srcData) {
            return;
        }

        $dsExport = $this->openExportDB();

        $data = [];

        $formElements = $currForm->formElements;
        foreach ($formElements as $fldI => $fldDescr) {
            if (!$fldDescr) { continue; }
            $fldType = @$fldDescr->type;
            if ((strpos(PSEUDO_TYPES, $fldType) !== false) || ($fldDescr->name[0] === '_')) {
                unset($formElements[$fldI]);
            }
        }

        if ($currForm->exportMetaElements) {
            $e = new FormElement();
            $e->type = 'text';
            $e->labelInOutput = TIMESTAMP_KEY_ID;
            $e->name = TIMESTAMP_KEY_ID;
            $e->dataKey = TIMESTAMP_KEY_ID;
            $formElements[] = $e;

            $e = new FormElement();
            $e->type = 'text';
            $e->labelInOutput = REC_KEY_ID;
            $e->name = REC_KEY_ID;
            $e->dataKey = REC_KEY_ID;
            $formElements[] = $e;
        }

        if (fileExt($outFile) !== 'csv') {
            foreach ($srcData as $recKey => $rec) {
                foreach ($rec as $elemKey => $v) {
                    if (is_array($v)) {
                        $srcData[$recKey][$elemKey] = isset($v[0])? $v[0]: implode(',', $v);
                    }
                }
            }
            $dsExport->write( $srcData );
            return;

        } else {
            $r = 1;
            foreach ($srcData as $recKey => $row) {
                $c = 0;
                foreach ($formElements as $fldI => $fldDescr) {
                    if (!$fldDescr) {
                        continue;
                    }

                    $dataKey = @$fldDescr->dataKey;
                    $fldName = @$fldDescr->name;
                    $fldType = @$fldDescr->type;
                    if (($fldType === 'checkbox') || ($fldType === 'radio') || ($fldType === 'dropdown')) {
                        if ($fldDescr->splitOutput) {
                            for ($i = 1; $i < (sizeof($fldDescr->optionNames) - 1); $i++) {
                                if (!$fldDescr->optionNames[$i]) {
                                    continue;
                                }
                                $lbl = $fldDescr->optionNames[$i];
                                $value = @$row[$dataKey][$lbl];
                                $data[$r][$c++] = $value ? '1' : ' ';
                                continue;
                            }
                            $lbl = $fldDescr->optionNames[$i];
                            $value = @$row[$dataKey][$lbl];
                            $value = $value ? '1' : ' ';
                        } else {
                            $value = isset($row[$dataKey][0]) ? $row[$dataKey][0] : '';
                        }

                    } elseif ($fldType === 'tel') {
                        $value = @$row[$dataKey];

                        // if tel number starts with 0 and contains no non-digits, we must reformat it
                        //   in order to prevent CSV-import interpreting it as integer and discard leading 0:
                        if ($value && ($value[0] === '0') && !preg_match('/\D/', $value)) {
                            $value = substr($value, 0, 3) . ' ' .
                                substr($value, 3, 3) . ' ' .
                                substr($value, 5, 2) . ' ' .
                                substr($value, 8);
                        }

                    } elseif ($fldType === 'password') {
                        $value = PASSWORD_PLACEHOLDER;
                    } elseif ($fldName === REC_KEY_ID) {
                        $value = $recKey;
                    } else {
                        $value = @$row[$dataKey];
                    }
                    $data[$r][$c++] = $value;
                }
                $r++;
            }
            $c = 0;
            $struc['key'] = 'index';

            foreach ($formElements as $fldDescr) {
                if (!$fldDescr) {
                    continue;
                }
                $fldType = @$fldDescr->type;
                if (($fldType === 'checkbox') || ($fldType === 'radio') || ($fldType === 'dropdown')) {
                    if ($fldDescr->splitOutput) {
                        for ($i = 1; $i < (sizeof($fldDescr->optionNames) - 1); $i++) {
                            if (!$fldDescr->optionNames[$i]) {
                                continue;
                            }
                            $lbl = $fldDescr->optionNames[$i];
                            $struc['elements'][$lbl] = ['type' => 'string'];
                            $struc['elemKeys'][$c++] = $lbl;
                        }
                        $lbl = $fldDescr->optionNames[$i];
                        $struc['elements'][$lbl] = ['type' => 'string'];
                        $struc['elemKeys'][$c++] = $lbl;
                    } else {
                        $struc['elements'][$fldDescr->labelInOutput] = ['type' => 'string'];
                        $struc['elemKeys'][$c++] = $fldDescr->labelInOutput;
                    }
                } elseif ($fldType !== 'button') {
                    $struc['elements'][$fldDescr->labelInOutput] = ['type' => 'string'];
                    $struc['elemKeys'][$c++] = $fldDescr->labelInOutput;
                }
            }
        }
        $dsExport->write( $data );
    } // export




    private function exportHeaderRow()
    {
        $row = [];
        $c = 0;
        foreach ($this->currForm->formElements as $fldDescr) {
            if (!$fldDescr) { continue; }
            $fldType = @$fldDescr->type;
            if ((strpos(PSEUDO_TYPES, $fldType) !== false) || ($fldDescr->name[0] === '_')) {
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
            if ($value && ($value[0] === '-')) {
                $value = $this->trans->translateVariable( substr($value, 1), true );
            } elseif ($fldDescr->translateLabel) {
                $value = $this->trans->translateVariable( $value, true );
            }
            $row[$c++] = $value;
        }
        if ($this->currForm->exportMetaElements) {
            $value = $this->trans->translateVariable( 'lzy-form-data-key', true );
            $row[] = $value;
        }
        $value = $this->trans->translateVariable( TIMESTAMP_KEY_ID, true );
        $row[] = $value;
        return [ $row ];
    } // exportHeaderRow




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
	    $formId = @$this->currForm->formId;
        $pathToPage = $GLOBALS["globalParams"]["pathToPage"];
	    if ($formId) {
            unset($_SESSION['lizzy']['formData'][ $pathToPage ][$formId]);
            unset($_SESSION['lizzy']['formErrDescr'][$formId]);
            unset($_SESSION["lizzy"]['forms'][$formId]);
        } else {
            unset($_SESSION['lizzy']['formData'][ $pathToPage ]);
            unset($_SESSION['lizzy']['formErrDescr']);
            unset($_SESSION["lizzy"]['forms']);
        }
        $this->errorDescr = null;
	    if ($this->formHash) {
            $this->tck->deleteTicket($this->formHash);
        }
	} // clearCache




    private function initAntiSpam()
    {
        $id = "fld_ch{$this->inx}";
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
<!--                <input id="lzy-popup-as-input" type="text" name="lzy-ch-name" />-->
            </div>
        </div>

EOT;
        $html = str_replace(["\n", '  '], ' ', $html);

        if ($this->currForm->antiSpam) {
            // submit: check honey pot

            $js = <<<EOT

function lzyChContinue( i, btn, callbackArg ) {
    lzyFormUnsaved = false;
    var val = $( '#lzy-popup-as-input-{$this->formInx}' ).val();
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
        buttons: '{{ Cancel }},{{ Continue }}',
        callbacks: 'lzyChAbort,lzyChContinue',
        callbackArg: '{$this->currForm->formId}',
        buttonClass: 'lzy-button, lzy-button lzy-button-submit',
        closeButton: false,
    });
    $( '#lzy-popup-as-input-{$this->formInx}' ).focus();
}

EOT;
            $this->page->addJs($js);
            $this->page->addModules('POPUPS');
        }
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
$('.lzy-formelem-show-info').click( function ( e ) {
     e.preventDefault();
});

EOT;

        if ($this->currForm->submitButtonCallback === 'auto') {
            if ($this->currForm->antiSpam) {
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
            }
        } else {

        }


        if ($this->currForm->cancelButtonCallback === 'auto') {
            $jq .= <<<EOT

$('$formId input[type=reset]').click(function(e) {  // reset: clear all entries
    var \$form = $('$formId');
    $('.lzy-form-cmd', \$form ).val('_reset_');
    lzyFormUnsaved = false;
    \$form[0].submit();
});
EOT;
        }

        $jq .= <<<EOT
       
$('$formId .lzy-form-pw-toggle').click(function(e) {
    e.preventDefault();
    var \$form = $('$formId');
    var \$pw = $('.lzy-form-password', \$form);
    if (\$pw.attr('type') === 'text') {
        \$pw.attr('type', 'password');
        $('.lzy-form-show-pw-icon', \$form).attr('src', systemPath+'rsc/show.png');
    } else {
        \$pw.attr('type', 'text');
        $('.lzy-form-show-pw-icon', \$form).attr('src', systemPath+'rsc/hide.png');
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



    protected function isHeadAttribute( $attr )
    {
        if (!$attr) {
            return false;
        }
        return (strpos(HEAD_ATTRIBUTES, ",$attr,") !== false);
    }



    protected function isElementAttribute( $attr )
    {
        if (!$attr || !is_string($attr)) {
            return false;
        }
        return (strpos(ELEM_ATTRIBUTES, ",$attr,") !== false);
    }



    protected function renderDataTable()
    {
        global $globalParams;

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
                    $continue = (bool)$_SESSION["lizzy"]["user"] || $globalParams["isAdmin"];
                    break;

                case 'privileged':
                    $continue = $this->lzy->config->isPrivileged;
                    break;

                case 'localhost':
                    $continue = $globalParams["localCall"];
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
        $ds = $this->openExportDB();
        $data = $ds->read( true );
        if (!$data) {
            return '';
        }
        $showDataMinRows = isset($currForm->showDataMinRows)? $currForm->showDataMinRows:
            (isset($this->currRec->showDataMinRows)? $this->currRec->showDataMinRows : false);
        if ($showDataMinRows) {
            $nCols = @sizeof($data[0]);
            $emptyRow = array_fill(0, $nCols, '&nbsp;');
            $max = intval($showDataMinRows) + 1;
            for ($i = sizeof($data); $i < $max; $i++) {
                $data[$i] = $emptyRow;
            }
            for ($i=0; $i < $max; $i++) {
                $index = $i ? $i : '#';
                $rec = $data[$i];
                array_splice($rec, 0,0, [$index]);
                $data[$i] = $rec;
            }
        }
        $structure = $ds->getStructure();
        array_unshift($data, $structure['elemKeys']);
        $options = [
            'data' => $data,
            'headers' => true,
        ];
        $tbl = new HtmlTable($this->lzy, $options);
        $out .= $tbl->render();
        $out .= "</div>\n";
        return $out;
    } // renderDataTable



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
            writeLogStr("Form-Error [{$currForm->formName}]:\n$log\n", FORM_LOG_FILE);
        }
    } // checkSuppliedDataEntries



    private function sendConfirmationMail( $rec )
    {
        $isHtml = false;
        if ($this->currForm->confirmationEmail === true) {
            $emailFieldName = 'e-mail';
            $to = $this->getUserSuppliedValue( $emailFieldName, true );
        } else {
            $to = $this->getUserSuppliedValue( $this->currForm->confirmationEmail );
        }
        if (!$to) {
            return;
        }
        foreach ($rec as $key => $value) {
            if (is_array($value)) {
                $value = $value[0];
            }
            $value = $value? $value: '{{ lzy-confirmation-response-element-empty }}';
            $this->trans->addVariable("{$key}_value", $value);
        }
        if ($this->currForm->confirmationEmailTemplate === true) {
            $subject = '{{ lzy-confirmation-response-subject }}';
            $message = '{{ lzy-confirmation-response-message }}';

        } else {
            $file = resolvePath($this->currForm->confirmationEmailTemplate, true);
            if (!file_exists($file)) {
                $this->responseToClient = '';
                if ($this->lzy->localCall || $GLOBALS['globalParams']['isLoggedin']) {
                    $this->lzy->page->addPopup("{{ lzy-form-response-email-template-not-found }}:<br>$file");
                }
                return;
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
                    return;
                }
            }
        }

        $subject = $this->trans->translate($subject);
        $message = $this->trans->translate($message);
        if ($to) {
            $mailfrom = @$this->mailfrom? $this->mailfrom: $this->trans->getVariable('webmaster_email');
            $this->lzy->sendMail($to, $subject, $message, $mailfrom, $isHtml);
            $this->responseToClient = '{{ lzy-form-confirmation-email-sent }}';
        }
    } // sendConfirmationMail



    protected function getFormHash()
    {
        return $this->formHash;
    }



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



    private function updateDbStructure()
    {
        $ds = $this->openDB();
        $struct = $ds->getStructure();
        if (isset($struct['elements'][TIMESTAMP_KEY_ID])) {
            return false;
        }

        unset($struct['elements'][REC_KEY_ID]);
        $struct['elements'][TIMESTAMP_KEY_ID] = ['type' => 'string'];
        $struct['elements'][REC_KEY_ID] = ['type' => 'string'];
        unset($struct['elemKeys']);
        $struct['elemKeys'] = array_keys($struct['elements']);
        $ds->setStructure($struct);
        $data = $ds->read();
        foreach ($data as $recKey => $rec) {
            unset($data[$recKey][REC_KEY_ID]);
            $data[$recKey][TIMESTAMP_KEY_ID] = 0;
            $data[$recKey][REC_KEY_ID] = $recKey;
        }
        $ds->write($data);
        return true;
    } // updateDbStructure




    private function openDB( $args = [])
    {
        if ($this->db) {
            return $this->db;
        }
        if (!$this->currForm) {
            return null;
        }
        $currForm = $this->currForm;

        $this->db = new DataStorage2([
            'dataFile' => $currForm->file,
            'useRecycleBin' => $currForm->useRecycleBin,
            'includeKeys' => true,
            'includeTimestamp' => true,
        ]);
        return $this->db;
    } // openDB




    private function openExportDB()
    {
        if ($this->dbExport) {
            return $this->dbExport;
        }
        if (!$this->currForm) {
            return null;
        }
        $currForm = $this->currForm;
        $structure['key'] = 'index';
        $structure['elements'] = [];
        $elemKeys = [];
        foreach ($currForm->formElements as $fldI => $fldDescr) {
            if ($fldDescr->name[0] === '_') {
                continue;
            }
            if ($fldDescr->splitOutput) {
                foreach ($fldDescr->optionLabels as $k => $v) {
                    if ($k === 0) {
                        continue;
                    }
                    $elemKeys[] = $v;
                }
            } else {
                $elemKeys[] = $fldDescr->label;
            }
        }
        $elemKeys[] = TIMESTAMP_KEY_ID;
        $elemKeys[] = REC_KEY_ID;

        foreach ($elemKeys as $label) {
            $structure['elements'][$label] = [
                'type' => 'string',
            ];
        }
        $structure['elemKeys'] = $elemKeys;

        $fileName = @$currForm->export;
        if (!$fileName) {
            $fileName = resolvePath( DEFAULT_EXPORT_FILE );
        }
        $this->dbExport = new DataStorage2([
            'dataSource' => $fileName,
            'structureDef' => $structure,
            'includeKeys' => $currForm->exportMetaElements,
            'includeTimestamp' => $currForm->exportMetaElements,
        ]);
        return $this->dbExport;
    } // openExportDB




    private function syncBackExportedData()
    {
        $currForm = $this->currForm;
        if (!$currForm->export) {
            return;
        }
        $exportedFile = $currForm->export;
        if (!file_exists($exportedFile)) {
            return;
        }
        $internalFormData = $currForm->file;
        $tExp = @filemtime($exportedFile);
        $tInt = @filemtime($internalFormData);
        if ($tExp < ($tInt + 2)) {
            return;
        }

        $dbE = $this->openExportDB();
        $dataMaster = $dbE->read();

        $formElements = $currForm->formElements;
        if (!$formElements) {
            return;
        }

        $data = [];
        foreach ($dataMaster as $recKey => $dataRec) {
            foreach ($formElements as $elemDef) {
                $elemKey = $elemDef->dataKey;
                $type = $elemDef->type;
                if ($elemKey === REC_KEY_ID) {
                    $value = $recKey;
                } elseif ($elemKey === TIMESTAMP_KEY_ID) {
                    $value = 0;
                } elseif (($elemKey[0] === '_') || ($type === 'button')) {
                    continue;
                } else {
                    $value = @$dataRec[ $elemKey ]? $dataRec[ $elemKey ]: '';
                }
                if (strpos('radio,checkbox,dropdown', $type) !== false) {
                    $newValue = [];
                    if (is_array($value)) {
                        $newValue[0] = implode(',', $value);
                    } else {
                        $newValue[0] = $value;
                    }
                    for ($i=1; $i<sizeof($elemDef->optionNames); $i++) {
                        $option = $elemDef->optionNames[$i];
                        $label = $elemDef->optionLabels[$i];
                        $newValue[$label] = (strpos(",$value,", ",$option,") !== false);
                    }
                    $value = $newValue;
                } elseif (($type === 'password') && ($value === PASSWORD_PLACEHOLDER)){
                    $value = '';
                }
                $data[ $recKey ][ $elemKey ] = $value;
            }
        }

        $db = $this->openDB();
        $db->write($data);
        reloadAgent();
    } // syncBackExportedData

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
    public $export = '';
    public $exportMetaElements = '';
    public $dataKey = '';
    public $bypassedValues = [];
    public $prefill = false;
    public $prefillRec = [];
    public $formHash = '';
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
