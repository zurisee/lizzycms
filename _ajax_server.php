<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Ajax Service for dynamic data, in particular 'editable' data
 * http://localhost/Lizzy/_lizzy/_ajax_server.php?lock=ed1
 *
*/

define('SYSTEM_PATH', 		'');		                            // same directory
define('PATH_TO_APP_ROOT', 	'../');		                            // root folder of web app
define('LOCK_TIMEOUT', 		120);	                                // max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE',  255);

ob_start();

require_once 'vendor/autoload.php';
require_once 'backend_aux.php';
require_once 'datastorage2.class.php';
require_once 'ticketing.class.php';


use Symfony\Component\Yaml\Yaml;

// prevent "PHPSESSID"-Cookie warning:
session_set_cookie_params(["SameSite" => "Strict"]); //none, lax, strict
session_set_cookie_params(["Secure" => "true"]); //false, true
session_set_cookie_params(["HttpOnly" => "true"]); //false, true


if (isset($_GET['abort'])) {
    session_start();
    $_SESSION['lizzy']['ajaxServerAbort'] = time();
    session_write_close();
    exit();
}

$serv = new AjaxServer;

class AjaxServer
{
	public function __construct()
	{
        $this->terminatePolling = false;
		if (sizeof($_GET) < 1) {
			exit('Hello, this is '.basename(__FILE__));
		}
        if (!session_start()) {
            $this->mylog("ERROR in __construct(): failed to start session");
        }
        $this->clear_duplicate_cookies();

        $timezone = isset($_SESSION['lizzy']['systemTimeZone']) && $_SESSION['lizzy']['systemTimeZone'] ? $_SESSION['lizzy']['systemTimeZone'] : 'CET';
        date_default_timezone_set($timezone);


        if (!isset($_SESSION['lizzy']['userAgent'])) {
            $this->mylog("*** Fishy request from {$_SERVER['HTTP_USER_AGENT']} (no valid session)");
            exit('restart');
        }

		$this->sessionId = session_id();
        $this->clientID = substr($this->sessionId, 0, 4);
		if (!isset($_SESSION['lizzy']['hash'])) {
			$_SESSION['lizzy']['hash'] = '#';
		}
		if (!isset($_SESSION['lizzy']['lastUpdated'])) {
			$_SESSION['lizzy']['lastUpdated'] = 0;
		}
        if (!isset($_SESSION['lizzy']['pagePath'])) {
		    die('Fatal Error: $_SESSION[\'lizzy\'][\'pagePath\'] not defined');
        }

        $this->db = false;
        $this->user = '';
        if (isset($_SESSION['lizzy']['userDisplayName']) && $_SESSION['lizzy']['userDisplayName']) {    // get user name for logging
            $this->user = '['.$_SESSION['lizzy']['userDisplayName'].']';

        } elseif (isset($_SESSION['lizzy']['user']) && $_SESSION['lizzy']['user']) {
            $this->user = '['.$_SESSION['lizzy']['user'].']';

        }

		$this->hash = $_SESSION['lizzy']['hash'];
		session_write_close();

		$this->remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
		$this->userAgent = isset($_SESSION['lizzy']['userAgent']) ? $_SESSION['lizzy']['userAgent'] : $_SERVER["HTTP_USER_AGENT"];
		$this->isLocalhost = (($this->remoteAddress === 'localhost') || (strpos($this->remoteAddress, '192.') === 0) || ($this->remoteAddress === '::1'));
		$this->handleUrlArguments();
		$this->formElements = [];

		$this->error = false;
	} // __construct



	private function handleUrlArguments()
	{
        $this->handleGenericRequests();     // log, info
        $this->handleFileRequests();        // Files: newfile, renamefile, getfile
    } // handleUrlArguments



    private function handleGenericRequests()
    {
        if (isset($_GET['log'])) {                                // remote log, write to backend's log
            $msg = $this->get_request_data('text');
            $file = $this->get_request_data('file');
            $this->mylog("Client: $msg", $file);
            lzyExit();
        }
        if ($this->get_request_data('info') !== null) {    // respond with info-msg
            $this->info();
        }
        if ($this->get_request_data('save-data') !== null) {    // respond with info-msg
            $this->saveData();
        }
        if ($this->get_request_data('get-all') !== null) {    // respond with info-msg
            $this->getAllData();
        }
        if ($this->get_request_data('get-rec') !== null) {    // respond with info-msg
            $this->getDataRec();
        }
        if ($this->get_request_data('get-elem') !== null) {    // respond with info-msg
            $this->getDataElem();
        }
        if ($this->get_request_data('lock-rec') !== null) {    // respond with info-msg
            $this->lockRec();
        }
        if ($this->get_request_data('unlock-rec') !== null) {    // respond with info-msg
            $this->unlockRec();
        }
        if ($this->get_request_data('save-rec') !== null) {    // respond with info-msg
            $this->saveDataRec();
        }
        if ($this->get_request_data('save-elem') !== null) {    // respond with info-msg
            $this->saveDataElem();
        }
        if ($this->get_request_data('del-rec') !== null) {    // respond with info-msg
            $this->deleteDataRec();
        }
    } // handleGenericRequests



	private function getAllData()
    {
        if (!$this->openDB( )) {
            lzyExit('failed#save');
        }
        lzyExit($this->prepareClientData().'#get-all');
    } // getAllData




	private function getDataRec()
    {
        if (!$this->openDB( )) {
            lzyExit('failed#get-rec');
        }
        $recKey = $this->get_request_data('recKey');
        if (!$recKey) {
            $recKey = $this->get_request_data('dataRef');
        }

        // lock record if requested:
        if (isset($_REQUEST['lock'])) {
            $res = $this->db->lockRec( $recKey );
            if (!$res) {
                $json = json_encode(['res' => 'failed', 'lockedRecs' => [$recKey]]);
                lzyExit( $json );
            }
        }

        $dataRec = $this->db->readRecord( $recKey );

        $json = json_encode(['res' => 'Ok', 'data' => $dataRec]);
        lzyExit( $json );
    } // getDataRec




	private function getDataElem()
    {
        if (!$this->openDB( )) {
            lzyExit('failed#get-rec');
        }

        $recKey = $this->getRecKey();

        // lock record if requested:
        if (isset($_REQUEST['lock'])) {
            $res = $this->db->lockRec( $recKey );
            if (!$res) {
                $json = json_encode(['res' => 'failed', 'lockedRecs' => [$recKey]]);
                lzyExit( $json );
            }
        }

        $dataRec = $this->db->readElement( $recKey );

        $json = json_encode(['res' => 'Ok', 'data' => $dataRec]);
        lzyExit( $json );
    } // getDataElem


//	private function getDataRec()
//    {
//        if (!$this->openDB( )) {
//            lzyExit('failed#get-rec');
//        }
//        $recKey = $this->get_request_data('recKey');
//        if (!$recKey) {
//            $recKey = $this->get_request_data('dataRef');
//        }
//
//        // lock record if requested:
//        if (isset($_REQUEST['lock'])) {
//            $res = $this->db->lockRec( $recKey );
//            if (!$res) {
//                $json = json_encode(['res' => 'failed', 'lockedRecs' => [$recKey]]);
//                lzyExit( $json );
//            }
//        }
//
//        $dataRec = $this->db->readRecord( $recKey );
//        $outData = [];
//        if (!$dataRec) {
//            $json = json_encode(['res' => 'Error: getDataRec() no data found', 'data' => '']);
//            lzyExit( $json );
//        }
//        if (isset($this->ticketRec['recDef'])) {
//            foreach ($this->ticketRec['recDef'] as $key => $rec) {
//                $outData["#fld_" . $rec[0]] = isset($dataRec[$key]) ? $dataRec[$key] : '';
//            }
//
//        } else {
//            foreach ( $this->ticketRec['formDescr'] as $descr) {
//                $key = $descr['name'];
//                $type = $descr['type'];
//                if (isset($dataRec[$key])) {
//                    $value = $dataRec[$key];
//                } else {
//                    $key1 = $descr['label'];
//                    $value = isset($dataRec[$key1])? $dataRec[$key1]: '';
//                }
//                if (is_array($value)) {
//                    // radio,checkbox,dropdown types have special structure:
//                    //  -> $value[0] contains display value
//                    if (isset($value[0])) {
//                        $value = $value[0];
//                    } else {
//                        $value = implode(',', $value);
//                    }
//                }
//                if ($type === 'radio') {
//                    $outData["input:radio[value='$value']"] = 'checked';
//                    continue;
//                } elseif ($type === 'checkbox') {
//                    $values = explodeTrim(',', rtrim($value, ','));
//                    foreach ($values as $vv) {
//                        $outData["input:checkbox[value='$vv']"] = 'checked';
//                    }
//                    continue;
//                } elseif ($type === 'dropdown') {
//                    // 	$('option[value=Italy]').attr('selected', 'selected');
//                    $outData["option[value='$value']"] = 'selected';
//                    continue;
//                }
//                if ($type === 'password') {
//                    $value = $value? PASSWORD_PLACEHOLDER:'';
//                }
//                $outData[ $key ] = $value;
//            }
//        }
//        $json = json_encode(['res' => 'Ok', 'data' => $outData]);
//        lzyExit( $json );
//    } // getDataRec



    private function deleteDataRec()
    {
        if (!$this->openDB( )) {
            lzyExit('failed#del-rec');
        }
        $recKeyStr = get_post_data('recKeys');
        mylog("deleteDataRec: $recKeyStr from $this->dataFile", false);
        $recKeys = explodeTrim(',', $recKeyStr);

        if (!$this->db->lockDB()) {
            lzyExit('failed#del-rec: error locking DB');
        }

        $res = true;
        rsort($recKeys);
        foreach ($recKeys as $recKey) {
            $res &= $this->db->deleteRecord($recKey, false, false);
        }

        if (!$this->db->unlockDB()) {
            lzyExit('failed#del-rec: error unlocking DB');
        }

        if (!$res) {
            mylog("deleteDataRec: Error deleting");
        }
        $res = $res? 'Ok': "del-rec#Error deleting records '$recKeyStr'";

        $json = json_encode(['res' => $res]);
        lzyExit( $json );
    } // deleteDataRec





    private function lockRec()
    {
        if (!$this->openDB( )) {
            if ( $this->error ) {
                mylog("AjaxServer: lock-rec -> openDB failed ($this->dataFile)");
                lzyExit(json_encode(['res' => 'restart#lock-rec openDB']));
            } else {
                mylog("AjaxServer: lock-rec -> openDB failed ($this->dataFile)");
                lzyExit(json_encode(['res' => 'failed#lock-rec openDB']));
            }
        }
        $recKey = $this->getRecKey();

        // lock record:
        $res = $this->db->lockRec( $recKey );

        mylog("AjaxServer: lock-rec -> success ($recKey)");
        $json = json_encode(['res' => $res? 'Ok': 'failed#lock-rec']);
        lzyExit( $json );
    } // lockRec





    private function unlockRec()
    {
        if (!$this->openDB( )) {
            mylog("AjaxServer: unlock-rec -> openDB failed ($this->dataFile)");
            lzyExit(json_encode(['res' => 'failed#unlock-rec openDB']));
        }
        $recKey = $this->getRecKey();

        // unlock record:
        $res = $this->db->unlockRec( $recKey );

        mylog("AjaxServer: unlock-rec -> success ($recKey)");
        $json = json_encode(['res' => $res? 'Ok': 'failed#unlock-rec']);
        lzyExit( $json );
    } // unlockRec





	private function saveDataRec()
    {
        if (!$this->openDB( )) {
            mylog("AjaxServer: save-rec -> openDB failed ($this->dataFile)");
            lzyExit('failed#get-rec');
        }
        $recKey = $this->getRecKey();
        $text = $this->get_request_data('text');
        $res = $this->db->writeRec( $recKey, $text );

        // compile and send back:
        if ($this->ticketRec['_renderAsMd']) {
            $text = compileMarkdownStr($text);
        }
        $targId = "#{$this->ticketRec['_id']}";
        $json = json_encode([
            'res' => ($res === true)? 'ok': 'failed',
            'data' => [ $targId => $text ],
        ]);
        mylog("AjaxServer: save-rec ". (($res === true)? 'success': 'failed') ." ($recKey)");
        lzyExit( $json );
    } // saveDataRec




	private function saveDataElem()
    {
        if (!$this->openDB( )) {
            mylog("AjaxServer: save-elem -> openDB failed ($this->dataFile)");
            lzyExit('failed#save-elem');
        }
        $recKey = $this->getRecKey();
        $text = $this->get_request_data('text');
        $locking = $this->get_request_data('locking');
        $res = $this->db->writeElement( $recKey, $text, $locking );

        // compile and send back:
        if ($this->ticketRec['_renderAsMd']) {
            $text = compileMarkdownStr($text);
        }
        $targId = "#{$this->ticketRec['_id']}";
        $json = json_encode([
            'res' => ($res === true)? 'ok': 'failed',
            'data' => [ $targId => $text ],
        ]);
        mylog("AjaxServer: save-elem -> openDB ". (($res === true)? 'success': 'failed') ." ($recKey)");
        lzyExit( $json );
    } // saveDataElem




	private function saveData() {
        $rawData = $this->get_request_data('data');
		if ($rawData === 'undefined') {
            lzyExit('failed#save-data');
        }
		$data = json_decode($rawData, true);
		$ticket = $this->get_request_data('ticket');
		if ($ticket === 'undefined') {
            lzyExit('failed#save-data');
        }

        if (!$this->openDB( )) {
            lzyExit('failed#save');
        }
        $res = $this->db->write($data);

		lzyExit($this->prepareClientData().'#save-data');
	} // saveData




    // === File Related Methods ============================================
    private function handleFileRequests()
    {
        if ($this->get_request_data('newfile') !== null) {       // create new file
            $this->createNewFile();
        }

        if ($this->get_request_data('renamefile') !== null) {    // rename file
            $this->renameFile();
        }

        if ($this->get_request_data('getfile') !== null) {          // send md-file
            $this->getFile();
        }
    } // handleFileRequests



    private function getFile() {
        $md = '';
        if (isset($_POST['lzy_filename'])) {
            $filename = $_POST['lzy_filename'];
            $approot = trunkPath($_SERVER['SCRIPT_FILENAME']);
            if ($filename === 'sitemap') {
                $filename = $approot . 'config/sitemap.txt';
            } else {
                $filename = $approot . $filename;
            }
            if (file_exists($filename)) {
                $md = file_get_contents($filename);
            }
        } elseif (isset($_POST['srcRef'])) {
            $srcRef = $_POST['srcRef'];
            $setName = preg_replace('/.*?:/', '', $srcRef);
            $tickHash = preg_replace('/:.*/', '', $srcRef);
            $tick = new Ticketing();
            $ticketRec = $tick->consumeTicket($tickHash);
            if (isset( $ticketRec[ $setName ] )) {
                $filename = PATH_TO_APP_ROOT . $ticketRec[ $setName ]['_dataSource'];
                if (file_exists( $filename )) {
                    $text = file_get_contents( $filename );
                    lzyExit( json_encode([
                        'result' => 'ok',
                        'data'   => $text,
                    ]));
                }
            }
        }
        lzyExit($md);

    } // getFile



    private function createNewFile()
    {
        if (isset($_POST['lzy_filename'])) {
            $filename0 = $_POST['lzy_filename'];
            $filename = '../pages/' . strtolower($filename0);
            $filename = preg_replace('/\.\w+$/', '', $filename) . '.md';
            $filename0 = basename($filename0);
            file_put_contents($filename, "# $filename0\n\n");
        }
    } // createNewFile




    private function renameFile()
    {
        if (isset($_POST['lzy_filename'])) {
            $filename0 = $_POST['lzy_filename'];
            $newName0 = $_POST['lzy_newName'];
            $filename = '../' . $filename0;
            $newFilename = dirname($filename)."/$newName0";
            if (file_exists($filename) &&
                rename($filename, $newFilename)) {
                $this->mylog( "rename($filename, $newFilename)");
                lzyExit('Ok');
            }
        }
        lzyExit('Failed');
    } // renameFile



    // === low level methods ===================================
    private function prepareClientData($key = false)
    {
        if (!$this->openDB()) {
            lzyExit('failed#getData');
        }
        if ($key === '_all') {
            $data = $this->db->read();
        } else {
            $data = $this->db->readElement($key);
        }
        if (!$data) {
            $data = [];
        } elseif ($key && isset($data[$key])) {
            $data = $data[$key];
        }
        return json_encode($data);
    } // prepareClientData




    private function openDB( $lockDB = false) {
        if ($this->db) {
            return true;    // already opened
        }
        $this->dataFile = false;
        $dataRef = $this->get_request_data('ds');
        $setId = '';
        if (preg_match('/(.*):(.*)/', $dataRef, $m)) {
            $dataRef = $m[1];
            $setId = $m[2];
        }
        if ($dataRef &&preg_match('/^[A-Z0-9]{4,20}$/', $dataRef)) {     // dataRef (=ticket hash) available
            $ticketing = new Ticketing();
            $ticketRec = $ticketing->consumeTicket($dataRef);
            if ($ticketRec) {      // corresponding ticket found
                if ($setId && isset($ticketRec[$setId])) {
                    $ticketRec = $ticketRec[$setId];
                    $this->dataFile = PATH_TO_APP_ROOT . $ticketRec['_dataSource'];
                } elseif (isset($ticketRec['_dataSource'])) {
                    $this->dataFile = PATH_TO_APP_ROOT . $ticketRec['_dataSource'];
                } elseif (isset($ticketRec['dataSrc'])) {
                    $this->dataFile = PATH_TO_APP_ROOT . $ticketRec['dataSrc']; //???
                } elseif (isset($ticketRec['form'])) {
                    $this->dataFile = PATH_TO_APP_ROOT . $ticketRec['file'];
                }
                $this->ticketRec = $ticketRec;
                if (isset($ticketRec['formDescr'])) {
                    $this->formElements = $ticketRec['formDescr'];
                }
            } else {
                $this->error = 'restart';
                return false;
            }
        }

        // if primary method didn't work, try default DB in page folder
        $pagePath = $GLOBALS['globalParams']['pageFolder'];
        if (!$this->dataFile && $pagePath) {
            $this->dataFile = PATH_TO_APP_ROOT . $pagePath . DEFAULT_EDITABLE_DATA_FILE;
        }

        if ($this->dataFile && file_exists( $this->dataFile)) {
            $this->db = new DataStorage2(['dataFile' => $this->dataFile, 'includeKeys' => true]);
            return true;
        }

        return false;
    } // openDB



    private function getRecKey()
    {
        $recKey = $this->get_request_data('recKey');
        if (!$recKey) {
            $recKey = $this->get_request_data('dataRef');
        }
        return $recKey;
    } // getRecKey





    private function get_request_data($varName) {
        global $argv;
        $out = null;
        if (isset($_GET[$varName])) {
            $out = $this->safeStr($_GET[$varName]);

        } elseif (isset($_POST[$varName])) {
            $out = $_POST[$varName];

        } elseif ($this->isLocalhost && isset($argv)) {	// for local debugging
            foreach ($argv as $s) {
                if (strpos($s, $varName) === 0) {
                    $out = preg_replace('/^(\w+=)/', '', $s);
                    break;
                }
            }
        }
        return $out;
    } // get_request_data




    private function var_r($data, $varName = false)
    {
        $out = var_export($data, true);
        if ($varName) {
            $out = "$varName: ".$out;
        }
        return str_replace("\n", '', $out);
    } // var_r




    private function mylog($str, $file = false)
    {
        if (!is_string( $str )) {
            $str = var_r($str);
        }
        if ($file) {
            $file = LOG_PATH.$file;
        } else {
            $file = SERVICE_LOG;
        }
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), MKDIR_MASK, true);
        }
        file_put_contents($file, $this->timestamp()." {$this->clientID}{$this->user}:  $str\n\n", FILE_APPEND);
    } // mylog




    /**
     * http://php.net/manual/de/function.session-start.php
     * Every time you call session_start(), PHP adds another
     * identical session cookie to the response header. Do this
     * enough times, and your response header becomes big enough
     * to choke the web server.
     *
     * This method clears out the duplicate session cookies. You can
     * call it after each time you've called session_start(), or call it
     * just before you send your headers.
     */
    private function clear_duplicate_cookies() {
        // If headers have already been sent, there's nothing we can do
        if (headers_sent()) {
            return;
        }
        $cookies = array();
        foreach (headers_list() as $header) {
            // Identify cookie headers
            if (strpos($header, 'Set-Cookie:') === 0) {
                $cookies[] = $header;
            }
        }
        // Removes all cookie headers, including duplicates
        header_remove('Set-Cookie');

        // Restore one copy of each cookie
        foreach(array_unique($cookies) as $cookie) {
            header($cookie, false);
        }
    } // clear_duplicate_cookies




    private function timestamp($short = false)
    {
        if (!$short) {
            return date('Y-m-d H:i:s');
        } else {
            return date('Y-m-d');
        }
    } // timestamp




    function safeStr($str) {
        if (preg_match('/^\s*$/', $str)) {
            return '';
        }
        $str = substr($str, 0, MAX_URL_ARG_SIZE);	// restrict size to safe value
        return $str;
    } // safe_str



    private function info()
    {
        $localhost = ($this->isLocalhost) ? 'yes':'no';
        $dbs = $this->var_r($_SESSION['lizzy']['db']);
        $msg = <<<EOT
	<pre>
	Page:		{$_SESSION['lizzy']['pagePath']}
	DB:		$dbs
	Hash:		{$this->hash}
	Remote Addr:	{$this->remoteAddress}
	UA:		{$this->userAgent}
	isLocalhost:	{$localhost}
	ClientID:	{$this->clientID}
	</pre>
EOT;
        lzyExit($msg);
    } // info

} // class AjaxServer

