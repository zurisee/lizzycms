<?php
/*
 * Lizzy maintains *one* SQlite DB (located in 'CACHE_PATH/.lzy_db.sqlite')
 * So, all data managed by DataStorage2 is stored in there.
 * However, shadow data files in yaml, json or cvs format may be maintained:
 *      they are imported at construction and exported at deconstruction time
 *
 * "Meta-Data" maintains info about DB-, record- and element-level locking.
 * It is maintained only with the Lizzy DB - deleting that will reset all locking.
 *
*/

 // PATH_TO_APP_ROOT must to be defined by the invoking module
 // *_PATH constants must only define path starting from app-root
define('LIZZY_DB',  PATH_TO_APP_ROOT . CACHE_PATH . '_lzy_db.sqlite');

if (!defined('LZY_LOCK_ALL_DURATION_DEFAULT')) {
    define('LZY_LOCK_ALL_DURATION_DEFAULT', 900.0); // 15 minutes
}
if (!defined('LZY_DEFAULT_DB_TIMEOUT')) {
    define('LZY_DEFAULT_DB_TIMEOUT', 0.333); // 1/3 sec
}
if (!defined('LZY_DB_POLLING_CYCLE_TIME')) {
    define('LZY_DB_POLLING_CYCLE_TIME', 50000); // 50ms [us]
}
if (!defined('LZY_DEFAULT_FILE_TYPE')) {
    define('LZY_DEFAULT_FILE_TYPE', 'json');
}

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage2
{
    private $lzyDb = null;
	private $dataFile;
	private $tableName;
	private $data = null;
	private $rawData = null;
	private $exportRequired = false;
	private $sid;
	private $format;
	private $lockDB = false;
	private $defaultTimeout = 30; // [s]
	private $defaultPollingSleepTime = LZY_DB_POLLING_CYCLE_TIME; // [us]


    public function __construct($args)
    {
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start();
        }
        $this->sessionId = session_id();

        $this->parseArguments($args);
        if (!$this->dataFile) {
            die("Error: DataStorage2 invoked without dataFile being specified.");
        }

        $this->initLizzyDB();

        if ($this->mode === 'readwrite') {
            $this->openDbReadWrite();
        } else {
            $this->openDbReadOnly();
        }

        $this->initDbTable();
        $this->appPath = getcwd();
    } // __construct



    public function __destruct()
    {
        if (!isset($this->appPath)) {
            return;
        }
        chdir($this->appPath); // workaround for include bug

        $this->exportToFile(); // saves data if modified

        if (@$_SESSION['lizzy']['debug']) {
            $str = $this->dumpDb(true, false);
            file_put_contents(PATH_TO_APP_ROOT . ".#logs/dBdump_$this->tableName.txt", $str);
        }

        if ($this->lzyDb) {
            $this->lzyDb->close();
            unset($this->lzyDb);
        }
    } // __destruct



    // === DB level operations ==========================
    public function read( $forceCacheRefresh = true )
    {
        $data = $this->getData($forceCacheRefresh);
        if (!$data) {
            $data = [];
        }
        return $data;
    } // read



    public function readModified( $since )
    {
        $data = $this->getData(true);
        if (!$data) {
            $data = [];
        }
        if (!$since) {
            return $data;
        }

        $rawLastRecModif = $this->lowlevelReadRawData('recLastUpdates');
        if ($rawLastRecModif && ($rawLastRecModif !== '[]')) {
            $lastRecModifs = $this->jsonDecode($rawLastRecModif);
            $outData = [];
            foreach ($data as $key => $rec) {
                if (isset($lastRecModifs[$key])) {
                    if ($lastRecModifs[$key] > $since) {
                        $outData[$key] = $rec;
                    }
                }
            }
        } else {
            $outData = $data;
        }
        return $outData;
    } // readModified



    // Remember: anybody using write() should do DB-locking explizitly
    public function write($data, $replace = true, $locking = false, $blocking = true, $logModifTimes = false)
    {
        if ($locking && !$this->lockDB( $blocking )) {
            return false;
        } elseif (!$this->_awaitDbLockEnd( $blocking )) {
            return false;
        }

        if ($replace) {
            $res = $this->lowLevelWrite($data);
        } else {
            $res = $this->_updateDB($data);
        }

        if ($locking) {
            $this->unlockDB();
        }
        if ($this->logModifTimes || $logModifTimes) {
            foreach ($data as $recId => $rec) {
                $this->updateRecLastUpdate($recId);
            }
        }

        $this->getData(true);
        return $res;
    } // write




    public function isDbLocked( $checkOnLockedRecords = true, $blocking = false )
    {
        if ($blocking) {
            return !$this->_awaitDbLockEnd( $blocking, $checkOnLockedRecords );

        } else {
            if ($this->_isDbLocked($checkOnLockedRecords)) {
                return true;
            } elseif ($checkOnLockedRecords) {
                return $this->_hasLockedRecords(false);
            }
            return false;
        }
    } // isDbLocked




    public function isLockDB( $checkOnLockedRecords = true, $blocking = false )
    {
        // to be depricated!
        // die("Method isLockDB() has been depricated, use isLockDB() instead");
        return $this->isDbLocked( $checkOnLockedRecords, $blocking);
    } // isLockDB





    public function lockDB( $blocking = true )
    {
        if ($blocking && !$this->_awaitDbLockEnd( $blocking )) {
            return false;

        } elseif ($this->_isDbLocked()) {
            return false;
        }
        return $this->_lockDB();
    } // lockDB




    public function unlockDB($force = false)
    {
        if (!$force && $this->isDbLocked()) {
            return false;
        }
        if ($force) {
            $this->_unlockAllRecs( $force );
        }
        return $this->_unlockDB( $force );
    } // unlockDB



    public function awaitChangedData($lastUpdate, $timeout = false, $pollingSleepTime = false /*us*/)
    {
        $timeout = $timeout ? $timeout : LZY_DEFAULT_DB_TIMEOUT;
        $pollingSleepTime = $pollingSleepTime ? $pollingSleepTime : $this->defaultPollingSleepTime;
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




    // === Record level operations ==========================
    // 'Record' defined as first level of multilevel nested data

    public function readRecord($recId)
    {
        $data = $this->getData(true);

        $recId = $this->fixRecId($recId);

        if (isset($data[$recId])) {
            return $data[$recId];
        } else {
            return null;
        }
    } // readRecord


    public function writeRecord($recId, $recData = null, $locking = true, $blocking = true, $logModifTimes = false)
    {
        // $supportedArgs defines the expected args, their default values, where null means required arg.
        $supportedArgs = ['recId' => null, 'recData' => null, 'locking' => false, 'blocking' => false];
        if (($recId = $this->fixRecId($recId, false, $supportedArgs)) === false) {
            $recId = $this->createNewRecId(); // recId -> append rec

        } elseif (is_array($recId)) {
            list($recId, $recData, $locking, $blocking) = $recId;
        }

        if (($recId === false) || !$recData) {
            return false;
        } elseif (is_array($recId)) {
            list($recId, $recData, $locking, $blocking) = $recId;
        }

        // if $blocking=false, _awaitRecLockEnd() performs isRecLocked():
        if (!$this->_awaitRecLockEnd($recId, $blocking, false)) {
            return false;
        }

        if ($locking && !$this->lockRec($recId)) {
            return false;
        }
        $data = $this->getData(true);

        if ($recId !== false) {
            if (@$this->structure['indexes'][0] === 0) { // maintain original data format
                $data[$recId] = array_values( $recData );
            } else {
                $data[$recId] = $recData;
            }
        } else {
            $data[] = $recData;
        }

        $this->lowLevelWrite($data);

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $recId );
        }

        if ($locking) {
            $this->unlockRec($recId);
        }
        $this->getData(true);
        return true;
    } // writeRecord



    public function appendRecord($recId, $recData, $locking = true, $blocking = true, $logModifTimes = false)
    {
        if (!$this->_awaitRecLockEnd($recId, $blocking, false)) {
            return false;
        }
        if ($locking && !$this->lockRec($recId)) {
            return false;
        }
        $data = $this->getData();
        if (($recId !== false) && ($recId !== null)) {  // case recId defined
            if (isset($data[ $recId ])) {
                if ($locking) {
                    $this->unlockRec($recId);
                }
                return false;   // record already in DB
            }
            $inx = $recId;

        } else {    // case recId undefined -> find next numeric index:
            $inx = 0;
            foreach ($data as $key => $rec) {
                if (is_int($key)) {
                    $inx = max($inx, $key);
                }
            }
        }

        $res = $this->writeRecord($inx, $recData, false, false);

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $recId );
        }

        if ($locking) {
            $this->unlockRec($recId);
        }
        return $res;
    } // appendRecord




    // like writeRecord but with separate args recId, elemName and value:
    public function writeRecordElement($recId, $elemName = null, $value = null, $locking = true, $blocking = true, $append = false, $logModifTimes = false)
    {
        $supportedArgs = ['recId' => null, 'elemName' => null, 'value' => null, 'locking' => false, 'blocking' => true, 'append' => false];
        if (($recId = $this->fixRecId($recId, false, $supportedArgs)) === false) {
            return false;
        } elseif (is_array($recId)) {
            list($recId, $elemName, $value, $locking, $blocking, $append) = $recId;
        }
        if (($recId === false) || ($elemName === false) || ($value === false)) {
            return false;
        }

        if (!$this->_awaitRecLockEnd($recId, $blocking, true)) {
            return false;
        }

        if ($locking && !$this->_lockRec($recId)) {
            return false;
        }

        $data = $this->getData(true);
        if ($recId !== false) {
            if ($append && isset($data[$recId][$elemName])) {
                $data[$recId][$elemName] .= $value;
            } else {
                $data[$recId][$elemName] = $value;
            }
        } else {
            $data[][$elemName] = $value;
        }

        $this->lowLevelWrite($data);

        if ($this->logModifTimes || $locking) {
            $this->updateRecLastUpdate( $recId );
        }

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $recId );
        }

        if ($locking) {
            $this->_unlockRec($recId);
        }
        $this->getData(true);
        return true;
    } // writeRecordElement



    public function deleteRecord($recId, $locking = true, $blocking = true)
    {
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }
        if ($this->isRecLocked( $recId )) {
            return false;
        }

        if (!$this->_awaitRecLockEnd($recId, $blocking)) {
            return false;
        }
        if ($locking) {
            if (!$this->_lockRec($recId)) {
                return false;
            }
        }
        $data = $this->getData(true);

        $res = false;
        if (isset($data[$recId])) {
            unset($data[$recId]);
            $this->lowLevelWrite($data);
            $res = true;
        }

        if ($locking) {
            $this->_unlockRec($recId);
        }
        return $res;
    } // deleteRecord




    public function lockRec( $recId, $blocking = true, $lockForAll = false )
    {
        if ($this->isDbLocked( false, $blocking )) {
            return false;
        }
        if (($recId = $this->fixRecId($recId, true)) === false) {
            return false;
        }

        if (!$this->_awaitRecLockEnd($recId, $blocking, true)) {
            return false;
        }
        return $this->_lockRec( $recId, $lockForAll );
    } // lockRec




    public function unlockRec( $recId, $force = false )
    {
        if (!$force && $this->isDbLocked( false )) {
            return false;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }

        $locked = $this->isRecLocked( $recId );
        if ($locked && !$force) { // rec already locked
            return false;
        }
        return $this->_unlockRec( $recId, $force );
    } // unlockRec




    public function isRecLocked( $recId, $skipDbLockCheck = false )
    {
        if (!$skipDbLockCheck && $this->_isDbLocked( false )) {
            return true;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }
        if (!$this->_isRecLocked( $recId )) {
            return false;
        }
        // lock found, now check timed out?
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        if (isset($recLocks[$recId])) {
            $locRec = $recLocks[$recId];
            $lockDuration = microtime(true) - $locRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                $this->unlockRec($recId, true);
                return false;
            }
            return true; // locked
        }
        return false;
    } // isRecLocked




    public function hasDbLockedRecords( $checkDBlevel = true)
    {
        if ($checkDBlevel && $this->isDbLocked(false)) {
            return true;
        }
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        $locked = false;
        foreach ($recLocks as $recId => $lockRec) {
            $locked = $this->isRecLocked( $recId );
            if ($locked) {
                break;
            }
        }
        return $locked;
    } // hasDbLockedRecords




    public function getNoOfRecords()
    {
        $data = $this->getData( true );
        if (!$data) {
            return 0;
        } else {
            return sizeof($data);
        }
    } // getNoOfRecords




    // === Element level operations ==========================
    // Element applies to any level of nested data, in particular below top level (i.e. records):

    public function readElement($key)
    {// supports scalar values and arrays

        // syntax variant '[d3][d31][d312]'
        $key = $this->parseElementSelector($key);

        if (strpos($key, '*') !== false) {
            return $this->_readElementGroup( $key );
        }

        $data = $this->getData(true);

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $rec = $data;
            foreach (explodeTrim(',', $key) as $k) {
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




    public function lastModifiedElement($key)
    {
        $rawLastRecModif = $this->lowlevelReadRawData('recLastUpdates');
        $lastRecModifs = $this->jsonDecode($rawLastRecModif);
        $lastRecModif = isset($lastRecModifs[ $key ])? $lastRecModifs[ $key ]: false;
        if (!$lastRecModif) {
            $lastRecModif = $this->lastDbModified();
        }
        return floatval($lastRecModif);
    } // lastModifiedElement




    public function writeElement($key, $value, $locking = true, $blocking = true, $logModifTimes = false)
    {
        if ($locking && !$this->lockDB( false, $blocking )) {
            return false;
        } elseif ($this->isDbLocked(false)) {
            return false;
        }
        $data = $this->getData(true);

        // syntax variant '[d3][d31][d312]'
        $key = $this->parseElementSelector($key);

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $keys = explode(',', $key);
            $rec = &$data;
            foreach ($keys as $k) {
                $k = trim($k, '\'"');
                $n = intval($k);
                if ($n || ($k === '0')) {
                    $k = $n;
                }
                if (!isset($rec[$k])) {
                    // if not as is, try to get element label:
                    $k1 = $this->resolveElementKey( $k );
                    if (!isset($rec[$k1])) {
                        $rec[$k] = null;    // instantiate element if not existing
                    } else {
                        $k = $k1;
                    }
                }
                $rec = &$rec[$k];
            }
            $rec = $value;

        } else {
            $data[$key] = $value;
        }
        $res = $this->lowLevelWrite($data);

        if ($this->logModifTimes || $logModifTimes) {
            $this->updateRecLastUpdate( $key );
        }

        if ($locking) {
            $this->unlockDB();
        }
        return $res;
    } // writeElement




    public function deleteElement($key, $locking = true, $blocking = true)
    {
        if ($locking && !$this->lockDB( $blocking )) {
            return false;
        } elseif ($this->isDbLocked( false, true )) {
            return false;
        }
        $data = $this->getData(true);
        if (isset($data[$key])) {
            unset($data[$key]);
            $this->lowLevelWrite($data);
            return true;
        }
        if ($locking) {
            $this->ds->unlockDB();
        }
        return false;
    } // delete



    public function isElementLocked( $elemKey )
    {
        if ($this->_isDbLocked( false )) {
            return true;
        }
        $elemKey = $this->parseElementSelector( $elemKey );
        $recId = $this->_recIdFromElementKey( $elemKey );
        if ($this->isRecLocked( $recId )) {
            return true;
        }
        return false;
    } // isElementLocked




    public function findRecByContent($key, $value, $returnKey = false)
    {
        // find rec for which key AND value match
        // returns the record unless $returnKey is true, then it returns the key
        $data = $this->getData();
        if (!$data) {
            return null;
        }
        if (!isset($data[0][$key])) {
            return null;
        }
        foreach ($data as $datakey => $rec) {
            if ($value === $rec[$key]) {
                if ($returnKey) {
                    return $datakey;
                } else {
                    return $rec;
                }
            }
        }
        return false;
    } // findRecByContent




    public function getDbRecStructure()
    {
        $rawData = $this->lowlevelReadRawData();
        $structure = $this->jsonDecode($rawData['structure']);
        if (!$structure) {
            $data = $this->getData();
            $structure = $this->analyseStructure($data,$rawData);
        }
        return $structure;
    } // getDbRecStructure




    public function lastDbModified()
    {
        $rawData = $this->lowlevelReadRawData();
        $filemtime = (float) filemtime( $this->dataFile );
        $lastModified = $rawData['lastUpdate'];
        if ($filemtime > $lastModified) {
            $lastModified = $filemtime;
            $this->importFromFile();
        }
        return $lastModified;
    } // lastModified




    public function checkNewData($lastUpdate, $returnJson = false)
    {
        // checks whether new data has been saved since the given time:
        $rawData = $this->lowlevelReadRawData();
        if ($rawData['lastUpdate'] > $lastUpdate) {
            $data = $this->getData(true);
            if ($returnJson) {
                $data['__lastUpdate'] = $rawData['lastUpdate'];
                $data = json_encode($data);
            }
            return $data;
        } else {
            return null;
        }
    } // checkNewData




 // === depricated ======================
    public function doLockDB()  // alias for compatibility
    {
        die("Method lockDB() has been depricated - use lockDB() instead");
        return $this->lockDB();
    } // doLockDB




    public function doUnlockDB()  // alias for compatibility
    {
        die("Method doUnlockDB() has been depricated - use unlockDB() instead");
        return $this->unlockDB();
    } // doUnlockDB



    public function getDbRef()
    {
        die("Method getDbRef() has been depricated");
    } // getDbRef

    


 // === aux methods ======================
    public function dumpDb( $raw = false, $flat = true )
    {
        if ($raw) {
            $d = $this->lowlevelReadRawData();
        } else {
            $d = $this->getData( true );
        }
        $s = var_r($d, 'DB "' . basename($this->dataFile).'"', $flat, false);
        $s = str_replace('⌑⌇⌑', '"', $s);
        return $s;
    } // dumpDb



    
    public function getSourceFormat() {
        return $this->format;
    } // getSourceFormat




 // === private methods ===============
    private function getData( $force = false )
    {
        if ($this->data && !$force) {
            return $this->data;
        }
        $rawData = $this->lowlevelReadRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
        $this->data = $data;
        return $data;
    } // getData




    // bare-data: excluding keys starting with '_'
    private function getBareData()
    {
        $rawData = $this->lowlevelReadRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
        $rec0 = reset($data);
        $s = implode(',', array_keys($rec0));
        if (strpos($s, ',_')) {
            foreach ($data as $key => $rec) {
                if (is_string($key) && $key && ($key[0] === '_')) {
                    unset($data[ $key ]);
                }
            }
        }
        return $data;
    } // getBareData




    private function resolveElementKey( $id )
    {
        $rec0 = reset( $this->data );
        if (isset($rec0[ $id ])) {
            return $id;
        }
        return @array_keys($rec0)[ $id ];
    } // resolveElementKey




    private function createNewRecId()
    {
        if ($this->structure["key"] === 'index') {
            $recId = sizeof($this->getData(true));
        } elseif ($this->structure["key"] === 'date') {
            $recId = date('Y-m-d');
        } elseif ($this->structure["key"] === 'datetime') {
            $recId = date('Y-m-d H:i:s');
        } else {
            $recId = time();
        }
        return $recId;
    } // createNewRecId




    private function _awaitDbLockEnd($timeout = true, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !$this->_isDbLocked($checkOnLockedRecords);
        }

        // wait for DB to be unlocked:
        if ($timeout === true) {
            $timeout = LZY_DEFAULT_DB_TIMEOUT;
        } else {
            $timeout = min(LZY_LOCK_ALL_DURATION_DEFAULT, $timeout);
        }
        $till = microtime(true) + $timeout;
        while (($locked = $this->_isDbLocked( $checkOnLockedRecords )) && $timeout && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitDbLockEnd





    private function _readElementGroup( $key )
    {
        $data = $this->getData(true);
        if (!$data) {
            return null;
        }

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $rec = $data;
            $keys = explodeTrim(',', $key);
            foreach ($keys as $k) {
                array_shift($keys);
                $k = trim($k, '\'"');
                if ($k === '*') {
                    $outRecs = [];
                    foreach ($rec as $k0 => $subRec) {
                        foreach ($keys as $k1) {
                            $n = intval($k1);
                            if ($n || ($k1 === '0')) {
                                $k1 = $n;
                            }
                            if (isset($subRec[$k1])) {
                                $outRecs[$k0] = $subRec[$k1];
                            } else {
                                $outRecs[$k0] = '';
                            }
                        }
                    }
                    return $outRecs;
                } else {
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
            }
            return $rec;
        }

    } // _readElementGroup




    private function _isDbLocked( $checkOnLockedRecords = true )
    {
        $rawData = $this->lowlevelReadRawData();
        $lockTime = $rawData['lockTime'];
        if ($lockTime && ($lockTime < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT))) {
            // lock too old - force it open:
            $this->_unlockDB();

        } elseif ($rawData['lockedBy'] &&
            ($rawData['lockedBy'] !== $this->sessionId)) {
            // locked by someone else:
            return true;
        }

        if ($checkOnLockedRecords) {
            return $this->_hasLockedRecords();
        } else {
            return false;
        }
    } // _isDbLocked




    private function _lockDB()
    {
        $rawData = $this->lowlevelReadRawData();
        if ($rawData['lockedBy'] && ($rawData['lockedBy'] !== $this->sessionId)) {
            return false;
        }
        $rawData['lockedBy'] = $this->sessionId;
        $rawData['lockTime'] = microtime(true);
        $this->updateRawDbMetaData($rawData);
        return true;
    } // _lockDB




    private function _unlockDB( $force = false )
    {
        $rawData = $this->lowlevelReadRawData();
        if (!$force && ($rawData['lockedBy'] && ($rawData['lockedBy'] !== $this->sessionId))) {
            return false;
        }
        $rawData['lockedBy'] = '';
        $rawData['lockTime'] = 0.0;
        $this->updateRawDbMetaData($rawData);
        return true;
    } // _unlockDB



    private function _awaitRecLockEnd($recId, $timeout, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !$this->isRecLocked($recId);
        }

        // wait for DB to be unlocked:
        if (!$this->_awaitDbLockEnd($timeout, false)) {
            return false;
        }
        $till = microtime(true) + $timeout;
        while (($locked = $this->_isRecLocked($recId)) && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitRecLockEnd




    private function _isRecLocked( $recId )
    {
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        if (isset($recLocks[$recId])) {
            $lockRec = $recLocks[$recId];
            // not locked, if it's my own lock:
            if ($this->isMySessionID( $lockRec['lockOwner'] )) {
                return false; // not locked
            }

            // check whether lock timed out:
            $lockDuration = microtime(true) - $lockRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                $this->_unlockRec($recId, true);
                writeLog("DataStorage: recLoc on $this->dataFile => $recId timed out -> forced open");
                return false;
            }
            // it's locked by somebody else:
            return true; // locked
        }
        return false;
    } // isRecLocked




    private function _hasLockedRecords()
    {
        $recLocks = $this->lowlevelReadRecLocks();
        if (!$recLocks) {
            return null;
        }
        $locked = false;
        foreach ($recLocks as $recId => $lockRec) {
            $lockDuration = microtime(true) - $lockRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                // rec-lock too old, force it open:
                $this->_unlockRec($recId);
                continue;
            }
            // not locked, if it's my own lock:
            if ($this->isMySessionID( $lockRec['lockOwner'] )) {
                continue;
            }
            // it's locked by somebody else:
            $locked = true; // locked
            break;
        }
        return $locked;
    } // hasLockedRecords




    private function _lockRec( $recId, $lockForAll = false )
    {

        if ($this->isRecLocked( $recId )) { // rec already locked
            return false;
        }
        $recLocks = $this->lowlevelReadRecLocks();
        $recLocks[$recId] = [
            'lockTime' => microtime(true),
            'lockOwner' => $lockForAll? 0 : $this->sessionId
        ];
        $this->lowLevelWriteRecLocks($recLocks);
        return true;
    } // _lockRec




    private function _unlockRec( $recId, $force = false )
    {
        $recLocks = $this->lowlevelReadRecLocks();
        if (isset($recLocks[$recId])) {
            if (!$force && !$this->isMySessionID( $recLocks[$recId]['lockOwner'] )) {
                return false;
            }
            unset($recLocks[$recId]);
            $this->lowLevelWriteRecLocks($recLocks);
        }
        return true;
    } // _unlockRec



    private function _unlockAllRecs( $force )
    {
        if ( $force ) {
            $this->lowLevelWriteRecLocks( [] );
        } else {
            $recLocks = $this->lowlevelReadRecLocks();
            foreach ($recLocks as $recId => $recLock) {
                if (!$this->isMySessionID($recLocks[$recId]['lockOwner'])) {
                    continue;
                }
                unset($recLocks[$recId]);
            }
            $this->lowLevelWriteRecLocks($recLocks);
        }
        return true;
    } // _unlockAllRecs




    // merge with new data:
    private function _updateDB($newData)
    {
        $data = $this->getData(true);
        if ($data) {
            $newData = array_merge($data, $newData);
        }
        $this->lowLevelWrite($newData);
    } // _updateDB




    private function fixRecId($recId, $allowNewRec = false, $supportedArgs = false)
    {
        if (!$this->data) {
            return $recId;
        }
        if (is_array($recId)) {
            return $this->parseRecModifArgs($recId, $supportedArgs);
        }
        if (isset($this->data[$recId])) {
            return $recId;
        }
        if (is_int($recId)) { // in case it was an index:
            if (!$this->data) {
                return false;
            }
            $n = sizeof($this->data);
            if (!$allowNewRec) {
                $n -= 1;
            }
            if (($recId < 0)) {
                $recId = $n + $recId;
            }
            if ($allowNewRec) {
                $recId = max(0, min($n, $recId));
            }
            if (isset($this->data[$recId])) {
                return $recId;
            }
            $keys = array_keys($this->data);
            if (isset($keys[$recId])) {
                $recId = $keys[$recId];
            }
            if (!$allowNewRec) {
                if (!isset($this->data[$recId])) {
                    return false;
                }
            }
        } elseif (is_string($recId) &&  (strpbrk($recId, ',]')) !== false) {
            $recId = preg_replace('/,.*/', '', $recId);

        } elseif (isset($this->data[ intval($recId) ])) {
            $recId = intval($recId);
        }
        return $recId;
    } // fixRecId




    private function parseRecModifArgs($writeArgs, $args)
    {
        $outArgs = [];
        foreach ($args as $argName => $default) {
            if (isset($writeArgs[$argName])) {
                $outArgs[] = $writeArgs[$argName];
            } elseif ($default === null) {
                return false;
            } else {
                $outArgs[] = $default;
            }
        }
        $outArgs[0] = $this->fixRecId($outArgs[0]);
        return $outArgs;
    } // parseWriteArgs




    private function parseElementSelector($key)
    {
        // syntax variant '[d3][d31][d312]' or ['d3']['d31']['d312']
        if (preg_match('/\[(.*)\]/', trim($key), $m)) {
            $key = str_replace('][', ',', $m[1]);
            $key = str_replace(['"', "'"], '', $key);
        }
        return $key;
    } // parseElementSelector




    private function _recIdFromElementKey($key )
    {
        $key = $this->parseElementSelector($key);
        if (strpos($key, ',') !== false) {
            $a = explode(',', $key);
            $key = $a[0];
        }
        return $key;
    } // _recIdFromElementKey



 // === Low Level Operations ===========================================================
    private function lowlevelReadRecLocks()
    {
        $query = "SELECT \"recLocks\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        return isset($rawData['recLocks']) ? $this->jsonDecode($rawData['recLocks']) : false;
    } // lowlevelReadMetaData




    private function lowlevelReadRecLastUpdates()
    {
        $query = "SELECT \"recLastUpdates\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        return isset($rawData['recLastUpdates']) ? $this->jsonDecode($rawData['recLastUpdates']): false;
    } // lowlevelReadRecLastUpdates




    private function lowlevelReadRawData($rawElem = false)
    {
        if (!$this->tableName) {
            return null;
        }
        $query = "SELECT * FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        if ($rawElem) {
            if (isset($rawData[$rawElem])) {
                return $rawData[$rawElem];
            } else {
                return null;
            }
        }
        $this->rawData = $rawData;
        return $rawData;
    } // lowlevelReadRawData




    private function lowLevelWrite($newData, $isJson = false)
    {
        $this->openDbReadWrite();

        $json = $this->jsonEncode($newData, $isJson);
        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem

        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json", 
    "lastUpdate" = $modifTime;

EOT;

        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->rawData = $this->lowlevelReadRawData();

        $this->exportRequired = true;
        return $res;
    } // lowLevelWrite




    private function lowLevelWriteRecLocks( $recLocks )
    {
        $this->openDbReadWrite();

        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        $recLocksJson = $this->jsonEncode( $recLocks );
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lastUpdate" = $modifTime,
    "recLocks" = "$recLocksJson";

EOT;

        $res = $this->lzyDb->query($sql);
        return $res;
    } // lowLevelWriteMeta



    private function lowLevelWriteStructure()
    {
        $this->openDbReadWrite();

        $structureJson = isset($this->structure)? $this->jsonEncode($this->structure): '';

        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "structure" = "$structureJson";

EOT;

        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        return $res;
    } // lowLevelWriteStructure




    private function updateRawDbMetaData($rawData)
    {
        $this->openDbReadWrite();

        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        $lockTime = str_replace(',', '.', $rawData['lockTime']); // fix float->str conversion problem
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lockedBy" = "{$rawData['lockedBy']}",
    "lockTime" = "$lockTime",
    "lastUpdate" = $modifTime;

EOT;
        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->rawData = $this->lowlevelReadRawData();
    } // updateRawDbMetaData




    private function updateDbModifTime( $modifTime = false )
    {
        $this->openDbReadWrite();

        if (!$modifTime) {
            $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem
        }
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "lastUpdate" = $modifTime;

EOT;
        try {
            $res = $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->rawData = $this->lowlevelReadRawData();
    } // updateDbModifTime




    private function updateRecLastUpdate( $recId )
    {
        // $recId can be of form 'r,c', if so, we need to drop the part ',c' in order to refer to the record:
        $recId = preg_replace('/(,.*)/', '', $recId);

        $query = "SELECT \"recLastUpdates\" FROM \"{$this->tableName}\"";
        $rawData = $this->lzyDb->querySingle($query, true);
        $recLastUpdates = $this->jsonDecode($rawData['recLastUpdates']);

        $recLastUpdates[ $recId ] = microtime(true);

        $this->lowlevelWriteRecLastUpdates( $recLastUpdates );
        return true;
    } // updateRecLastUpdate




    private function lowlevelWriteRecLastUpdates( $lastRecUpdates )
    {
        $this->openDbReadWrite();

        $json = $this->jsonEncode( $lastRecUpdates );
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "recLastUpdates" = "$json";

EOT;
        try {
            $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
    } // lowlevelWriteRecLastUpdates




    private function initLizzyDB()
    {
        if (!file_exists(LIZZY_DB)) {
            touch(LIZZY_DB);
        }
    } // initLizzyDB




    private function openDbReadWrite()
    {
        if ($this->lzyDb) {
            $this->lzyDb->close();
        }
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READWRITE);
        $this->lzyDb->busyTimeout(5000);
        $this->lzyDb->exec('PRAGMA journal_mode = wal;'); // https://www.php.net/manual/de/sqlite3.exec.php
    } // openDbReadWrite





    private function openDbReadOnly()
    {
        if ($this->lzyDb) {
            // if it's already open, leave it open, even it's in read-write mode:
            return;
        }
        $this->lzyDb = new SQLite3(LIZZY_DB, SQLITE3_OPEN_READONLY);
        $this->lzyDb->busyTimeout(5000);
    } // openDbReadWrite





    private function initDbTable()
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
        if (is_bool($sql)) {
            return;
        }
        $stmt = $this->lzyDb->prepare($sql);
        if (is_bool($stmt)) {
            return;
        }
        $res = $stmt->execute();
        $table = $res->fetchArray(SQLITE3_ASSOC);
        if (!$table) {  // if table does not exist: create it and populate it with data from origFile
            $this->createNewTable($tableName);
            $rawData = $this->lowlevelReadRawData();

        } else { // if table exists, check whether update necessary:
            $ftime = floatval(filemtime($dataFile));
            $rawData = $this->lowlevelReadRawData();
            if ($ftime > $rawData['lastUpdate']) {
                $res = $this->importFromFile();
                if ($res === false) {
                    die("Error: unable to update table in lzyDB: '$tableName'");
                }
            } else {
                $this->getData();
            }
        }
        if ($rawData["structure"] === 'null') {
            $raw = $this->loadFile();
            $this->analyseStructure($this->data, $raw);
            $this->lowLevelWriteStructure();
        } else {
            $this->structure = $this->jsonDecode($rawData["structure"]);
        }

        return;
    } // initDbTable




    private function importFromFile($initial = false)
    {
        $this->openDbReadWrite();
        $rawData = $this->loadFile();

        if ($this->logModifTimes) {
            $oldDat = $this->getData();
            $newData = $this->decode($rawData, false, false, $initial);
            foreach ($newData as $key => $rec) {
                if ($rec !== $oldDat[$key]) {
                    $this->updateRecLastUpdate( $key );
                }
            }
            $json = $this->jsonEncode($newData, false);

        } else {
            $json = $this->decode($rawData, false, true, $initial);
            $json = $this->jsonEncode($json, true);
        }

        $json = SQLite3::escapeString($json);
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    "data" = "$json";

EOT;
        try {
            $this->lzyDb->query($sql);
        }
        catch (exception $e) {
            fatalError($e->getMessage());
        }
        $this->updateDbModifTime();
        $this->rawData = $this->lowlevelReadRawData();
    } // importFromFile




    private function exportToFile()
    {
        $rawData = $this->lowlevelReadRawData();
        if ($this->exportRequired) {
            if (isset($GLOBALS['appRoot'])) {
                $filename = $GLOBALS['appRoot'] . $rawData['origFile'];

            } else {
                $filename = PATH_TO_APP_ROOT . $rawData['origFile'];
            }
            if (!$filename) {
                mylog("Error: filename missing for export file (".__FILE__.' '.__LINE__.')');
                return;
            }
            if (!file_exists($filename)) {
                mylog("Error: unable to export data to file '$filename'");
                return;
            }

            if ($this->useRecycleBin) {
                require_once SYSTEM_PATH.'page-source.class.php';
                $ps = new PageSource;
                $ps->copyFileToRecycleBin($filename, false, true);
            }

            $data = $this->getBareData();
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




    private function writeToYamlFile($filename, $data)
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




    private function writeToCsvFile($filename, $array, $quote = '"', $delim = ';', $forceQuotes = true)
    {
        $out = '';
        if (!is_array($array)) {
            return false;
        }
        // prepend header row:
        $outData[0] = array_values($this->structure['labels']);
        // remove field labels:
        foreach ($array as $row) {
            $outData[] = array_values($row);
        }
        // transform into CSV format:
        foreach ($outData as $row) {
            if (!is_array($row)) { continue; }
            foreach ($row as $i => $elem) {
                if (is_array($elem)) {
                    $elem = @$elem[0];
                }
                if ($forceQuotes || strpbrk($elem, "$quote$delim")) {
                    $row[$i] = $quote . str_replace($quote, $quote.$quote, $elem) . $quote;
                }
                $row[$i] = str_replace(["\n", "\r"], ["\\n", ''], $row[$i]);
            }
            $out .= implode($delim, $row)."\n";
        }
        file_put_contents($filename, $out);
    } // writeToCsvFile




    private function jsonEncode($data, $isAlreadyJson = false)
    {
        if ($isAlreadyJson && is_string($data)) {
            $json = $data;
        } else {
            $json = json_encode($data);
        }
        $json = str_replace(['"', "'"], ['⌑⌇⌑', '⌑⊹⌑'], $json);
        return $json;
    } // jsonEncode




    private function jsonDecode($json)
    {
        if (!is_string($json)) {
            return null;
        }
        $json = str_replace(['⌑⌇⌑', '⌑⊹⌑'], ['"', "'"], $json);
        return json_decode($json, true);
    } // jsonDecode




    private function parseArguments($args)
    {
        if (is_string($args)) {
            $args = ['dataFile' => $args];
        }
        $this->dataFile = isset($args['dataFile']) ? $args['dataFile'] :
            (isset($args['dataSource']) ? $args['dataSource'] : ''); // for compatibility
        $this->dataFile = resolvePath($this->dataFile);
        $this->logModifTimes = isset($args['logModifTimes']) ? $args['logModifTimes'] : false;
        $this->sid = isset($args['sid']) ? $args['sid'] : '';
        $this->mode = isset($args['mode']) ? $args['mode'] : 'readonly';
        $this->format = isset($args['format']) ? $args['format'] : '';
        $this->includeKeys = isset($args['includeKeys']) ? $args['includeKeys'] : false;
        $this->secure = isset($args['secure']) ? $args['secure'] : true;
        $this->userCsvFirstRowAsLabels = isset($args['userCsvFirstRowAsLabels']) ? $args['userCsvFirstRowAsLabels'] : true;
        $this->useRecycleBin = isset($args['useRecycleBin']) ? $args['useRecycleBin'] : false;
        $this->format = ($this->format) ? $this->format : pathinfo($this->dataFile, PATHINFO_EXTENSION) ;
        $this->tableName = isset($args['tableName']) ? $args['tableName'] : '';
        if ($this->tableName && !$this->dataFile) {
            $rawData = $this->lowlevelReadRawData();
            $this->dataFile = PATH_TO_APP_ROOT.$rawData["origFile"];
        }
        $this->resetCache = isset($args['resetCache']) ? $args['resetCache'] : false;
        return;
    } // parseArguments




    private function decode($rawData, $fileFormat = false, $outputAsJson = false, $analyzeStructure = false)
    {
        if (!$rawData) {
            return null;
        }
        if (!$fileFormat) {
            $fileFormat = $this->format;
        }
        if ($fileFormat === 'json') {
            $rawData = str_replace(["\r", "\n", "\t"], '', $rawData);
            $data = $this->jsonDecode($rawData);

        } elseif ($fileFormat === 'yaml') {
            $data = $this->convertYaml($rawData);

        } elseif (($fileFormat === 'csv') || ($this->format === 'txt')) {
            $data = $this->parseCsv($rawData);

            // if not suppressed: use first data row as element-labels:
            if ($this->userCsvFirstRowAsLabels) {
                $labels = array_shift( $data );
                $out = [];
                foreach ($data as $r => $rec) {
                    foreach ($labels as $i => $label) {
                        $out[$r][$label] = $rec[$i];
                    }
                }
                $data = $out;
            }

        } else {    // unknown fileType:
            $data = false;
        }

        if ($analyzeStructure) {
            // structure may be submitted within data in a special record "_structure":
            if (isset($data["_structure"])) {
                $this->structure = $data["_structure"];
                unset($data["_structure"]);
            } else {
                $this->structure = $this->analyseStructure($data, $rawData, $fileFormat);
            }
        } else {
            $this->structure = $this->analyseStructure(false, $rawData, $fileFormat);
        }
        if (!$data) {
            $data = array();
        }

        if ($this->includeKeys) {
            foreach ($data as $key => $rec) {
                $data[ $key]['_key'] = $key;
            }
        }

        $this->data = $data;
        if ($outputAsJson) {
            return $this->jsonEncode($data);
        }
        return $data;
    } // decode




    private function analyseStructure($data, &$rawData, $fileFormat = false)
    {
        $structure = [
            'key' => null,
            'labels' => [],
            'types' => [],
        ];
        if (!$data) {
            $this->structure = $structure;
            return $structure;
        }
        if (!$fileFormat) {
            $fileFormat = $this->format;
        }

        if ($fileFormat === 'yaml') {
            if (isset($rawData['origFile'])) {
                $rawData = trim(file_get_contents($rawData['origFile']));
            }
            if ($rawData && ($rawData[0] === '-')) {
                $structure['key'] = 'index';
            } else {
                $key0 = substr($rawData, 0, strpos($rawData, ':'));
            }
        } elseif ($fileFormat === 'json') {
            if (isset($rawData['origFile'])) {
                $rawData = trim(file_get_contents($rawData['origFile']));
            }
            if ($rawData && ($rawData[0] === '[')) {
                $structure['key'] = 'index';
            } else {
                if (!preg_match('/^{"(.*?)"/', $rawData, $m)) {
                    exit("Error in json file");
                }
                $key0 = $m[1];
            }
        } else {    // csv
            $structure['key'] = 'index';
            $rec0 = reset($data);
            // note: this requires that CSV file has been transformed already, ie first row used as labels.
            $structure['labels'] = is_array($rec0) ? array_keys( $rec0 ): [];
        }

        if (!$structure['key']) {
            if (preg_match('/^ \d{2,4} - \d\d - \d\d/x', $key0)) {
                $structure['key'] = 'date';
            } elseif (preg_match('/\D/', $key0)) {
                $structure['key'] = 'string';
            } else {
                $structure['key'] = 'numeric';
            }
        }

        $rec0 = reset($data);
        $l = is_array($rec0) ? sizeof($rec0) : 0;
        if (!$structure['labels']) {
            if (is_array($rec0)) {
                $indexes = array_keys($rec0);
            } else {
                $indexes = [$key0];
            }
            $labels = $indexes;
            // if data has numeric rec-keys, use first row as labels:
            if (is_int($labels[0])) {
                $labels = array_values($rec0);
            }
            $structure['labels'] = is_array($rec0) ? $labels : [];
            $structure['indexes'] = $indexes;
        }
        $structure['types'] = array_fill(0, $l, 'string');
        // note: types only makes sense if supplied in '_structure' record within data.
        $this->structure = $structure;
        return $structure;
    } // analyseStructure




    private function convertYaml($str)
    {
        $data = null;
        if ($str) {
            $str = str_replace("\t", '    ', $str);
            try {
                $data = Yaml::parse($str);
            } catch(Exception $e) {
                writeLog("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
                die("Error in Yaml-Code: <pre>\n$str\n</pre>\n".$e->getMessage());
            }
        }
        return $data;
    } // convertYaml





    private function parseCsv($str, $delim = false, $enclos = false) {

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



    private function getSessionID()
    {
        return $this->sessionId;
    } // getSessionID




    private function isMySessionID( $sid )
    {
        return ($sid === $this->sessionId);
    } // isMySessionID




    private function deriveTableName()
    {
        $tableName = str_replace(['/', '.'], '_', $this->dataFile);
        $tableName = preg_replace('|^[\./_]*|', '', $tableName);
        return $tableName; // remove leading '../...'
    } // deriveTableName




    private function createNewTable($tableName)
    {
        $this->openDbReadWrite();

        $sql = "CREATE TABLE IF NOT EXISTS \"$tableName\" (";
        $sql .= '"id" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,';
        $sql .= '"data" VARCHAR, "structure" VARCHAR, "origFile" VARCHAR, "recLastUpdates" VARCHAR, "recLocks" VARCHAR, "lockedBy" VARCHAR, "lockTime" REAL, "lastUpdate" REAL)';
        $res = $this->lzyDb->query($sql);
        if ($res === false) {
            die("Error: unable to create table in lzyDB: '$tableName'");
        }

        $origFileName = $this->dataFile;
        if (PATH_TO_APP_ROOT && (strpos($origFileName, PATH_TO_APP_ROOT) === 0)) {
            $origFileName = substr($origFileName, strlen(PATH_TO_APP_ROOT));
        }
        $modifTime = str_replace(',', '.', microtime(true)); // fix float->str conversion problem

        $sql = <<<EOT
INSERT INTO "$tableName" ("data", "structure", "origFile", "recLastUpdates", "recLocks", "lockedBy", "lockTime", "lastUpdate")
VALUES ("", "", "$origFileName", "[]", "[]", "", 0.0, $modifTime);
EOT;
        $stmt = $this->lzyDb->prepare($sql);
        if (!$stmt) {
            die("Error: unable to initialize table in lzyDB: '$tableName'");
        }
        $res = $stmt->execute();
        if ($res === false) {
            die("Error: unable to initialize table in lzyDB: '$tableName'");
        }

        $res = $this->importFromFile(true);
        if ($res === false) {
            die("Error: unable to populate table in lzyDB: '$tableName'");
        }
        $this->lowLevelWriteStructure();
    } // createNewTable



    private function loadFile()
    {
        $lines = file($this->dataFile);
        $rawData = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#') && ($line[0] !== "\n")) { // skip commented and empty lines
                $rawData .= $line;
            }
        }
        return $rawData;
    } // loadFile

} // DataStorage

