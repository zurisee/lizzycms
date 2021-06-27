<?php

 // PATH_TO_APP_ROOT must to be defined by the invoking module
 // *_PATH constants must only define path starting from app-root

define('EXTENSIONS_PATH', 	    SYSTEM_PATH.'extensions/');
define('DATA_PATH', 		    'data/');
define('CACHE_PATH',            '.cache/');
define('LOG_PATH',              '.#logs/');
define('DEFAULT_TICKETS_PATH',  '.#tickets/');

define('SERVICE_LOG',           PATH_TO_APP_ROOT . LOG_PATH.'backend-log.txt');
define('ERROR_LOG',             PATH_TO_APP_ROOT . LOG_PATH.'errlog.txt');

define('RECYCLE_BIN',           '.#recycleBin/');
define('RECYCLE_BIN_PATH',      '~page/'.RECYCLE_BIN);
if (!defined('MKDIR_MASK')) {
    define('MKDIR_MASK', 0700);
}
define('DEFAULT_EDITABLE_DATA_FILE', 'editable.yaml');
define('REC_KEY_ID', 	        '_key');
define('TIMESTAMP_KEY_ID', 	    '_timestamp');
define('PASSWORD_PLACEHOLDER', 	'●●●●');

use Symfony\Component\Yaml\Yaml;


$GLOBALS['globalParams']['isBackend'] = true;
$appRoot = getcwd().'/';
if (strpos($appRoot, '_lizzy/') !== false) {
    $appRoot = preg_replace('/_lizzy\/.*$/', '', $appRoot);
} else {
    // if script not located below _lizzy/ it must be in code/, so appRoot must be one above:
    $appRoot = trunkPath(getcwd().'/', 1);
}


function translateToIdentifier($str, $removeDashes = false, $removeNonAlpha = false)
{
    // translates special characters (such as , , ) into identifier which contains but safe characters:
    $str = mb_strtolower($str);		// all lowercase
    $str = strToASCII( $str );		// replace umlaute etc.
    $str = strip_tags( $str );							// strip any html tags
    if ($removeNonAlpha) {
        $str = preg_replace('/[^a-zA-Z]/ms', '', $str);

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




function explodeTrim($sep, $str, $excludeEmptyElems = false)
{
    $str = trim($str);
    if ($str === '') {
        return [];
    }
    if (strpos($str, $sep) === false) {
        return [ $str ];
    }
    if (strlen($sep) > 1) {
        $sep = preg_quote($sep);
        $out = array_map('trim', preg_split("/[$sep]/", $str));
    } else {
        $out = array_map('trim', explode($sep, $str));
    }

    if ($excludeEmptyElems) {
        $out = array_filter($out, function ($item) {
            return ($item !== '');
        });
    }
    return $out;
} // explodeTrim




function get_post_data($varName, $permitNL = false)
{
    $out = false;
    if (isset($_POST) && isset($_POST[$varName])) {
        $out = $_POST[$varName];
        $out = safeStr($out, $permitNL, false);
    }
    return $out;
} // get_post_data




function array2DKey(&$key)
{
    $key = str_replace(' ','', $key);
    $a = explode(',', $key);
    if (sizeof($a) > 1) {
        return $a;
    } else {
        return false;
    }
} // array2DKey




function trunkPath($path, $n = 1)
{
    $path = ($path[strlen($path)-1] === '/') ? rtrim($path, '/') : dirname($path);
    return implode('/', explode('/', $path, -$n)).'/';
} // trunkPath




function preparePath($path)
{
    $path = dirname($path.'x');
    if (!file_exists($path)) {
        if (!mkdir($path, MKDIR_MASK, true)) {
            fatalError("Error: failed to create folder '$path'");
        }
    }
} // preparePath




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




function safeStr($str, $permitNL, $isGetArg = true)
{
    if (preg_match('/^\s*$/', $str)) {
        return '';
    }
    if ($permitNL) {
        $str = preg_replace("/[^[:print:]À-ž\n\t]/m", ' ', $str);

    } else {
        $str = preg_replace('/[^[:print:]À-ž]/m', ' ', $str);
    }
    if ($isGetArg) {
        $str = substr($str, 0, MAX_URL_ARG_SIZE);    // restrict size to safe value
    }
    return $str;
} // safe_str



function compileMarkdownStr($mdStr, $removeWrappingPTags = false)
{
    require_once SYSTEM_PATH.'markdown_extension.class.php';
    require_once SYSTEM_PATH.'lizzy-markdown.class.php';

    $md = new LizzyMarkdown();
    $str = $md->compileStr($mdStr);
    if ($removeWrappingPTags) {
        $str = preg_replace('/^\<p>(.*)\<\/p>(\s*)$/ms', "$1$2", $str);
    }
    return $str;
} // compileMarkdownStr



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





function lzyExit( $str = '' )
{
    if (strlen($buff = ob_get_clean ()) > 1) {
        $buff = strip_tags( $buff );
        file_put_contents(PATH_TO_APP_ROOT.'.#logs/output-buffer-backend.txt', $buff);
    }
    exit($str);
} // lzyExit





function fatalError($msg)
{
    $msg = date('Y-m-d H:i:s')." [_ajax_server.php]\n$msg\n";
    file_put_contents(ERROR_LOG, $msg, FILE_APPEND);
    exit;
} // fatalError




function checkPermission($str) {
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
        $res = $_SESSION['lizzy']['isPrivileged'];

    } elseif (preg_match('/loggedin/i', $str)) {
        $res = ($_SESSION['lizzy']['user'] || $_SESSION['lizzy']['isAdmin']);

    } elseif (($str !== 'true') && !is_bool($str)) {
        $res = checkGroupMembership($str);
    }

    if ($neg) {
        $res = !$res;
    }
    return $res;
} // checkPermission




function checkGroupMembership($requiredGroup)
{
    if (!isset($_SESSION['lizzy']['userRec']['groups'])) {
        return false;
    }

    $usersGroup = $_SESSION['lizzy']['userRec']['groups'];
    $requiredGroups = explode(',', $requiredGroup);
    $usersGroups = strtolower(str_replace(' ', '', ",$usersGroup,"));
    foreach ($requiredGroups as $rG) {
        $rG = strtolower(trim($rG));
        if ((strpos($usersGroups, ",$rG,") !== false) ||
            (strpos($usersGroups, ",admins,") !== false)) {
            return true;
        }
    }
    return false;
} // checkGroupMembership



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




function createHash( $hashSize = 8)
{
    $hash = chr(random_int(65, 90));  // first always a letter
    $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $hashSize - 1));  // letters and digits
    return $hash;
} // createHash





function resolvePath($path)
{
    // Note: resolvePath() on backend always resolves for file-access (not HTTP access):
    if (strpos($path, ':') !== false) {   // https://, tel:, sms:, etc.
        return $path;
    }
    $path = trim($path);
    $dataPath = isset($GLOBALS['globalParams']['dataPath'])? $GLOBALS['globalParams']['dataPath'] :
        (isset($_SESSION['lizzy']['dataPath'])? $_SESSION['lizzy']['dataPath']: 'data/');
    $pageFolder = isset($GLOBALS['globalParams']['pageFolder'])? $GLOBALS['globalParams']['pageFolder'] :
        (isset($_SESSION['lizzy']['pageFolder'])? $_SESSION['lizzy']['pageFolder']: '');

    $from = [
        '|~/|',
        '|~data/|',
        '|~sys/|',
        '|~ext/|',
        '|~page/|',
    ];
    $to = [
        PATH_TO_APP_ROOT,
        PATH_TO_APP_ROOT.$dataPath,
        SYSTEM_PATH,
        PATH_TO_APP_ROOT.EXTENSIONS_PATH,
        PATH_TO_APP_ROOT.$pageFolder,
    ];

    $path = preg_replace($from, $to, $path);
    return $path;
} // resolvePath




function mylog($str, $notDebugOnly = true)
{
    if (is_array($str)) {
        $str = var_r($str, 'mylog', true);
    }
    if ($notDebugOnly || @$_SESSION['lizzy']['debug']) {
        writeLog( $str );
    }
} // mylog




function writeLog($str, $user = false, $destFile = SERVICE_LOG)
{
    if (!$user) {
        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anon';
    }
    $path = dirname($destFile);
    if (!file_exists($path)) {
        mkdir($path, MKDIR_MASK, true);
    }
    file_put_contents($destFile, timestamp()." [$user]:  $str\n\n", FILE_APPEND);
} // writeLog




function timestamp($short = false)
{
    if (!$short) {
        return date('Y-m-d H:i:s');
    } else {
        return date('Y-m-d');
    }
} // timestamp




function var_r($var, $varName = '', $flat = false, $asHtml = true)
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
        if ($asHtml) {
            $out = "<div><pre>$varName: " . var_export($var, true) . "\n</pre></div>\n";
        } else {
            $out = "$varName: " . var_export($var, true) . "\n";
        }
    }
    return $out;
} // var_r




function getFile($pat, $removeComments = false, $removeEmptyLines = false)
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




