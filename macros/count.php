<?php

define('DEFAULT_COUNTER_FILENAME', '~data/_counters.yaml');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $counterName = $this->getArg($macroName, 'name', '(optional) Identifier for the counter', '');
    $renderResult = $this->getArg($macroName, 'renderResult', '[true,false,loggedin,privileged] Whether and to whom the result shall be shown.', false);
    $label = $this->getArg($macroName, 'label', 'Optional label preceding the count, if rendered', '');
    $option = $this->getArg($macroName, 'option', '[as-comment] How result shall be shown.', false);
    $avoidRepeatCount = $this->getArg($macroName, 'avoidRepeatCount', '(optional) If true, does not count repeated loading of same page (default: true).', true);
    $freezeTime = $this->getArg($macroName, 'freezeTime', '(optional) If set, defines the time (in sec) during which page hits within the same sesssion are ignored (default: 60s).', 60);

    if ($counterName === 'help') {
        return '';
    }

    if (!$counterName) {
        $counterName = "{$GLOBALS['lizzy']['pagePath']}counter-$inx";
    }

    $filename = resolvePath(DEFAULT_COUNTER_FILENAME, true);
    $counters = getYamlFile($filename);
    $lastAccess = getStaticVariable('lastCounterAccess');
    $lastAccessTime = isset($lastAccess[$counterName]) ? $lastAccess[$counterName]: 0;
    $doCount = !$avoidRepeatCount || ($lastAccessTime < (time() - $freezeTime));
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if ($doCount) {
        if (isset($counters[$counterName])) {
            $counters[$counterName] += 1;
        } else {
            $counters[$counterName] = 1;
        }
        if ($avoidRepeatCount) {
            $lastAccess[$counterName] = time();
            setStaticVariable('lastCounterAccess', $lastAccess);
        }
        writeToYamlFile($filename, $counters);

    } elseif (!isset($counters[$counterName])) {
        $counters[$counterName] = 1;
    }

    $cnt = $counters[$counterName];
    $str = '';
    if ($renderResult === true) {
        $str = $cnt;
    } elseif ($this->lzy->auth->checkPrivilege($renderResult)) {
        $str = $cnt;
    }

    if ($option === 'as-comment') {
        $str = "\t<!-- $label $cnt -->\n";
    } elseif ($str) {
        $str = "\t<div class='lzy-count'><span class='lzy-count-label'>$label</span><span class='lzy-count-value'>$str</span></div>\n";
    }
    $this->optionAddNoComment = true;
	return $str;
});
