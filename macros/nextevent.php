<?php
// see https://www.php.net/manual/en/function.strftime.php

require_once SYSTEM_PATH.'/nexttime.class.php';

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');

    $source = $options['source'] = $this->getArg($macroName, 'source', '[file.yaml|file.txt] Data source to obtain event dates from. In case '.
        'of DB, you need to append the key of the date field, separated by colon, e.g. "file.yaml:key". '.
        'Value of "source"  will be remembered for subsequent calls - no need to repeat it.', '');
//    $options['data'] = $this->getArg($macroName, 'data', '[list] Alternative to "source" -> provide data as a '.
//        'string of comma or newline separated date values. E.g. "2021-04-29,2021-05-13,2021-05-27"', '');
//    $options['offset'] = $this->getArg($macroName, 'offset', '[string] If defined, will be interpreted to compute an offset '.
//        'from found event date. E.g. "-1day 12:00" (meaning "previous day at noon.").', false);
//    $options['format'] = $this->getArg($macroName, 'format', 'Optional format definition for output. See PHP\'s strftime() for reference.'.
//        'E.g. "%d. %m. %Y bis %H:%M".', '');
//    $options['every'] = $this->getArg($macroName, 'every', 'Alternative to providing data: provide a date pattern like '.
//        '"<strong>Every</strong> second Thursday <strong>Starting</strong> on 2021-04-22".<br>The "every" argument can '.
//        'be specified as the number seconds between events.<br>'.
//        'Alternatively, use the pattern "D:M:Y", where D,M,Y correspond to day,month,year. And each of these values '.
//        'can be either "*" (=every) or an integer (e.g. "2" for every other...) or an integer followed by a dot (e.g. "3." for every '.
//        'third ...).<br>Example: "28.:2.:4" means "Feb. 28 in every 4th year (aka leap year)". ', false);
//    $options['start'] = $this->getArg($macroName, 'start', '[ISO date] Defines the initial date of the sequence, optionally including a time. See above.', false);
//    $options['starting'] = $this->getArg($macroName, 'starting', 'Synonym for "start".', false);
//    $options['prefix'] = $this->getArg($macroName, 'prefix', 'Optional string which will be prepended to the output', '');
//    $options['postfix'] = $this->getArg($macroName, 'postfix', 'Optional string which will be apppended to the output', '');
//    $options['excludeCondition'] = $this->getArg($macroName, 'excludeCondition', 'Optional string that will be evaluated against '.
//        'each event record to determine whether to skip given event. E.g. "(\$rec[\'canceled\'] !== \'\'". '.
//        'Value of "excludeCondition" will be remembered for subsequent calls - no need to repeat it.', '');
//    $options['id'] = $this->getArg($macroName, 'id', 'Optional id internally used for remembering source and excludeCondition. '.
//        '(only required if next() is used multiple times within an web-app.', 0);

    if ($source === 'help') {
        $this->getArg($macroName, 'data', '[list] Alternative to "source" -> provide data as a '.
            'string of comma or newline separated date values. E.g. "2021-04-29,2021-05-13,2021-05-27"', '');
        $this->getArg($macroName, 'offset', '[string] If defined, will be interpreted to compute an offset '.
            'from found event date. E.g. "-1day 12:00" (meaning "previous day at noon.").', false);
        $this->getArg($macroName, 'format', 'Optional format definition for output. See PHP\'s strftime() for reference.'.
            'E.g. "%d. %m. %Y bis %H:%M".', '');
        $this->getArg($macroName, 'every', 'Alternative to providing data: provide a date pattern like '.
            '"<strong>Every</strong> second Thursday <strong>Starting</strong> on 2021-04-22".<br>The "every" argument can '.
            'be specified as the number seconds between events.<br>'.
            'Alternatively, use the pattern "D:M:Y", where D,M,Y correspond to day,month,year. And each of these values '.
            'can be either "*" (=every) or an integer (e.g. "2" for every other...) or an integer followed by a dot (e.g. "3." for every '.
            'third ...).<br>Example: "28.:2.:4" means "Feb. 28 in every 4th year (aka leap year)". ', false);
        $this->getArg($macroName, 'start', '[ISO date] Defines the initial date of the sequence, optionally including a time. See above.', false);
        $this->getArg($macroName, 'starting', 'Synonym for "start".', false);
        $this->getArg($macroName, 'prefix', 'Optional string which will be prepended to the output', '');
        $this->getArg($macroName, 'postfix', 'Optional string which will be apppended to the output', '');
        $this->getArg($macroName, 'excludeCondition', 'Optional string that will be evaluated against '.
            'each event record to determine whether to skip given event. E.g. "(\$rec[\'canceled\'] !== \'\'". '.
            'Value of "excludeCondition" will be remembered for subsequent calls - no need to repeat it.', '');
        $this->getArg($macroName, 'id', 'Optional id internally used for remembering source and excludeCondition. '.
            '(only required if next() is used multiple times within an web-app.', 0);

        $str = <<<'EOT'

### Format Codes (strftime):

%a      >> day of week (Mon .. Sun)
%A      >> day of week (Monday .. Sunday)
%d      >> Day (01 .. 31)
%b      >> month (Jan .. Dec)
%B      >> month (January .. December)
%m      >> month (01 .. 12)
%Y      >> Year (2000)
%H      >> Hour (00 .. 23)
%M      >> Hour (00 .. 59)

EOT;
        $this->compileMd = true;
        return $str;
    }

    $args = $this->getArgsArray($macroName);
    $nxt = new NextInTimeSequence($this->lzy, $args);
    $out = $nxt->render();

	$this->optionAddNoComment = true;
	return $out;
});




