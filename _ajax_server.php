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

date_default_timezone_set('CET');		// modify as appropriate
define('SYSTEM_PATH', 		'');		        // same directory
define('DATA_PATH', 		'../data/');		// modify if necessary
define('SERVICE_LOG', 		'../.#logs/log.txt');	// modify if necessary
define('ERROR_LOG', 		'../.#logs/errlog.txt');	// modify if necessary
define('LOCK_TIMEOUT', 		120);	// max time till field is automatically unlocked
define('MAX_URL_ARG_SIZE', 255);
define('MKDIR_MASK',            0700); // remember to modify _lizzy/_install/install.sh as well

require_once 'vendor/autoload.php';
require_once 'datastorage.class.php';

use Symfony\Component\Yaml\Yaml;

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
        $pagePath = isset($_REQUEST['pg']) ? $_REQUEST['pg'] : $_SESSION['lizzy']['pagePath'];

        if (isset($_SESSION['lizzy']['db'][$pagePath]) && $_SESSION['lizzy']['db'][$pagePath]) {
            $dbFile = $this->dbFile = '../' . $_SESSION['lizzy']['db'][$pagePath];
            $this->db = new DataStorage($dbFile, $this->sessionId, true);
        }
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
	} // __construct



	//---------------------------------------------------------------------------
	private function handleUrlArguments()
	{
		if ($upd = $this->get_request_data('upd')) {		// update request (long-polling)
			$this->update($upd);
			exit;
		}

		if ($id = $this->get_request_data('get')) {		// get value(s)
			$this->get($id);
			exit;
		}

        if (isset($_GET['conn'])) {	    // conn  initial interaction with client, defines used ids
			$this->initConnection();
			exit;
		}

		if (isset($_GET['reset'])) {					// reset all locks
			$this->reset();
			exit;
		}

		if ($id = $this->get_request_data(LIZZY_LOCK)) {	// lock an editable field
			$this->lock($id);
			exit;
		}

		if ($id = $this->get_request_data('unlock')) {	// unlock an editable field
			$this->unlock($id);
			exit;
		}

		if ($id = $this->get_request_data('save')) {	// save data & unlock
			$this->save($id);
			exit;
		}

		if (isset($_GET['log'])) {	// remote log, write to backend's log
            $msg = $this->get_request_data('text');
			$this->mylog("Client: $msg");
			exit;
		}
		if ($this->get_request_data('info') !== null) {	// respond with info-msg
			$this->info();
		}

		if ($this->get_request_data('end') !== null) {	// end update
			$this->endPolling();
		}

		if ($this->get_request_data('newfile') !== null) {    // new file
            $this->createNewFile();
        }

		if ($this->get_request_data('renamefile') !== null) {    // new file
            $this->renameFile();
        }

		if ($this->get_request_data('getfile') !== null) {	// send md-file
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



	//---------------------------------------------------------------------------
	private function initConnection() {
        $json = $this->get_request_data('ids');
        $ids = json_decode($json);

        if (!session_start()) {
            $this->mylog("ERROR in initConnection(): failed to start session");
        }
        $this->clear_duplicate_cookies();		if (!isset($_SESSION['lizzy']['pageName']) || !isset($_SESSION['lizzy']['pagePath'])) {
            $this->mylog("*** Client connection failed: [{$this->remoteAddress}] {$this->userAgent}");
            exit('failed#conn');
        }

        $pagePath = $_SESSION['lizzy']['pagePath'];
        $pathToPage = $_SESSION['lizzy']['pagesFolder'].$pagePath;

        $this->mylog("=======");
        $this->mylog("Client connected: [{$this->remoteAddress}] {$this->userAgent} (pagePath: $pagePath)");

        if (isset($_SESSION['lizzy']['db'][$pagePath]) && $_SESSION['lizzy']['db'][$pagePath]) {   // explicit data path provided
            $dbFile = '../'.$_SESSION['lizzy']['db'][$pagePath];

        } else {                                            // no data path, use default: local to page
            $dbFile = "../{$pathToPage}editable.yaml";
            $_SESSION['lizzy']['db'][$pagePath] = "{$pathToPage}editable.yaml";
        }

		$_SESSION['lizzy']['lastSentData'] = '';
		session_write_close();

        $this->mylog("Database File: $dbFile");
        $this->db = new DataStorage($dbFile, $this->sessionId, true);

        $this->db->initRecs($ids);

		exit('ok#conn');
	} // initConnection



    private function endPolling()
    {
        $this->terminatePolling = true;
        $this->mylog("termination initiated");
        exit('#termination initiated');
    }




	//---------------------------------------------------------------------------
	private function lock($id) {
		if (!$id) {
			$this->mylog("lock: Error -> id not defined");
			exit;
		}
		if (!isset($this->db) || !$this->db) {
            $this->mylog("lock: Error: need to initialize connection first");
			exit('Error: need to initialize connection first');
		}

		// Safety measure: datasource stated by client must correspond to session data
        // otherwise, either client got out of sync or somebody tried some manipulation
        $dataSrc = $this->get_request_data('ds');
		$dbFile = substr($this->dbFile, 3);
		if ($dbFile != $dataSrc) {
		    unset($_SESSION['lizzy']);
		    exit('restart');
        }

		if (!$this->db->lock($id)) {
			$this->mylog("lock: $id -> failed");
			exit('failed');

		} else {
            $id0 = preg_replace('/_\d+-\d+$/', '', $id);
            $val = $this->db->read($id);
		    $th = $this->db->read("_meta_/$id0/lzy-editable-freeze-after");
		    if ($val && $th) {
                $t = $this->db->lastModified($id);
                $th = time() - $th;
                if ($t && ($t < $th)) {       // too old
                    exit('frozen');
                }
            }

		    $json = json_encode(['res' => 'ok', 'val' => $val]);
			$this->mylog("lock: $id -> $json");
			exit($json);
		}
	} // lock




	//---------------------------------------------------------------------------
	private function unlock($id) {
		if (!$id) {
			$this->mylog("unlock: Error -> id not defined");
			exit;
		}
		if ($this->db->unlock($id)) {
			$this->mylog("unlock: $id -> ok");
            exit('ok');
        }
	} // unlock




	//---------------------------------------------------------------------------
	private function get($id) {
		exit($this->prepareClientData($id).'#get');
	} // get




	//---------------------------------------------------------------------------
	private function save($id) {
		if (!$id) {
			$this->mylog("save & unlock: Error -> id not defined");
			exit;
		}
		$text = $this->get_request_data('text');
		$this->mylog("save: $id -> [$text]");
		$lzyEditableFreezeAfter = $this->db->read('lzy-editable-freeze-after');
		if ($lzyEditableFreezeAfter) {
		    $freezeBefore = time() - $lzyEditableFreezeAfter;
		    $lastModif = $this->db->lastModified($id);
		    if ($lastModif && ($lastModif < $freezeBefore)) {
                $this->mylog("### value frozen - save failed!: $id -> [$text]");
                exit('restart');
            }
        }
		if (!$this->db->write($id, $text)) {
            $this->mylog("### save failed!: $id -> [$text]");
        }
		$this->db->unlock(true); // unlock all owner's locks

		exit($this->prepareClientData($id).'#save');
	} // save




	//---------------------------------------------------------------------------
	private function reset() {
		if ($this->db) {
			$this->db->unlock('*');
		}
		session_start();
        $this->clear_duplicate_cookies();
		unset($_SESSION['lizzy']['hash']);
		unset($_SESSION['lizzy']['lastSentData']);
		unset($_SESSION['lizzy']['db']);
		session_write_close();
		exit('ok');
	} // reset




	//------------------------------------------------------------
	private function prepareClientData($key = false)
	{
	    $data = $this->db->read('*');
		if (!$data) {
			$data = [];
		} elseif ($key && isset($data[$key])) {
		    $data = $data[$key];
        }
		return json_encode($data);
	} // prepareClientData




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




	//---------------------------------------------------------------------------
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

} // EditingService



//--------------------------------------------------------------
function convertYaml($str)
{
	$data = null;
	if ($str) {
		$str = str_replace("\t", '    ', $str);
		try {
			$data = Yaml::parse($str);
		} catch(Exception $e) {
            fatalError("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
		}
	}
	return $data;
} // convertYaml




//--------------------------------------------------------------
function convertToYaml($data, $level = 3)
{
	return Yaml::dump($data, $level);
} // convertToYaml




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

