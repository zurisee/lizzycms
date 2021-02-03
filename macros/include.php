<?php

// @info: Renders content of a file or all files in a folder.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $file = $this->getArg($macroName, 'file', '(optional) Identifies the file to be included.', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', "[CSS-Selector] If set, Lizzy attempts to retrieve content from the specified DOM element.", '');
    $url = $this->getArg($macroName, 'url', "[https://...] If set, Lizzy attempts to retrieve content from the specified webpage.", '');
    $selector = $this->getArg($macroName, 'selector', "[CSS-Selector] In conjunction with url specifies which part of the source webpage to include.", '');
    $id = $this->getArg($macroName, 'id', 'ID to be used for the target element.', '');
    $folder = $this->getArg($macroName, 'folder', '(optional) Identifies the folder to be included.', '');
    $reverseOrder = $this->getArg($macroName, 'reverseOrder', '[true,false] In conjunction with folder defines the sort order.', false);
    $wrapperTag = $this->getArg($macroName, 'wrapperTag', '(optional) HTML-tag in which to wrap the content of each included file.', false);
    $wrapperClass = $this->getArg($macroName, 'wrapperClass', '(optional) class applied to each file-wrapper.', false);
    $outerWrapperTag = $this->getArg($macroName, 'outerWrapperTag', '(optional) HTML-tag in which to wrap the set of included files.', false);
    $outerWrapperClass = $this->getArg($macroName, 'outerWrapperClass', '(optional) class applied to the wrapper around all files.', '');
    $literal = $this->getArg($macroName, 'literal', '(optional) If true, wraps output in pre tags.', false);
    $trim = $this->getArg($macroName, 'trim', '(optional) If false, leading and trailing whitespace will not be removed from included text.', true);
    $compileMarkdown = $this->getArg($macroName, 'compileMarkdown', '(optional) Flag to activate MD-compilation of .md files.', null);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($wrapperClass) {
        $wrapperClass = " class='$wrapperClass'";
    }

    $allMD = true;
    $str = '';

    if (preg_match('/^https?:/', $file )) {
        $url = $file;
        $file = false;
    } elseif (preg_match('/^https?:/', $contentFrom )) {
        $url = $file;
        $contentFrom = false;
    }

    $inhibitMD = false;
    if ($compileMarkdown === null) {
        $compileMarkdown = false;
        $inhibitMD = false;
    } elseif ($compileMarkdown === false) {
        $inhibitMD = true;
    }

    if ($file) {
        if (strtolower(fileExt($file)) !== 'md') {
            $allMD = false;
            $compileMarkdown = true;
        }
        $file = resolvePath($file, true);
        $str = getFile($file);
        if ($trim) {
            $str = trim($str, "\n\r");
        }
        if ($wrapperTag) {
            $str = "\n@@1@@\n$str\n@@2@@\n\n";
        }
    }

    $id = $id ? $id : "include$inx";

    if ($contentFrom) {
        // . or # -> jq, else .load
        if (($contentFrom[0] === '.') || ($contentFrom[0] === '#')) {     // selector for a local element
            $jq = "$('#$id').html( $('$contentFrom').html() );\n";
            $this->page->addJq($jq);
            $str = "\t\t<div id='$id'></div>\n";
        }
        $allMD = false;
    }

    if ($url) {                                   // url & selector for remote element
        if (!$selector && $url) {
            list($url, $selector) = explode(' ', $url);
        }
        if ($selector) {
            $jq = "\nconsole.log('loading content from: \"$url $selector\"');\n$('#$id').load( '$url $selector' );\n";
            $this->page->addJq($jq);
            $str = "\t\t<div id='$id'>{{ Loading }}</div>\n";
            
        } else {
            $str .= "\t\t<iframe src='$url' />\n";
        }
        $allMD = false;
    }

    if ($folder) {
        $folder = resolvePath(fixPath($folder), true);
        $files = getDir($folder.'*');
        if ($reverseOrder) {
            rsort($files);
        }
        foreach ($files as $file) {
            if (strtolower(fileExt($file)) !== 'md') {
                $allMD = false;
            }
            $s = getFile($file, true);
            if ($wrapperTag) {
                $str .= "\n@@1@@\n$s\n@@2@@\n\n";
            } else {
                $str .= $s;
            }
        }
    }

    if($inhibitMD) {
        $str = str_replace(['{{','<'], ['&#123;{','&lt;'], $str);

    } elseif ($compileMarkdown && $allMD) {
        $str = compileMarkdownStr($str);
    }

    if ($outerWrapperClass) {
        $outerWrapperClass = " class='$outerWrapperClass'";
    } else {
        $outerWrapperClass = " class='lzy-include'";
    }

    if ($literal) {
        $str = "\t<pre$outerWrapperClass>\n$str\n\t</pre>\n";
    }

    if ($wrapperTag) {
        $str = str_replace('<p>@@1@@</p>', "\t\t<$wrapperTag$wrapperClass>\n", $str);
        $str = str_replace('<p>@@2@@</p>', "\t\t</$wrapperTag>\n", $str);
    }

    if ($outerWrapperTag) {
        $str = "\t<$outerWrapperTag$outerWrapperClass>\n$str\n\t</$outerWrapperTag>\n";
    }

    $this->optionAddNoComment = true;
	return $str;
});
