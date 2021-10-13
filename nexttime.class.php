<?php

class NextInTimeSequence
{
    private $persistant = null;

    public function __construct($lzy, $options)
    {
        $this->lzy = $lzy;
        $this->parseArgs( $options );
        $this->persistant = &$GLOBALS['lizzy']['nextEvent'][$this->id];
    } // __construct




    public function render()
    {
        $event = $this->determineNextEvent();

        $out = $this->applyFormat($event);

        // apply optional pre-and postfix:
        $out = "$this->prefix$out$this->postfix";

        return $out;
    } // render



    public function get( $what = false )
    {
        return $this->determineNextEvent( $what );
    } // getRec



    private function determineNextEvent( $returnRec = false )
    {
        $data = $this->getData();

        if (!$data || !is_array($data)) {
            return '<div class="lzy-nexttime">{{ lzy-nexttime-no-event-found }}</div>';
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
            } else {
                $next = 0;
            }
        }
        if (!$next) {
            return '<div class="lzy-nexttime">{{ lzy-nexttime-no-event-found }}</div>';
        }
        if ($returnRec === null) {
            return $next;
        } elseif ($returnRec) {
            return $rec;
        }

        $event = $this->applyOffset( $event, $dayOfEvent );
        return $event;
    } // determineNextEvent




    private function getData()
    {
        $source = $this->source;
        $data = $this->data;
        $dateKey = false;

        // check cached data:
        if (!$source && !$data && !$this->every && !$this->start) {
            if (isset($this->persistant['data']) &&
                $this->persistant['data']) {
                $data = $this->persistant['data'];
                $this->dateKey = $this->persistant['dateKey'];
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
        if ($data) {
            if ($dateKey) {
                usort($data, function ($a, $b) use ($dateKey) {
                    return ($a[$dateKey] > $b[$dateKey]);
                });
            } else {
                sort($data);
            }
        }

        // cache data and dateKey for later re-use:
        $this->persistant['data'] = $data;
        $this->dateKey = $this->persistant['dateKey'] = $dateKey;
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
                $pattern = "+1 day";
            } elseif (strpos($days, '.') === false) {
                $pattern = "+$days days";
            } else {
                $days = str_pad($days, 2, '0', STR_PAD_LEFT);
                $pattern2 = "1970-01-$days";
            }

            $month = @$a[1];
            if ($month === '*') {
                $pattern .= " +1 month";
            } elseif (strpos($month, '.') === false) {
                $pattern .= " +$month months";
            } else {
                $month = str_pad($month, 2, '0', STR_PAD_LEFT);
                $pattern2 = "1970-$month-".substr($pattern2,-2);
            }

            $years = @$a[2];
            if ($years === '*') {
                $pattern .= " +1 year";
            } elseif (strpos($years, '.') === false) {
                $pattern .= " +$years years";
            } else {
                $years = str_pad($years, 4, '20', STR_PAD_LEFT);
            }

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



    public function today( $includeHour = false)
    {
        // for testing: url-arg 't' may inject a "test-now":
        if (isset($_GET['now'])) {
            $now = strtotime( $_GET['now'] );
        } else {
            $now = time();
        }
        if ($includeHour) {
            return $now;
        } else {
            return strtotime(date('Y-m-d', $now));
        }
    } // today



    private function parseArgs( $options )
    {
        if (!isset($options['source'])) { $options['source'] = false; }
        if (!isset($options['data'])) { $options['data'] = false; }

        if (strpos($options['source'], ',') === false) {
            $this->source = $options['source'];
            $this->data = null;
            $this->data   = $options['data'];
        } else {
            $this->data   = $options['source'];
            $this->source = false;
        }
        $this->offset = @$options['offset'];
        $this->format = @$options['format'];
        $this->prefix = @$options['prefix'];
        $this->postfix = @$options['postfix'];
        $this->every = @$options['every'];
        if ($this->every) {
            $this->every = str_replace(['<em>','</em>'], '*', $this->every);
        }
        $this->start = @$options['start'];
        if (@$options['starting']) {
            $this->start = $options['starting'];
        }
        $excludeCondition = @$options['excludeCondition'];
        $id = $this->id = isset($options['id']) ? $options['id'] : 0;

        if (isset($GLOBALS['lizzy']['nextEvent'][$id]['excludeCondition']) && ($this->source || $this->data)) {
            unset($GLOBALS['lizzy']['nextEvent'][$id]['excludeCondition']);
        }

        if ($excludeCondition) {
            $excludeCondition = replaceCodesByQuotes( $excludeCondition );
            if (strpos($excludeCondition, 'return ') === false) {
                $excludeCondition = 'return '.$excludeCondition;
            }
            $excludeCondition = trim($excludeCondition, ';') . ';';
            $GLOBALS['lizzy']['nextEvent'][$id]['excludeCondition'] = $excludeCondition;
        } elseif (isset($GLOBALS['lizzy']['nextEvent'][$id]['excludeCondition'])) {
            $excludeCondition = $GLOBALS['lizzy']['nextEvent'][$id]['excludeCondition'];
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
        if (!is_int($event)) {
            return '';
        }
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

