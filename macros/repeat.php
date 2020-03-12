<?php

// @info: Renders given content multipe times.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function ($args) {
	$macroName = basename(__FILE__, '.php');

    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $count = $this->getArg($macroName, 'count', 'Number of times to repeat the process');
    $text0 = $this->getArg($macroName, 'text', 'Text to be repeated', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', 'CSS-Selector from which to import text', '');
    $variable = $this->getArg($macroName, 'variable', 'Variable to be repeated');
    $file = $this->getArg($macroName, 'file', 'Name of file to be repeatedly included');
    $wrapperClass = $this->getArg($macroName, 'wrapperClass', 'Variable to be repeated', '.repeated');
    $indexPlaceholder = $this->getArg($macroName, 'indexPlaceholder', 'Pattern that will be replaced by the index number, e.g. "##"', '##');
    $bare = $this->getArg($macroName, 'bare', 'Output will be rendered without any wrapper code');
    $prefixVar = $this->getArg($macroName, 'prefixVar', 'Variable-value that will be prepended to output');
    $prefixText = $this->getArg($macroName, 'prefixText', 'Text that will be prepended to output');
    $postfixVar = $this->getArg($macroName, 'postfixVar', 'Variable-value that will be appended to output');
    $postfixText = $this->getArg($macroName, 'postfixText', 'Text that will be appended to output');
    $mdCompile = $this->getArg($macroName, 'mdCompile', 'Runs the output through the MD-compiler', true);
    $execMacros = $this->getArg($macroName, 'execMacros', 'Runs the output through Variable/Macro translation');

    if ($count == 'help') {
        return '';
    }

    $c = $wrapperClass[0];
    if (($c != '.') && ($c != '#')) {
        $wrapperClass = '.'.$wrapperClass;
    }

    if ($variable) {
        $text0 .= $this->getVariable($variable);
    }

    if ($file) {
        $file = resolvePath($file, true);
        if (!fileExists($file)) {
            fatalError("Error: file not found: '$file'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
        }
        $text0 .= getFile($file, true);
    }

    if ($prefixVar) {
        $prefixText .= $this->getVariable($prefixVar);
    }
    $postfixText = str_replace('\n', "\n", $postfixText);
    if ($postfixVar) {
        $postfixText .= $this->getVariable($postfixVar);
    }

    $str = '';
    $count = intval($count);
    for ($i=0; $i < $count; $i++) {
        if ($indexPlaceholder) {
            $text = str_replace($indexPlaceholder, $i+1, $text0);
        } else {
            $text = $text0;
        }
        if (!$bare) {
            $str .= "::::::: $wrapperClass\n$text\n:::::::\n\n";
        } else {
            $str .= $text."\n";
        }
    }

    if ($contentFrom) {
        $str .= "<div id='lzy-repeat-wrapper$inx'></div>\n";
        $jq = <<<EOT
var html = $('$contentFrom').html();
var \$wrapper = $('#lzy-repeat-wrapper$inx');
for (i=0; i<$count; i++) {
    \$wrapper.append( html );
}
EOT;
        $this->page->addJq($jq);
    }

    if (!$bare) {
        $str .= "\n";
    }
    if ($execMacros) {
        $str = $this->translate($str);
    }

    if ($mdCompile) {
        $str = compileMarkdownStr($str);
    }

    return $prefixText.$str.$postfixText;
});


