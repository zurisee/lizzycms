<?php

class PopupWidget
{
    public $popups = null;
    private $confirmButton = false;
    private $cancelButton = false;

    public function __construct($page)
    {
        $this->page = $page;
        $this->popups = &$this->page->popups;
    }



    //-----------------------------------------------------------------------
    public function addPopup($args)
    {
        $this->popups[] = $args;
        return "\t<!-- lzy-popup invoked -->\n";
    } // addPopup




    //-----------------------------------------------------------------------
    public function applyPopup()
    {
        $popup = $this->page->get('popup');
        if ($popup) { // in frontmatter it's possible to to use popup (singular)
            $this->popups[] = $popup;
            $this->page->set('popup', false);
        }
        if (!$this->popups) {
            return false;
        }

        $popupInx = $this->page->get('popupInx');
        if (!$popupInx) {
            $popupInx = 0;
            $this->page->set('popupInx', 0);
            $this->page->addModules('POPUPS');
        }

        if (!isset($this->popups[0])) {
            $this->popups[0] = $this->popups;
        }
        $jq = '';

        foreach ($this->popups as $args) {
            $popupInx++;
            $this->page->set('popupInx', $popupInx);
            $this->args = $args;
            $this->argStr = '';

            if (is_string($args)) {
                if ($args == 'help') {
                    $this->popups = [];
                    $this->page->addContent( $this->renderPopupHelp() );
                    $this->page->addJQ($jq);
                    return true;
                }
                $args1['text'] = $args;
                $this->args = $args = $args1;
            }

            $defaultConfirmBtn = $defaultCancelBtn = '';
            $this->getArg('text');
            $this->getArg('type', 'info'); // [info, confirm, dialog]
            switch ($this->type) {
                case 'confirm' :
                    $defaultConfirmBtn = '{{ Confirm }}';
                    $defaultCancelBtn = '{{ Cancel }}';
                    break;
                case 'dialog' :
                    $defaultConfirmBtn = '{{ Save }}';
                    $defaultCancelBtn = '{{ Cancel }}';
                    break;
            }
            $this->getArg('contentFrom');
            $this->getArg('class', "lzy-popup lzy-popup$popupInx");
            $class = $this->class;
            $this->getArg('triggerSource');
            $this->getArg('triggerEvent', 'click');
            $this->getArg('confirmButton', $defaultConfirmBtn);
            $this->getArg('cancelButton', $defaultCancelBtn);
            $this->getArg('onConfirm');
            $this->getArg('onConfirmFrom');
            $this->getArg('onCancel');
            $this->getArg('onCancelFrom');
            $this->getArg('closeButton', true, 'closebutton');
            $this->getArg('closeOnBgClick', true, 'blur');
            $this->getArg('horizontal', 0);
            $this->getArg('vertical', 0);
            $this->getArg('transition', '', 'transition');
            $this->getArg('speed', 0.3);
            $this->getArg('opacity', 0.8, 'opacity', false);
            $this->getArg('bgColor', '#000', 'color');

            if (isset($args[0])) {
                if ($args[0] == 'help') {
                    $this->popups = [];
                    $this->page->addContent( $this->renderPopupHelp() );
                    $this->page->addJQ($jq);
                    return true;
                }
                $this->text = $args[0];
            }

            $popupId = $this->contentFrom ? $this->contentFrom : "lzy-popup$popupInx";
            $popupId = str_replace('#', '', $popupId);

            // case text but no contentFrom:
            if ($this->text && !$this->contentFrom) {
                $this->contentFrom = "#$popupId";
                $jq .= "$('body').append('<div class=\"dispno\"><div id=\"$popupId\">{$this->text}</div></div>');\n";
            }

            // transition:
            if ($this->speed) {
                $this->argStr .= "transition: 'all {$this->speed}s',\n";
            }

            // invocation:
            if ($this->triggerSource) {
                if ($this->triggerEvent == "right-click") {
                    $jq .= "$('{$this->triggerSource}').contextmenu(function() { $('#$popupId').popup('show'); return false; }).css('user-select', 'none');\n";
                } else {
                    $jq .= "$('{$this->triggerSource}').bind('{$this->triggerEvent}', function() { $('#$popupId').popup('show'); });\n";
                }
                $this->getArg('triggerEvent', 'click');
                $jq .= "$('{$this->triggerSource}').attr('aria-expanded', false);\n";
                $this->argStr .= "onopen: function() { $('{$this->triggerSource}').attr('aria-expanded', true); },\n";
                $this->argStr .= "onclose: function() { $('{$this->triggerSource}').attr('aria-expanded', false); },\n";
            } else {
                $this->argStr .= "autoopen: true,\n";
            }

            // confirmButton:
            $confirmButton = '';
            $onConfirm = str_replace(['&#34;', '&#39;'], ['"', "'"], $this->onConfirm);
            $onConfirm = str_replace(["\'", '\"'], ['&#39;','&#34;'], $onConfirm);

            if ($this->confirmButton) {
                $confirmButton = "<button class='lzy-popup-confirm lzy-popup-button'>{$this->confirmButton}</button> ";
                if ($this->onConfirmFrom) {
                    $filename = resolvePath($this->onConfirmFrom, true);
                    if (file_exists($filename)) {
                        $onConfirm = file_get_contents($filename);
                    } else {
                        fatalError("File not found: '$filename'");
                    }
                }
                $onConfirm = <<<EOT
$('#$popupId .lzy-popup-confirm').click(function(e) {
    var \$popup = $(e.target).closest('.popup_content');
    {$onConfirm}
    \$popup.popup('hide');
});

EOT;
            }

            // cancelButton:
            $cancelButton = '';
            $onCancel = str_replace(['&#34;', '&#39;'], ['"', "'"], $this->onCancel);
            $onCancel = str_replace(["\'", '\"'], ['&#39;','&#34;'], $onCancel);
            if ($this->cancelButton) {
                $cancelButton = "<button class='lzy-popup-cancel lzy-popup-button'>{$this->cancelButton}</button> ";
                if ($this->onCancelFrom) {
                    $filename = resolvePath($this->onCancelFrom, true);
                    if (file_exists($filename)) {
                        $onCancel = file_get_contents($filename);
                    } else {
                        fatalError("File not found: '$filename'");
                    }
                }
                $onCancel = <<<EOT
$('#$popupId .lzy-popup-cancel').click(function(e) {
    var \$popup = $(e.target).closest('.popup_content');
    {$onCancel}
    \$popup.popup('hide');
});
EOT;
            }

            $buttons =  "\t.append(\"<div class='lzy-popup-buttons'>$cancelButton$confirmButton</div>\")";

            // class:
            $addClass = '';
            if ($class) {
                if ($this->type != 'info') {
                    $class .=  ' lzy-popup-'.$this->type;
                }
                $addClass = "\t.addClass('$class')\n";
            }

            // offset vertical / horizontal:
            if ($this->horizontal && $this->vertical) {
                $offset = "\t.css('transform', 'translate({$this->horizontal}, {$this->vertical})')\n";
            } elseif ($this->horizontal) {
                $offset = "\t.css('transform', 'translateX({$this->horizontal}')\n";
            } elseif ($this->vertical) {
                $offset = "\t.css('transform', 'translateY({$this->vertical}')\n";
            } else {
                $offset = '';
            }

            $jq .= <<<EOT
$('#$popupId')
$addClass$buttons
    .popup({
{$this->argStr}
})
$offset;

$onConfirm
$onCancel


EOT;
        } // loop popup instances

        $this->page->addJQ($jq);
        $this->popups = [];
    } // applyPopup






    private function getArg($argName, $default = false, $jBoxArgName = null, $addQuotes = true)
    {
        if (!isset($this->args[$argName]) && !$default) {
            $this->$argName = false;
            return;
        }
        if ($jBoxArgName === false) {
            $jBoxArgName = $argName;
        }
        $value = isset($this->args[$argName]) ? $this->args[$argName] : '';

        if (!$value && $default) {
            $value = $default;
        }
        $value1 = '';
        if (($value != 'false') &&  ($value != 'true')) {
            $value1 = "'$value'";
        } elseif (($value === 'false') || ($value === false)) {
            $value = false;
            $value1 = 'false';
        } elseif (($value === 'true') || ($value === true)) {
            $value = true;
            $value1 = 'true';
        }
        $this->$argName = $value;
        if ($jBoxArgName !== null) {
            if (!$addQuotes) {
                $value1 = trim($value1, "'");
            }
            $this->argStr .= "$jBoxArgName: $value1,\n";
        }
    } // getArg






    private function renderPopupHelp()
    {
        $str = <<<EOT
<h2>Options for macro <em>popup()</em></h2>
<dl>
	<dt>text:</dt>
		<dd>Text to be displayed in the popup (for small messages, otherwise use contentFrom) </dd>
		
	<dt>contentFrom:</dt>
		<dd>Selector that identifies content which will be imported and displayed in the popup (example: '#box'). </dd>
		
	<dt>triggerSource:</dt>
		<dd>If set, the popup opens upon activation of the trigger source element (example: '#btn'). </dd>
		
	<dt>triggerEvent:</dt>
		<dd>[click, right-click, dblclick, blur] Specifies the type of event that shall open the popup (default: click). </dd>
		
	<dt>confirmButton:</dt>
		<dd>If set, defines the text to be displayed in the confirm button (default: '&#123;&#123; Confirm }}'). </dd>
		
	<dt>cancelButton:</dt>
		<dd>If set, defines the text to be displayed in the cancel button (default: '&#123;&#123; Cancel }}'). </dd>
		
	<dt>onConfirm:</dt>
		<dd>Code to be executed when the user activates the confirm button (example: "alert('User clicked Confirm!');"). </dd>
		
	<dt>onConfirmFrom:</dt>
		<dd>Like onConfirm, but code will be imported from the specified file (example: 'myonconfirm.js') </dd>
		
	<dt>onCancel:</dt>
		<dd>Code to be executed when the user activates the cancel button (example: "alert('User clicked Cancel!');"). </dd>
		
	<dt>onConfirmFrom:</dt>
		<dd>Like onCancel, but code will be imported from the specified file (example: 'myoncancel.js') </dd>
		
	<dt>closeButton:</dt>
		<dd>Specifies whether a close button shall be displayed in the upper right corner (default: true). </dd>
		
	<dt>closeOnBgClick:</dt>
		<dd>Specifies whether clicks on the background will close the popup (default: true). </dd>
		
	<dt>horizontal:</dt>
		<dd>Specifies a horizontal offset from the central position (experimental). </dd>
		
	<dt>vertical:</dt>
		<dd>Specifies a vertical offset from the central position (experimental). </dd>
		
	<dt>transition:</dt>
		<dd>Specifies a transition for opening/closing the popup (experimental). </dd>
		
	<dt>speed:</dt>
		<dd>Specifies the duration of the standard transition (i.e. zoom effect). </dd>


</dl>

EOT;

        return $str;
    } // renderPopupHelp

} // Popup