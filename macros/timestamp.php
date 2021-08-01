<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
    $format = $this->getArg($macroName, 'format', 'Format of output &rarr; see PHP strftime().');
    $offset = $this->getArg($macroName, 'offset', 'Offset from now, &rarr; see PHP strtotime().');
    $wrapper = $this->getArg($macroName, 'wrapper', 'Tag name of optional wrapper.');

    if (trim($format) === 'help') {
        $str = <<<EOT
## Typical Format Codes:

|===
|%a 	|An abbreviated textual representation of the day 	|Sun through Sat
|---
|%d 	|Two-digit day of the month (with leading zeros) 	|01 to 31
|---
|---
|%b 	|Abbreviated month name, based on the locale 	|Jan through Dec
|---
|%m 	|Two digit representation of the month 	|01 (for January) through 12 (for December)
|---
|---
|%Y 	|Four digit representation for the year 	|Example: 2038
|---
|---
|%H 	|Two digit representation of the hour in 24-hour format 	|00 through 23
|---
|%M 	|Two digit representation of the minute 	|00 through 59
|---
|%S 	|Two digit representation of the second 	|00 through 59
|---
|---
|%c 	|Preferred date and time stamp based on locale 	|Example: Tue Feb 5 00:45:10 2009 for February 5, 2009 at 12:45:10 AM
|---
|%F 	|ISO-Date 	|Example: 2009-02-05
|===

See https://www.php.net/manual/en/function.strftime.php for reference.

EOT;
        $this->compileMd = true;
        return $str;
    }
    $this->optionAddNoComment = true;

    $t = time();

    if (!$format) {
        $format = '%c';
    }
    if ($offset) {
        $t = strtotime($offset, $t);
    }

    $str = strftime($format, $t);
    if ($wrapper) {
        $str = "\n<$wrapper class='lzy-timestamp'>$str</$wrapper>\n";
    }
	return $str;
});
