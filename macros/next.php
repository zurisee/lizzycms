<?php


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');

    $source = $options['source'] = $this->getArg($macroName, 'source', '[file.yaml|file.txt] Data source to obtain event dates from. In case '.
        'of DB, you need to append the key of the date field, separated by colon, e.g. "file.yaml:key". '.
        'Value of "source"  will be remembered for subsequent calls - no need to repeat it.', '');
    $options['data'] = $this->getArg($macroName, 'data', '[list] Alternative to "source" -> provide data as a '.
        'string of comma or newline separated date values. E.g. "2021-04-29,2021-05-13,2021-05-27"', '');
    $options['offset'] = $this->getArg($macroName, 'offset', '[string] If defined, will be interpreted to compute an offset '.
        'from found event date. E.g. "-1day 12:00" (meaning "previous day at noon.").', false);
    $options['format'] = $this->getArg($macroName, 'format', 'Optional format definition for output. See PHP\'s strftime() for reference.'.
        'E.g. "%d. %m. %Y bis %H:%M".', '');
    $options['every'] = $this->getArg($macroName, 'every', 'Alternative to providing data: provide a date pattern like '.
        '"<strong>Every</strong> second Thursday <strong>Starting</strong> on 2021-04-22".<br>The "every" argument can '.
        'be specified as the number seconds between events.<br>'.
        'Alternatively, use the pattern "D:M:Y", where D,M,Y correspond to day,month,year. And each of these values '.
        'can be either "*" (=every) or an integer (e.g. "2" for every other...) or an integer followed by a dot (e.g. "3." for every '.
        'third ...).<br>Example: "28.:2.:4" means "Feb. 28 in every 4th year (aka leap year)". ', false);
    $options['start'] = $this->getArg($macroName, 'start', '[ISO date] Defines the initial date of the sequence, optionally including a time. See above.', false);
    $options['starting'] = $this->getArg($macroName, 'starting', 'Synonym for "start".', false);
    $options['prefix'] = $this->getArg($macroName, 'prefix', 'Optional string which will be prepended to the output', '');
    $options['postfix'] = $this->getArg($macroName, 'postfix', 'Optional string which will be apppended to the output', '');
    $options['excludeCondition'] = $this->getArg($macroName, 'excludeCondition', 'Optional string that will be evaluated against '.
        'each event record to determine whether to skip given event. E.g. "(\$rec[\'canceled\'] !== \'\'". '.
        'Value of "excludeCondition" will be remembered for subsequent calls - no need to repeat it.', '');
    $options['id'] = $this->getArg($macroName, 'id', 'Optional id internally used for remembering source and excludeCondition. '.
        '(only required if next() is used multiple times within an web-app.', 0);

    if ($source === 'help') {
        return '';
    }

    $nxt = new NextInTimeSequence($this, $options);
    $out = $nxt->render();

	$this->optionAddNoComment = true;
	return $out;
});




class NextInTimeSequence
{
    public function __construct($trans, $options)
    {
        $this->lzy = $trans->lzy;
        $this->parseArgs( $options );
    } // __construct




    public function render()
    {
        $data = $this->getData();

        if (!$data || !is_array($data)) {
            return "Error in next(): no data found.";
        }

        $today = $this->today();    // optionally overridden by url-arg 'now'

        // loop through list of events, till one is equal or greater than today:
        $event = 0;
        $dateKey = $this->dateKey;
        foreach ($data as $rec) {
            if (!$rec) { continue; }
            if ($dateKey) {
                $next = strtotime($rec[ $dateKey ]); // may or may not carry a time
            } elseif (!is_numeric($rec)) {
                $next = strtotime($rec);
            } else {
                $next = $rec;
            }
            $dayOfEvent = strtotime(date('Y-m-d', $next));
            if ($this->excludeCondition) {
                try {
                    $skip = eval( $this->excludeCondition );
                } catch (Exception $e) {
                    die( 'Error in next() -> excludeCondition: '. $e->getMessage());
                }
            } else {
                $skip = false;
            }
            if (!$skip && ($dayOfEvent >= $today)) {
                $event = $next;
                break;
            }
        }

        $event = $this->applyOffset( $event, $dayOfEvent );

        $out = $this->applyFormat($event);

        // apply optional pre-and postfix:
        $out = "$this->prefix$out$this->postfix";

        return $out;
    } // render




    private function getData()
    {
        $source = $this->source;
        $data = $this->data;
        $dateKey = false;

        // check cached data:
        if (!$source && !$data && !$this->every && !$this->start) {
            if (isset($_SESSION['lizzy']['nextEvent'][$this->id]['data']) &&
                $_SESSION['lizzy']['nextEvent'][$this->id]['data']) {
                $data = $_SESSION['lizzy']['nextEvent'][$this->id]['data'];
                $this->dateKey = $_SESSION['lizzy']['nextEvent'][$this->id]['dateKey'];
                return $data;
            }
        }

        // check whether data is supplied in source arg:
        if ((strpos($source, ',') !== false) || (strpos($source, "\n") !== false)) {
            $data = $source;
            $source = false;
        }

        // read data from external source:
        if ($source) {
            if (preg_match('/^ (.*) : (.*) $/x', $source, $m)) {
                $sourceFile = resolvePath( $m[1], true);
                $dateKey = $m[2];
                $db = new DataStorage2($sourceFile);
                $data = $db->read();
            } else {
                $source = resolvePath($source);
                $str = getFile($source, true, true);
                $data = explodeTrim(",\n", $str);
            }

        } elseif ($data) {        // get data supplied inline
            $data = explodeTrim(",\n", $data);
        } elseif ($this->every && $this->start) {
            $data = $this->constructSequence();
        }

        // sort data, if dateKey is defined, sort on that element:
        if ($dateKey) {
            usort($data, function($a,$b) use($dateKey){
                return ($a[$dateKey] > $b[$dateKey]);
            });
        } else {
            sort($data);
        }

        // cache data and dateKey for later re-use:
        $_SESSION['lizzy']['nextEvent'][$this->id]['data'] = $data;
        $this->dateKey = $_SESSION['lizzy']['nextEvent'][$this->id]['dateKey'] = $dateKey;
        return $data;
    } // getData



    private function constructSequence()
    {
        $data = [];
        $every = $this->every;
        $next = strtotime( $this->start );
        if (!$next) {
            die("Error in next(): argument 'start' could not be interpreted - must be an ISO date.");
        }
        $today = $this->today();
        if (is_numeric($every)) {
            while ($next < $today) {
                $next = strtotime("+$every days", $next);
            }
            $data[] = strtotime("-$every days", $next);
            $data[] = $next;
            $data[] = strtotime("+$every days", $next);

        } else {
            $a = explodeTrim(':', $every);
            if (!isset($a[0])) {
                die("Error in next(): argument 'every' could not be interpreted - must be a number of pattern 'D:M:Y'.");
            }

            $pattern = '';
            $pattern2 = '';
            $days = $a[0];
            if ($days === '*') {
//                $days = 1;
                $pattern = "+1 day";
            } elseif (strpos($days, '.') === false) {
                $pattern = "+$days days";
            } else {
                $days = str_pad($days, 2, '0', STR_PAD_LEFT);
                $pattern2 = "1970-01-$days";
//                die("next() -> constructSequence(): not impl yet ");
            }

            $month = @$a[1];
//            if (!$month) {
//                $month = 0;
//            } else
            if ($month === '*') {
                $pattern .= " +1 month";
            } elseif (strpos($month, '.') === false) {
                $pattern .= " +$month months";
            } else {
                $month = str_pad($month, 2, '0', STR_PAD_LEFT);
                $pattern2 = "1970-$month-".substr($pattern2,-2);
//                die("next() -> constructSequence(): not impl yet ");
            }

            $years = @$a[2];
            if ($years === '*') {
                $pattern .= " +1 year";
            } elseif (strpos($years, '.') === false) {
                $pattern .= " +$years years";
            } else {
                $years = str_pad($years, 4, '20', STR_PAD_LEFT);
//                die("next() -> constructSequence(): not impl yet ");
            }
            $pattern2 = $years.substr($pattern2, 5);

//            $pattern = '';
            if ($days) {
                $pattern = "+$days days";
            }
            if ($month) {
                $pattern .= " +$month months";
            }
            if ($years) {
                $pattern .= " +$years years";
            }
            while ($next < $today) {
                $next = strtotime($pattern, $next);
            }
            $_pattern = str_replace('+', '&', $pattern);
            $_pattern = str_replace('-', '+', $_pattern);
            $_pattern = str_replace('&', '-', $_pattern);
            $data[] = strtotime($_pattern, $next);
            $data[] = $next;
            $data[] = strtotime($pattern, $next);
        }
        return $data;
    } // constructSequence



    private function today()
    {
        // for testing: url-arg 't' may inject a "test-now":
        if (isset($_GET['now'])) {
            $now = strtotime( $_GET['now'] );
        } else {
            $now = time();
        }
        return strtotime(date('Y-m-d', $now));
    } // today



    private function parseArgs( $options )
    {
        if (strpos($options['source'], ',') === false) {
            $this->source = $options['source'];
            $this->data = null;
            $this->data   = $options['data'];
        } else {
            $this->data   = $options['source'];
            $this->source = false;
        }
        $this->offset = $options['offset'];
        $this->format = $options['format'];
        $this->prefix = $options['prefix'];
        $this->postfix = $options['postfix'];
        $this->every = $options['every'];
        if ($this->every) {
            $this->every = str_replace(['<em>','</em>'], '*', $this->every);
        }
        $this->start = $options['start'];
        if ($options['starting']) {
            $this->start = $options['starting'];
        }
        $excludeCondition = $options['excludeCondition'];
        $id = $this->id = $options['id'];

        if (isset($_SESSION['lizzy']['nextEvent'][$id]['excludeCondition']) && ($this->source || $this->data)) {
            unset($_SESSION['lizzy']['nextEvent'][$id]['excludeCondition']);
        }

        if ($excludeCondition) {
            $excludeCondition = replaceCodesByQuotes( $excludeCondition );
            if (strpos($excludeCondition, 'return ') === false) {
                $excludeCondition = 'return '.$excludeCondition;
            }
            $_SESSION['lizzy']['nextEvent'][$id]['excludeCondition'] = $excludeCondition;
        } elseif (isset($_SESSION['lizzy']['nextEvent'][$id]['excludeCondition'])) {
            $excludeCondition = $_SESSION['lizzy']['nextEvent'][$id]['excludeCondition'];
        }
        $this->excludeCondition = $excludeCondition;
    } // parseArgs




    private function applyOffset( $event, $eventDay )
    {
        // apply optional offset:
        if (!$this->offset) {
            return $event;
        }

        // if offset is numeric, we assume it's in nr of seconds -> just add it to event:
        if (is_numeric($this->offset)) {
            $event += $this->offset;
            return $event;
        }

        // offset defined as string of pattern "{offset} {time}":
        $offsetDays = 0;
        $offsetTime = 0;
        if (preg_match('/([+-]\s?\d+) .*? (\d\d:\d\d) /x', $this->offset, $m)) {
            $offsetDays = $m[1];
            $offsetTime = $m[2];
        } elseif (preg_match('/ (\d\d:\d\d) /x', $this->offset, $m)) {
            $offsetDays = false;
            $offsetTime = $m[1];
        } elseif (preg_match('/([+-]\s?\d+) /x', $this->offset, $m)) {
            $offsetDays = $m[1];
            $offsetTime = false;
        }
        if (intval($offsetDays)) {
            $eventDay = strtotime("$offsetDays days", $eventDay);
        }
        if ($offsetTime) {
            $event = $eventDay + strtotime("1970-01-01 $offsetTime UTC");
        } else {
            $event = $eventDay;
        }
        return $event;
    } // applyOffset




    private function applyFormat($event)
    {
        // apply optional format:
        if ($this->format) {
            $out = strftime($this->format, $event);
        } else {
            $out = date('Y-m-d H:i', $event); // default: ISO
            if (strpos($out, ' 00:00') !== false) {
                $out = str_replace(' 00:00', '', $out);
            }
        }
        return $out;
    } // applyFormat

} // NextInTimeSequence