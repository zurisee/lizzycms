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

$serv = new LiveDataService();
$response = $serv->execute();
exit($response);



class LiveDataService
{
    public function __construct()
    {
        session_start();
        $this->session = isset($_SESSION['lizzy']) ? $_SESSION['lizzy'] : [];
        $timezone = isset($session['systemTimeZone']) && $this->session['systemTimeZone'] ? $this->session['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);

        $this->lastModif = 0;
        $this->pollingTime = DEFAULT_POLLING_TIME;
        session_abort();
    } // __construct




    public function execute()
    {
        $dynDataSel = isset($_GET['dynDataSel']) ? $_GET['dynDataSel'] : false;
        $dynDataSelectors = [];
        if ($dynDataSel && preg_match('/(.*):(.*)/', $dynDataSel, $m)) {
            $dynDataSelectors[ 'name' ] = $m[1];
            $dynDataSelectors[ 'value' ] = $m[2];
        }
        $this->dynDataSelectors = $dynDataSelectors;
        $this->dynDataSel = $dynDataSel;

        $this->openDataSrcs();

        $returnImmediately = isset($_GET['returnImmediately']);
        if ($returnImmediately) {
            $this->lastUpdated = microtime( true );
            $returnData = $this->assembleResponse();
            $returnData['result'] = 'Ok';

        } else {
            if ($this->awaitDataChange()) {
                $returnData = $this->assembleResponse();
                $returnData['result'] = 'Ok';
            } else {
                $returnData['result'] = 'None';
            }
        }

        $returnData['lastUpdated'] = microtime(true) + 0.000001;
        $json = json_encode($returnData);
        exit($json);
    } // execute




    private function getListOfTickets()
    {
        if (!isset($_POST['ref'])) {
            exit('Error: "ref" missing in call to _live_data_service.php');
        }
        $ref = $_POST['ref'];
        $this->lastUpdated = floatval($_POST['last']);

        $tickets = explode(',', $ref);
        $ticketList = [];
        foreach ($tickets as $ticket) {
            if (in_array($ticket, $ticketList) === false) {
                $ticketList[] = $ticket;
            }
        }
        return $ticketList;
    } // getListOfTickets




    private function openDataSrcs()
    {
        $ticketList = $this->getListOfTickets();

        $tick = new Ticketing();
        $this->dataSrcs = [];
        foreach ($ticketList as $ticket) {
            $recs = $tick->consumeTicket($ticket);
            if (!is_array($recs)) {
                continue;
            }
            foreach ($recs as $rec) {
                $file = $rec['dataSource'];
                if (!isset($this->dataSrcs[$file])) {
                    $this->dataSrcs[$file] = [$rec];
                } else {
                    array_push($this->dataSrcs[$file], $rec);
                }

                if (isset($rec['pollingTime']) && ($rec['pollingTime'] > 2)) {
                    $this->pollingTime = $rec['pollingTime'];
                } else {
                    $this->pollingTime = DEFAULT_POLLING_TIME;
                }
            }
        }
        foreach ($this->dataSrcs as $file => $elems) {
            $this->dataSrcs[$file]['db'] = new DataStorage2(PATH_TO_APP_ROOT . $file);
        }
    } // openDataSrcs





    private function awaitDataChange()
    {
        $till = time() + min($this->pollingTime,100); // s
        while (time() < $till) {
            $this->outData = [];
            // there may be multiple data sources, so loop over all of them:
            foreach ($this->dataSrcs as $file => $taskDescr) {
                $db = $taskDescr['db'];
                $lastDbModified = $db->lastDbModified();
                if (($this->lastUpdated < $lastDbModified)) {
                    $this->lastUpdated = $lastDbModified;
                    return true;
                }

                foreach ($taskDescr as $k => $r) {
                    if (!is_int($k)) {
                        continue;
                    }
                    $this->lastModif = $db->lastModifiedElement( $k );
                    if ($this->lastUpdated < $this->lastModif) {
                        $this->lastUpdated = $this->lastModif;
                        return true;
                    }
                }
            }
            $this->checkAbort();
            usleep( POLLING_INTERVAL );
        }
        return false;
    } // awaitDataChange




    private function assembleResponse()
    {
        $outData = [];
        foreach ($this->dataSrcs as $file => $dbDescr) {
            $outRec = $this->getData( $dbDescr );
            if (is_array($outRec)) {
                $outData = array_merge($outData, $outRec);
            } else {
                $outData = $outRec; // case error msg
                break;
            }
        }
        return $outData;
    } // assembleResponse




    private function getData( $dbDescr )
    {
        $db = $dbDescr['db'];
        $dbIsLocked = $db->isDbLocked( false );
        $lockedElements = [];
        foreach ($dbDescr as $k => $elem) {
            if (!is_int($k)) {
                continue;
            }
            $targetSelector = $dbDescr[$k]['targetSelector'];
            $dataKey = $dbDescr[$k]["dataSelector"];
            if ((strpos($dataKey, '{') !== false) && $this->dynDataSelectors) {
                $dataKey = preg_replace('/\{'.$this->dynDataSelectors['name'].'\}/', $this->dynDataSelectors['value'], $dataKey);
            }
            if ($dbIsLocked || $db->isRecLocked( $dataKey )) {
                $lockedElements[] = $targetSelector;
            }
            $value = $db->readElement( $dataKey );
            if (is_array($value)) {
                foreach ($value as $i => $v) {
                    $t = str_replace('*', ($i + 1), $targetSelector);
                    $outData['data'][$t] = $v;
                }
            } else {
                $outData['data'][$targetSelector] = $value;
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($lockedElements !== $_SESSION['lizzy']['hasLockedElements']) {
            $outData['locked'] = $lockedElements;
            $_SESSION['lizzy']['hasLockedElements'] = $lockedElements;
        }
        session_abort();
        return $outData;
    } // getData




    private function checkAbort()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['lizzy']['ajaxServerAbort'])) {
            $abortRequest = $_SESSION['lizzy']['ajaxServerAbort'];
            unset($_SESSION['lizzy']['ajaxServerAbort']);
            session_abort();
            if ($abortRequest < (time() - 2)) {
                writeLog("live-data ajax-server aborting (\$_SESSION['lizzy']['ajaxServerAbort'] is set)");
                exit();
            }
        }
        session_abort();
    } // checkAbort

} // LiveDataService