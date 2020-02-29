<?php

define('DEFAULT_COUNTER_FILENAME', '~data/_counters.yaml');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $counterName = $this->getArg($macroName, 'name', '(optional) Identifier for counter to', '');
    $renderResult = $this->getArg($macroName, 'renderResult', '[true,false,loggedin,privileged] Whether and to whom the result shall be shown.', false);
    $label = $this->getArg($macroName, 'label', 'Optional label preceding the count, if rendered', '');
    $option = $this->getArg($macroName, 'option', '[as-comment] How result shall be shown.', false);
    $avoidRepeatCount = $this->getArg($macroName, 'avoidRepeatCount', '(optional) If true, does not count repeated loading of same page.', true);

    if (!$counterName) {
        $counterName = translateToIdentifier($GLOBALS["globalParams"]["pagePath"]);
    }
    $reqId = "{$_SERVER["REMOTE_ADDR"]}/$counterName";

    $filename = resolvePath(DEFAULT_COUNTER_FILENAME, true);
    $counters = getYamlFile($filename);
    $doCount = !$avoidRepeatCount || (!isset($counters['_lastRequest']) || ($counters['_lastRequest'] !== $reqId));

    if ($doCount) {
        if (isset($counters[$counterName])) {
            $counters[$counterName] += 1;
        } else {
            $counters[$counterName] = 1;
        }
        $counters['_lastRequest'] = $reqId;
        writeToYamlFile($filename, $counters);
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
