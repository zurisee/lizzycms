<?php

// @info: Renders a link.

require_once SYSTEM_PATH.'link.class.php';


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $href = $this->getArg($macroName, 'href', 'Path or URL to link target', '');
    $this->getArg($macroName, 'text', 'Link-text. If missing, "href" will be displayed instead.', '');
    $this->getArg($macroName, 'type', '[intern, extern or mail, pdf, sms, tel, gsm, geo, slack] "mail" renders link as "mailto:", "intern" suppresses automatic prepending of "https://", "extern" causes link target to be opened in new window.', '');
    $this->getArg($macroName, 'id', 'ID to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'class', 'Class to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'title', 'Title attribute to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'target', 'Target attribute to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'subject', 'In case of "mail" and "sms": subject to be preset.', '');
    $this->getArg($macroName, 'body', 'In case of "mail": mail body to be preset.', '');
    $option = $this->getArg($macroName, 'option', '[download,abs,src,url] Modifies the output. "download" forces a download action. "abs" renders absolute paths/urls. "src" renders the source code. "url" renders the resulting address (without HTML wrapper)', '');

    if ($href == 'help') {
        return 'Supported link-types: mail, pdf, sms, tel, geo, gsm, slack';
    }

    if (!$href) {
        return "Error: link address missing";
    }


    $args = $this->getArgsArray($macroName, false, ['href','text','type','id','class','title','target','subject','body','option']);


    $cl = new CreateLink( $this->lzy );
    $str = $cl->render($args);

    if (stripos($option, 'url') !== false) {
        if (preg_match("/href='([^']*)'/ix", $str, $m)) {
            $str = $m[1];
        }
    } elseif (stripos($option, 'src') !== false) {
        $str = str_replace('<', '&lt;', $str);
    }

    $this->optionAddNoComment = true;
	return $str;
});
