<?php

define('EXTENSIONS_PATH', 	SYSTEM_PATH.'extensions/');		        //
define('DATA_PATH', 		PATH_TO_APP_ROOT.'data/');		        // must correspond to lizzy app
define('CACHE_PATH',        PATH_TO_APP_ROOT.'.#cache/');           // required by Ticketing class
define('SERVICE_LOG',       PATH_TO_APP_ROOT.'.#logs/backend-log.txt');	    //
define('ERROR_LOG',         PATH_TO_APP_ROOT.'.#logs/errlog.txt');	//
define('RECYCLE_BIN',           '.#recycleBin/');
define('RECYCLE_BIN_PATH',      '~page/'.RECYCLE_BIN);
if (!defined('MKDIR_MASK')) {
    define('MKDIR_MASK', 0700);
}
define('DEFAULT_EDITABLE_DATA_FILE', 'editable.yaml');

$appRoot = preg_replace('/_lizzy\/.*$/', '', getcwd().'/');

//------------------------------------------------------------------------------
function explodeTrim($sep, $str)
{
    if (!$str) {
        return [];
    }
    if (strpos($str, $sep) === false) {
        return [ $str ];
    }
    if (strlen($sep) > 1) {
        $sep = preg_quote($sep);
        $out = array_map('trim', preg_split("/[$sep]/", $str));
        return $out;
    } else {
        return array_map('trim', explode($sep, $str));
    }
} // explodeTrim



//---------------------------------------------------------------------------
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






//-----------------------------------------------------------------------------
function trunkPath($path, $n = 1)
{
    $path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
    return implode('/', explode('/', $path, -$n)).'/';
} // trunkPath




//------------------------------------------------------------
function preparePath($path)
{
    $path = dirname($path.'x');
    if (!file_exists($path)) {
        if (!mkdir($path, MKDIR_MASK, true)) {
            fatalError("Error: failed to create folder '$path'");
        }
    }
} // preparePath



//------------------------------------------------------------
function fatalError($msg)
{
    $msg = date('Y-m-d H:i:s')." [_ajax_server.php]\n$msg\n";
    file_put_contents(ERROR_LOG, $msg, FILE_APPEND);
    exit;
} // fatalError



//------------------------------------------------------------
function resolvePath($path)
{
    if (strpos($path, ':') !== false) {   // https://, tel:, sms:, etc.
        return $path;
    }
    $path = trim($path);
    $dataPath = isset($_SESSION["lizzy"]["dataPath"])? $_SESSION["lizzy"]["dataPath"]: 'data/';
    $pathToPage = isset($_SESSION["lizzy"]["pathToPage"])? $_SESSION["lizzy"]["pathToPage"]: '';

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
//        PATH_TO_APP_ROOT.$_SESSION["lizzy"]["dataPath"],
        SYSTEM_PATH,
        PATH_TO_APP_ROOT.EXTENSIONS_PATH,
        PATH_TO_APP_ROOT.$pathToPage,
//        PATH_TO_APP_ROOT.$_SESSION["lizzy"]["pathToPage"],
    ];
//    $to = [
//        '',
//        $_SESSION["lizzy"]["dataPath"],
//        SYSTEM_PATH,
//        EXTENSIONS_PATH,
//        $_SESSION["lizzy"]["pathToPage"],
//    ];

    $path = preg_replace($from, $to, $path);
    return $path;
} // resolvePath




//---------------------------------------------------------------------------
function mylog($str, $user = false)
{
    writeLog($str, $user);
} // mylog


//---------------------------------------------------------------------------
function writeLog($str, $user = false)
{
    if (!$user) {
        $user = isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user'] ? $_SESSION['lizzy']['user'] : 'anonymous';
    }
    $path = dirname(SERVICE_LOG);
    if (!file_exists($path)) {
        mkdir($path, MKDIR_MASK, true);
    }
    file_put_contents(SERVICE_LOG, timestamp()." user $user:  $str\n", FILE_APPEND);
} // writeLog



//---------------------------------------------------------------------------
function timestamp($short = false)
{
    if (!$short) {
        return date('Y-m-d H:i:s');
    } else {
        return date('Y-m-d');
    }
} // timestamp



//---------------------------------------------------------------------------
function var_r($var)
{
    return str_replace("\n", '', var_export($var, true));
}