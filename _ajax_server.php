<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Ajax Service for dynamic data, in particular 'editable' data
 * http://localhost/Lizzy/_lizzy/_ajax_server.php?lock=ed1
 *
 *  Protocol:

conn
	GET:	?conn=list-of-editable-fields

upd
	GET:	?upd=time

lock
	GET:	?lock=id

unlock
	GET:	?unlock=id

save
	GET:	?save=id
    POST:   text => data

get
	GET:	?get=id

reset
	GET:	?reset

log
	GET:	?log=message

info
	GET:	?info

getfile
	GET:	?getfile=filename

*/

define('SYSTEM_PATH', 		'');		                            // same directory
//define('EXTENSIONS_PATH', 	'extensions/');		                            // same directory
define('PATH_TO_APP_ROOT', 	'../');		                            // root folder of web app
//define('DATA_PATH', 		PATH_TO_APP_ROOT.'data/');		        // must correspond to lizzy app
//define('CACHE_PATH',        PATH_TO_APP_ROOT.'.#cache/');           // required by Ticketing class
//define('SERVICE_LOG',       PATH_TO_APP_ROOT.'.#logs/log.txt');	    //
//define('ERROR_LOG',         PATH_TO_APP_ROOT.'.#logs/errlog.txt');	//
define('LOCK_TIMEOUT', 		120);	                                // max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE',  255);
//define('MKDIR_MASK',        0700);                                  // remember to modify _lizzy/_install/install.sh as well
//define('RECYCLE_BIN',           '.#recycleBin/');
//define('RECYCLE_BIN_PATH',      '~page/'.RECYCLE_BIN);

require_once 'vendor/autoload.php';
require_once 'backend_aux.php';
require_once 'datastorage2.class.php';
require_once 'ticketing.class.php';

//define('DEFAULT_EDITABLE_DATA_FILE', 'editable.'.LZY_DEFAULT_FILE_TYPE);

use Symfony\Component\Yaml\Yaml;

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
        preparePath(DATA_PATH);

		$this->remoteAddress = isset($_SERVER["REMOTE_ADDR"]) ? $_SERVER["REMOTE_ADDR"] : 'REMOTE_ADDR';
		$this->userAgent = isset($_SESSION['lizzy']['userAgent']) ? $_SESSION['lizzy']['userAgent'] : $_SERVER["HTTP_USER_AGENT"];
		$this->isLocalhost = (($this->remoteAddress == 'localhost') || (strpos($this->remoteAddress, '192.') === 0) || ($this->remoteAddress == '::1'));
		$this->handleUrlArguments();
		$this->config = [];
	} // __construct



	//---------------------------------------------------------------------------
	private function handleUrlArguments()
	{
//        $this->handleEditableRequests();    // Editable: conn, get, reset, lock, unlock, save

        $this->handleGenericRequests();     // log, info

        $this->handleFileRequests();        // Files: newfile, renamefile, getfile

    } // handleUrlArguments



	//---------------------------------------------------------------------------
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
		exit($msg);
	} // info



//	//---------------------------------------------------------------------------
//	private function initConnection() {
//        $json = $this->get_request_data('ids');
//        try {
//            $ids = json_decode($json);
//        } catch (Exception $e) {
//            $this->mylog("*** Client connection failed: ". $e->getMessage());
//            exit( 'failed#conn' );
//        }
//
//
//        if (!session_start()) {
//            $this->mylog("ERROR in initConnection(): failed to start session");
//        }
//        $this->clear_duplicate_cookies();		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
//            $this->mylog("*** Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
//            exit('failed#conn');
//        }
//
//        $pagePath = $_SESSION['lizzy']['pagePath'];
//        $this->mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");
//
//		$_SESSION['lizzy']['lastSentData'] = '';
//		session_write_close();
//
//        if ($ids) {
//            $this->openDB();
//            $this->db->initRecs($ids);
//        }
//
//		exit('ok#conn');
//	} // initConnection



//	//---------------------------------------------------------------------------
//	private function lock($id) {
//		if (!$id) {
//			$this->mylog("lock: Error -> id not defined");
//			exit;
//		}
//
//        if (!$this->openDB( true )) {
//            exit('failed#lock/DB');
//        }
//
//        $ref = $this->getRef();
//        if ($ref) {
//            $res = $this->db->lock($ref);
//        } else {
//            $res = $this->db->lock($id);
//        }
//
//        if (!$res) {
//			$this->mylog("lock: $id -> failed");
//			exit('failed#lock');
//
//		} else {
//            $id0 = preg_replace('/_\d+-\d+$/', '', $id);
//
//            $val = $this->db->read($id);
//		    $th = $this->db->readMeta("$id0/lzy-editable-freeze-after");
//		    if ($val && $th) {
//                $t = $this->db->lastModified($id);
//                $th = time() - $th;
//                if ($t && ($t < $th)) {       // too old
//                    exit('frozen');
//                }
//            }
//
//            exit($this->prepareClientData($id).'#lock:ok');
//            $json = json_encode(['res' => 'ok', 'val' => $val]);
//			$this->mylog("lock: $id -> $json");
//			exit($json);
//		}
//	} // lock




//	//---------------------------------------------------------------------------
//	private function unlock($id) {
//		if (!$id) {
//			$this->mylog("unlock: Error -> id not defined");
//			exit;
//		}
//        if (!$this->openDB( true )) {
//            exit('failed#unlock');
//        }
//        $ref = $this->get_request_data('ref');
//        if ($ref) {
//            $res = $this->db->unlock($ref);
//        } else {
//            $res = $this->db->unlock($id);
//        }
//        if ($res) {
//			$this->mylog("unlock: $id -> ok");
//            exit('ok');
//        }
//	} // unlock




	//---------------------------------------------------------------------------
    //	private function update($upd) {
    //		exit($this->prepareClientData($upd).'#update');
    //	} // get




//	//---------------------------------------------------------------------------
//	private function get($id) {
//		exit($this->prepareClientData($id).'#get');
//	} // get




//	//---------------------------------------------------------------------------
//	private function save($id) {
//		if (!$id) {
//			$this->mylog("save & unlock: Error -> id not defined");
//			exit;
//		}
//		$text = $this->get_request_data('text');
//		if ($text == 'undefined') {
//            exit('failed#save');
//        }
//
//        $ref = $this->getRef();
//        $this->mylog("save: $id -> [$text]");
//        if (!$this->openDB( true )) {
//            exit('failed#save');
//        }
//		$lzyEditableFreezeAfter = $this->db->read('lzy-editable-freeze-after');
//		if ($lzyEditableFreezeAfter) {
//		    $freezeBefore = time() - $lzyEditableFreezeAfter;
//		    $lastModif = $this->db->lastModified($id);
//		    if ($lastModif && ($lastModif < $freezeBefore)) {
//                $this->mylog("### value frozen - save failed!: $id -> [$text]");
//                exit('restart');
//            }
//        }
//        if ($ref) {
//            $res = $this->db->write($ref, $text);
//        } else {
//            $res = $this->db->write($id, $text);
//        }
//
//        if (!$res) {
//            $this->mylog("### save failed!: $id -> [$text]");
//        }
//		$this->db->unlock(true); // unlock all owner's locks
//
//		exit($this->prepareClientData($id).'#save');
//	} // save
//



	//---------------------------------------------------------------------------
	private function getAllData()
    {
        if (!$this->openDB( )) {
            exit('failed#save');
        }
        exit($this->prepareClientData().'#get-all');
    } // getAllData




	//---------------------------------------------------------------------------
	private function getDataRec()
    {
        if (!$this->openDB( )) {
            exit('failed#save');
        }
        $recId = $this->get_request_data('recId');
        $dataRec = $this->db->readRecord( $recId );
        $outData = [];
        foreach ($this->config["recDef"] as $key => $rec) {
            $outData["#fld_".$rec[0]] = $dataRec[$key];
        }
        $json = json_encode(['res' => 'Ok', 'data' =>$outData]);
        exit( $json );
    } // getDataRec




	//---------------------------------------------------------------------------
	private function saveDataRec()
    {
        if (!$this->openDB( )) {
            exit('failed#save');
        }
        $json = $this->get_request_data('lzy_data_input_form');
        if ($json) {
            $dataRec = json_decode($json, true);
            $recId = $dataRec["rec-id"];
            unset($dataRec['lizzy_form']);
            unset($dataRec['lizzy_time']);
            unset($dataRec['data-ref']);
            unset($dataRec['rec-id']);

            $keys = array_keys($this->config["recDef"]);
            $dataRec = array_combine($keys, $dataRec);
            if (isset($recId) && ($recId !== '')) {
                $res = $this->db->writeRecord( intval($recId), $dataRec);
//                $res = $this->db->writeRecord($recId, $dataRec);
            } else {
                $res = 'Error: rec-id missing';
            }
        } else {
            $res = 'Error: no data received';
        }
//        $outData = [];
//        foreach ($this->config["recDef"] as $key => $rec) {
//            $outData["#fld_".$rec[0]] = $dataRec[$key];
//        }
        $json = json_encode(['res' => $res, 'data' => [] ]);
        exit( $json );
    } // saveDataRec




	//---------------------------------------------------------------------------
	private function saveData() {
        $rawData = $this->get_request_data('data');
		if ($rawData == 'undefined') {
            exit('failed#save-data');
        }
		$data = json_decode($rawData, true);
		$ticket = $this->get_request_data('ticket');
		if ($ticket == 'undefined') {
            exit('failed#save-data');
        }

        if (!$this->openDB( )) {
            exit('failed#save');
        }
        $res = $this->db->write($data);

		exit($this->prepareClientData().'#save-data');
	} // saveData



//    //---------------------------------------------------------------------------
//	private function reset() {
//        if (!$this->openDB( true )) {
//            exit('failed#lock/DB');
//        }
//        if ($this->db) {
//			$this->db->unlock('*');
//		}
//		session_start();
//        $this->clear_duplicate_cookies();
//		unset($_SESSION['lizzy']['hash']);
//		unset($_SESSION['lizzy']['lastSentData']);
//		unset($_SESSION['lizzy']['db']);
//		session_write_close();
//		exit('ok');
//	} // reset




	//------------------------------------------------------------
	private function prepareClientData($key = false)
	{
        if (!$this->openDB()) {
            exit('failed#getData');
        }
        if ($key === '_all') {
            $data = $this->db->read();
        } else {
            $data = $this->db->readElement($key);
        }
//        $data = $this->db->read('*');
		if (!$data) {
			$data = [];
		} elseif ($key && isset($data[$key])) {
		    $data = $data[$key];
        }
		$t = json_encode($data);
		return json_encode($data);
	} // prepareClientData




    //---------------------------------------------------------------------------
    private function openDB( $lockDB = false) {
	    if ($this->db) {
	        return true;    // already opened
        }
        $this->dataFile = false;
	    $useRecycleBin = false;
        $dataRef = $this->get_request_data('ds');
        if ($dataRef &&preg_match('/^[A-Z0-9]{4,20}$/', $dataRef)) {     // dataRef (=ticket hash) available
//            require_once SYSTEM_PATH . 'ticketing.class.php';
            $ticketing = new Ticketing();
            $ticketRec = $ticketing->consumeTicket($dataRef);
            if ($ticketRec) {      // corresponding ticket found
                $this->dataFile = PATH_TO_APP_ROOT . $ticketRec['dataSrc'];
                $this->config = $ticketRec;
            }
        }

        // if primary method didn't work, try default DB in page folder
        $pagePath = isset($_SESSION["lizzy"]["pathToPage"]) ? $_SESSION["lizzy"]["pathToPage"] : '';
        if (!$this->dataFile && $pagePath) {
            $this->dataFile = PATH_TO_APP_ROOT . $pagePath . DEFAULT_EDITABLE_DATA_FILE;
        }

        if ($this->dataFile) {
            $this->db = new DataStorage2(['dataFile' => $this->dataFile]);
//            $this->db = new ElementLevelDataStorage([
//                'dataFile' => $this->dataFile,
//                'sid' => $this->sessionId,
//                'lockDB' => $lockDB,
//                'useRecycleBin' => $useRecycleBin
//            ]);
//            $this->db = new DataStorage([
//                'dataFile' => $this->dataFile,
//                'sid' => $this->sessionId,
//                'lockDB' => $lockDB,
//                'useRecycleBin' => $useRecycleBin
//            ]);
            return true;
        }

        return false;
    } // openDB




//------------------------------------------------------------
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




    //    private function endPolling()
    //    {
    //        $this->terminatePolling = true;
    //        $this->mylog("termination initiated");
    //        exit('#termination initiated');
    //    }




    //---------------------------------------------------------------------------
	private function var_r($data, $varName = false)
	{
		$out = var_export($data, true);
		if ($varName) {
		    $out = "$varName: ".$out;
        }
		return str_replace("\n", '', $out);
	} // var_r




	//---------------------------------------------------------------------------
	private function mylog($str)
	{
		if (!file_exists(dirname(SERVICE_LOG))) {
			mkdir(dirname(SERVICE_LOG), MKDIR_MASK, true);
		}
		file_put_contents(SERVICE_LOG, $this->timestamp()." {$this->clientID}{$this->user}:  $str\n", FILE_APPEND);
	} // mylog




    //---------------------------------------------------------------------------
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




	//---------------------------------------------------------------------------
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
                exit('Ok');
            }
        }
        exit('Failed');
    } // renameFile




//    private function handleEditableRequests()
//    {
//        // Possible enhancement: continuous updating of editable fields:
//        //		if ($upd = $this->get_request_data('upd')) {		// update request (long-polling)
//        //			$this->update($upd);
//        //			exit;
//        //		}
//        //        if ($this->get_request_data('end') !== null) {	    // end update
//        //            $this->endPolling();
//        //        }
//
//
//        if (isset($_GET['conn'])) {                                // conn  initial interaction with client, defines used ids
//            $this->initConnection();
//            exit;
//        }
//
//        if ($id = $this->get_request_data('get')) {     // get value(s)
//            $this->get($id);
//            exit;
//        }
//
//        if (isset($_GET['reset'])) {                            // reset all locks
//            $this->reset();
//            exit;
//        }
//
//        if ($id = $this->get_request_data('lock')) {    // lock an editable field
//            $this->lock($id);
//            exit;
//        }
//
//        if ($id = $this->get_request_data('unlock')) {    // unlock an editable field
//            $this->unlock($id);
//            exit;
//        }
//
//        if ($id = $this->get_request_data('save')) {    // save data & unlock
//            $this->save($id);
//            exit;
//        }
//    } // handleEditableRequests
//



    private function handleFileRequests()
    {
        if ($this->get_request_data('newfile') !== null) {       // create new file
            $this->createNewFile();
        }

        if ($this->get_request_data('renamefile') !== null) {    // rename file
            $this->renameFile();
        }

        if ($this->get_request_data('getfile') !== null) {          // send md-file
            $md = '';
            if (isset($_POST['lzy_filename'])) {
                $filename = $_POST['lzy_filename'];
                $approot = trunkPath($_SERVER['SCRIPT_FILENAME']);
                if ($filename == 'sitemap') {
                    $filename = $approot . 'config/sitemap.txt';
                } else {
                    $filename = $approot . $filename;
                }
                if (file_exists($filename)) {
                    $md = file_get_contents($filename);
                }
            }
            exit($md);
        }
    } // handleFileRequests




    private function handleGenericRequests()
    {
        if (isset($_GET['log'])) {                                // remote log, write to backend's log
            $msg = $this->get_request_data('text');
            $this->mylog("Client: $msg");
            exit;
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
        if ($this->get_request_data('save-rec') !== null) {    // respond with info-msg
            $this->saveDataRec();
        }
    } // handleGenericRequests




//    private function getRef()
//    {
//        $ref = $this->get_request_data('ref');
//        if ($ref) {
//            if (isset($this->config["protectedCells"])) {
//                list($c, $r) = $this->db->array2DKey($ref);
//                if (isset($this->config["protectedCells"][$r][$c]) && $this->config["protectedCells"][$r][$c]) {
//                    $this->mylog("attempted access to protected cell: $ref -> failed");
//                    exit('protected#lock');
//                }
//            }
//        }
//        return $ref;
//    } // getRef

} // EditingService



//--------------------------------------------------------------
//function convertYaml($str)
//{
//	$data = null;
//	if ($str) {
//		$str = str_replace("\t", '    ', $str);
//		try {
//			$data = Yaml::parse($str);
//		} catch(Exception $e) {
//            fatalError("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
//		}
//	}
//	return $data;
//} // convertYaml
//
//
//
//
////--------------------------------------------------------------
//function convertToYaml($data, $level = 3)
//{
//	return Yaml::dump($data, $level);
//} // convertToYaml
//
//
//
//
////-----------------------------------------------------------------------------
//function trunkPath($path, $n = 1)
//{
//	$path = ($path[strlen($path)-1] == '/') ? rtrim($path, '/') : dirname($path);
//	return implode('/', explode('/', $path, -$n)).'/';
//} // trunkPath
//
//
//
//
////------------------------------------------------------------
//function preparePath($path)
//{
//    $path = dirname($path.'x');
//    if (!file_exists($path)) {
//        if (!mkdir($path, MKDIR_MASK, true)) {
//            fatalError("Error: failed to create folder '$path'");
//        }
//    }
//} // preparePath
//
//
//
////------------------------------------------------------------
//function fatalError($msg)
//{
//    $msg = date('Y-m-d H:i:s')." [_ajax_server.php]\n$msg\n";
//    file_put_contents(ERROR_LOG, $msg, FILE_APPEND);
//    exit;
//} // fatalError
//
//
////------------------------------------------------------------
//function resolvePath($path)
//{
//    global $globalParams;
//
//    if (strpos($path, ':') !== false) {   // https://, tel:, sms:, etc.
//        return $path;
//    }
//    $path = trim($path);
////    if ((($ch1=$path[0]) != '/') && ($ch1 != '~') && ($ch1 != '.')/* && ($ch1 != '_')*/) {	//default to path local to page ???always ok?
////        $path = '~/'.$path;
////    }
//
//    $from = [
//        '|~/|',
//        '|~data/|',
//        '|~sys/|',
//        '|~ext/|',
//        '|~page/|',
//    ];
//    $to = [
//        '',
//        $_SESSION["lizzy"]["dataPath"],
////        $globalParams['dataPath'],
//        SYSTEM_PATH,
//        EXTENSIONS_PATH,
//        $_SESSION["lizzy"]["pathToPage"],
////        $globalParams['pathToPage'],
////        $globalParams['pathToRoot'].$globalParams['pagePath'];
//    ];
//
////    $pathToRoot = $globalParams['pathToRoot'];
////    if ($resourceAccess) {    // -> include 'pages/' in path
////        for ($i=0; $i<4; $i++) {
////            $to[$i] = $pathToRoot.$to[$i];
////        }
////        $to[4] = $globalParams["host"].$globalParams['pathToPage'];
////    }
////    if ($pageAccess) {    // -> exclude 'pages/' in path
////        for ($i=0; $i<4; $i++) {
////            $to[$i] = $pathToRoot.$to[$i];
////        }
////        $to[4] = $globalParams['pathToRoot'].$globalParams['pagePath'];
////    }
//
//    $path = preg_replace($from, $to, $path);
//    return $path;
//} // resolvePath
//

