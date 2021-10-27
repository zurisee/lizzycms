<?php

define('LOG_PATH', '.#logs/');
define('MAX_URL_ARG_SIZE', 16000);
define('DEFAULT_ASPECT_RATIO', 0.6667);
define('LOG_WIDTH', 80);

use Symfony\Component\Yaml\Yaml;



function parseArgumentStr($str, $delim = ',', $yamlCompatibility = false)
{
    $str0 = $str;
    if (strpos($str, '↵') !== false) {
        $str = trim($str, '↵ ');
        if (!$str) {
            return [];
        }
        $str = preg_replace('|\s//.*?↵|', '', $str); // remove inline comments
        $str = str_replace("\t", '    ', $str);
    }
    if (!($str = trim($str))) {
        return [];
    }

    // skip '{{ ... }}' to avoid conflict with '{ ... }':
    if (preg_match('/^\s* {{ .* }} \s* $/x', $str)) {
        return [ $str ];
    }

    $options = [];

    if ($yamlCompatibility) {
        // for compatibility with Yaml, the argument list may come enclosed in { }
        if (preg_match('/^\s* {  (.*)  } \s* $/x', $str, $m)) {
            $str = $m[1];
        }
    }
    $supportedBrackets1 = [
        '"' => '"',
        "'" => "'",
    ];
    $supportedBrackets2 = [
        '"' => '"',
        "'" => "'",
        '(' => ')',
        '[' => ']',
        '<' => '>',
    ];

    $assoc = false;
    while ($str || $assoc) {
        $str = trim($str, '↵ ');
        $c = (isset($str[0])) ? $str[0] : '';
        $val = '';

        // grab next value, can be bare or enclosed:
        if ($assoc && !$yamlCompatibility) {
            $supportedBrackets = &$supportedBrackets2;
        } else {
            $supportedBrackets = &$supportedBrackets1;
        }
        // extended brackets to enclose args: e.g. ## ... ##
        // Note: special case '!!' -> skips translation to HTML-quotes
        $cc = false;
        if ($c && (strpos(TRANSVAR_ARG_QUOTES, $c) !== false)) {
            $cc = preg_quote("$c$c");
        }
        if ($cc && preg_match("/^ $cc ([^$c]+) $cc (.*) $/x", $str, $m)) {
            $val = $m[1];
            $str = $m[2];

        } elseif (array_key_exists($c, $supportedBrackets)) {
            $cEnd = $supportedBrackets[$c];
            $p = findNextPattern($str, $cEnd, 1);
            if ($p) {
                $val = substr($str, 1, $p - 1);
                $val = str_replace('\\"', '"', $val);
                $str = trim(substr($str, $p + 1));
                $str = preg_replace('/^\s*↵\s*$/', '', $str);
            } else {
                fatalError("Error in key-value string: '$str0'", 'File: '.__FILE__.' Line: '.__LINE__);
            }

        } elseif ($c === '{') {    // -> {
            $p = findNextPattern($str, "}", 1);
            if ($p) {
                $val = substr($str, 1, $p - 1);
                $val = str_replace("\\}", "}", $val);

                $val = parseArgumentStr($val);

                $str = trim(substr($str, $p + 1));
                $str = preg_replace('/^\s*↵\s*$/', '', $str);
            } else {
                fatalError("Error in key-value string: '$str0'", 'File: '.__FILE__.' Line: '.__LINE__);
            }

        } else {    // -> bare value
            $rest = strpbrk($str, ':'.$delim);
            if ($rest) {
                $val = substr($str, 0, strpos($str, $rest));
            } else {
                $val = $str;
            }
            $str = $rest;
        }

        // shape value:
        if ($val === 'true') {
            $val = true;
        } elseif ($val === 'false') {
            $val = false;
        } elseif (is_string($val)) {
            if (preg_match('/^ (!?) (isLoggedin|isPrivileged|isAdmin) $/x', $val, $m)) {
                $GLOBALS['lizzy']['cachingActive'] = false;
                $val = false;
                if ($m[2] === 'isAdmin') {
                    $val = $GLOBALS['lizzy']['isAdmin'];
                } elseif ($m[2] === 'isPrivileged') {
                    $val = $GLOBALS['lizzy']['isPrivileged'];
                } elseif ($m[2] === 'isLoggedin') {
                    $val = $GLOBALS['lizzy']['isLoggedin'];
                } elseif ($cc !== '\!\!') {
                    $val = str_replace(['"', "'"], ['&#34;', '&#39;'], $val);
                }
                if ($m[1]) {
                    $val = !$val;
                }
            } elseif ($cc !== '\!\!') {
                $val = str_replace(['"', "'"], ['&#34;', '&#39;'], $val);
            }
        }

        // now, check whether it's a single value or a key:value pair
        if ($str && ($str[0] === ':')) {         // -> key:value pair
            $str = substr($str, 1);
            $assoc = true;
            $key = $val;

        } elseif (!$str || ($str[0] === $delim)) {  // -> single value
            if ($assoc) {
                $options[$key] = $val;
            } else {
                $options[] = $val;
            }
            $assoc = false;
            $str = ltrim(substr($str, 1));

        } else {    // anything else is an error
            fatalError("Error in argument string: '$str0'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    }

    return $options;
} // parseArgumentStr



function parseInlineBlockArguments($str, $returnElements = false, $lzy = null)
{
    // Example: span  #my-id  .my-class  color:orange .class2 aria-expanded=false line-height: '1.5em;' !off .class3 aria-hidden= 'true' lang=de-CH literal=true md-compile=false "some text"
    // Usage:
    //   list($tag, $id, $class, $style, $attr, $text, $comment) = parseInlineBlockArguments($str, true);
    //   list($tag, $str, $lang, $comment, $literal, $mdCompile, $text) = parseInlineBlockArguments($str);

    $tag = $id = $class = $style = $attr = $lang = $comment = $text = '';
    $literal = false;
    $metaOut = '';
    $mdCompile = true;
    $elems = [];
    $str = preg_replace('|//.*|', '', $str);    // ignore //-style comments

    // special commands: !lang, !off, !literal, !md-compile
    while (preg_match('/(.*) !(\S+) (.*)/x', $str, $m)) {      // !arg
        $str = $m[1].$m[3];
        if (strpos($m[2], '=') !== false) {
            $arr = explode('=', strtolower($m[2]));
            $meta = isset($arr[0]) ? strtolower($arr[0]) : '';
            $v = isset($arr[1]) ? strtolower($arr[1]) : '';
        } else {
            $meta = strtolower($m[2]);
            $v = 'true';
        }
        $metaOut .= "$meta,";
        if ($meta === 'lang') {                                                  // lang
            $attr .= " lang='$v' data-lang='$v'";
            $lang = $v;

        } elseif ($meta === 'off') {                                             // off
            $style = ' display:none;';

        } elseif ($meta === 'literal') {                                         // literal
            $literal = $v? (stripos($v, 'true') !== false): true;

        } elseif ($meta === 'md-compile') {                                      // md-compile
            $mdCompile = $v? (stripos($v, 'true') !== false): true;

        } elseif ($meta === 'showtill') {                                      // showTill
            $t = strtotime($v);
            if ($t < time()) {
                $lang = 'none';
            }

        } elseif ($meta === 'showfrom') {                                      // showFrom
            $t = strtotime($v);
            if ($t > time()) {
                $lang = 'none';
            }

        } elseif (($meta === 'visible') || ($meta === 'visibility')) {         // visible
            if (!$v || ($v === 'false') || !$lzy->auth->checkPrivilege($v)) {
                $lang = 'none';
            }
        }
    }

    // style instructions:  key:value
    if (preg_match_all('/([\w-]+:\s*[^\s,]+)/x', $str, $m)) {  // style:arg
        foreach ($m[1] as $elem) {
            $s = str_replace([';', '"', "'"], '', $elem);
            $style .= " $s;";
        }
        $str = str_replace($m[0], '', $str);
    }
    if (!$returnElements && $style) {
        $style = ' style="'. trim($style) .'"';
    }

    // attribute instructions:  key=value
    if (preg_match_all('/( [\w-]+ =\s* " .*? " ) /x', $str, $m)) {  // attr="arg "
        $elems = $m[1];
        $str = str_replace($m[1], '', $str);
    }
    if (preg_match_all("/( [\w-]+ =\s* ' .*? ' ) /x", $str, $m)) {  // attr='arg '
        $elems = array_merge($elems, $m[1]);
        $str = str_replace($m[1], '', $str);
    }
    if (preg_match_all("/( [\w-]+ =\s* [^\s,]+ ) /x", $str, $m)) {  // attr=arg
        $elems = array_merge($elems, $m[1]);
        $str = str_replace($m[1], '', $str);
    }
    foreach ($elems as $elem) {
        list($name, $arg) = explode('=', $elem);
        $arg = trim($arg);
        $ch1 = isset($arg[0]) ? $arg[0]: '';
        if ($ch1 === '"') {
            $arg = trim($arg, '"');
        } elseif ($ch1 === "'") {
            $arg = trim($arg, "'");
        }

        // pseudo attributes (for compatibility): lang=, literal=true, md-compile=true
        if (strtolower($name) === 'lang') {                                  // pseudo-attr: 'lang'
            $lang = $arg;
        }
        if (strtolower($name) === 'literal') {                               // pseudo-attr: 'literal'
            $literal = stripos($arg, 'true') !== false;
        } elseif (strtolower($name) === 'md-compile') {                      // pseudo-attr: 'md-compile'
            $mdCompile = stripos($arg, 'false') === false;
        } else {
            $attr .= " $name='$arg'";
        }
    }

    // #id:
    if (preg_match('/(.*) \#([\w-]+) (.*)/x', $str, $m)) {      // #id
        if ($m[2]) {    // found
            $str = $m[1].$m[3];
            if (!$returnElements) {
                $id = " id='{$m[2]}'";
            } else {
                $id = $m[2];
            }
            $comment = "#{$m[2]}"; // -> comment which can be used in end tag, e.g. <!-- /.myclass -->
        }
    }

    // .class:
    if (preg_match_all('/\.([\w-]+)/x', $str, $m)) {            // .class
        $class = implode(' ', $m[1]);
        foreach ($m[0] as $pat) {
            $p = strpos($str, $pat);
            $str = substr($str, 0, $p).substr($str, $p + strlen($pat));
        }
        $comment .= '.'.str_replace(' ', '.', $class);
    }
    if (!$returnElements && $class) {
        $class = ' class="'. trim($class) .'"';
    }

    // "text":
    if (preg_match('/(["\']) ([^\1]*) \1 /x', $str, $m)) {            // text
        $text = $m[2];
        $str = str_replace($m[0], '', $str);
    }

    // tag (i.e. no quotes):
    if (preg_match('/\b(\w+)\b/', trim($str), $m)) {                // tag
        $tag = $m[1];
    }

    if ($returnElements) {
        return [$tag, $id, $class, trim($style), $attr, $text, $comment, rtrim($metaOut, ',')];
    } else {
        $str = "$id$class$style$attr";
        return [$tag, $str, $lang, $comment, $literal, $mdCompile, $text, rtrim($metaOut, ',')];
    }
} // parseInlineBlockArguments




function csv_to_array($str, $delim = ',') {
    $str = trim($str);
    if (preg_match('/^({.*})[\s,]*$/', $str, $m)) {   // {}
        $str = preg_replace('/,*$/', '', $str);
        $a = array($str, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '');
        return $a;
    }
    $a = str_getcsv($str, $delim, "'");
    for ($i=0; $i<sizeof($a); $i++) {
        $a[$i] = trim($a[$i]);
        if (preg_match('/^"(.*)"$/', $a[$i], $m)) {
            $a[$i] = $m[1];
        }
    }
    return $a;
} // csv_to_array




function convertYaml($str, $stopOnError = true, $origin = '', $convertDates = true)
{
	$data = null;
    $str = removeHashTypeComments($str);
	if ($str) {
	    if (preg_match('/^[\'"] [^\'"]+ [\'"] (?!:) /x', $str)) {
	        $data = csv_to_array($str);

        } else {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                if ($stopOnError) {
                    fatalError("Error in Yaml-Code: <pre>\n$str\n</pre>\n" . $e->getMessage(), 'File: '.__FILE__.' Line: '.__LINE__, $origin);
                } else {
                    writeLogStr("Error in Yaml-Code: [$str] -> " . $e->getMessage(), true);
                    return null;
                }
            }
        }
	}
    if (!$convertDates) {
        return $data;
    }
    $data1 = [];
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            if (is_string($key) && ($t = strtotime($key))) {
                $data1[$t] = $value;
            } else {
                $data1[$key] = $value;
            }
        }
    }
    return $data1;
} // convertYaml




function checkYamlCache($filename)
{
    if (!file_exists($filename) || strpos($filename, 'config/users.yaml') !== false) {
        return false;
    }

    $data = false;
    $cacheFile = SYSTEM_CACHE_PATH.'yaml/'.fileExt($filename,true).'.json';
    if (file_exists($cacheFile)) {
        $t = filemtime($cacheFile);
        $t0 = filemtime($filename);
        if ($t0 < $t) {
            $data = file_get_contents($cacheFile);
            $data = json_decode($data, true);
        }
    }
    return $data;
} // checkYamlCache




function writeYamlCache($filename, $data)
{
    if (strpos($filename, 'config/users.yaml') !== false) {
        return;
    }
    $cacheFile = SYSTEM_CACHE_PATH.'yaml/'.fileExt($filename,true).'.json';
    if (!file_exists($cacheFile)) {
        preparePath($cacheFile);
    }

    $json = json_encode( $data );
    file_put_contents($cacheFile, $json);
} // writeYamlCache




function getYamlFile($filename, $returnStructure = false)
{
    if (!$returnStructure && ($data = checkYamlCache($filename))) {
        return $data;
    }
	$yaml = getFile($filename, true);
	if ($yaml) {
        $data = convertYaml($yaml);
    } else {
	    $data = [];
    }

    $structure = false;
    $structDefined = false;
    if (isset($data['_structure'])) {
        $structure = $data['_structure'];
        unset($data['_structure']);
        $structDefined = true;
    }
	if ($returnStructure) {     // return structure of data
	    if (!$structure) {      // source fild didn't contain a '_structure' record, so derive it from data:
	        $yaml = removeHashTypeComments($yaml);
            $yaml = preg_replace("/\n.*/", '', $yaml);
            $inx0 = '';
	        if (preg_match('/^ ([\'"]) ([\w\-\s:T]*) \1 : .*/x', $yaml, $m)) {
                $inx0 = $m[2];
            } elseif (preg_match('/^ ([\w\-\s:T]*) : .*/x', $yaml, $m)) {
	            $inx0 = $m[1];
            }
	        if ($inx0) {
                if (strtotime(($inx0))) {
                    if (preg_match('/\d\d:\d\d/', $inx0)) {
                        $structure['key'] = 'datetime';
                    } else {
                        $structure['key'] = 'date';
                    }
                } elseif (preg_match('/^\d+$/', $inx0)) {
                    $structure['key'] = 'number';
                } else {
                    $structure['key'] = 'string';
                }
            }
            $inxs = array_keys($data);  // get first data rec
	        if (isset($inxs[0])) {
                foreach (array_keys($data[ $inxs[0] ]) as $name) {   // extract field names
                    $structure['elements'][$name] = 'string';
                }
            }
	        if (!isset($structure['key'])) {
                $structure['key'] = 'string';
            }
        }
	    return [$data, $structure, $structDefined];
    }
    writeYamlCache($filename, $data);
	return $data;
} // getYamlFile




function convertToYaml($data, $level = 3)
{
	return Yaml::dump($data, $level);
} // convertToYaml




function writeToYamlFile($file, $data, $level = 3, $saveToRecycleBin = false)
{
	$yaml = Yaml::dump($data, $level);
	if ($saveToRecycleBin) {
        require_once SYSTEM_PATH.'page-source.class.php';
        $ps = new PageSource;
	    $ps->copyFileToRecycleBin($file);
    }
	$hdr = getHashCommentedHeader($file);
	file_put_contents($file, $hdr.$yaml);
    writeYamlCache($file, $yaml);
} // writeToYamlFile




function findFile($pat)
{
	$pat = rtrim($pat, "\n");
	$dir = array_filter(glob($pat), 'isNotShielded');
	return (isset($dir[0])) ? $dir[0] : false;
} // findFile



function findFileDeep($pattern, $flags = 0) {
    $files = glob($pattern, $flags);
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, findFileDeep($dir.'/'.basename($pattern), $flags));
    }
    return $files;
} // findFileDeep




function getFile($pat, $removeComments = false, $removeEmptyLines = false)
{
    global $lizzy;
	$pat = str_replace('~/', '', $pat);
	if (strpos($pat, '~page/') === 0) {
	    $pat = str_replace('~page/', $lizzy['pageFolder'], $pat);
    }
    if (file_exists($pat)) {
        $file = file_get_contents($pat);

    } elseif ($fname = findFile($pat)) {
		if (is_file($fname) && is_readable($fname)) {
			$file = file_get_contents($fname);
		} else {
            fatalError("Error trying to read file '$fname'", 'File: '.__FILE__.' Line: '.__LINE__);
		}
    } else {
        return false;
    }

    $file = zapFileEND($file);
    if ($removeComments === true) {
        $file = removeCStyleComments($file);
    } elseif ($removeComments) {
        $file = removeHashTypeComments($file);
    }
    if ($removeEmptyLines || strpos($removeComments, 'emptyLines')) {
        $file = removeEmptyLines($file);
    }
    return $file;
} // getFile




function zapFileEND($file)
{
    $p = strpos($file, "__END__");
    if ($p === false) {
        return $file;
    }

    if ($p === 0) {     // on first line?
        return '';
    } elseif ($file[ $p - 1] === "\n") {	// must be at beginning of line
        $file = substr($file, 0, $p);
    }
    return $file;
} // zapFileEND




function fileExists($file)
{
    global $lizzy;
    $file = str_replace('~/', '', $file);
    if (strpos($file, '~page/') === 0) {
        $file = str_replace('~page/', $lizzy['pageFolder'], $file);
    }
    return file_exists($file);

} // fileExists




function removeEmptyLines($str)
{
	$lines = explode(PHP_EOL, $str);
	foreach ($lines as $i => $l) {
		if (!$l) {
			unset($lines[$i]);
		}
	}
	return implode("\n", $lines);
} // removeEmptyLines




function getHashCommentedHeader($fileName)
{
    $str = getFile($fileName);
    if (!$str) {
        return '';
    }
	$lines = explode(PHP_EOL, $str);
    $out = '';
	foreach ($lines as $i => $l) {
		if (isset($l[0]) ) {
		    $c1 = $l[0];
		    if (($c1 !== '#') && ($c1 !== ' ')) {
		        break;
            }
		}
        $out .= "$l\n";
	}
	return $out;
} // getHashCommentedHeader




function removeHashTypeComments($str)
{
    if (!$str) {
        return '';
    }
	$lines = explode(PHP_EOL, $str);
    $lead = true;
	foreach ($lines as $i => $l) {
		if (isset($l[0]) && ($l[0] === '#')) {  // # at beginning of line
			    unset($lines[$i]);
        } elseif ($lead && !$l) {   // empty line while no data line encountered
            unset($lines[$i]);
        } else {
            $lead = false;
        }
	}
	return implode("\n", $lines);
} // removeHashTypeComments




function removeCStyleComments($str)
{
	$p = 0;
	while (($p = strpos($str, '/*', $p)) !== false) {		// /* */ style comments

        $ch_1 = $p? $str[$p-1] : "\n"; // char preceding '/*' must be whitespace
        if (strpbrk(" \n\t", $ch_1) === false) {
            $p += 2;
            continue;
        }
		$p2 = strpos($str, "*/", $p);
		$str = substr($str, 0, $p).substr($str, $p2+2);
	}

	$p = 0;
	while (($p = strpos($str, '//', $p)) !== false) {		// // style comments

		if ($p && ($str[$p-1] === ':')) {			// avoid http://
			$p += 2;
			continue;
		}

		if ($p && ($str[$p-1] === '\\')) {					// avoid shielded //
			$str = substr($str, 0, $p-1).substr($str,$p);
			$p += 2;
			continue;
		}
		$p2 = strpos($str, "\n", $p);
		if ($p2 === false) {
			return substr($str, 0, $p);
		}

		if ((!$p || ($str[$p-1] === "\n")) && ($str[$p2])) {
			$p2++;
		}
		$str = substr($str, 0, $p).substr($str, $p2);
	}
	return $str;
} // removeCStyleComments




function getDir($pat, $supportLinks = false)
{
	if (strpos($pat, '{') === false) {
        $files = glob($pat);
    } else {
        $files = glob($pat, GLOB_BRACE);
    }
    $files = array_filter($files, function ($str) {
        return ($str && ($str[0] !== '#') && (strpos($str,'/#') === false));
    });

    if ($supportLinks) {
        $path = dirname($pat) . '/';
        $fPat = basename($pat);
        $linkFiles = array_filter(glob($path . '*.link'), 'isNotShielded');
        foreach ($linkFiles as $f) {
            $linkedFiles = explode(PHP_EOL, getFile($f, 'hashTypeComments+emptyLines'));
            foreach ($linkedFiles as $f) {
                if (strpos($f, '~/') === 0) {
                    $pat1 = substr($f, 2);
                } else {
                    $pat1 = $path . $f;
                }
                $lf = glob($pat1 . $fPat);
                $files = array_merge($files, $lf);
            }
        }
    }
	return $files;
} // getDir





function getDirDeep($path, $onlyDir = false, $assoc = false, $returnAll = false)
{
    $files = [];
    $f = basename($path);
    $pattern = '*';
    if (strpos($f, '*') !== false) {
        $pattern = basename($path);
        $path = dirname($path);
    }
    $patt = '|/[_#]|';
    $patt2 = '|/[._#]|';
    if ($returnAll) {
        $patt = '|/#|';
        $patt2 = '|/[.#]|';
    }
    $it = new RecursiveDirectoryIterator($path);
    foreach(new RecursiveIteratorIterator($it) as $fileRec) {
        $f = $fileRec->getFilename();
        $p = $fileRec->getPathname();
        if ($onlyDir) {
            if (($f === '.') && !preg_match($patt, $p)) {
                $files[] = rtrim($p, '.');
            }
            continue;
        }
        if (!$returnAll && (preg_match($patt2, $p) || !fnmatch($pattern, $f))) {
            continue;
        }
        if ($assoc) {
            $files[$f] = $p;
        } else {
            $files[] =$p;
        }
    }
    return $files;
} // getDirDeep





function lastModified($path, $recursive = true, $exclude = null)
{
    $newest = 0;
    $path = resolvePath($path);

    if ($recursive) {
        $path = './' . rtrim($path, '*');

        $it = new RecursiveDirectoryIterator($path);
        foreach (new RecursiveIteratorIterator($it) as $fileRec) {
        	// ignore files starting with . or # or _
            if (preg_match('|^[._#]|', $fileRec->getFilename())) {
                continue;
            }
            $newest = max($newest, $fileRec->getMTime());
        }
    } else {
        $files = glob($path);
        foreach ($files as $file) {
            $newest = max($newest, filemtime($file));
        }
    }
    return $newest;
} // filesTime





function is_inCommaSeparatedList($keyword, $list)
{
    $list = ','.str_replace(' ', '', $list).',';
    return (strpos($list, ",$keyword,") !== false);
} // is_inCommaSeparatedList




function fileExt($file0, $reverse = false, $isUrl = false)
{
    $file = basename($file0);
    if ($isUrl) {
        $file = preg_replace(['|^\w{1,6}://|', '/[#?&:].*/'], '', $file);
    }
    if ($reverse) {
        $path = dirname($file0).'/';
        if ($path === './') {
            $path = '';
        }
        $file = pathinfo($file, PATHINFO_FILENAME);
        return $path.$file;

    } else {
        return pathinfo($file, PATHINFO_EXTENSION);
    }
} // fileExt




function isNotShielded($str)
{	// first char of file or dirname must not be '#'
	return (($str[0] !== '#') && (strpos($str,'/#') === false));
} // isNotShielded




function base_name($file, $incl_ext = true, $incl_args = false) {
	if (!$incl_args && ($pos = strpos($file, '?'))) {
		$file = substr($file, 0, $pos);
	}
	if (preg_match('/&#\d+;/',  $file)) {
		$file = htmlspecialchars_decode($file);
		$file = preg_replace('/&#\d+;/', '', $file);
	}
	if (!$incl_args && ($pos = strpos($file, '#'))) {
		$file = substr($file, 0, $pos);
	}
	if (substr($file,-1) === '/') {
		return '';
	}
	$file = basename($file);
	if (!$incl_ext) {
		$file = preg_replace("/(\.\w*)$/U", '', $file);
	}
	return $file;
} //base_name




function dir_name($path)
{
    // last element considered a filename, if doesn't end in '/' and contains a dot
    if (!$path) {
        return '';
    }

    if ($path[strlen($path)-1] === '/') {  // ends in '/'
        return $path;
    }
    $path = preg_replace('/[#?*].*/', '', $path);
    if (strpos(basename($path), '.') !== false) {  // if it contains a '.' we assume it's a file
        return dirname($path).'/';
    } else {
        return rtrim($path, '/').'/';
    }
} // dir_name




function correctPath($path)
{
	if ($path) {
		$path = rtrim($path, '/').'/';
	}
    return $path;
} // correctPath




function fixPath($path)
{
	if ($path) {
		$path = rtrim($path, '/').'/';
	}
    return $path;
} // fixPath




function commonSubstr($str1, $str2, $delim = '')
{
    $res = '';
    if ($delim) {
        $a1 = explode($delim, $str1);
        $a2 = explode($delim, $str2);

    } else {
        $a1 = str_split($str1);
        $a2 = str_split($str2);
    }

    foreach($a1 as $i => $p) {
        if (!isset($a2[$i]) || ($p !== $a2[$i])) {
            break;
        }
        $res .= $p.$delim;
    }
    return $res;
} // commonSubstr





function makePathDefaultToPage($path)
{
	if (!$path) {
		return '';
	}
	$path = rtrim($path, '/').'/';
	
	if ((($ch1=$path[0]) !== '/') && ($ch1 !== '~') && ($ch1 !== '.') && ($ch1 !== '_')) {	//default to path local to page ???always ok?
		$path = '~page/'.$path;
	}
	return $path;
} // makePathDefaultToPage




function convertFsToHttpPath($path)
{
    $pagesPath = $GLOBALS['lizzy']['pathToPage'];
    if ($path && ($path[0] !== '~') && (strpos($path, $pagesPath) === 0)) {
        $path = '~page/'.substr($path, strlen($pagesPath));
    }
    return $path;
} //convertFsToHttpPath





function resolvePathSecured($path, $relativeToCurrPage = false, $httpAccess = false, $absolutePath = false, $isResource = null, $caller = false)
{
    $path1 = resolvePath($path, $relativeToCurrPage, $httpAccess, $absolutePath, $isResource);

    $adminPermit = $GLOBALS['lizzy']['isLocalhost'] || $GLOBALS['lizzy']['isAdmin'];
    if ($adminPermit || !preg_match('/\b(code|config|.cache|\.#tickets|_lizzy)\b/', $path1)) {
        return [$path1, false]; // path is ok
    }

    mylog("=== Warning: suspicious path request in $caller(): '$path'");
    $path = str_replace('~', '&#126;', $path);
    $msg = '';
    if ($GLOBALS['lizzy']['isLocalhost']) {
        $msg = "<strong>Warning: suspicious path request in $caller(): '$path'</strong>";
    }
    return [$path1, $msg];
} // resolvePathSecured




function resolvePath($path, $relativeToCurrPage = false, $httpAccess = false, $absolutePath = false, $isResource = null)
{
    global $lizzy;
    $path = trim($path);

    // nothing to do, if first char is not '~', unless relativeToCurrPage was requested:
    if ((@$path[0] !== '~') && !$relativeToCurrPage) {
        return $path;
    }

    if (!$path) {
        if ($relativeToCurrPage) {
            $path = '~page/';
        } else {
            $path = '~/';
        }
    } elseif (preg_match('/^https?:/i', $path)) {   // https://
        return $path;
    }
    $ch1=$path[0];
    if ($ch1 === '/') {
        $path = '~docroot'.$path;
    } else {
        if ($relativeToCurrPage) {        // for HTTP Requests
            $path = makePathRelativeToPage($path);

        } else {
            if (($ch1 !== '/') && ($ch1 !== '~')) {    //default to path local to page ???always ok?
                $path = '~/' . $path;
            }
        }
    }

    $ext = fileExt($path);
    if ($isResource === null) { // not explicitly specified:
        if ($httpAccess) {      // !$isResource only possible in case of HTTP access, i.e. page request:
            $isResource = (stripos(',png,gif,jpg,jpeg,svg,pdf,css,txt,js,md,csv,json,yaml,', ",$ext,") !== false);
        } else {                // in all other cases it's a plain and normal path (including 'pages/')
            $isResource = true;
        }
    }

	$host  = isset($lizzy['host']) ? $lizzy['host'] : '';
	$appRoot  = isset($lizzy['appRoot']) ? $lizzy['appRoot'] : '';
	$pagePath  = isset($lizzy['pagePath']) ? $lizzy['pagePath'] : '';
	$pageFolder  = isset($lizzy['pageFolder']) ? $lizzy['pageFolder'] : '';
	$pathToPage  = isset($lizzy['pathToPage']) ? $lizzy['pathToPage'] : '';
	$absAppRoot = isset($lizzy['absAppRoot']) ? $lizzy['absAppRoot'] : '';


    if (preg_match('|^(~.*?/)(.*)|', $path, $m)) {
        $path = $m[2];
        if ($httpAccess) {    // http access:
            switch ($m[1]) {
                case '~/':
                    if ($absolutePath) {
                        $path = "$host$appRoot$path";
                    } else {
                        $path = "$appRoot$path";
                    }
                    break;
                case '~page/':
                    if ($isResource) { // HTTP resource file access -> include 'pages/'
                        if ($absolutePath) {
                            $path = "$host$appRoot$pathToPage$path";
                        } else {
                            $path = "$appRoot$pathToPage$path";
                        }
                    } else { // HTTP page access -> exclude 'pages/'
                        if ($absolutePath) {
                            $path = "$host$appRoot$pagePath$path";
                        } else {
                            $path = "$appRoot$pagePath$path";
                        }
                    }
                    break;
                case '~data/':
                    $path = $appRoot . $lizzy['dataPath'] . $path;
                    if ($absolutePath) {
                        $path = "$host$path";
                    }
                    break;
                case '~sys/':
                    $path = $appRoot . SYSTEM_PATH . $path;
                    if ($absolutePath) {
                        $path = "$host$path";
                    }
                    break;
                case '~ext/':
                    $path = $appRoot . EXTENSIONS_PATH . $path;
                    if ($absolutePath) {
                        $path = "$host$path";
                    }
                    break;
                case '~docroot/':
                    if ($absolutePath) {
                        $path = "$host$path";
                    } else {
                        $path = "/$path";
                    }
                    break;
            }
        } else {            // file access:
            switch ($m[1]) {
                case '~/':    //
                    if ($absolutePath) {
                        $path = "$absAppRoot$path";
                    }
                    break;
                case '~page/':    // case B
                    if ($absolutePath) {
                        $path = "$absAppRoot$pageFolder$path";
                    } else {
                        $path = "$pageFolder$path";
                    }
                    break;
                case '~data/':
                    $path = $lizzy['dataPath'] . $path;
                    if ($absolutePath) {
                        $path = "$absAppRoot$path";
                    }
                    break;
                case '~sys/':
                    $path = SYSTEM_PATH . $path;
                    if ($absolutePath) {
                        $path = "$absAppRoot$path";
                    }
                    break;
                case '~ext/':
                    $path = EXTENSIONS_PATH . $path;
                    if ($absolutePath) {
                        $path = "$absAppRoot$path";
                    }
                    break;
                case '~docroot/':
                    if ($absolutePath) {
                        $path = $_SERVER["DOCUMENT_ROOT"] . '/' . $path;
                    } else {
                        $path = "{$lizzy["filepathToDocroot"]}$path";
                    }
                    break;
            }
        }
    }

    $path = normalizePath($path);
    return $path;
} // resolvePath





function normalizePath($path)
{
    $hdr = '';
    if (preg_match('|^ ((\.\./)+) (.*)|x', $path, $m)) {
        $hdr = $m[1];
        $path = $m[3];
    }
    while ($path && preg_match('|(.*?) ([^/.]+/\.\./) (.*)|x', $path, $m)) {
        $path = $m[1] . $m[3];
    }
    if (strpos($path, '//')) {
        $x = true;
    }
    $path = str_replace('/./', '/', $path);
    $path = preg_replace('|(?<!:)//|', '/', $path);
    return $hdr.$path;
} // normalizePath





function makePathRelativeToPage($path, $resolvePath = false)
{
    if (!$path || (preg_match('/^\w{3,10}:/', $path))) {
        return $path;
    }

    if ((($ch1=$path[0]) !== '/') && ($ch1 !== '~')) {	//default to path local to page
            $path = '~page/' . $path;
    }
    if ($resolvePath) {
        $path = resolvePath($path);
    }

    return $path;
} // makePathRelativeToPage




function resolveHrefs( &$html )
{
    $appRoot = $GLOBALS['lizzy']['appRoot'];
    $prefix = $appRoot.'?lzy=';
    $p = strpos($html, '~/');
    while ($p !== false) {
        if (substr($html, $p-6, 5) === 'href=') {
            if (preg_match('|^([\w-/.]*)|', substr($html, $p + 2, 30), $m)) {
                $s = $m[1];
                if (!file_exists($s)) {
                    $html = substr($html, 0, $p) . $prefix . substr($html, $p + 2);
                }
            }
        }
        $p = strpos($html, '~/', $p + 2);
    }
} // resolveHrefs




function generateNewVersionCode()
{
    $prevRandCode = false;
    if (file_exists(VERSION_CODE_FILE)) {
        $prevRandCode = file_get_contents(VERSION_CODE_FILE);
    }
    do {
        $randCode = rand(0, 9) . rand(0, 9);
    } while ($prevRandCode && ($prevRandCode === $randCode));
    file_put_contents(VERSION_CODE_FILE, $randCode);
    return $randCode;
} // generateNewVersionCode





function getVersionCode($forceNew = false, $str = '')
{
    if (!$forceNew && file_exists(VERSION_CODE_FILE)) {
        $randCode = file_get_contents(VERSION_CODE_FILE);
    } else {
        if (!$forceNew) {
            $randCode = generateNewVersionCode();
        } else {
            $randCode = rand(0, 9) . rand(0, 9);
        }
    }
    if ($str && strpos($str, '?') !== false) {
        $str .= '&fup='.$randCode;
    } else {
        $str .= '?fup='.$randCode;
    }
    return $str;
} // getVersionCode




function parseNumbersetDescriptor($descr, $minValue = 1, $maxValue = 9, $headers = false)
{
 // extract patterns such as '1,3, 5-8', or '-3, 5, 7-'
 // don't parse if pattern contains ':' because that means it's a key:value
    if (!$descr) {
        return [];
    }
    $names = false;
    $descr = str_replace(['&#34;', '&#39;'], ['"', "'"], $descr);
	$set = parseArgumentStr($descr);
	if (!isset($set[0])) {
        foreach (array_values($set) as $i => $hdr) {
            $names[] = $hdr ? $hdr : ((isset($headers[$i])) ? $headers[$i] : array_keys($set)[$i]);
	    }
	    $set = array_keys($set);
    }

	$out = [];
	foreach ($set as $i => $elem) {
	    if ((strpos($elem, ':') === false) && preg_match('/(\S*)\s*-\s*(\S*)/', $elem, $m)) {
			$from = ($m[1]) ? alphaIndexToInt($m[1], $headers) : $minValue;
			$to = ($m[2]) ? alphaIndexToInt($m[2], $headers) : $maxValue;
			$out = array_merge($out, range($from, $to));
		} else {
		    if (preg_match('/^(\w+):(.*)/', $elem, $m)) {
		        $elem = $m[1];
		        $names[$i] = $m[2];
            }
			$inx = alphaIndexToInt($elem, $headers);
			if ($inx === 0) {
                fatalError("Error in table()-macro: unknown element '$elem'", 'File: '.__FILE__.' Line: '.__LINE__);
            }
			$out[] = $inx; //alphaIndexToInt($elem, $headers);
		}
	}
	if ($names) {
	    $keys = $out;
	    $out = [];
        foreach ($keys as $i => $inx) {
            $out[] = [$inx, isset($names[$i])? $names[$i] : $set[$i] ];
        }
    }
	return $out;
} // parseNumbersetDescriptor



define('ORD_0', ord('a')-1);

function alphaIndexToInt($str, $headers = false, $ignoreCase = true)
{
    if ($ignoreCase) {
        $str = strtolower($str);
        if ($headers) {
            $headers = array_map('strtolower', $headers);
        }
    }
    if ($headers && (($i = array_search($str, $headers, true)) !== false)) {
        $int = $i+1;

    } elseif (preg_match('/^[a-z]{1,2}$/', strtolower($str))) {
		$str = strtolower($str);
		$int = ord($str) - ORD_0;
		if (strlen($str) > 1) {
			$int = $int * 26 + ord($str[1]) - ORD_0;
		}

	} else {
		$int = intval($str);
	}
	return $int;
} // alphaIndexToInt





function setNotificationMsg($msg)
{
    // notification message is displayed once on next page load
    $_SESSION['lizzy']['reload-arg'] = $msg;
} // setNotificationMsg




function getNotificationMsg($clearMsg = true)
{
    if (isset($_SESSION['lizzy']['reload-arg'])) {
        $arg = $_SESSION['lizzy']['reload-arg'];
        if ($clearMsg) {
            clearNotificationMsg();
        }
    } else {
        $arg = getUrlArg('reload-arg', true);
    }
    return $arg;
} // getNotificationMsg





function clearNotificationMsg()
{
    unset($_SESSION['lizzy']['reload-arg']);
} // clearNotificationMsg




function getCliArg($argname, $stringMode = true)
{
	$cliarg = null;
	if (isset($GLOBALS['argv'])) {
		foreach ($GLOBALS['argv'] as $arg) {
			if (preg_match("/".preg_quote($argname)."=?(\S*)/", $arg, $m)) {
				$cliarg = $m[1];
				break;
			}
		}
	} else {
	    $cliarg = @$_REQUEST[ $argname ];
	    if (!$stringMode) {
	        $cliarg = ($cliarg !== null);
        }
    }
	return $cliarg;
} // getCliArg




function getPostData($varName, $permitNL = false, $unsetAfterRead = false)
{
    $out = false;
    if (isset($_POST) && isset($_POST[$varName])) {
        $out = $_POST[$varName];
        if (is_string($out)) {
            $out = safeStr($out, $permitNL, false);
        } elseif (is_array($out)) {
            foreach ($out as $i => $item) {
                $out[$i] = safeStr($item, $permitNL, false);
            }
        }
        if ($unsetAfterRead) {
            unset( $_POST[$varName] );
        }
    }
    return $out;
} // getPostData




function get_post_data($varName, $permitNL = false)
{
    $out = false;
    if (isset($_POST) && isset($_POST[$varName])) {
        $out = $_POST[$varName];
        $out = safeStr($out, $permitNL, false);
    }
    return $out;
} // get_post_data



function getRequestData($varName) {
    global $argv;
    $out = null;
    if (isset($_GET[$varName])) {
        $out = safeStr($_GET[$varName]);

    } elseif (isset($_POST[$varName])) {
        $out = $_POST[$varName];

    } elseif (isLocalhost() && isset($argv)) {	// for local debugging
        foreach ($argv as $s) {
            if (strpos($s, $varName) === 0) {
                $out = preg_replace('/^(\w+=)/', '', $s);
                break;
            }
        }
    }
    return $out;
} // getRequestData




function getUrlArg($tag, $stringMode = false, $unset = false)
{
 // in case of arg that is present but empty:
 // stringMode: returns value (i.e. '')
 // otherwise, returns true or false, note: empty string == true!
 // returns null if no url-arg was found
    $out = null;
	if (isset($_GET[$tag])) {
	    if ($stringMode) {
            $out = safeStr($_GET[$tag], false, true);

        } else {    // boolean mode
            $arg = $_GET[$tag];
            $out = (($arg !== 'false') && ($arg !== '0') && ($arg !== 'off') && ($arg !== 'no'));
        }
		if ($unset) {
			unset($_GET[$tag]);
		}
	}
	return $out;
} // getUrlArg




function getUrlArgStatic($tag, $stringMode = false, $varName = false)
{
 // like get_url_arg()
 // but returns previously received value if corresp. url-arg was not recived
 // returns null if no value has ever been received
	if (!$varName) {
		$varName = $tag;
	}
    $out = getUrlArg($tag, $stringMode);
	if ($out !== null) {    // -> received new value:
        $_SESSION['lizzy'][$varName] = $out;
    } elseif (isset($_SESSION['lizzy'][$varName])) { // no val received, but found in static var:
	    $out = $_SESSION['lizzy'][$varName];
    }
	return $out;
} // getUrlArgStatic




function setStaticVariable($varName, $value, $append = false)
{
    if (strpos($varName, '.') === false) {  // scalar static var:
        if (!isset($_SESSION['lizzy'][$varName])) {
            $_SESSION['lizzy'][$varName] = null;
        }
        $var = &$_SESSION['lizzy'][$varName];

    } else {                                        // nested static var:
        $a = explode('.', $varName);
        $var = &$_SESSION['lizzy'];
        foreach ($a as $item) {
            if (!isset($var[ $item ])) {
                $var[ $item ] = false;
            } elseif (!is_array($var[ $item ])) {
                unset( $var[ $item ] );
                $var[ $item ] = false;
            }
            $var = &$var[ $item ];
        }
    }

    if (!$var || !$append) {
        $var = $value;
    } elseif ($append === true) {
        $var .= $value;
    } else {
        $var .= $append . $value;
    }
} // setStaticVariable




function getStaticVariable($varName)
{
    if (strpos($varName, '.') === false) {  // scalar static var:
        if (isset($_SESSION['lizzy'][$varName])) {
            return $_SESSION['lizzy'][$varName];
        } else {
            return null;
        }

    }

    // nested static var:
    $a = explode('.', $varName);
    if (!isset($_SESSION['lizzy'])) {
        $_SESSION['lizzy'] = [];
    }
    $var = &$_SESSION['lizzy'];
    foreach ($a as $item) {
        if (!isset($var[ $item ])) {
            return null;
        }
        $var = &$var[ $item ];
    }
    return $var;
} // getStaticVariable




function getClientIP($normalize = false)
{
    $ip = getenv('HTTP_CLIENT_IP')?:
        getenv('HTTP_X_FORWARDED_FOR')?:
            getenv('HTTP_X_FORWARDED')?:
                getenv('HTTP_FORWARDED_FOR')?:
                    getenv('HTTP_FORWARDED')?:
                        getenv('REMOTE_ADDR');

    if ($normalize) {
        $elems = explode('.', $ip);
        foreach ($elems as $i => $e) {
            $elems[$i] = str_pad($e, 3, "0", STR_PAD_LEFT);
        }
        $ip = implode('.', $elems);
    }
    return $ip;
} // getClientIP




function reloadAgent($target = false, $getArg = false)
{
    global $lizzy;
    if ($target === true) {
        $target = $lizzy['requestedUrl'];
    } elseif ($target) {
        $target = resolvePath($target, false, true);
    } else {
        $target = $lizzy['pageUrl'];
        $target = preg_replace('|/[A-Z][A-Z0-9]{4,}/?$|', '/', $target);
    }
    if ($getArg) {
        setNotificationMsg($getArg);
    }
    header("Location: $target");
    exit;
} // reloadAgent




function path_info($file)
{
	if (substr($file, -1) === '/') {
		$pi['dirname'] = $file;
		$pi['filename'] = '';
		$pi['extension'] = '';
	} else {
		$pi = pathinfo($file);
		$pi['dirname'] = correctPath(isset($pi['dirname']) ? $pi['dirname'] : '');
		$pi['filename'] = isset($pi['filename']) ? $pi['filename'] : '';
		$pi['extension'] = isset($pi['extension']) ? $pi['extension'] : '';
	}
	return $pi;
} // path_info




function preparePath($path0, $accessRights = false)
{
    if ($path0 && ($path0[0] === '~')) {
        $path0 = resolvePath($path0);
    }

    // check for inappropriate path:
    if (strpos($path0, '../') !== false) {
        $path0 = normalizePath($path0);
        if (strpos($path0, '../') !== false) {
            mylog("=== Warning: preparePath() trying to access inappropriate location: '$path0'");
            return;
        }
    }

	$path = dirname($path0.'x');
    if (!file_exists($path)) {
        $accessRights1 = $accessRights ? $accessRights : MKDIR_MASK;
        try {
            mkdir($path, $accessRights1, true);
        } catch (Exception $e) {
            fatalError("Error: failed to create folder '$path'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
    }

    if ($accessRights) {
        $path1 = '';
        foreach (explode('/', $path) as $p) {
            $path1 .= "$p/";
            chmod($path1, $accessRights);
        }
    }

    if ($path0 && !file_exists($path0)) {
        touch($path0);
    }

} // preparePath




function is_legal_email_address($email)
{
 // multiple address allowed, if separated by comma.
    if (!is_safe($email)) {
        return false;
    }
    $res = true;
    foreach (explode(',', $email) as $s) {
        $s = filter_var($s, FILTER_VALIDATE_EMAIL);
        $res = $res && $s;
    }
    return $res;
} // is_legal_email_address




function isValidUrl( $url, $checkExists = false )
{
    if ($checkExists) {
        return (preg_match("#^https?://.+#", $url) and @fopen($url,"r"));
    } else {
        return preg_match("/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i", $url);
    }
} // isValidUrl




function is_safe($str, $multiline = false)
{
	if ($multiline) {
		$str = str_replace(PHP_EOL, '', $str);
		$str = str_replace("\r", '', $str);
	}
    return !preg_match("/[^\pL\pS\pP\pN\pZ]/um", $str);
} // is_safe




function safeStr($str, $permitNL = false, $isGetArg = true)
{
	if (preg_match('/^\s*$/', $str)) {
		return '';
	}
	$str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
    if ($permitNL) {
        $str = preg_replace("/[^[:print:]À-ž\n\t]/m", ' ', $str);

    } else {
        $str = preg_replace('/[^[:print:]À-ž]/m', ' ', $str);
    }
    if ($isGetArg) {
        $str = substr($str, 0, MAX_URL_ARG_SIZE);    // restrict size to safe value
    }
	return $str;
} // safeStr




function strToASCII($str)
{
 // transliterate special characters (such as ä, ö, ü) into pure ASCII
	$specChars = array('ä','ö','ü','Ä','Ö','Ü','é','â','á','à',
		'ç','ñ','Ñ','Ç','É','Â','Á','À','ẞ','ß','ø','å');
	$specCodes2 = array('ae','oe','ue','Ae',
		'Oe','Ue','e','a','a','a','c',
		'n','N','C','E','A','A','A',
		'SS','ss','o','a');
	return str_replace($specChars, $specCodes2, $str);
} // strToASCII




function translateUmlauteToHtml($str)
{
 // transliterate special characters (such as ä, ö, ü) into pure ASCII
	$specChars = array('ä','ö','ü','Ä','Ö','Ü','é','â','á','à',
		'ç','ñ','Ñ','Ç','É','Â','Á','À','ẞ','ß','ø','Ø','å','Å');
	$specCodes2 = array('&auml;','&ouml;','&uuml;','&Auml;',
		'&Ouml;','&Uuml;','&eacute;','&#226;','&aacute;','&agrave;','&ccedil;',
		'&ntilde;','&Ntilde;','&Ccedil;','&Eacute;','&Acirc;','&Aacute;','&Agrave;',
		'&Szlig;','&szlig;','&oslash;','&Oslash;','&aring;','&Aring;');
	return str_replace($specChars, $specCodes2, $str);
} // translateUmlauteToHtml




function timestamp($short = false)
{
	if (!$short) {
		return date('Y-m-d H:i:s');
	} else {
		return date('Y-m-d');
	}
} // timestamp




function dateFormatted($date = false, $format = false)
{
    if (!$date) {
        $date = time();
    } if (is_string($date)) {
        $date = strtotime($date);
    }
    if (!$format) {
        $format = '%x';
    }
    $out = strftime($format, $date);
    return $out;
} // dateFormatted




function touchFile($file, $time = false)
{	// work-around: PHP's touch() fails if http-user is not owner of file
	if ($time) {
        touch($file, $time);
	} else {
		touch($file);
	}
} // touchFile




function translateToFilename($str, $appendExt = true)
{
 // translates special characters (such as , , ) into "filename-safe" non-special equivalents (a, o, U)
	$str = strToASCII(trim(mb_strtolower($str)));	// replace special chars
	$str = strip_tags($str);						// strip any html tags
	$str = str_replace([' ', '-'], '_', $str);				// replace blanks with _
	$str = str_replace('/', '_', $str);				// replace '/' with _
	$str = preg_replace("/[^[:alnum:]._-`]/m", '', $str);	// remove any non-printables
	$str = preg_replace("/\.+/", '.', $str);		// reduce multiple ... to one .
	if ($appendExt && !preg_match('/\.html?$/', $str)) {	// append file extension '.html'
		if ($appendExt === true) {
			$str .= '.html';
		} else {
			$str .= '.'.$appendExt;
		}
	}
	return $str;

} // translateToFilename




function translateToIdentifier($str, $removeDashes = false, $removeNonAlpha = false, $toLowerCase = true)
{
 // translates special characters (such as , , ) into identifier which contains but safe characters:
    if ($toLowerCase) {
        $str = mb_strtolower($str);        // all lowercase
    }
    $str = strToASCII( $str );		// replace umlaute etc.
    $str = strip_tags( $str );							// strip any html tags
    if ($removeNonAlpha) {
        $str = preg_replace('/[^a-zA-Z-_\s]/ms', '', $str);

    } elseif (preg_match('/^ \W* (\w .*? ) \W* $/x', $str, $m)) { // cut leading/trailing non-chars;
        $str = trim($m[1]);
    }
	$str = preg_replace('/\s+/', '_', $str);			// replace blanks with _
	$str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except _ and -
	if ($removeDashes) {
		$str = str_replace("-", '_', $str);				// remove -, if requested
	}
	return $str;
} // translateToIdentifier




function translateToClassName($str)
{
	$str = strToASCII(mb_strtolower($str));		// replace special chars
	$str = strip_tags($str);							// strip any html tags
	$str = preg_replace('/\s+/', '-', $str);			// replace blanks with _
	$str = preg_replace("/[^[:alnum:]_-]/m", '', $str);	// remove any non-letters, except _ and -
    if (!preg_match('/[a-z]/', @$str[0])) {
        $str = "_$str";
    }
	return $str;
} // translateToClassName




function mylog($str, $notDebugOnly = true)
{
    if (is_array($str)) {
        $str = var_r($str, 'mylog', true);
    }
    if ($notDebugOnly) {
        writeLogStr($str);
    } elseif (@$_SESSION['lizzy']['debug']) {
        writeLogStr('### '.$str);
    }
} // mylog




function writeLog()
{
    $str = '';
    $args = func_get_args();
    if (!$args) {
        return;
    }
    foreach ($args as $arg) {
        if (!is_string($arg)) {
            $arg = var_export($arg, true);
            $arg = str_replace("\n", '', $arg);
        }
        $indent = '                     ';
        $s = str_replace(["\n", "\t", "\r"], [' ',' ',''], $arg);
        while (strlen($s) > LOG_WIDTH) {
            $str .= substr($s, 0,LOG_WIDTH)."\n$indent";
            $s = substr($s, LOG_WIDTH);
        }
        $str .= $s;
    }
    $str = rtrim($str);
    writeLogStr($str);
} // writeLog




function writeLogStr($str, $errlog = false)
{
    global $lizzy;

    $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anon';
    if (is_array($str)) {
        $str = json_encode($str);
    }
    if (!$errlog) {
        if ((@$lizzy['activityLoggingEnabled'] !== null) && !@$lizzy['activityLoggingEnabled']) {
            return;
        }
        file_put_contents(LOG_FILE, timestamp()."  $str\n\n", FILE_APPEND);

    } else {
        if (($errlog === true) && ($lizzy['errorLoggingEnabled'] !== null) && !$lizzy['errorLoggingEnabled']) {
            return;
        }
        if (is_string($errlog)) {
            // only allow files in LOGS_PATH and only with extension .txt or .log:
            $destination = LOGS_PATH . basename( $errlog );
        } else {
            $destination = $lizzy['errorLogFile'];
        }
        if ($destination) {
            file_put_contents($destination, timestamp() . " [$user]: $str\n\n", FILE_APPEND);
        }
    }
} // writeLogStr




function logError($str)
{
    writeLogStr($str, true);
} // logError




function explodeTrim($sep, $str, $excludeEmptyElems = false)
{
    if (!is_string($str)) {
        return [];
    }
    $str = trim($str);
    if ($str === '') {
        return [];
    }
    if (strlen($sep) > 1) {
        if (!preg_match("/[$sep]/", $str)) {
            return [ $str ];
        }
        $sep = preg_quote($sep);
        $out = array_map('trim', preg_split("/[$sep]/", $str));

    } else {
        if (strpos($str, $sep) === false) {
            return [ $str ];
        }
        $out = array_map('trim', explode($sep, $str));
    }

    if ($excludeEmptyElems) {
        $out = array_filter($out, function ($item) {
            return ($item !== '');
        });
    }
    return $out;
} // explodeTrim





function revertQuotes($s, $unshield = true)
{
    // &#39; -> '
    // &#34; -> "
    // unless shielded by preceding '\'
    $s = preg_replace(['/(?<!\\\) (&\#39;)/x', '/(?<!\\\) (&\#34;)/x'], ["'", '"'], $s);
    if ($unshield) {
        $s = str_replace(['\\&#39;', '\\&#34;'], ['&#39;', '&#34;'], $s);
    }
	return $s;
} // revertQuotes




function var_r($var, $varName = '', $flat = false)
{
	if ($flat) {
		$out = preg_replace("/".PHP_EOL."/", '', var_export($var, true));
		if (preg_match('/array \((.*),\)/', $out, $m)) {
		    $out = "[{$m[1]} ]";
        }
		if ($varName) {
		    $out = "$varName: $out";
        }
	} else {
        $out = "<div><pre>$varName: ".var_export($var, true)."\n</pre></div>\n";
    }
	return $out;
} // var_r




function createWarning($msg) {
	return "\t\t<div class='lzy-msgbox lzy-warning'>$msg</div>\n";
} // createWarning




function createDebugOutput($msg) {
	if ($msg) {
		return "\t\t<div id='lzy-log'>$msg</div>\n";
	} else {
		return '';
	}
} // createDebugOutput



$lzyRenderingTimer = 0;

function startTimer() {
	$GLOBALS['lzyRenderingTimer'] = microtime(true);
} // startTimer




function readTimer( $verbose = false ) {
    $t = (round((microtime(true) - $GLOBALS['lzyRenderingTimer'])*1000000) / 1000 - 0.005);
    if ($verbose) {
        return "Time: {$t}ms";
    } else {
        return $t;
    }
} // readTimer




function renderLink($href, $text = '', $type = '', $class = '')
{
	$target = '';
	$title = '';
	$hiddenText = '';
	if (stripos($href, 'mailto:') !== false) {
		$class = ($class) ?  "$class mail_link" : 'mail_link';
		$title = " title='`opens mail app`'";
		if (!$text) {
			$text = substr($href, 7);
		} else {
			$hiddenText = "<span class='print_only'> [$href]</span>";
		}
	}
	if (!$text) {
		$text = $href;
	} else {
		$hiddenText = "<span class='print_only'> [$href]</span>";
	}
	if ((stripos($type, 'extern') !== false) || (stripos($href, 'http') === 0)) {
		$target = " target='_blank'";
		$class = ($class) ?  "$class external_link" : 'external_link';
		$title = " title='`opens in new win`'";
	}
	$class = " class='$class'";
	$str = "<a href='$href' $class$title$target>$text$hiddenText</a>";
	return $str;
} // renderLink




function mb_str_pad ($input, $pad_length, $pad_string, $pad_style=STR_PAD_RIGHT, $encoding="UTF-8") { 
   return str_pad($input, strlen($input)-mb_strlen($input,$encoding)+$pad_length, $pad_string, $pad_style); 
} // mb_str_pad




function stripHtml( $str )
{
    $str = preg_replace('/(<.*?>)/', '', $str);
    return $str;
} // stripHtml




function checkBracesBalance($str, $pat1 = '{{', $pat2 = '}}', $p0 = 0)
{
    $shieldedOpening = substr_count($str, '\\' . $pat1, $p0);
    $opening = substr_count($str, $pat1, $p0) - $shieldedOpening;
    $shieldedClosing = substr_count($str, '\\' . $pat2, $p0);
    $closing = substr_count($str, $pat2, $p0) - $shieldedClosing;
    if ($opening > $closing) {
        fatalError("Error in source: unbalanced number of &#123;&#123; resp }}");
    }
} // checkBracesBalance




function strPosMatching($str, $pat1 = '{{', $pat2 = '}}', $p0 = 0)
{	// returns positions of opening and closing patterns, ignoring shielded patters (e.g. \{{ )

    if (!$str) {
        return [false, false];
    }
    checkBracesBalance($str, $pat1, $pat2, $p0);

	$d = strlen($pat2);
    if ((strlen($str) < 4) || ($p0 > strlen($str))) {
        return [false, false];
    }

    if (!checkNesting($str, $pat1, $pat2)) {
        return [false, false];
    }

	$p1 = $p0 = findNextPattern($str, $pat1, $p0);
	$cnt = 0;
	do {
		$p3 = findNextPattern($str, $pat1, $p1+$d); // next opening pat
		$p2 = findNextPattern($str, $pat2, $p1+$d); // next closing pat
        if ($p2 === false) { // no more closing pat
                return [false, false];
		}
		if ($cnt === 0) {	// not in nexted structure
			if ($p3 === false) {	// no more opening pat
				return [$p0, $p2];
			}
			if ($p2 < $p3) { // no more opening patterns or closing before next opening
				return [$p0, $p2];
			} else {
				$cnt++;
				$p1 = $p3;
			}
		} else {	// within nexted structure
			if ($p3 === false) {	// no more opening pat
				$cnt--;
				$p1 = $p2;
			} else {
				if ($p2 < $p3) { // no more opening patterns or closing before next opening
					$cnt--;
					$p1 = $p2;
				} else {
					$cnt++;
					$p1 = $p3;
				}
			}
		}
	} while (true);
} // strPosMatching





function checkNesting($str, $pat1, $pat2)
{
    $n1 = substr_count($str, $pat1);
    $n2 = substr_count($str, $pat2);
    if ($n1 > $n2) {
        fatalError("Nesting Error in string '$str'", 'File: '.__FILE__.' Line: '.__LINE__);
    }
    return $n1;
} // checkNesting




function findNextPattern($str, $pat, $p1 = 0)
{
	while (($p1 = strpos($str, $pat, $p1)) !== false) {
		if (($p1 === 0) || (substr($str, $p1-1, 1) !== '\\')) {
			break;
		}
		$p1 += strlen($pat);
	}
	return $p1;
} // findNextPattern




function trunkPath($path, $n = 1, $leaveNotRemove = true)
{
 // case $leaveNotRemove == false:
 //      n==2   trunk from right   '/a/b/c/d/e/x.y' ->  /a/b/c/
 //      n==-2  trunk from left    '/a/b/c/d/e/x.y' ->  c/d/e/x.y
 // case $leaveNotRemove == true:
 //      n==2   leave from right   '/a/b/c/d/e/x.y' ->  d/e/x.y
 //      n==-2  leave from left    '/a/b/c/d/e/x.y' ->  /a/b/
    if ($leaveNotRemove) {
        if ($n > 0) {   // leave from right
            $file = basename($path);
            $path = dirname($path);
            $parray = explode('/', $path);
            $n = sizeof($parray) - $n;
            $parray = array_splice($parray, $n);
            $path = implode('/', $parray);
            return "$path/$file";
            return implode('/', explode('/', $path, -$n)) . '/';
        } else {        // leave from left
            $path = ($path[0] === '/') ? substr($path, 1) : $path;
            $parray = explode('/', $path);
            return '/'.implode('/', array_splice($parray, 0, -$n)).'/';
        }

    } else {
        if ($n > 0) {   // trunk from right
            $path = ($path[strlen($path) - 1] === '/') ? rtrim($path, '/') : dirname($path);
            return implode('/', explode('/', $path, -$n)) . '/';
        } else {        // trunk from left
            $path = ($path[0] === '/') ? substr($path, 1) : $path;
            $parray = explode('/', $path);
            return implode('/', array_splice($parray, -$n));
        }
    }
} // trunkPath





function rrmdir($src)
{
    // remove dir recursively
    $src = rtrim($src, '/');
    if (!file_exists($src)) {
        return;
    }
    $dir = opendir($src);
    if (!$dir) {
        return;
    }
    while(false !== ( $file = readdir($dir)) ) {
        if (( $file !== '.' ) && ( $file !== '..' )) {
            $full = $src . '/' . $file;
            if ( is_dir($full) ) {
                rrmdir($full);
            }
            else {
                unlink($full);
            }
        }
    }
    closedir($dir);
    rmdir($src);
} // rrmdir




function compileMarkdownStr($mdStr, $removeWrappingPTags = false, $lzy = null)
{
    $md = new LizzyMarkdown( $lzy );
    $str = $md->compileStr($mdStr);
    if ($removeWrappingPTags) {
        $str = preg_replace('/^<p>(.*)<\/p>(\s*)$/ms', "$1$2", $str);
    }
    return $str;
} // compileMarkdownStr




function shieldMD($md)
{
    $md = str_replace('#', '&#35;', $md);
    return $md;
} // shieldMD





function isAdmin()
{
    return $GLOBALS['lizzy']['isAdmin'];
} // isAdmin




function isLocalhost()
{
    return @$GLOBALS['lizzy']['isLocalhost'];
} // isLocalhost




function checkPermission($str0, $lzy = false, $and = false) {
    if ($str0 === null) {
        return null;
    }
    if (is_bool($str0)) {
        return $str0;
    }
    $resOut = $and;
    $strs = explodeTrim(',', $str0);
    foreach ($strs as $str) {
        $neg = false;
        $res = false;
        if (preg_match('/^((non|not|!)-?)/i', $str, $m)) {
            $neg = true;
            $str = substr($str, strlen($m[1]));
        }

        if (($str === true) || ($str === 'true')) {
            $res = true;
        } elseif (($str === false) || ($str === 'false')) {
            $res = false;
        } elseif (preg_match('/privileged/i', $str)) {
            $res = $GLOBALS['lizzy']['isPrivileged'];
        } elseif (preg_match('/loggedin/i', $str)) {
            $res = $GLOBALS['lizzy']['isLoggedin'] || $GLOBALS['lizzy']['isAdmin'];
        } elseif (($str !== 'true') && !is_bool($str)) {
            if ($lzy) {
                // if not 'true', it's interpreted as a group
                $res = $lzy->auth->checkGroupMembership($str);
            } else {
                $res = false;
            }
        }
        if ($neg) {
            $res = !$res;
        }
        if ($and) {
            $resOut = $resOut && $res;
        } else {
            $resOut = $resOut || $res;
        }
    }
    return $resOut;
} // checkPermission





function getGitTag($shortForm = true)
{
    if (isset($GLOBALS['lizzy']['gitTag'])) {
        $str = $GLOBALS['lizzy']['gitTag'];
    } elseif (is_callable('shell_exec') && false === stripos(ini_get('disable_functions'), 'shell_exec')) {
        $str = shell_exec('cd _lizzy; git describe --tags --abbrev=0; git log --pretty="%ci" -n1 HEAD');
        $GLOBALS['lizzy']['toCache']['gitTag'] = $str;
    } else {
        $GLOBALS['lizzy']['toCache']['gitTag'] = 'unknown';
    }
    if ($shortForm) {
        return preg_replace("/\n.*/", '', $str);
    } else {
        return str_replace("\n", ' ', $str);
    }
} // getGitTag





function fatalError($msg, $origin = '', $offendingFile = '')
 // $origin =, 'File: '.__FILE__.' Line: '.__LINE__;
{
    global $lizzy;
    $out = '';
    $problemSrc = '';
    if ($offendingFile) {
        $problemSrc = "problemSrc: $offendingFile, ";
    } elseif (isset($lizzy['lastLoadedFile'])) {
        $offendingFile = $lizzy['lastLoadedFile'];
        $problemSrc = "problemSrc: $offendingFile, ";
    }
    if ($origin) {
        if (preg_match('/File:\s*(.*)\s*Line:(.*)/', $origin, $m)) {
            $file = trim($m[1]);
            $l = (isset($lizzy['absAppRoot'])) ? $lizzy['absAppRoot']: 0;
            $file = substr($file, strlen($l));
            $line = trim($m[2]);
            $origin = "$file::$line";
        }
        $out = date('Y-m-d H:i:s')."  $origin  $problemSrc\n$msg\n";
    }
    preparePath(ERROR_LOG);
    file_put_contents(ERROR_LOG, $out, FILE_APPEND);

    if ($origin && $offendingFile) {
        require_once SYSTEM_PATH.'page-source.class.php';
        PageSource::rollBack($offendingFile, $msg);
        reloadAgent();
    }

    if (isLocalhost()) {
        exit($msg);
    } else {
        exit;
    }
} // fatalError





function sendMail($to, $from, $subject, $message, $options = null, $exitOnError = true)
{
    $html = $options;
    $base64_encode = false;
    $wrap = false;
    if (is_string($options)) {
        $html = (stripos($options, 'html') !== false)? true: null;
        $wrap = (stripos($options, 'wrap') !== false);
        $base64_encode = (stripos($options, 'encode') !== false) ||
            (stripos($options, 'base64') !== false);
    }

    $name = '';
    if (preg_match('/(.*?) < ([\w\d\'-.@]+) >/x', $to, $m)) {
        $to = $m[2];
        $name = $m[1];
    }
    $to = strtolower($to);
    if (!is_legal_email_address($to)) {
        writeLog("lzy-mail-invalid-to-address: $to");
        return 'lzy-mail-invalid-to-address';
    }

    if ($name) {
        if ($base64_encode) {
            $to = '=?UTF-8?B?' . base64_encode($name) . "?= <$to>";
        } else {
            $to = "$name <$to>";
        }
    }

    if ($from) {
        $name = '';
        if (preg_match('/(.*?) < ([\w\d\'-.@]+) >/x', $from, $m)) {
            $from = $m[2];
            $name = $m[1];
        }
        if (preg_match("/[^\w\d'-.@]/", $from)) {
            writeLog("lzy-mail-invalid-from-address: $from");
            return 'lzy-mail-invalid-from-address';
        }
        $from = strtolower($from);
        if (!is_legal_email_address($from)) {
            writeLog("lzy-mail-invalid-from-address: $from");
            return 'lzy-mail-invalid-from-address';
        }

        if ($name) {
            if ($base64_encode) {
                $from = '=?UTF-8?B?' . base64_encode($name) . "?= <$from>";
            } else {
                $from = "$name <$from>";
            }
        }
    }

    // replace tabs:
    $subject = str_replace("\t", '    ', $subject);
    // strip non-printable chars incl. \n,\t etc.
    $subject = preg_replace('/[\x00-\x1F\x7F-\xA0\xAD]/u', '', $subject);
    if ($base64_encode) {
        $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    writeLog("sendMail to:[$to], from:[$from], subject:[$subject],\nmessage:[$message]");

    // replace tabs, shield nl:
    $message = str_replace(["\t", "\n"], ['    ', '~~NL~~'], $message);
    // strip non-printable chars etc.
    $message = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F]/u', '', $message);
    $message = str_replace('~~NL~~', "\n", $message);

    // check for HTML:
    if (($html === null) && preg_match('/^<(html|!DOCTYPE)/i', $message)) {
        $html = true;
    }
    if ($wrap && !$html) {
        $message = wordwrap($message, 70, "\r\n");
    }
    if ($base64_encode) {
        $message = base64_encode($message);
    }

    $headers = "From: $from\r\n";
    if ($html) {
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Content-Type: text/html; charset=utf-8' . "\r\n";
    } else {
        $headers .= 'Content-Type: text/plain; charset=utf-8' . "\r\n";
    }
    if ($base64_encode) {
        $headers .= 'Content-Transfer-Encoding: base64' . "\r\n";
    }
    $headers .= 'X-Mailer: PHP/' . phpversion();

    $res = mail($to, $subject, $message, $headers);
    if (!$res) { // error case
        $err = error_get_last()['message'];
        writeLog("ERROR sending email: $err");
        if ($exitOnError) {
            fatalError("Error: unable to send e-mail ($err)", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        return $err;
    } else {
        return false;
    }
} // sendMail





function handleFatalPhpError() {
    $last_error = error_get_last();

    if (isset($last_error['type']) && ($last_error['type'] === E_ERROR)) {
        print( $last_error['message'] );
    }
} // handleFatalPhpError




function parseDimString($str)
{
    // E.g. 200x150, or 200x  or x150 or 200 etc.
    $h = $w = null;
    if (preg_match('/(\d*)x(\d*)/', $str, $m)) {
        $w = intval($m[1]);
        $h = intval($m[2]);
    } elseif (preg_match('/(\d+)/', $str, $m)) {
        $w = intval($m[1]);
    }
    return [$w, $h];
} // parseDimString




function createHash( $hashSize = 8, $unambiguous = false, $lowerCase = false )
{
    if ($unambiguous) {
        $chars = UNAMBIGUOUS_CHARACTERS;
        $max = strlen(UNAMBIGUOUS_CHARACTERS) - 1;
        $hash = $chars[ random_int(4, $max) ];  // first always a letter
        for ($i=1; $i<$hashSize; $i++) {
            $hash .= $chars[ random_int(0, $max) ];
        }

    } else {
        $hash = chr(random_int(65, 90));  // first always a letter
        $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $hashSize - 1));  // letters and digits
    }
    if ($lowerCase) {
        $hash = strtolower( $hash );
    }
    return $hash;
} // createHash




function renderList( $list, $args )
{
    $sort           = isset($args['sort'])? $args['sort']: false;
    $exclude        = isset($args['exclude'])? $args['exclude']: false;
    $capitalize     = isset($args['capitalize'])? $args['capitalize']: false;
    $prefix         = isset($args['prefix'])? $args['prefix']: '';
    $postfix        = isset($args['postfix'])? $args['postfix']: '';
    $separator      = isset($args['separator'])? $args['separator']: ',';
    $wrapperTag     = isset($args['wrapperTag'])? $args['wrapperTag']: '';
    $wrapperClass   = isset($args['wrapperClass'])? $args['wrapperClass']: '';
    $outerWrapperTag     = isset($args['wrapperTag'])? $args['wrapperTag']: '';
    $outerWrapperClass   = isset($args['wrapperClass'])? $args['wrapperClass']: '';
    $mode           = isset($args['mode'])? $args['mode']: '';
    $options        = isset($args['options'])? $args['options']: '';
    $splitter       = isset($args['splitter'])? $args['splitter']: ',';
    if ($options) {
        $options = ",$options,";
        if (strpos($options, ',capitalize,') !== false) {
            $capitalize = true;
        }
        if (strpos($options, ',sort,') !== false) {
            $sort = true;
        }
        if (strpos($options, ',ul,') !== false) {
            $mode = 'ul';
        } elseif (strpos($options, ',ol,') !== false) {
            $mode = 'ol';
        }
    }

    // short-hands:
    if (($mode === 'ul') || ($mode === 'ol')) {
        $wrapperTag = 'li';
        $separator = '';
        $outerWrapperTag = $mode;
    }

    if (is_array($list)) {
        $elements = $list;
    } else {
        $elements = explodeTrim($splitter, $list);
    }
    if ($sort) {
        if (($sort === true) || ($sort && ($sort[0] !== 'd'))) {
            sort($elements, SORT_NATURAL | SORT_FLAG_CASE);
        } else {
            rsort($elements, SORT_NATURAL | SORT_FLAG_CASE);
        }
    }

    $out = '';
    foreach ($elements as $i => $element) {
        if ($exclude) {
            $pattern = $exclude;
            if (preg_match("/$pattern/i", $element)) {
                continue;
            }
        }
        if ($capitalize) {
            $element = ucfirst($element);
        }

        if ($prefix) {
            $element = "$prefix$element";
        }
        if ($postfix) {
            $element .= $postfix;
        }
        if ($separator) {
            $element .= $separator;
        }

        if ($wrapperTag) {
            $cls = $wrapperClass? " class='$wrapperClass'": '';
            $element = "\t\t<$wrapperTag$cls>$element</$wrapperTag>\n";
        }

        $out .= $element;
    }
    if (!$wrapperTag) {
        $out = substr($out, 0, -strlen($separator));
    }

    if ($outerWrapperTag) {
        $cls = $outerWrapperClass? " class='$outerWrapperClass'": '';
        $out = <<<EOT
    <$outerWrapperTag$cls>
$out
    </$outerWrapperTag>

EOT;
    }

    return $out;
} // renderList




function replaceQuotesByCodes( $str ) {
    return str_replace(['"', "'"], ['&#34;', '&#39;'], $str);
} // replaceQuotesToCodes




function replaceCodesByQuotes( $str ) {
    return str_replace(['&#34;', '&#39;'], ['"', "'"], $str);
} // replaceCodesByQuotes



function findArrayElementByAttribute( $array, $key, $value) {
    $res = array_filter($array, function ($rec) use($key, $value) {
        if (isset($rec[$key])) {
            if (($value === null) || ($rec[$key] === $value)) {
                return true;
            }
        }
        return false;
    });
    return $res;
} // findArrayElementByAttribute



function secondsToTime( $seconds, $sep = '' )
{
    $days = intdiv($seconds, 86400);
    $hours = intdiv(($seconds - $days*86400), 3600);
    $minutes = intdiv(($seconds - $days*86400 - $hours*3600), 60);
    $seconds = $seconds - ($days*86400 + $hours*3600 + $minutes*60);

    $days = $days? (($days == 1)? "$days{{ lzy-time-day }}": "$days{{ lzy-time-days }}"): '';
    $hours = $hours? (($hours == 1)? "$hours{{ lzy-time-hours }}": "$hours{{ lzy-time-hours }}"): '';
    $minutes = $minutes? (($minutes == 1)? "$minutes{{ lzy-time-minutes }}": "$minutes{{ lzy-time-minutes }}"): '';
    $seconds = $seconds? (($seconds == 1)? "$seconds{{ lzy-time-seconds }}": "$seconds{{ lzy-time-seconds }}"): '';

    $out = $days;
    $out = ($out && $hours)? "$out$sep$hours": $out;
    $out = ($out && $minutes)? "$out$sep$minutes": $out;
    $out = ($out && $seconds)? "$out$sep$seconds": $out;
    return $out;
} // secondsToTime