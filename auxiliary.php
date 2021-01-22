<?php

define('LOG_PATH', '.#logs/');
define('MAX_URL_ARG_SIZE', 16000);
define('DEFAULT_ASPECT_RATIO', 0.6667);
define('LOG_WIDTH', 80);

use Symfony\Component\Yaml\Yaml;



function parseArgumentStr($str, $delim = ',', $yamlCompatibility = false)
{
    $str0 = $str;
    $str = trim($str);
    $str = str_replace("\t", '    ', $str);
    if (!($str = trim($str))) {
        return false;
    }
    if (preg_match('/^\s*\{\{ .* \}\} \s* $/x', $str)) {    // skip '{{ ... }}' to avoid conflict with '{ ... }'
        return [ $str ];
    }

    $options = [];

    if ($yamlCompatibility) {
        // for compatibility with Yaml, the argument list may come enclosed in { }
        if (preg_match('/^\s* \{  (.*)  \} \s* $/x', $str, $m)) {
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
        if (array_key_exists($c, $supportedBrackets)) {
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
                $GLOBALS["globalParams"]["cachingActive"] = false;
                $val = false;
                if ($m[2] === 'isAdmin') {
                    $val = $GLOBALS['globalParams']['isAdmin'];
                } elseif ($m[2] === 'isPrivileged') {
                    $val = $GLOBALS['globalParams']['isPrivileged'];
                } elseif ($m[2] === 'isLoggedin') {
                    $val = $GLOBALS['globalParams']['isLoggedin'];
                } else {
                    $val = str_replace(['"', "'"], ['&#34;', '&#39;'], $val);
                }
                if ($m[1]) {
                    $val = !$val;
                }
            } else {
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



function parseInlineBlockArguments($str, $returnElements = false)
{
    // Example: span  #my-id  .my-class  color:orange .class2 aria-expanded=false line-height: '1.5em;' !off .class3 aria-hidden= 'true' lang=de-CH literal=true md-compile=false "some text"
    // Usage:
    //   list($tag, $id, $class, $style, $attr, $text, $comment) = parseInlineBlockArguments($str, true);
    //   list($tag, $str, $lang, $comment, $literal, $mdCompile, $text) = parseInlineBlockArguments($str);

    $tag = $id = $class = $style = $attr = $lang = $comment = $text = '';
    $literal = false;
    $mdCompile = true;
    $elems = [];
    $str = preg_replace('|//.*|', '', $str);    // ignore //-style comments

    // special commands: !lang, !off, !literal, !md-compile
    while (preg_match('/(.*) !(\S+) (.*)/x', $str, $m)) {      // !arg
        $str = $m[1].$m[3];
        if (strpos($m[2], '=') !== false) {
            $arr = explode('=', strtolower($m[2]));
            $k = isset($arr[0]) ? strtolower($arr[0]) : '';
            $v = isset($arr[1]) ? strtolower($arr[1]) : '';
        } else {
            $k = strtolower($m[2]);
            $v = 'true';
        }
        if ($k === 'lang') {                                                  // lang
            $attr .= " lang='$v' data-lang='$v'";
            $lang = $v;

        } elseif ($k === 'off') {                                             // off
            $style = ' display:none;';

        } elseif ($k === 'literal') {                                         // literal
            $literal = $v? (stripos($v, 'true') !== false): true;

        } elseif ($k === 'md-compile') {                                      // md-compile
            $mdCompile = $v? (stripos($v, 'true') !== false): true;

        } elseif (($k === 'showtill')) {                                      // showTill
            $t = strtotime($v);
            if ($t < time()) {
                $lang = 'none';
            }

        } elseif (($k === 'showfrom')) {                                      // showFrom
            $t = strtotime($v);
            if ($t > time()) {
                $lang = 'none';
            }
        }
    }

    // style instructions:  key:value
    if (preg_match_all('/([\w-]+\:\s*[^\s,]+)/x', $str, $m)) {  // style:arg
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
    if (preg_match_all('/( [\w-]+ \=\s* " .*? " ) /x', $str, $m)) {  // attr="arg "
        $elems = $m[1];
        $str = str_replace($m[1], '', $str);
    }
    if (preg_match_all("/( [\w-]+ \=\s* ' .*? ' ) /x", $str, $m)) {  // attr='arg '
        $elems = array_merge($elems, $m[1]);
        $str = str_replace($m[1], '', $str);
    }
    if (preg_match_all("/( [\w-]+ \=\s* [^\s,]+ ) /x", $str, $m)) {  // attr=arg
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
        return [$tag, $id, $class, trim($style), $attr, $text, $comment];
    } else {
        $str = "$id$class$style$attr";
        return [$tag, $str, $lang, $comment, $literal, $mdCompile, $text];
    }
} // parseInlineBlockArguments




function csv_to_array($str, $delim = ',') {
    $str = trim($str);
    if (preg_match('/^(\{.*\})[\s,]*$/', $str, $m)) {   // {}
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




function getYamlFile($filename, $returnStructure = false)
{
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
} // writeToYamlFile




function findFile($pat)
{
	$pat = rtrim($pat, "\n");
	$dir = array_filter(glob($pat), 'isNotShielded');
	return (isset($dir[0])) ? $dir[0] : false;
} // findFile




function getFile($pat, $removeComments = false)
{
    global $globalParams;
	$pat = str_replace('~/', '', $pat);
	if (strpos($pat, '~page/') === 0) {
	    $pat = str_replace('~page/', $globalParams['pageFolder'], $pat);
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
    if (strpos($removeComments, 'emptyLines')) {
        $file = removeEmptyLines($file);
    }
    return $file;
} // getFile




function zapFileEND($file)
{
    if (($p = strpos($file, "\n__END__")) !== false) {	// must be at beginning of line
        $file = substr($file, 0, $p+1);
    }
    return $file;
}




function fileExists($file)
{
    global $globalParams;
    $file = str_replace('~/', '', $file);
    if (strpos($file, '~page/') === 0) {
        $file = str_replace('~page/', $globalParams['pageFolder'], $file);
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




function fileExt($file0, $reverse = false)
{
    $file = basename($file0);
    $file = preg_replace(['|^\w{1,6}://|', '/[#?&:].*/'], '', $file);
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
	if (preg_match('/\&\#\d+\;/',  $file)) {
		$file = htmlspecialchars_decode($file);
		$file = preg_replace('/\&\#\d+\;/', '', $file);
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
    $path = preg_replace('/[\#\?\*].*/', '', $path);
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
    $pagesPath = $GLOBALS['globalParams']['pathToPage'];
    if ($path && ($path[0] !== '~') && (strpos($path, $pagesPath) === 0)) {
        $path = '~page/'.substr($path, strlen($pagesPath));
    }
    return $path;
} //convertFsToHttpPath





function resolvePath($path, $relativeToCurrPage = false, $httpAccess = false, $absolutePath = false, $isResource = null)
{
    global $globalParams;
    $path = trim($path);
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

	$host  = isset($globalParams['host']) ? $globalParams['host'] : '';
	$appRoot  = isset($globalParams['appRoot']) ? $globalParams['appRoot'] : '';
	$pagePath  = isset($globalParams['pagePath']) ? $globalParams['pagePath'] : '';
	$pageFolder  = isset($globalParams['pageFolder']) ? $globalParams['pageFolder'] : '';
	$pathToPage  = isset($globalParams['pathToPage']) ? $globalParams['pathToPage'] : '';
	$absAppRoot = isset($globalParams['absAppRoot']) ? $globalParams['absAppRoot'] : '';


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
                    $path = $appRoot . $globalParams['dataPath'] . $path;
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
                    $path = $globalParams['dataPath'] . $path;
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
                        $path = "{$globalParams["filepathToDocroot"]}$path";
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
    while ($path && preg_match('|(.*?) ([^/\.]+/\.\./) (.*)|x', $path, $m)) {
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
    $appRoot = $GLOBALS["globalParams"]["appRoot"];
    $prefix = $appRoot.'?lzy=';
    $p = strpos($html, '~/');
    while ($p !== false) {
        if (substr($html, $p-6, 5) === 'href=') {
            if (preg_match('|^([\w-/\.]*)|', substr($html, $p + 2, 30), $m)) {
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
	    if ((strpos($elem, ':') === false) && preg_match('/(\S*)\s*\-\s*(\S*)/', $elem, $m)) {
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




function getCliArg($argname, $stringMode = false)
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
	    $cliarg = getUrlArg($argname, $stringMode);
    }
	return $cliarg;
} // getCliArg




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
    global $globalParams;
    if ($target === true) {
        $target = $globalParams['requestedUrl'];
    } elseif ($target) {
        $target = resolvePath($target, false, true);
    } else {
        $target = $globalParams['pageUrl'];
        $target = preg_replace('|/[A-Z][A-Z0-9]{4,}/?$|', '/', $target);
    }
    if ($getArg) {
        setNotificationMsg($getArg);
    }
    header("Location: $target");
    exit;
} // reloadAgent




function get_post_data($varName, $permitNL = false)
{
	$out = false;
	if (isset($_POST) && isset($_POST[$varName])) {
		$out = $_POST[$varName];
		$out = safeStr($out, $permitNL);
	}
	return $out;
} // get_post_data




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




function preparePath($path)
{
    if ($path && ($path[0] === '~')) {
        $path = resolvePath($path);
    }
	$path = dirname($path.'x');
    if (!file_exists($path)) {
        try {
            mkdir($path, MKDIR_MASK2, true);
        } catch (Exception $e) {
            fatalError("Error: failed to create folder '$path'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
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




function isValidUrl( $url )
{
    return preg_match("/(?i)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]".
        "+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'\".,<>?«»“”‘’]))/", $url);
} // isValidUrl




function is_safe($str, $multiline = false)
{
	if ($multiline) {
		$str = str_replace(PHP_EOL, '', $str);
		$str = str_replace("\r", '', $str);
	}
    return !preg_match("/[^\pL\pS\pP\pN\pZ]/um", $str);
} // is_safe




function safeStr($str, $permitNL = false)
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
        shell_exec("touch -t ".date("YmdHi.s", $time)." $file");
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
	$str = preg_replace("/[^[:alnum:]\._-`]/m", '', $str);	// remove any non-printables
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




function mylog($str, $destination = false)
{
	writeLogStr($str, $destination);
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
    global $globalParams;

    if (is_array($str)) {
        $str = json_encode($str);
    }
    if (!$errlog) {
        if (($globalParams['activityLoggingEnabled'] !== null) && !$globalParams['activityLoggingEnabled']) {
            return;
        }
        file_put_contents(LOG_FILE, timestamp()."  $str\n\n", FILE_APPEND);

    } else {
        if (($errlog === true) && ($globalParams['errorLoggingEnabled'] !== null) && !$globalParams['errorLoggingEnabled']) {
            return;
        }
        if (is_string($errlog)) {
            // only allow files in LOGS_PATH and only with extension .txt or .log:
            $destination = $errlog;
        } else {
            $destination = $globalParams['errorLogFile'];
        }
        if ($destination) {
            file_put_contents($destination, timestamp() . "  $str\n\n", FILE_APPEND);
        }
    }
} // writeLogStr




function logError($str)
{
    writeLogStr($str, true);
} // logError




function explodeTrim($sep, $str)
{
    if (!$str) {
        return [];
    }
    if (strpbrk($str, $sep) === false) {
        return [ $str ];
    }
    if (strlen($sep) > 1) {
        $sep = preg_quote($sep);
        return array_map('trim', preg_split("/[$sep]/", $str));
    } else {
        return array_map('trim', explode($sep, $str));
    }
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



$timer = 0;

function startTimer() {
	global $timer;
	$timer = microtime(true);
} // startTimer




function readTimer() {
	global $timer;
	return "Time: ".(round((microtime(true) - $timer)*1000000) / 1000 - 0.005).' ms';
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




function stripNewlinesWithinTransvars($str)
{
    $p1 = strpos($str, '{{');
	if ($p1 === false) {
		return $str;
	}
    do {
        list($p1, $p2) =  strPosMatching($str, '{{',  '}}',$p1);

        if ($p1 === false) {
            break;
        }
        $s = substr($str, $p1, $p2-$p1+2);
        $s = preg_replace("/\n\s*/ms", '↵ ',$s);
 
        $str = substr($str, 0, $p1) . $s . substr($str, $p2+2);
        $p1 += strlen($s);
    } while (strpos($str, '{{', $p1) !== false);
	
    return $str;
} // stripNewlinesWithinTransvars



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




function compileMarkdownStr($mdStr, $removeWrappingPTags = false)
{
    $md = new LizzyMarkdown();
    $str = $md->compileStr($mdStr);
    if ($removeWrappingPTags) {
        $str = preg_replace('/^\<p>(.*)\<\/p>(\s*)$/ms', "$1$2", $str);
    }
    return $str;
} // compileMarkdownStr




function shieldMD($md)
{
    $md = str_replace('#', '&#35;', $md);
    return $md;
} // shieldMD





function isLocalCall()
{
    $serverName = (isset($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost';
    $remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : '';
    if (($state = getStaticVariable('localcall')) !== null) {
        return $state;
    }
    if (($serverName === 'localhost') || ($remoteAddress === '::1')) {
        return true;
    } else {
        return false;
    }
} // isLocalCall




function checkPermission($str, $lzy = false) {
    $neg = false;
    $res = false;
    if (preg_match('/^((non|not|\!)\-?)/i', $str, $m)) {
        $neg = true;
        $str = substr($str, strlen($m[1]));
    }

    if ( ($str === true) || ($str === 'true') ) {
        $res = true;
    } elseif ( ($str === false) || ($str === 'false') ) {
        $res = false;
    } elseif (preg_match('/privileged/i', $str)) {
        $res = $GLOBALS['globalParams']['isPrivileged'];
    } elseif (preg_match('/loggedin/i', $str)) {
        $res = $GLOBALS['globalParams']['isLoggedin'] || $GLOBALS['globalParams']['isAdmin'];
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
    return $res;
} // checkPermission





function getGitTag($shortForm = true)
{
    $str = shell_exec('cd _lizzy; git describe --tags --abbrev=0; git log --pretty="%ci" -n1 HEAD');
    if ($shortForm) {
        return preg_replace("/\n.*/", '', $str);
    } else {
        return str_replace("\n", ' ', $str);
    }
} // getGitTag





function fatalError($msg, $origin = '', $offendingFile = '')
 // $origin =, 'File: '.__FILE__.' Line: '.__LINE__;
{
    global $globalParams;
    $out = '';
    $problemSrc = '';
    if ($offendingFile) {
        $problemSrc = "problemSrc: $offendingFile, ";
    } elseif (isset($globalParams['lastLoadedFile'])) {
        $offendingFile = $globalParams['lastLoadedFile'];
        $problemSrc = "problemSrc: $offendingFile, ";
    }
    if ($origin) {
        if (preg_match('/File:\s*(.*)\s*Line:(.*)/', $origin, $m)) {
            $file = trim($m[1]);
            $l = (isset($globalParams['absAppRoot'])) ? $globalParams['absAppRoot']: 0;
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

    if (isLocalCall()) {
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
    if (preg_match('/(.*?) \< ([\w\d\'-.@]+) \>/x', $to, $m)) {
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
        if (preg_match('/(.*?) \< ([\w\d\'-.@]+) \>/x', $from, $m)) {
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
    if (($html === null) && preg_match('/^\<(html|!DOCTYPE)/i', $message)) {
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




function registerFileDateDependencies($list)
{
    if (!$GLOBALS['globalParams']['cachingActive']) {
        return;
    }

    $depFileName = $GLOBALS['globalParams']['pathToPage'].CACHE_DEPENDENCY_FILE;
    if (file_exists($depFileName)) {
        $dependencies = file($depFileName, FILE_IGNORE_NEW_LINES);
        if (is_array($list)) {
            foreach ($list as $item) {
                if (!in_array($item, $dependencies)) {
                    $dependencies[] = $item;
                }
            }

        } else {
            if (!in_array($list, $dependencies)) {
                $dependencies[] = $list;
            }
        }
    } else {
        if (is_array($list)) {
            $dependencies = $list;
        } else {
            $dependencies = [$list];
        }
    }
    file_put_contents($depFileName, implode("\n", $dependencies));
} // registerFileDateDependencies




function writeToCache($obj, $cacheFileName = CACHE_FILENAME)
{
    global $globalParams;

    $cacheFile = $globalParams['pathToPage'].$cacheFileName;
    file_put_contents($cacheFile, serialize($obj));
} // writeToCache




function readFromCache($cacheFileName = CACHE_FILENAME)
{
    global $globalParams;

    $cacheFile = $globalParams['pathToPage'].$cacheFileName;
    if (!file_exists($cacheFile)) {
        return false;
    }
    $pageCacheDependencies = $globalParams['pathToPage'].CACHE_DEPENDENCY_FILE;
    if (!file_exists($pageCacheDependencies)) {
        return false;
    }
    $srcFiles = file($pageCacheDependencies, FILE_IGNORE_NEW_LINES);

    $fTime = filemtime($cacheFile);
    foreach($srcFiles as $f) {
        if (file_exists($f) && ($fTime < filemtime($f))) {
            return false;
        }
    }

    return unserialize(file_get_contents($cacheFile));
} // readFromCache




function createHash( $hashSize = 8, $unambiguous = false )
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

    // short-hands:
    if (($mode === 'ul') || ($mode === 'ol')) {
        $args['wrapperTag'] = 'li';
        $args['separator'] = '';
        $outerWrapperTag = $mode;
    }

    if (is_array($list)) {
        $elements = $list;
    } else {
        $elements = explodeTrim(',', $list);
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