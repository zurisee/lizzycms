<?php

// @info: Renders an upload button and implements the entire upload functionality.


require_once SYSTEM_PATH.'forms.class.php';

$macroName = basename(__FILE__, '.php');


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $path = $this->getArg($macroName, 'path', 'Path in which uploaded files will be stored (default: &#126;/upload/).', '~/upload/');
    $this->getArg($macroName, 'showExisting', 'If true, images in the target folder will be displayed.', false);
    $this->getArg($macroName, 'label', 'Text on the upload button (default: "&#123;{ Upload File(s) }}").', '');
    $this->getArg($macroName, 'labelClass', 'Class applied to the upload button (default: "lzy-button").', 'lzy-button');
    $this->getArg($macroName, 'multiple', '[true,false] If true, user can select multiple files for upload (default: false).', false);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);
    if ($path === 'help') {
        return '';
    }
    $args = $this->getArgsArray($macroName);
    if (isset($args['disableCaching'])) {
        unset($args['disableCaching']);
    }

    if ($inx === 1) {
		$this->form = new Forms($this->lzy);
	}
	
	$args1 = ["type" => "form-head",  "label" => "Lzy Upload Form:", "class" => "lzy-form lzy-files-upload-form$inx"];

    // set default upload button label: make dependent on 'multiple':
    if (!$args['label']) {
        if ($args['multiple']) {
            $args['label'] = '{{ lzy-files-upload-label }}'; // plural
        } else {
            $args['label'] = '{{ lzy-file-upload-label }}';
        }
    }

	$str = $this->form->render($args1);

    $args['type'] = 'file';
	$str .= $this->form->render($args);

    $str .= $this->form->render(['type' => 'form-tail' ]);
	return $str;
});
