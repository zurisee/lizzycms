<?php
// Backend for live-data() macro


define('SYSTEM_PATH', 		'');		 // same directory
define('PATH_TO_APP_ROOT', 	'../');		 // root folder of web app
define('DEFAULT_POLLING_TIME', 	60);		 // s
define('MAX_POLLING_TIME', 	    90);		 // s
define('POLLING_INTERVAL', 	330000);		 // Us

require_once 'vendor/autoload.php';
require_once 'backend_aux.php';
require_once 'datastorage2.class.php';
require_once 'ticketing.class.php';

session_start();
$session = isset($_SESSION['lizzy']) ? $_SESSION['lizzy'] : [];
session_abort();

$timezone = isset($session['systemTimeZone']) && $session['systemTimeZone'] ? $session['systemTimeZone'] : 'CET';
date_default_timezone_set($timezone);

$lastModif = 0;
$pollingTime = DEFAULT_POLLING_TIME;

if (!isset($_POST['ref'])) {
    exit('Error: "ref" missing in call to _live_data_service.php');
}
$ref = $_POST['ref'];
$lastUpdated = floatval( $_POST['last'] );

if (isset($_POST['polltime'])) {
    $pollingTime = intval($_POST['polltime']);
    $pollingTime = max(2, min(MAX_POLLING_TIME, $pollingTime));
}

$dataSel = isset($_GET['dataSelector']) ? $_GET['dataSelector'] : false;
$dataSelector = [];
if ($dataSel && preg_match('/(.*):(.*)/', $dataSel, $m)) {
    $dataSelector[ 'name' ] = $m[1];
    $dataSelector[ 'value' ] = $m[2];
}

$returnImmediately = isset($_GET['returnImmediately']);

$tick = new Ticketing();
$tickets = explode(',', $ref);

$files = [];
$ticketList = [];
foreach ($tickets as $ticket) {
    if (in_array($ticket, $ticketList) === false) {
        $ticketList[] = $ticket;
    }
}

$files = [];
foreach ($ticketList as $ticket) {
    $recs = $tick->consumeTicket($ticket);
    if (!is_array($recs)) {
        continue;
    }
    foreach ($recs as $rec) {
        $file = $rec['file'];
        if (!isset($files[$file])) {
            $files[$file] = [$rec];
        } else {
            array_push($files[$file], $rec);
        }
    }
}

openDBs($files);

if ($returnImmediately) {
    $res = getAllData($files);
} else {
    $till = time() + $pollingTime;
    $res = awaitDataChange($files, $till);
}
if (!$res) {
    $rec['result'] = 'None';
} else {
    $rec = [
        'result' => 'Ok',
        'data' => $res,
    ];
}
$rec['lastUpdated'] = $lastModif + 0.001;
$json = json_encode($rec);
exit($json);




function openDBs( &$files )
{
    foreach ($files as $file => $elems) {
        $files[$file]['db'] = new DataStorage2(PATH_TO_APP_ROOT . $file);
    }
}



function getAllData( &$files )
{
    $outData = [];
    foreach ($files as $file => $dbDescr) {
        $db = $dbDescr['db'];
        foreach ($dbDescr as $k => $r) {
            if (!is_int($k)) {
                continue;
            }
            $outRec = getData($db, $dbDescr);
            $outData = array_merge($outData, $outRec);
        }
    }
    return $outData;
}



function awaitDataChange( &$files, $till )
{
    global $lastUpdated, $lastModif;
    $till = min($till, time() + 100);   // for savety
    while (time() < $till) {
        $outData = [];
        // there may be multiple data sources, so loop over all of them
        foreach ($files as $file => $dbDescr) {
            $db = $dbDescr['db'];
            foreach ($dbDescr as $k => $r) {
                if (!is_int($k)) {
                    continue;
                }
                $lastModif = $db->lastModifiedElement( $k );
                if ($lastUpdated < $lastModif) {
                    $outRec = getData($db, $dbDescr);
                    $outData = array_merge($outData, $outRec);
                }
            }
        }
        if ($outData) {
            return $outData;
        }
        checkAbort();
        usleep( POLLING_INTERVAL );
    }
    return false;
}



function checkAbort()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['lizzy']['ajaxServerAbort'])) {
        unset($_SESSION['lizzy']['ajaxServerAbort']);
        session_abort();
        exit();
    }
    session_abort();
} // checkAbort



function getData( &$db, $rec)
{
    global $dataSelector;
    foreach ($rec as $k => $elem) {
        if (!is_int($k)) {
            continue;
        }
        $id = $rec[$k]['id'];
        $dataKey = $rec[$k]["elementName"];
        if ((strpos($dataKey, '{') !== false) && $dataSelector) {
            $dataKey = preg_replace('/\{'.$dataSelector['name'].'\}/', $dataSelector['value'], $dataKey);
        }
        $value = $db->readElement( $dataKey );
        $outData[$id] = $value;
    }
    return $outData;
} // getData

