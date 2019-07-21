<?php

define('EXTENSIONS_PATH', 	SYSTEM_PATH.'extensions/');		        //
define('DATA_PATH', 		PATH_TO_APP_ROOT.'data/');		        // must correspond to lizzy app
define('CACHE_PATH',        PATH_TO_APP_ROOT.'.#cache/');           // required by Ticketing class
define('SERVICE_LOG',       PATH_TO_APP_ROOT.'.#logs/backend-log.txt');	    //
define('ERROR_LOG',         PATH_TO_APP_ROOT.'.#logs/errlog.txt');	//
define('RECYCLE_BIN',           '.#recycleBin/');
define('RECYCLE_BIN_PATH',      '~page/'.RECYCLE_BIN);

$appRoot = preg_replace('/_lizzy\/.*$/', '', getcwd());

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

    $from = [
        '|~/|',
        '|~data/|',
        '|~sys/|',
        '|~ext/|',
        '|~page/|',
    ];
    $to = [
        '',
        $_SESSION["lizzy"]["dataPath"],
        SYSTEM_PATH,
        EXTENSIONS_PATH,
        $_SESSION["lizzy"]["pathToPage"],
    ];

    $path = preg_replace($from, $to, $path);
    return $path;
} // resolvePath




//---------------------------------------------------------------------------
function mylog($str)
{
//    $file = $GLOBALS["appRoot"].SERVICE_LOG;
    $path = dirname(SERVICE_LOG);
//    $path = dirname($file);
    if (!file_exists($path)) {
        mkdir($path, MKDIR_MASK, true);
    }
    file_put_contents(SERVICE_LOG, timestamp()." user:  $str\n", FILE_APPEND);
//    file_put_contents($file, timestamp()." user:  $str\n", FILE_APPEND);
} // mylog



//---------------------------------------------------------------------------
function timestamp($short = false)
{
    if (!$short) {
        return date('Y-m-d H:i:s');
    } else {
        return date('Y-m-d');
    }
} // timestamp

