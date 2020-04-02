<?php
/*
 * Lizzy maintains *one* SQlite DB (located in 'data/.lzy_db.sqlite')
 * So, all data managed by DataStorage2 is stored in there.
 * However, shadow data files in yaml, json or cvs format may be maintained:
 *      they are imported at construction and exported at deconstruction time
 *
 * Old:
 *     public function __construct($args, $sid, $lockDB, $format, $lockTimeout, $secure)
*/


define('LIZZY_DB',  PATH_TO_APP_ROOT.'data/_lzy_db.sqlite');

if (!defined('LZY_LOCK_ALL_DURATION_DEFAULT')) {
    define('LZY_LOCK_ALL_DURATION_DEFAULT', 900.0); // 15 minutes
}
if (!defined('LZY_DEFAULT_FILE_TYPE')) {
    define('LZY_DEFAULT_FILE_TYPE', 'json');
}
if (!defined('LZY_META')) {
    define('LZY_META', '_meta');
}

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage2
{
    private $lzyDb = null;
	protected $dataFile;
	protected $tableName;
	protected $data = null;
	protected $rawData = null;
	protected $is2Ddata = false;
	protected $sid;
	protected $format;
	protected $lockDB = false;
	protected $defaultTimeout = 30; // [s]
	protected $defaultPollingSleepTime = 500; // [ms]


    //--------------------------------------------------------------
    public function __construct($args)
    {
        $this->parseArguments($args);
        if (!$this->dataFile) {
            die("Error: DataStorage2 invoked without dataFile being specified.");
        }
        $this->initLizzyDB();
        $this->initDbTable();
        $this->appPath = getcwd();

        if ($this->doLockDB) {
            $this->lockDB();
        }

    } // __construct



    //---------------------------------------------------------------------------
    public function __destruct()
    {
        if (!isset($this->appPath)) {
            return;
        }
        chdir($this->appPath); // workaround for include bug

        $this->exportToFile(); // saves data if modified
        if ($this->lzyDb) {
            if ($this->doLockDB) {
                $this->unlockDB();
            }
            $this->lzyDb->close();
        }
    } // __destruct




    //---------------------------------------------------------------------------
    public function read()
    {
        $data = $this->getData();
        if (isset($data[LZY_META])) {
            unset($data[LZY_META]);
        }
        return $data;
    } // read



    public function readElement($key)
    {
        // supports scalar values and arrays
        $data = $this->getData();

        // syntax variant '[d3][d31][d312]'
        if (preg_match('/\[(.*)\]/', trim($key), $m)) {
            $key = str_replace('][', ',', $m[1]);
        }

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $rec = $data;
            foreach (explode(',', $key) as $k) {
                $k = trim($k, '\'"');
                $n = intval($k);
                if ($n || ($k === '0')) {
                    $k = $n;
                }
                if (isset($rec[$k])) {
                    $rec = $rec[$k];
                } else {
                    return null;
                }
            }
            return $rec;
        }

        if (isset($data[$key])) {
            return $data[$key];
        } else {
            return null;
        }
    } // readElement




    public function write($data, $replace = true)
    {
        if ($this->isLockDB()) {
            return false;
        }
        if ($replace) {
            return $this->lowLevelWrite($data);
        } else {
            return $this->update($data);
        }
    } // write




    public function writeRecord($recId, $recData, $recLocking = false)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $data = $this->getData( true );

        if ($recId !== false) {
            $data[$recId] = $recData;
        } else {
            $data[] = $recData;
        }

        return $this->lowLevelWrite($data);
    } // writeRecord




    public function writeElement($key, $value)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $data = $this->getData( true );

        // syntax variant '[d3][d31][d312]'
        if (preg_match('/\[(.*)\]/', trim($key), $m)) {
            $key = str_replace('][', ',', $m[1]);
        }

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $keys = explode(',', $key);
            if ($this->format === 'csv') {
                $keys = array_reverse($keys);
            }
            $rec = &$data;
            foreach ($keys as $k) {
                $k = trim($k, '\'"');
                $n = intval($k);
                if ($n || ($k === '0')) {
                    $k = $n;
                }
                if (!isset($rec[$k])) {
                    $rec[$k] = null;    // instantiate element if not existing
                }
                $rec = &$rec[$k];
            }
            $rec = $value;

        } else {
            $data[$key] = $value;
        }
        return $this->lowLevelWrite($data);
    } // writeElement




    public function append($key, $value = null)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $data = $this->getData( true );

        if (is_array($key)) {
            if ($data) {
                $data = array_merge($data, $key);
            } else {
                $data = $key;
            }
        } else {
            $data[$key] = $value;
        }
        return $this->lowLevelWrite($data);
    } // append



    public function delete($key)
    {
        if ($this->isLockDB()) {
            return false;
        }
        $modified = false;
        $data = $this->getData( true );
        if (is_array($key)) {
            foreach ($key as $k) {
                if (isset($data[$k])) {
                    unset($data[$k]);
                    $modified = true;
                }
            }

        } else {
            if (isset($data[$key])) {
                unset($data[$key]);
                $modified = true;
            }
        }
        if ($modified) {
            $this->lowLevelWrite($data);
        }
    } // delete



    //---------------------------------------------------------------------------
    public function findRec($key, $value, $returnKey = false)
    {
        // find rec for which key AND value match
        // returns the record unless $returnKey is true, then it returns the key
        //TODO: extend for 2D data
        $data = $this->getData();

        foreach ($data as $datakey => $rec) {
            foreach ($rec as $k => $v) {
                if (($key === $k) && ($value === $v)) {
                    if ($returnKey) {
                        return $datakey;
                    } else {
                        return $rec;
                    }
                }
            }
        }
        return false;
    } // findRec




    public function getRecStructure()
    {
        $rawData = $this->getRawData();
        $structure = $this->jsonDecode($rawData['structure']);
        return $structure;
    }




    public function isLockDB()
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockTime'] < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT)) {
            $rawData['lockedBy'] = '';
            $rawData['lockTime'] = 0.0;
            $this->updateRawMetaData($rawData);
            return false;   // lock timed out
        }
        if ($rawData['lockedBy'] !== '') {  // it's locked
            if ($rawData['lockedBy'] === $mySessionID) { // it's locked by myself
                return false;

            } else {    // it's locked by other session
                return true;
            }
        }
        return false;   // it's not locked
    } // isLockDB




    public function lockDB()
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockedBy'] !== '') {
            if ($rawData['lockedBy'] === $mySessionID) {
                $rawData['lockTime'] = microtime(true);

            } else {
                if ($rawData['lockTime'] < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT)) {
                    $rawData['lockedBy'] = $mySessionID;
                    $rawData['lockTime'] = microtime(true);

                } else {
                    return false;
                }
            }
        } else {
            $rawData['lockedBy'] = $mySessionID;
            $rawData['lockTime'] = microtime(true);
        }
        $this->updateRawMetaData($rawData);
        return true;
    } // lockDB




    public function unLockDB($force = false)
    {
        $rawData = $this->getRawData();
        $mySessionID = $this->getSessionID();
        if ($rawData['lockedBy'] !== '') {
            if ($rawData['lockedBy'] === $mySessionID) {
                $rawData['lockTime'] = 0.0;
                $rawData['lockedBy'] = '';

            } elseif ($force) {
                $rawData['lockTime'] = 0.0;
                $rawData['lockedBy'] = '';

            } else {
                return false;
            }
            $this->updateRawMetaData($rawData);
        }
        return true;
    } // unLockDB




    public function getMetaElement($key)
    {
        $meta = $this->getMetaData();
        if (isset($meta[$key])) {
            return $meta[$key];
        } else {
            return null;
        }
    } // getMetaElement




    public function getMetaData()
    {
        if ($this->separateMetaData) {
            $query = "SELECT \"meta\" FROM \"{$this->tableName}\"";
            $metaData = $this->lzyDb->querySingle($query, true);
            $meta = $this->jsonDecode($metaData);

        } else {
            $data = $this->getData( true );
            $meta = isset($data[LZY_META]) ? $data[LZY_META] : [];
        }
        if (!$meta || !is_array($meta)) {
            $meta = [];
        }
        return $meta;
    } // getMetaData




    //---------------------------------------------------------------------------
    public function lastModified()
    {
        $rawData = $this->getRawData();
        return $rawData['lastUpdate'];
    } // lastModified




    //---------------------------------------------------------------------------
    public function checkNewData($lastUpdate, $returnJson = false)
    {
        $rawData = $this->getRawData();
        if ($rawData['lastUpdate'] > $lastUpdate) {
            $data = $this->getData();
            if ($returnJson) {
                $data['__lastUpdate'] = $rawData['lastUpdate'];
                $data = json_encode($data);
            }
            return $data;
        } else {
            return null;
        }
    } // checkNewData




    //---------------------------------------------------------------------------
    public function awaitChangedData($lastUpdate, $timeout = false, $pollingSleepTime = false)
    {
        $timeout = $timeout ? $timeout : $this->defaultTimeout;
        $pollingSleepTime = $pollingSleepTime ? $pollingSleepTime : $this->defaultPollingSleepTime;
        $pollingSleepTime = $pollingSleepTime*1000;
        $json = $this->checkNewData($lastUpdate, true);
        if ($json !== null) {
            return $json;
        }
        $tEnd = microtime(true) + $timeout - 0.01;

        while ($tEnd > microtime(true)) {
            $json = $this->checkNewData($lastUpdate, true);
            if ($json !== null) {
                return $json;
            }
            usleep($pollingSleepTime);
        }
        return '';
    } // awaitChangedData



    public function doLockDB()  // alias for compatibility
    {
        return $this->lockDB();
    } // doLockDB




    public function doUnlockDB()  // alias for compatibility
    {
        return $this->unLockDB();
    } // doUnlockDB




    public function dumpDb( $raw = false)
    {
        if ($raw) {
            $d = $this->getRawData();
        } else {
            $d = $this->data;
        }
        return var_r($d, 'DB "' . base_name($this->dataFile, false).'"');
    } // dumpDb




    public function is2Ddata()
    {
        $rawData = $this->getRawData();$res = (bool) $rawData['2D'];
        return (bool) $rawData['2D'];
    } // is2Ddata



    public function getSourceFormat() {
        return $this->format;
    } // getSourceFormat




// === protected methods ===============
    protected function update($newData)
    {
        $data = $this->getData( true );
        if ($data) {
            $newData = array_merge($data, $newData);
        }
        $this->lowLevelWrite($newData);
    } // update




    protected function updateElement($key, $value)
    {
        $data = $this->getData( true );
        if (preg_match('/^(\d+),(\d+)/', $key, $m)) {
            $data[$m[2]][$m[1]] = $value;
        } else {
            $data[$key] = $value;
        }
        $this->lowLevelWrite($data);
    } // updateElement




    protected function lowLevelWrite($newData, $isJson = false, $markModified = true, $structure = false)
    {
        $this->openDbReadWrite();

        $json = $this->jsonEncode($newData, $isJson);
        if (is_string($json)) {
            $json = SQLite3::escapeString($json);
        }
        $ftime = microtime(true);
        $modified = $markModified ? 1 : 0;

        if ($structure) {
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "structure" = "$structure", 
    "lastUpdate" = $ftime, 
    'modified' = $modified;

EOT;
        } elseif ($markModified) {
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "lastUpdate" = $ftime, 
    'modified' = $modified;

EOT;
        } else {
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json";

EOT;
        }

        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->rawData = $this->getRawData();

        return $res;
    } // lowLevelWrite




    protected function updateRawMetaData($rawData)
    {
        $this->openDbReadWrite();
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    'modified' = 1,
    'lockedBy' = '{$rawData['lockedBy']}',
    'lockTime' = '{$rawData['lockTime']}'

EOT;

        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->rawData = $this->getRawData();
    } // updateRawMetaData




    protected function updateMetaData($key, $value = null)
    {
        if ($this->separateMetaData || $this->rawData["2D"]) {
            $query = "SELECT \"meta\" FROM \"{$this->tableName}\"";
            $metaData = $this->lzyDb->querySingle($query, true);
            $metaData = $this->jsonDecode($metaData['meta']);
            if (is_array($key)) {
                if (is_array($metaData)) {
                    $metaData = array_merge($metaData, $key);
                } else {
                    $metaData = $key;
                }
            } else {
                $metaData[$key] = $value;
            }
            $metaData = $this->jsonEncode($metaData);
            $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    'meta' = "$metaData"

EOT;

            $res = $this->lzyDb->query($sql);
            return $res;

        } else {
            $data = $this->getData( true );
            if (!isset($data[LZY_META])) {
                $data[LZY_META] = [];
            }
            $metaData = &$data[LZY_META];

            if (is_array($key) && is_array($metaData)) {
                $metaData = array_merge($metaData, $key);
            } else {
                $metaData[$key] = $value;
            }
            return $this->lowLevelWrite($data, false, false);
        }
    } // updateMetaData




    //---------------------------------------------------------------------------
    protected function initLizzyDB()
    {
        if (!file_exists(LIZZY_DB)) {
            preparePath(LIZZY_DB);
            touch(LIZZY_DB);
        }
    } // initLizzyDB




    public function getDbRef()
    {
        if (!$this->lzyDb) {
            $this->openDbReadWrite();
        }
        return $this->lzyDb;
    }



    protected function openDbReadWrite()
    {
        if ($this->lzyDb) {
            return;
        }
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
        $this->lzyDb->busyTimeout(5000);
        $this->lzyDb->exec('PRAGMA journal_mode = wal;'); // https://www.php.net/manual/de/sqlite3.exec.php
    } // openDbReadWrite





    //---------------------------------------------------------------------------
    protected function initDbTable()
    {
        // 'dataFile' refers to a yaml or csv file that contains the original data source
        // each dataFile is copied into a table within the lizzyDB

        if ($this->secure && (strpos($this->dataFile, 'config/') !== false)) {
            return null;
        }

        // check data file
        $dataFile = $this->dataFile;
        if ($this->resetCache) {
            touch($dataFile);
        }

        if ($dataFile && !file_exists($dataFile)) {
            $path = pathinfo($dataFile, PATHINFO_DIRNAME);
            if (!file_exists($path) && $path) {
                if (!mkdir($path, 0777, true) || !is_writable($path)) {
                    if (function_exists('fatalError')) {
                        fatalError("DataStorage: unable to create file '{$dataFile}'", 'File: ' . __FILE__ . ' Line: ' . __LINE__);
                    } else {
                        die("DataStorage: unable to create file '{$dataFile}'");
                    }
                }
            }
            touch($dataFile);
        }

        $this->openDbReadWrite();

        // check whether dataFile-table exists:
        if ($this->tableName) {
            $tableName = $this->tableName;

        } elseif (!$this->dataFile) { // neither file- nor tablename -> nothing to do
            return;

        } else {
            $tableName = $this->deriveTableName();
            $this->tableName = $tableName;
        }

        $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='$tableName';";
        $stmt = $this->lzyDb->prepare($sql);
        $res = $stmt->execute();
        $table = $res->fetchArray(SQLITE3_ASSOC);
        if (!$table) {  // if table does not exist: create it and populate it with data from origFile
            $sql = "CREATE TABLE IF NOT EXISTS \"$tableName\" (";
            $sql .= '"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,';
            $sql .= '"data" VARCHAR, "meta" VARCHAR, "lastUpdate" REAL, "structure" VARCHAR, "2D" BIT, "origFile" VARCHAR, "modified" INTEGER, "lockedBy" VARCHAR, "lockTime" REAL)';
            $res = $this->lzyDb->query($sql);
            if ($res === false) {
                die("Error: unable to create table in lzyDB: '$tableName'");
            }

            $origFileName = $this->dataFile;
            if (PATH_TO_APP_ROOT && (strpos($origFileName, PATH_TO_APP_ROOT) === 0)) {
                $origFileName = substr($origFileName, strlen(PATH_TO_APP_ROOT));
            }
            $is2D = (stripos($origFileName, '.csv') !== false) ? 1: 0;

            $sql = <<<EOT
INSERT INTO "$tableName" ("data", "meta", "lastUpdate", "structure", "2D", "origFile", "modified", "lockedBy", "lockTime")
VALUES ("", "", 0.0, "", $is2D, "$origFileName", 0, "", 0.0);
EOT;
            $stmt = $this->lzyDb->prepare($sql);
            $res = $stmt->execute();
            if ($res === false) {
                die("Error: unable to initialize table in lzyDB: '$tableName'");
            }

            $res = $this->importFromFile( true );
            if ($res === false) {
                die("Error: unable to populate table in lzyDB: '$tableName'");
            }

        } else { // if table exists, check whether update necessary:
            $ftime = floatval(filemtime($dataFile));
            $rawData = $this->getRawData();
            if ($ftime > $rawData['lastUpdate']) {
                $res = $this->importFromFile();
                if ($res === false) {
                    die("Error: unable to update table in lzyDB: '$tableName'");
                }
            } else {
                $this->getData();
            }
        }

        return;
    } // initDbTable




    //--------------------------------------------------------------
    protected function importFromFile($initial = false)
    {
        $lines = file($this->dataFile);
        $rawData = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#') && ($line[0] !== "\n")) { // skip commented and empty lines
                $rawData .= $line;
            }
        }
        list($json, $structure) = $this->decode($rawData, false, true);
        if ($initial) {
            $this->lowLevelWrite($json, true, false, $structure);
        } else {
            $this->lowLevelWrite($json, true, false);
        }
    } // importFromFile




    //--------------------------------------------------------------
    protected function exportToFile()
    {
        $rawData = $this->getRawData();
        if ($rawData['modified']) {
            if (isset($GLOBALS["appRoot"])) {
                $filename = $GLOBALS["appRoot"] . $rawData['origFile'];

            } else {
                $filename = PATH_TO_APP_ROOT . $rawData['origFile'];
            }
            if (!file_exists($filename)) {
                mylog("Error: unable to export data to file '$filename'");
                return;
            }

            if ($this->useRecycleBin) {
                require_once SYSTEM_PATH.'page-source.class.php';
                $ps = new PageSource;
                $ps->copyFileToRecycleBin($filename);
            }

            $data = $this->getData( true );
            if ($this->format === 'yaml') {
                $this->writeToYamlFile($filename, $data);

            } elseif ($this->format === 'json') {
                file_put_contents($filename, json_encode($data));

            } elseif ($this->format === 'csv') {
                $this->writeToCsvFile($filename, $data);
            }
        }
        return;
    } // exportToFile




    //--------------------------------------------------------------
    protected function writeToYamlFile($filename, $data)
    {
        $yaml = Yaml::dump($data, 3);

        // retrieve header from original file:
        $lines = file($filename);
        $hdr = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#')) {
                break;
            }
            $hdr .= "$line\n";
        }
        file_put_contents($filename, $hdr.$yaml);
    } // writeToYamlFile




    //--------------------------------------------------------------
    protected function writeToCsvFile($filename, $array, $quote = '"', $delim = ',', $forceQuotes = true)
    {
        if (isset($array[LZY_META])) {
            unset($array[LZY_META]);
        }

        $out = '';
        foreach ($array as $row) {
            foreach ($row as $i => $elem) {
                if ($forceQuotes || strpbrk($elem, "$quote$delim")) {
                    $row[$i] = $quote . str_replace($quote, $quote.$quote, $elem) . $quote;
                }
                $row[$i] = str_replace(["\n", "\r"], ["\\n", ''], $row[$i]);
            }
            $out .= implode($delim, $row)."\n";
        }
        file_put_contents($filename, $out);
    } // writeToCsvFile




    protected function getData( $includeMetaData = false )
    {
        $rawData = $this->getRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
        if ($includeMetaData && $this->is2Ddata()) {
            $meta = $this->jsonDecode($rawData['meta']);
            if ($meta) {
                $data = array_merge($data, [LZY_META => $meta]);
            }
        }

        if (!$includeMetaData && isset($data[LZY_META])) {
            unset($data[LZY_META]);
        }
        $this->data = $data;
        return $data;
    } // getData




    protected function getRawData()
    {
        if (!$this->tableName) {
            return null;
        }
        $query = "SELECT * FROM \"{$this->tableName}\"";
        $this->rawData = $this->lzyDb->querySingle($query, true);
        return $this->rawData;
    } // getRawData




    protected function jsonEncode($data, $isAlreadyJson = false)
    {
        if ($isAlreadyJson && is_string($data)) {
            $json = $data;
        } else {
            $json = json_encode($data);
        }
        $json = str_replace('"', '⌑⌇⌑', $json);
        return $json;
    } // jsonEncode




    protected function jsonDecode($json)
    {
        if (!is_string($json)) {
            return null;
        }
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = json_decode($json, true);
        return $data;
    } // jsonDecode




    //---------------------------------------------------------------------------
    protected function parseArguments($args)
    {
        if (is_string($args)) {
            $args = ['dataFile' => $args];
        }
        $this->dataFile = isset($args['dataFile']) ? $args['dataFile'] :
            (isset($args['dataSource']) ? $args['dataSource'] : ''); // for compatibility
        $this->dataFile = resolvePath($this->dataFile);
        $this->sid = isset($args['sid']) ? $args['sid'] : '';
        $this->doLockDB = isset($args['lockDB']) ? $args['lockDB'] : false;
        $this->format = isset($args['format']) ? $args['format'] : '';
        $this->secure = isset($args['secure']) ? $args['secure'] : true;
        $this->useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->separateMetaData = isset($args['separateMetaData']) ? $args['separateMetaData'] : false;
        $this->format = ($this->format) ? $this->format : pathinfo($this->dataFile, PATHINFO_EXTENSION) ;
        $this->tableName = isset($args['tableName']) ? $args['tableName'] : '';
        if ($this->tableName && !$this->dataFile) {
            $rawData = $this->getRawData();
            $this->dataFile = PATH_TO_APP_ROOT.$rawData["origFile"];
        }
        $this->resetCache = isset($args['resetCache']) ? $args['resetCache'] : false;
        return;
    } // parseArguments




    //---------------------------------------------------------------------------
    protected function decode($rawData, $format = false, $outputAsJson = false)
    {
        if (!$rawData) {
            return null;
        }
        $data = false;
        $structure = [];
        if (!$format) {
            $format = $this->format;
        }
        if ($format === 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            if ($outputAsJson) {
                $this->data = $this->jsonDecode($rawData);

                $rec0 = $data[0];
                $structure['key'] = 'index';
                if (is_array($rec0)) {
                    $structure['labels'] = array_values($rec0);
                    $structure['types'] = array_fill(0, sizeof($rec0), 'string');
                } else {
                    $structure['labels'] = [];
                    $structure['types'] = [];

                }
                $structure = $this->jsonEncode($structure);

                $data = [$rawData, $structure];
            } else {
                $data = $this->jsonDecode($rawData, true);
            }

        } elseif ($format === 'yaml') {
            $data = $this->convertYaml($rawData);
// Todo: cases s/s, a/s, s/a, a/a
            if ($outputAsJson) {
                if (isset($data["_structure"])) {
                    $structure = $data["_structure"];
                    unset($data["_structure"]);
                } elseif ($data) {
                    $rec0 = reset($data);
                    $key0 = (array_keys($data))[0];
                    if (is_int($key0)) {
                        $structure['key'] = 'index';
                    } elseif (preg_match('/^ \d{2,4} - \d\d - \d\d/x', $key0)) {
                        $structure['key'] = 'date';
                    } else {
                        $structure['key'] = 'string';
                    }
//???
                    $structure['labels'] = false; //array_keys($rec0);
                    $structure['types'] = false;//array_fill(0, sizeof($rec0), 'string');

                } else {
                    return [[], $structure];
                }

                $this->data = $data;

                $structure = $this->jsonEncode($structure);
                $data = [$this->jsonEncode($data), $structure];
            }

        } elseif (($format === 'csv') || ($this->format === 'txt')) {
            $data = $this->parseCsv($rawData);
            if ($outputAsJson) {
                $this->data = $data;

                $rec0 = $data[0];
                $structure['key'] = 'index';
                $structure['labels'] = array_values($rec0);
                $structure['types'] = array_fill(0, sizeof($rec0), 'string');

                $structure = $this->jsonEncode($structure);
                $data = [$this->jsonEncode($data), $structure];
            }

        }
        if (!$data) {
            $data = array();
        }
        return $data;
    } // decode




    //--------------------------------------------------------------
    protected function convertYaml($str)
    {
        $data = null;
        if ($str) {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                die("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
            }
        }
        return $data;
    } // convertYaml





    //--------------------------------------------------------------
    protected function parseCsv($str, $delim = false, $enclos = false) {

        if (!$delim) {
            $delim = (substr_count($str, ',') > substr_count($str, ';')) ? ',' : ';';
            $delim = (substr_count($str, $delim) > substr_count($str, "\t")) ? $delim : "\t";
        }
        if (!$enclos) {
            if (strpbrk($str[0], '"\'')) {
                $enclos = $str[0];
            } else {
                $enclos = (substr_count($str, '"') > substr_count($str, "'")) ? '"' : "'";
            }
        }

        $lines = explode(PHP_EOL, $str);
        $array = array();
        foreach ($lines as $line) {
            if (!$line) { continue; }
            $line = str_replace("\\n", "\n", $line);
            $array[] = str_getcsv($line, $delim, $enclos);
        }
        return $array;
    } // parseCsv



    protected function getSessionID()
    {
        if (!$sessionId = session_id()) {
            session_start();
            $sessionId = session_id();
            session_abort();
        }
        return $sessionId;
    } // getSessionID




    protected function isMySessionID( $sid )
    {
        if (!$sessionId = session_id()) {
            session_start();
            $sessionId = session_id();
            session_abort();
        }
        return ($sid === $sessionId);
    } // isMySessionID




    protected function deriveTableName()
    {
        $tableName = str_replace(['/', '.'], '_', $this->dataFile);
        $tableName = preg_replace('|^[\./_]*|', '', $tableName);
        return $tableName; // remove leading '../...'
    }

} // DataStorage

