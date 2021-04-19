<?php
// see https://www.php.net/manual/en/function.strftime.php


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$every = $this->getArg($macroName, 'every', '[string] Specifies the interval between recurring events. '.
        'E.g. "4 days" or "1 week" etc.', false);
	$this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($every === 'help') {
        $this->getArg($macroName, 'start', '[ISO Date] Defines the initial date from which to count.', false);
        $this->getArg($macroName, 'offset', '[integer, string] If set, defines an offset from "start". E.g. "3600" (sec),'.
            ' "-1 day" or "12:00".', false);
        $this->getArg($macroName, 'format', '[strftime] Defines the format in which to return the result. Default is ISO Date.', '%F');
        $this->getArg($macroName, 'from', '[file] Alternative to specifying "every" and "start": specifies the file '.
            'from which to pull dates. Supported file types: txt, yaml, json, csv. Optionally, you can append a data-key '.
            'in like "file.yaml:data-key".', false);
        $this->getArg($macroName, 'id', '[unique string] If defined, found date is stored under that name. Further calls '.
            'to recurring() can skip arguments  "every" and "start" -> the last returned date will be re-used instead.', "lzy-recurring-$inx");
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
    $rc = new Recurring($args);
    $this->optionAddNoComment = true;
    return $rc->render();
});



class Recurring
{
    public function __construct($args)
    {
        $this->every = isset($args['every']) ? $args['every'] : false;
        $this->start = isset($args['starting']) ? $args['starting'] : false;
        $this->start = isset($args['start']) ? $args['start'] : $this->start;
        $this->offset = isset($args['offset']) ? $args['offset'] : false;
        $this->format = isset($args['format']) ? $args['format'] : '%F';
        $this->from = isset($args['from']) ? $args['from'] : false;
        $this->id = isset($args['id']) ? $args['id'] : false;

    } // __construct



    public function render()
    {
        if ($this->every && $this->start) {
            $next = $this->determineNext();
            $_SESSION['lizzy']['recurring'][$this->id] = $next;

        } elseif ($this->from) {
            $next = $this->getFromSource();
            if (!$next) {
                return '{{ lzy-recurring-no-event }}';
            } elseif (is_string($next)) {
                return $next; // error msg
            }
            $_SESSION['lizzy']['recurring'][$this->id] = $next;

        } elseif (isset($_SESSION['lizzy']['recurring'][$this->id])) {
            $next = $_SESSION['lizzy']['recurring'][$this->id];
            $next = $this->handleOffset($next);

        } elseif (!$this->start) {
            die('Error in recurring macro: argument \'start\' not specified');
        } elseif (!$this->every) {
            die("Error in recurring macro: argument 'every' not specified: '$this->every'");
        }

        $str = strftime($this->format, $next);
        return $str;
    } // render



    private function determineNext()
    {
        $startT = strtotime($this->start);

        if (!preg_match('/\+? (\d*) \s* (\w+)/x', $this->every, $m)) {
            die("Error in recurring macro: argument 'every' not specified: '$this->every'");
        } else {
            $n = $m[1];
            if (!$n) {
                $n = 1;
            } else {
                $n = intval($n);
            }
            $unit = $m[2];
        }

        // now evaluate:
        if ($unit[0] === 'd') {
            $intervalT = 86400 * $n;
            $next = $this->findNext($startT, $intervalT);

        } elseif ($unit[0] === 'w') {
            $intervalT = 604800 * $n;
            $next = $this->findNext($startT, $intervalT);

        } else {
            $now = time();
            $incr = "$n $unit";
            $next = strtotime("+$incr", $startT);
            $i = 1;
            while (($next < $now) && ($i < 30)) {
                $i++;
                $incr = ($n * $i)." $unit";
                $next = strtotime("+$incr", $startT);
            }
        }

        $next = $this->handleOffset($next);

        return $next;
    } // determineNext



    private function findNext($startT, $intervalT)
    {
        $now = time();
        $t = $startT;
        $i = 100;
        while (($t < $now) && $i--) {
            $t += $intervalT;
        }
        return $t;
    } // findNext



    private function getFromSource()
    {
        $dataKey = false;
        $from = $this->from;
        if (preg_match('/(.*?) : (.*)/x', $from, $m)) {
            $dataKey = $m[2];
            $from = $m[1];
        }
        $file = resolvePath($from, true);
        if (!file_exists($file)) {
            die("Recurring(): file not found");
        }
        $ext = fileExt($file);

        if ($ext === 'txt') {
            $str = getFile($file, true);
            $list = explode("\n", $str);

        } elseif (strpos('yaml,csv,json', $ext) !== false) {
            $db = new DataStorage2($file);
            $data = $db->read();
            if (!$dataKey || ($dataKey === 'index')) {
                $list = array_keys($data);

            } else {
                $list = array_map(function ($rec) use ($dataKey) {
                    $val = $rec[ $dataKey ];
                    return $val;
                }, $data);
                $list = array_values($list);
            }
        } else {
            return '{{ lzy-recurring-unknown-data-source }}';
        }

        $list = array_map(function ($val) {
            if (is_string($val)) {
                $val = strtotime($val);
            }
            return $val;
        }, $list);
        sort($list);
        $now = time();

        foreach ($list as $t) {
            if ($t >= $now) {
                return $t;
            }
        }
        return false;
    } // getFromSource




    private function handleOffset($next)
    {
        if (!$this->offset) {
            return $next;
        }

        if (is_numeric($this->offset)) {
            $offset = intval($this->offset);

        } elseif (is_string($this->offset)) {
            $c1 = $this->offset[0];
            if (($c1 === '-') || ($c1 === '+')) {
                return strtotime($this->offset, $next);

            } elseif (preg_match('/^\d\d:\d\d$/', $this->offset)) {
                $offset = strtotime("1970-01-01 $this->offset UTC");
            } else {
                $offset = intval($this->offset);
            }
        } else {
            $offset = 0;
        }
        $next += $offset;
        return $next;
    } // handleOffset

} // Recurring

