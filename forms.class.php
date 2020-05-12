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
        if (isset($this->currRec->wrapperClass) && ($this->currRec->wrapperClass)) {
	        $class = "$wrapperClass lzy-form-field-type-$type {$this->currRec->wrapperClass}";
		} else {
            $elemId = $this->currForm->formId.'_'. $this->currRec->elemId;
            $class = "$elemId $wrapperClass lzy-form-field-type-$type";
		}

        // error in supplied data? -> signal to user:
        $error = '';
        $name = $this->currRec->elemId;
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
        return $out;
    } // render




//-------------------------------------------------------------
    private function renderFormHead($args)
    {
		$this->currForm->class = $class = (isset($args['class'])) ? $args['class'] : 'lzy-form';
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

        $out .= "\t<form$id$_class$_method$_action>\n";
		$out .= "\t\t<input type='hidden' name='lizzy_form' value='{$this->currForm->formId}' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_time' name='lizzy_time' value='$time' />\n";
		$out .= "\t\t<input type='hidden' class='lizzy_next' value='{$this->currForm->next}' />\n";
		return $out;
	} // renderFormHead


//-------------------------------------------------------------
    private function renderTextInput()
    {
        $out = $this->getLabel();
        $cls = $this->currRec->class? " class='{$this->currRec->class}'": '';
        $value = $this->getValueAttr();

        $out .= "<input type='text' id='{$this->currRec->fldPrefix}{$this->currRec->elemId}'{$this->currRec->inpAttr}$cls$value />\n";
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
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-radio-label'><div class='lzy-legend'><legend>{$this->currRec->label}</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";
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
        $out = "\t\t\t<fieldset class='lzy-form-label lzy-form-checkbox-label'><div class='lzy-legend'><legend>{$this->currRec->label}</legend></div>\n\t\t\t  <div class='lzy-fieldset-body'>\n";

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
			if (progress == 100) {
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
					
				} elseif (stripos($type, 'reset') !== false) {
					$out .= "$indent<input type='reset' id='$id' value='$label' $class />\n";
					
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
        return $out;
	} // formTail


//-------------------------------------------------------------
    private function getLabel($id = false)
    {
		$id = ($id) ? $id : "{$this->currRec->fldPrefix}{$this->currRec->elemId}";
        $requiredMarker =  $this->currRec->requiredMarker;
		$label = $this->currRec->label;
		if ($this->translateLabel) {
		    $hasColon = (strpos($label, ':') !== false);
            $label = trim(str_replace([':', '*'], '', $label));
            $label = "{{ $label }}";
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

        return "\t\t\t<label for='$id'>$label</label>";
    } // getLabel




//-------------------------------------------------------------
    private function classAttr($class = '')
    {
        $out = " class='".trim($class). "'";
        return trim($out);
    } // classAttr
    


//-------------------------------------------------------------
    private function parseArgs($args)
    {
		if (!$this->currForm) {	// first pass -> must be type 'form-head' -> defines formId
		    if (!isset($args['type'])) {
		        fatalError("Forms: mandatory argument 'type' missing.");
            }
			if ($args['type'] !== 'form-head') {
                fatalError("Error: syntax error \nor form field definition encountered without previous element of type 'form-head'", 'File: '.__FILE__.' Line: '.__LINE__);
			}
            $label = (isset($args['label'])) ? $args['label'] : 'Lizzy-Form'.($this->inx + 1);
	        $formId = (isset($args['id'])) ? $args['id'] : false;
	        if (!$formId) {
                $formElemId =  '';
                $formId = (isset($args['class'])) ? $args['class'] : translateToIdentifier($label);
                $formId = str_replace('_', '-', $formId);
            } else {
                $formElemId = $formId;
            }

	        $this->formId = $formId;
			$this->formDescr[ $formId ] = new FormDescriptor;
			$this->currForm = &$this->formDescr[ $formId ];
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
            $this->currForm->replaceQuotes =  (isset($args['replaceQuotes']))? $args['replaceQuotes']: true;

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

        if (isset($args['id'])) {
            $elemId = $args['id'];
        } else {
            $elemId = translateToIdentifier($label);
            while (isset($this->currForm->formElements[$elemId])) {
                if (preg_match('/(.*?)(\d+)$/', $elemId, $m)) {
                    $elemId = $m[1] . (intval($m[2]) + 1);
                } else {
                    $elemId .= '1';
                }
            }
        }

        $this->currForm->formElements[$elemId] = new FormElement;
        $this->currRec = &$this->currForm->formElements[$elemId];
        $rec = &$this->currRec;

		$rec->type = $type;
		$rec->elemId = $elemId;

		if (strpos($label, '*')) {
			$label = trim(str_replace('*', '', $label));
			$args['required'] = true;
		}
		$rec->label = $label;

		if (isset($args['name'])) {
            $name = $args['name'];
        } else {
            $name = $elemId;
        }
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
			$rec->shortLabel = (isset($args['shortlabel'])) ? $args['shortlabel'] : $label;
			if (($type === 'checkbox') || ($type === 'radio') || ($type === 'dropdown')) {
				$checkBoxLabels = ($rec->valueNames) ? preg_split('/\s* [\|,] \s*/x', $rec->valueNames) : [];
				array_unshift($checkBoxLabels, $rec->shortLabel);
				$this->currForm->formData['labels'][] = $checkBoxLabels;
			} else {
				$this->currForm->formData['labels'][] = $rec->shortLabel;
			}
			$this->currForm->formData['names'][] = $name;
		}

        $rec->placeholder = (isset($args['placeholder'])) ? $args['placeholder'] : '';
        $rec->class = (isset($args['class'])) ? $args['class'] : '';
        
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

        $this->userSuppliedData = $userSuppliedData = (isset($_GET['lizzy_form'])) ? $_GET : $_POST;
		if (isset($userSuppliedData['lizzy_form'])) {
			$this->formId = $formId = $userSuppliedData['lizzy_form'];
		} else {
			$this->clearCache();
			return false;
            fatalError("ERROR: unexpected value received from browser", 'File: '.__FILE__.' Line: '.__LINE__);
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

		$next = @$currFormDescr->next;
		
        list($str, $msg) = $this->defaultFormEvaluation($currFormDescr);

        $postprocess = isset($currFormDescr->postprocess) ? $currFormDescr->postprocess : false;
        if ($postprocess) {
			$result = $this->transvar->doUserCode($postprocess, null, true);
			if (is_array($result)) {
                fatalError($result[1]);
            }
			if ($result) {
				$str1 = evalForm($userSuppliedData, $currFormDescr, $msg, $this->page);
				if (is_string($str1)) {
				    $str .= $str1;
                }
			} else {
                fatalError("Warning: executing code '$postprocess' has been blocked; modify 'config/config.yaml' to fix this.", 'File: '.__FILE__.' Line: '.__LINE__);
			}
        }

        if (!$this->errorDescr) {
            $this->page->addCss(".$formId { display: none; }");
            $str .= "<div class='lzy-form-continue'><a href='{$next}'>{{ lzy-form-continue }}</a></div>\n";
            $this->clearCache();
            $this->formEvalResult = $str;
        }
        return $str;
    } // evaluate



//-------------------------------------------------------------
	private function defaultFormEvaluation($currFormDescr)
	{
		$formName = $currFormDescr->formName;
		$mailto = $currFormDescr->mailto;
		$mailfrom = $currFormDescr->mailfrom;
		$formData = &$currFormDescr->formData;
		$labels = &$formData['labels'];
		$names = &$formData['names'];
		$userSuppliedData = $this->userSuppliedData;

		// check required entries:
        foreach ($currFormDescr->formElements as $name => $rec) {
            if ($rec->requiredMarker) {
                if ($g = isset($rec->requiredGroup)? $rec->requiredGroup : []) {
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
        }


        $str = "$formName\n===================\n\n";
		$log = '';
		$labelNames = '';
		$out = $currFormDescr->confirmationText;
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

			$str .= mb_str_pad($label, 22, '.').": $value\n\n";
			$log .= "'$value'; ";
            $labelNames .= "'$name'; ";
		}
		$str = trim($str, "\n\n");
		if ($res = $this->saveCsv($currFormDescr)) {
		    $this->saveErrorDescr($res);
		    return '';
        }
		
		$serverName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost';
		$remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
		$localCall = (($serverName === 'localhost') || (strpos($remoteAddress, '192.') === 0) || ($remoteAddress === '::1'));

        $out = "\n<div class='lzy-form-response'>\n$out\n</div><!-- /lzy-form-response -->\n";

        // send mail if requested:
		if ($mailto) {
            $formName = str_replace(':', '', $formName);
            $subject = $this->transvar->translateVariable('lzy-form-email-notification-subject');
            if (!$subject) {
                $subject = 'user data received';
            }
            $subject = "[$formName] ".$subject;
            if ($localCall) {
                $str1 = "-------------------------------\n$str\n\n-------------------------------\n(-> would be be sent to $mailto)\n";
                $out .= <<<EOT

<div class='lzy-localhost-response'>
    <p>{{ lzy-form-feedback-local }}</p>
    <p>Subject: $subject</p>
    <pre>
$str1
    </pre>
</div> <!-- /lzy-localhost-response -->

EOT;
            } else {
                $this->sendMail($mailto, $mailfrom, $subject, $str);
            }
        }
        $this->writeLog($labelNames, $log, $formName, $mailto);
        return [$out, $str];
	} // defaultFormEvaluation()





//-------------------------------------------------------------
	private function saveCsv($currFormDescr)
	{
		$formId = $currFormDescr->formId;
		$errorDescr = false;

		if (isset($currFormDescr->file) && $currFormDescr->file) {
		    $fileName = resolvePath($currFormDescr->file, true);
        } else {
            $fileName = resolvePath("~page/{$formId}_data.csv");
        }

        $userSuppliedData = $this->userSuppliedData;
        $names = $currFormDescr->formData['names'];
        $labels = $currFormDescr->formData['labels'];

        $db = new DataStorage2($fileName);
        $data = $db->read();

        if (!$data) {   // no data yet -> prepend header row containing labels:
            $data = [];
            $j = 0;
            foreach($labels as $l => $label) {
                $label = html_entity_decode($label);
                if (is_array($label)) { // checkbox returns array of values
                    $name = $names[$l];
                    $splitOutput = (isset($currFormDescr->formElements[$name]->splitOutput))? $currFormDescr->formElements[$name]->splitOutput: false ;
                    if (!$splitOutput) {
                        $data[0][$j++] = $label[0];
                    } else {
                        for ($i=1;$i<sizeof($label); $i++) {
                            $data[0][$j++] = $label[$i];
                        }
                    }

                } else {        // normal value
                    $label = trim($label, ':');
                    $data[0][$j++] = $label;
                }
            }
            $data[0][$j] = 'timestamp';
        }

        $r = sizeof($data);
        $j = 0;
        $formElements = &$currFormDescr->formElements;
        $errors = 0;
        foreach($names as $i => $name) {
            $value = (isset($userSuppliedData[$name])) ? $userSuppliedData[$name] : '';
            if (@$currFormDescr->formElements[$name]->type === 'bypassed') {
                if (@$currFormDescr->formElements[$name]->value[0] !== '$') {
                    $data[$r][$j++] = $currFormDescr->formElements[$name]->value;
                } else {
                    $data[$r][$j++] = $value;
                }

            } elseif (is_array($value)) { // checkbox returns array of values
                $name = $names[$i];
                $splitOutput = (isset($currFormDescr->formElements[$name]->splitOutput))? $currFormDescr->formElements[$name]->splitOutput: false ;
                if (!$splitOutput) {
                    $data[$r][$j++] = implode(', ', $value);

                } else {
                    $labs = $labels[$i];
                    for ($k=1; $k<sizeof($labs); $k++) {
                        $l = $labs[$k];
                        $val = (in_array($l, $value)) ? '1' : '0';
                        $data[$r][$j++] = $val;
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
                $data[$r][$j++] = $value;
            }
        }
        $data[$r][$j] = time();
        if ($errors === 0) {
            $db->write($data);
            return false;
        }
        return $errorDescr;
	} // saveCsv



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

postprocess:
: (optional) Name of php-script (in folder _code/) that will process submitted data.

warnLeavingPage:
: If true (=default), user will be warned if attempting to leave the page without submitting entries.

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

shortlabel:
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
        if ($pw.attr('type') == 'text') {
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

} // Forms


