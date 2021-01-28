<?php


$GLOBALS['globalParams']['popupInx'] = 0;


class PopupWidget
{
    public $popups = null;
//    private $confirmButton = false;
//    private $cancelButton = false;

    public function __construct($page = null)
    {
        $this->page = $page;
        $this->popups = &$this->page->popups;
        $this->popupInx = &$GLOBALS['globalParams']['popupInx'];
    } // __construct



    //-----------------------------------------------------------------------
    public function addPopup($args)
    {
        $this->popups[$this->popupInx++] = $args;
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

        if (!isset($this->popups[0])) {
            $this->popups[0] = $this->popups;
        }

        foreach ($this->popups as $args) {
            $this->render( $args );
        } // loop popup instances

        $this->popups = [];
    } // applyPopup




    public function render( $args )
    {
        // load POPUPS:
        $this->page->addModules('POPUPS');

        $jsArgs = $this->parseArgs( $args );
        $jq = <<<EOT

lzyPopup({
$jsArgs});

EOT;

        $this->page->addJq($jq);
    } // render



    private function parseArgs( $args )
    {
        $this->args = $args;
        $jsArgs = '';
        foreach ($args as $key => $value) {
            if (is_string(($key))) {
                $jsArgs .= "\t$key: '$value',\n";
            }
        }

        return $jsArgs;
    } // parseArgs




    private function getJsArg( $argName, $default = false)
    {
        // render "argname: 'value',"
        $value = isset($this->args[$argName]) ? $this->args[$argName] : $default;
        $out = '';
        if ($value) {
            $out = "\t$argName: '$value',\n";
        }
        return $out;
    } // getJsArg





    public function renderPopupHelp()
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