<?php
// Backend for live-data() macro


define('SYSTEM_PATH', 		'');		 // same directory
define('PATH_TO_APP_ROOT', 	'../');		 // root folder of web app
define('POLLING_INTERVAL', 	330);		 // ms

require_once 'vendor/autoload.php';
require_once 'backend_aux.php';
require_once 'datastorage2.class.php';
require_once 'ticketing.class.php';

$timezone = isset($_SESSION['lizzy']['systemTimeZone']) && $_SESSION['lizzy']['systemTimeZone'] ? $_SESSION['lizzy']['systemTimeZone'] : 'CET';
date_default_timezone_set($timezone);

$lastModif = 0;
$pollingTime = 10;

if (!isset($_POST['ref'])) {
    exit('Error: "ref" missing in call to _live_data_service.php');
}
$ref = $_POST['ref'];
$lastUpdated = floatval( $_POST['last'] );
$lastUpdatedStr = date('H:i:s', intval($lastUpdated));

if (isset($_POST['polltime'])) {
    $pollingTime = intval($_POST['polltime']);
    $pollingTime = max(2, min(90, $pollingTime));
}
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

$files = openDBs($files);
$till = time() + $pollingTime;
$res = awaitDataChange($files, $till);
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




function openDBs($files)
{
    foreach ($files as $file => $elems) {
        $files[$file]['db'] = new DataStorage2(PATH_TO_APP_ROOT . $file);
    }
    return $files;
}




function awaitDataChange($files, $till)
{
    global $lastUpdated, $lastModif;
    $interval = POLLING_INTERVAL + 1000;  // -> us
    while ($till > time()) {
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
        usleep($interval);
    }
    return false;
}



function getData($db, $rec)
{
    foreach ($rec as $k => $elem) {
        if (!is_int($k)) {
            continue;
        }
        $id = $rec[$k]['id'];
        $dataKey = $rec[$k]["elementName"];
        $value = $db->readElement( $dataKey );
        $outData[$id] = $value;
    }
    return $outData;
} // getData

