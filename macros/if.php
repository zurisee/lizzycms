<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $state = $this->getArg($macroName, 'state', '[isLocalhost, isPrivileged] The system state to be checked.', '');
    $config = $this->getArg($macroName, 'config', 'Name of a config-value (as in config/config.yaml)', '');
    $file = $this->getArg($macroName, 'file', 'File name to be checked (relative to page path by default)', '');
    $path = $this->getArg($macroName, 'path', 'Checks whether the pattern is found in the path from filesystem root to the current page', '');
    $urlArg = $this->getArg($macroName, 'urlArg', 'Name of URL-argument, e.g. "?arg=true"', '');
    $post = $this->getArg($macroName, 'post', 'Name of post-argument as submitted by a form', '');
    $sessVar = $this->getArg($macroName, 'sessVar', 'Name of a general Session-Variable, e.g. $_SESSION["var"]', '');
    $lizzySessVar = $this->getArg($macroName, 'lizzySessVar', 'Name of a Session-Variable, e.g. $_SESSION{"lizzy"}["var"]', '');
    $op = $this->getArg($macroName, 'op', "[==, <, >, <=, >=, !=] Operand to be applied in comparison of config-value and argument.  \nOr file-op [exists, empty, <, >]", '');
    $arg = $this->getArg($macroName, 'arg', 'Argument to be applied in comparison', '');
    $then = $this->getArg($macroName, 'then', 'What to return if the state is active', '');
    $else = $this->getArg($macroName, 'else', 'What to return if the state is not active', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    $inx = $this->invocationCounter[$macroName] + 1;

    $res = false;
    if (is_string($state)) {
        $state = strtolower($state);
    }
    if ($state === 'help') {
        return '';
    }

    if (($state === true) || ($state === 'true')) {
        $res = true;

    } elseif (($state === false) || ($state === 'false')) {
        $res = false;

    } elseif ($state === 'islocalhost') {
        $res = islocalHost();

    } elseif ($state === 'loggedin') {
        $res = $this->lzy->auth->getLoggedInUser();

    } elseif ($state === 'user') {
        $res = ($_SESSION['lizzy']['user'] === $arg);

    } elseif ($state === 'group') {
        $res = $this->lzy->auth->checkGroupMembership($arg);

    } elseif ($state === 'isprivileged') {
        $res = $this->lzy->config->isPrivileged;

    } elseif ($config) {
        if (isset($this->lzy->config->$config)) {
            $val = $this->lzy->config->$config;
            $res = evalOp($arg, $op, $val);
        }

    } elseif($file) {       // if file exists and is not empty:
        $file = resolvePath($file, true);
        if (!$op) {
            $res = (file_exists($file) && filesize($file));
        } elseif ($op === 'exists') {
            $res = file_exists($file);
        } elseif ($op === 'empty') {
            $res = (file_exists($file) && (filesize($file) === 0));
        } elseif (($op === '&lt;') || ($op === 'lt') || ($op === '<')) {
            $res = (!file_exists($file) || (filesize($file) < intval($arg)));
        } elseif (($op === '&gt;') || ($op === 'gt') || ($op === '>')) {
            $res = (file_exists($file) && (filesize($file) > intval($arg)));
        }

    } elseif ($path) {
        $currPath = getcwd().'/'.$GLOBALS['lizzy']['pathToPage'];
        $res = (strpos($currPath, $path) !== false);

    } elseif ($urlArg) {
        $res = getUrlArg($urlArg);
        if ($op) {
            $res = evalOp($res, $op, $arg);
        }
    } elseif ($post) {
        $res = isset($_POST[$post]) ? $_POST[$post] : '';
        if ($op) {
            $res = evalOp($res, $op, $arg);
        }
    } elseif ($sessVar) {
        $res = isset($_SESSION[$sessVar]) ? $_SESSION[$sessVar] : '';
        if ($op) {
            $res = evalOp($res, $op, $arg);
        }
    } elseif ($lizzySessVar) {
        $res = isset($_SESSION['lizzy'][$lizzySessVar]) ? $_SESSION['lizzy'][$lizzySessVar] : '';
        if ($op) {
            $res = evalOp($res, $op, $arg);
        }
    }
    $this->optionAddNoComment = true;

    if ($res) {
        $then =  evalResult($this, $then, $inx);
        return $then;
    } else {
        $else =  evalResult($this, $else, $inx);
        return $else;
    }
});



function evalResult($trans, $code, $inx)
{
    $out = $code;
    if (preg_match('/^\%(\w+):(.*)/', $code, $m)) {
        $code = $m[1];
        $arg = trim($m[2]);
        $out = '';
        switch ($code) {
            case 'redirect' :
                $trans->page->addRedirect($arg);
                break;

            case 'message' :
                $trans->page->addMessage($arg);
                break;

            case 'debugMsg' :
                $trans->page->addDebugMsg($arg);
                break;

            case 'overlay' :
                $trans->page->addOverlay($arg);
                $trans->page->setOverlayMdCompile(true);
                break;

            case 'override' :
                $trans->page->addOverride($arg);
                $trans->page->setOverrideMdCompile(true);
                break;

            case 'pageSubstitution' :
                $trans->page->addPageSubstitution($arg);
                break;

            case 'description' :
                $trans->page->addDescription($arg);
                break;

            case 'keywords' :
                $trans->page->addKeywords($arg);
                break;

            case 'popup' :
                $trans->page->addPopup($arg);
                break;

            case 'variable' :
                $out = $trans->translateVariable($arg, true);
                break;

            case 'macro' :
                if (preg_match('/([\w-]+) \( (.*) \)/x', $arg, $m)) {
                    $macro = $m[1];
                    $arg = revertQuotes($m[2]);
                    $out = $trans->translateMacro($macro, $arg);
                }
                break;

            case 'contentFrom' :
                $arg = html_entity_decode($arg);
                if (preg_match('/^ ["\']? ([\#\.] [\w-]+)/x', $arg, $m)) {
                    $id = "lzy-content-from-if{$inx}";
                    $selector = $m[1];
                    $jq = "\n\t$('#$id').html( $('$selector').html() );";
                    $trans->page->addJq($jq);
                    $trans->optionAddNoComment = true;
                    $out = "\t\t<div id='$id' class='lzy-content-from'></div>\n";
                }
                break;

            case 'include' :
                $filename = html_entity_decode($arg);
                $filename = str_replace(['"', "'"], '', $filename);
                $filename = resolvePath($filename, true);
                $str = getFile($filename);
                if ($str) {
                    $str = compileMarkdownStr($str);
                    $trans->page->addContent($str);
                }
                break;

            default:
                $out = $code;
        }
    } elseif (preg_match('/([\w-]+) \( (.*) \)/x', $code, $m)) {
        $macro = $m[1];
        $arg = $m[2];
        $out = $trans->translateMacro($macro, $arg);
    }

    $out = str_replace(['&#34;', '&#39;'], ['"', "'"], $out);
    return $out;
}



function evalOp($arg, $op, $val) {
    if (($op === 'eq') || ($op === '==')) {
        $res = ($val === $arg);
    } elseif (($op === 'lt') || ($op === '<')) {
        $res = ($val < $arg);
    } elseif (($op === 'le') || ($op === '<=')) {
        $res = ($val <= $arg);
    } elseif (($op === 'ge') || ($op === '>=')) {
        $res = ($val >= $arg);
    } elseif (($op === 'ne') || ($op === '!=')) {
        $res = ($val !== $arg);
    }
    return $res;
}