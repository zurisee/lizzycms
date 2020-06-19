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
    $this->getArg($macroName, 'altText', 'Text appended to "text", but made visually hidden. I.e. text only available to assistive technologies.', '');
    $this->getArg($macroName, 'target', 'Target attribute to be applied to the &lt;a> Tag.', '');
    $this->getArg($macroName, 'subject', 'In case of "mail" and "sms": subject to be preset.', '');
    $this->getArg($macroName, 'body', 'In case of "mail": mail body to be preset.', '');
    $option = $this->getArg($macroName, 'option', '[download,abs,src,url] Modifies the output. "download" forces a download action. "abs" renders absolute paths/urls. "src" renders the source code. "url" renders the resulting address (without HTML wrapper)', '');

    if ($href === 'help') {
        return 'Supported link-types: mail, pdf, sms, tel, geo, gsm, slack';
    }

    if (!$href) {
        return "Error: link address missing";
    }


    $args = $this->getArgsArray($macroName, false, ['href','text','type','id','class','title','altText','target','subject','body','option']);

    $text = $args['text'];

    // special case: HTML embedded -> convert '&#39;' to quote:
    if (preg_match_all("/ (< [^>]* \&\#39; [^>]* >) /x", $text, $m)) {
        foreach ($m[0] as $key => $elem) {
            $elem1 = str_replace('&#39;', "'", $elem);
            $text = str_replace($elem, $elem1, $text);
        }
        $args['text'] = $text;
    }
    if (preg_match_all("/ (< [^>]* \&\#34; [^>]* >) /x", $text, $m)) {
        foreach ($m[0] as $key => $elem) {
            $elem1 = str_replace('&#34;', '"', $elem);
            $text = str_replace($elem, $elem1, $text);
        }
        $args['text'] = $text;
    }

    // AT support: alternative text for assistive technologies:
    if ($args['altText']) {
        $args['text'] = "<span class='lzy-visible' aria-hidden='true'>{$args['text']}</span>";
        $args['text'] .= "<span class='lzy-invisible'>{$args['altText']}</span>";
    }

    $args['relativeToPage'] = true;
    if (stripos($args['href'], 'http') === 0) {
        $args['relativeToPage'] = false;

    } elseif ((stripos($args['href'], 'www.') === 0) || preg_match('/^ ([\w-]+ \. \w+)+ $/x', $args['href'])) {
        $args['href'] = "https://{$args['href']}";
        $args['relativeToPage'] = false;
    }
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
