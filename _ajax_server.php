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
define('PATH_TO_APP_ROOT', 	'../');		                            // root folder of web app
define('LOCK_TIMEOUT', 		120);	                                // max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE',  255);

require_once 'vendor/autoload.php';
require_once 'backend_aux.php';
require_once 'datastorage2.class.php';
require_once 'ticketing.class.php';


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
        $this->handleGenericRequests();     // log, info
        $this->handleFileRequests();        // Files: newfile, renamefile, getfile
    } // handleUrlArguments




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
        $recKey = $this->get_request_data('recKey');
        $dataRec = $this->db->readRecord( $recKey );
        $outData = [];
        if (!$dataRec || !isset($this->config["recDef"])) {
            $json = json_encode(['res' => 'Error: getDataRec() no data found', 'data' => '']);
            exit( $json );
        }
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
            $recKey = $dataRec["rec-key"];
            unset($dataRec['lizzy_form']);
            unset($dataRec['lizzy_time']);
            unset($dataRec['data-ref']);
            unset($dataRec['rec-key']);

            if (isset($recKey) && ($recKey !== '')) {
                $recKey = intval( $recKey );

                $dataRec1 = $dataRec;
                $dataRec = [];
                if (!isset($this->config["recDef"])) {
                    $json = json_encode(['res' => 'Error: saveDataRec() config data missing', 'data' => '' ]);
                    exit( $json );
                }
                foreach ($this->config["recDef"] as $k => $rec) {
                    if (isset($dataRec1[ $rec[0] ])) {
                        $dataRec[$k] = $dataRec1[$rec[0]];
                    }
                }

                $dataRec0 = $this->db->readRecord( $recKey );
                if (!$dataRec0) {
                    $res = 'Error: rec not found';
                } else {
                    $dataRec = array_merge($dataRec0, $dataRec);
                    $res = $this->db->writeRecord(intval($recKey), $dataRec);
                }
            } else {
                $res = 'Error: rec-id missing';
            }
        } else {
            $res = 'Error: no data received';
        }
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



    // === low level methods ===================================
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

} // class AjaxServer

