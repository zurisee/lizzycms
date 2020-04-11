<?php
/*
 * Lizzy maintains *one* SQlite DB (located in 'data/.lzy_db.sqlite')
 * So, all data managed by DataStorage2 is stored in there.
 * However, shadow data files in yaml, json or cvs format may be maintained:
 *      they are imported at construction and exported at deconstruction time
 *
 * "Meta-Data" maintains info about DB-, record- and element-level locking.
 * It is maintained only with the Lizzy DB - deleting that will reset all locking.
 *
*/


define('LIZZY_DB',  PATH_TO_APP_ROOT.'data/_lzy_db.sqlite');

if (!defined('LZY_LOCK_ALL_DURATION_DEFAULT')) {
    define('LZY_LOCK_ALL_DURATION_DEFAULT', 900.0); // 15 minutes
}
//if (!defined('LZY_MAX_AWAIT_LOCK_END_TIME')) {
//    define('LZY_MAX_AWAIT_LOCK_END_TIME', 10); // 10 sec
//}
if (!defined('LZY_MAX_AWAIT_LOCK_END_TIME')) {
    define('LZY_MAX_AWAIT_LOCK_END_TIME', 333000); // 1/3 sec
}
if (!defined('LZY_DEFAULT_FILE_TYPE')) {
    define('LZY_DEFAULT_FILE_TYPE', 'json');
}

require_once SYSTEM_PATH.'vendor/autoload.php';


use Symfony\Component\Yaml\Yaml;

class DataStorage2
{
    private   $lzyDb = null;
	protected $dataFile;
	protected $tableName;
	protected $data = null;
	protected $rawData = null;
	protected $sid;
	protected $format;
	protected $lockDB = false;
	protected $defaultTimeout = 30; // [s]
	protected $defaultPollingSleepTime = LZY_MAX_AWAIT_LOCK_END_TIME; // [us]


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
        if ( session_status() !== PHP_SESSION_ACTIVE ) {
            session_start();
        }
        $this->sessionId = session_id();
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




    // === DB level operations ==========================
    public function read( $forceCacheRefresh = true )
    {
        return $this->getData($forceCacheRefresh);
    } // read



    public function write($data, $replace = true)
    {
        if ($this->_isDbLocked()) {
            return false;
        }
        if ($replace) {
            $res = $this->lowLevelWrite($data);
        } else {
            $res = $this->updateDB($data);
        }
        $this->getData(true);
        return $res;
    } // write



    public function isLockDB( $checkOnLockedRecords = true )
    {
        // to be depricated!
//        die("Method isLockDB() has been depricated");
        return $this->isDbLocked();
    }

    public function isDbLocked( $checkOnLockedRecords = true )
    {
        if ($this->_isDbLocked( $checkOnLockedRecords )) {
            return true;
        } elseif ($checkOnLockedRecords) {
            return $this->_hasLockedRecords( false );
        }
        return false;
    } // isDbLocked




    public function lockDB()
    {
        if ($this->_isDbLocked()) {
            return false;
        }
        return $this->_lockDB();
//        $rawData = $this->getRawData();
//        $mySessionID = $this->sessionId;
//        if ($rawData['lockedBy'] !== '') {
//            if ($rawData['lockedBy'] === $mySessionID) {
//                $rawData['lockTime'] = microtime(true);
//
//            } else {
//                if ($rawData['lockTime'] < (microtime(true) - LZY_LOCK_ALL_DURATION_DEFAULT)) {
//                    $rawData['lockedBy'] = $mySessionID;
//                    $rawData['lockTime'] = microtime(true);
//
//                } else {
//                    return false;
//                }
//            }
//        } else {
//            $rawData['lockedBy'] = $mySessionID;
//            $rawData['lockTime'] = microtime(true);
//        }
//        $this->updateRawMetaData($rawData);
//        return true;
    } // lockDB




    public function unlockDB($force = false)
    {
        if (!$force && $this->isDbLocked()) {
//        if ($this->isDbLocked()) {
            return false;
        }
        if ($force) {
            $this->_unlockAllRecs( $force );
        }
        return $this->_unlockDB( $force );

//        $rawData = $this->getRawData();
//        $mySessionID = $this->sessionId;
//        if ($rawData['lockedBy'] !== '') {
//            if ($rawData['lockedBy'] === $mySessionID) {
//                $rawData['lockTime'] = 0.0;
//                $rawData['lockedBy'] = '';
//
//            } elseif ($force) {
//                $rawData['lockTime'] = 0.0;
//                $rawData['lockedBy'] = '';
//
//            } else {
//                return false;
//            }
//            $this->updateRawMetaData($rawData);
//        }
//        return true;
    } // unlockDB



    public function awaitChangedData($lastUpdate, $timeout = false, $pollingSleepTime = false /*us*/)
    {
        $timeout = $timeout ? $timeout : LZY_MAX_AWAIT_LOCK_END_TIME;
//        $timeout = $timeout ? $timeout : $this->defaultTimeout;
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



    public function getSize()
    {
        return sizeof($this->getData( true ));
    } // getSize




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



    public function writeRecord($recId, $recData = null, $locking = false, $blocking = false)
    {
        // supports arguments as array (in $recId)
//        if ($this->_isDbLocked( false )) {
//            return false;
//        }

        // $supportedArgs defines the expected args, their default values, where null means required arg.
        $supportedArgs = ['recId' => null, 'recData' => null, 'locking' => false, 'blocking' => false];
        if (($recId = $this->fixRecId($recId, false, $supportedArgs)) === false) {
            return false;
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
            $data[$recId] = $recData;
        } else {
            $data[] = $recData;
        }

        $this->lowLevelWrite($data);
        if ($locking) {
            $this->unlockRec($recId);
        }
        if ($this->logModifTimes || $locking) {
            $this->updateMetaData('lastRecModif_'.$recId, microtime(true));
        }
        $this->getData(true);
        return true;
    } // writeRecord



    public function writeRecordElement($recId, $elemName = null, $value = null, $locking = false, $blocking = false, $append = false)
    {
        // supports arguments as array (in $recId)
//        if ($this->_isDbLocked()) {
//            return false;
//        }
        // like writeRecord but with separate args recId, elemName and value
        $supportedArgs = ['recId' => null, 'elemName' => null, 'value' => null, 'locking' => false, 'blocking' => false, 'append' => false];
        if (($recId = $this->fixRecId($recId, false, $supportedArgs)) === false) {
            return false;
        } elseif (is_array($recId)) {
            list($recId, $elemName, $value, $locking, $blocking, $append) = $recId;
        }
        if (($recId === false) || ($elemName === false) || ($value === false)) {
            return false;
        }

        // if $blocking=false, _awaitRecLockEnd() performs isRecLocked():
        if (!$this->_awaitRecLockEnd($recId, $blocking, true)) {
            return false;
        }

        if ($locking && !$this->_lockRec($recId)) {
            return false;
        }
//        if ($locking) {
//            if (!$this->lockRec($recId)) {
//                return false;
//            }
//        }

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
        if ($locking) {
            $this->_unlockRec($recId);
        }
        $this->getData(true);
        return true;
    } // writeRecordElement



    public function deleteRecord($recId, $locking = false, $blocking = false)
    {
//        if ($this->isDbLocked()) {
//            return false;
//        }
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
            $data = array_values($data);
            $this->lowLevelWrite($data);
            $res = true;
        }

        if ($locking) {
            $this->_unlockRec($recId);
        }
        return $res;
    } // deleteRecord



    public function lockRec( $recId )
    {
        if ($this->isDbLocked( false )) {
            return false;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }


        if ($this->isRecLocked( $recId )) { // rec already locked
            return false;
        }

        return $this->_lockRec( $recId);

//        $mySessionID = $this->sessionId;
//        $lockData[$recId] = [
//            'lockTime' => microtime(true),
//            'lockOwner' => $mySessionID
//        ];
//        $this->updateMetaData('recLocks', $lockData);
//        return true;
    } // lockRec



    public function unlockRec( $recId, $force = false )
    {
        if (!$force && $this->isDbLocked( false )) {
            return false;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }

        $locked = $this->_isRecLocked( $recId );
        if ($locked && !$force) { // rec already locked
            return false;
        }

        return $this->_unlockRec( $recId, $force );
//        $meta = $this->getMetaData();
//        if (isset($meta['recLocks'][$recId])) {
//            if (!$force && !$this->isMySessionID( $meta['recLocks'][$recId]['lockOwner'] )) {
//                return false;
//            }
//            unset($meta['recLocks'][$recId]);
//            $this->updateMetaData($meta);
//        }
//        return true;
    } // unlockRec



    public function isRecLocked( $recId )
    {
        if ($this->_isDbLocked( false )) {
            return true;
        }
        if (($recId = $this->fixRecId($recId)) === false) {
            return false;
        }
//        $recLocked = $this->_isRecLocked( $recId );
        if ($this->_isRecLocked( $recId )) {
            return true;
        }
        // lock found, now check timed out?
        $lockData = $this->getMetaElement('recLocks');
        if (!$lockData) {
            return null;
        }
        if (isset($lockData[$recId])) {
            $locRec = $lockData[$recId];
            $lockDuration = microtime(true) - $locRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                $this->unlockRec($recId, true);
                return false;
            }
            // not locked, if it's my own lock:
            if ($this->isMySessionID( $locRec['lockOwner'] )) {
                return false; // not locked
            }
            // it's locked by somebody else:
            return true; // locked
        }
        return false;
    } // isRecLocked



    public function hasLockedRecords( $checkDBlevel = true)
    {
        if ($checkDBlevel && $this->isDbLocked()) {
            return true;
        }
        $lockData = $this->getMetaElement('recLocks');
        if (!$lockData) {
            return null;
        }
        $locked = false;
        foreach ($lockData as $recId => $lockRec) {
//            $locRec = $lockData[$recId];
            $lockDuration = microtime(true) - $lockRec['lockTime'];
            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
                if ($checkDBlevel) {
                    $this->unlockRec($recId, true);
                }
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




    // === Element level operations ==========================
    // Element applies to any level of nested data:

    public function readElement($key)
    {
        // supports scalar values and arrays
        $data = $this->getData(true);

        // syntax variant '[d3][d31][d312]'
        $key = $this->parseElementSelector($key);

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




    public function lastModifiedElement($key)
    {
        $lastRecModif = $this->getMetaElement('lastRecModif_'.$key);
        if (!$lastRecModif) {
            $lastRecModif = $this->lastModified();
        }
        return floatval($lastRecModif);
    } // lastModifiedElement




    public function writeElement($key, $value)
    {
        if ($this->isDbLocked()) {
            return false;
        }
        $data = $this->getData(true);

        // syntax variant '[d3][d31][d312]'
        $key = $this->parseElementSelector($key);

        // syntax variant 'd3,d31,d312'
        if (strpos($key, ',') !== false) {
            $keys = explode(',', $key);
            $key0 = $keys[0];
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

        if ($this->logModifTimes) {
            $this->updateMetaData('lastRecModif_'.$key0, microtime(true));
        }

        return $this->lowLevelWrite($data);
    } // writeElement




    //Todo: rename
    public function append($key, $value = null)
    {
        if ($this->isDbLocked()) {
            return false;
        }
        $data = $this->getData(true);

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



    //Todo: rename
    public function delete($key)
    {
        if ($this->isDbLocked()) {
            return false;
        }
        $modified = false;
        $data = $this->getData(true);
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



    public function isElementLocked( $elemKey )
    {
        if ($this->_isDbLocked( false )) {
            return true;
        }
//        if (($recId = $this->fixRecId($recId)) === false) {
//            return false;
//        }
        $elemKey = $this->parseElementSelector( $elemKey );
        $recId = $this->recIdFromElementKey( $elemKey );
//        $recLocked = $this->_isRecLocked( $recId );
        if ($this->isRecLocked( $recId )) {
            return true;
        }
//        // lock found, now check timed out?
//        $lockData = $this->getMetaElement('recLocks');
//        if (!$lockData) {
//            return null;
//        }
//        if (isset($lockData[$recId])) {
//            $locRec = $lockData[$recId];
//            $lockDuration = microtime(true) - $locRec['lockTime'];
//            if ($lockDuration > LZY_LOCK_ALL_DURATION_DEFAULT) {
//                $this->unlockRec($recId, true);
//                return false;
//            }
//            // not locked, if it's my own lock:
//            if ($this->isMySessionID( $locRec['lockOwner'] )) {
//                return false; // not locked
//            }
//            // it's locked by somebody else:
//            return true; // locked
//        }
        return false;
    } // isElementLocked


    private function recIdFromElementKey( $key )
    {
        $key = $this->parseElementSelector($key);
        if (strpos($key, ',') !== false) {
            $a = explode(',', $key);
            $key = $a[0];
        }
        return $key;
    }



    //---------------------------------------------------------------------------
    //Todo: rename
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




    //Todo: rename
    public function getRecStructure()
    {
        $rawData = $this->getRawData();
        $structure = $this->jsonDecode($rawData['structure']);
        return $structure;
    }




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
    //---------------------------------------------------------------------------
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
        if (!$this->lzyDb) {
            $this->openDbReadWrite();
        }
        return $this->lzyDb;
    } // getDbRef



// === aux methods ======================
    public function dumpDb( $raw = false)
    {
        if ($raw) {
            $d = $this->getRawData();
        } else {
            $d = $this->getData( true );
        }
        return var_r($d, 'DB "' . basename($this->dataFile).'"');
//        return var_r($d, 'DB "' . base_name($this->dataFile, false).'"');
    } // dumpDb



    public function getSourceFormat() {
        return $this->format;
    } // getSourceFormat




// === protected methods ===============
    private function _awaitDbLockEnd($timeout, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !_isDbLocked($checkOnLockedRecords);
        }

        // wait for DB to be unlocked:
        $timeout = min(LZY_MAX_AWAIT_LOCK_END_TIME, $timeout);
        $till = microtime(true) + $timeout;
        while (($locked = $this->_isDbLocked( $checkOnLockedRecords )) && $timeout && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitDbLockEnd




    private function _isDbLocked( $checkOnLockedRecords = true )
    {
        $rawData = $this->getRawData();
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
        $rawData = $this->getRawData();
        $rawData['lockedBy'] = $this->sessionId;
        $rawData['lockTime'] = microtime(true);
        $this->updateRawMetaData($rawData);
        return true;
    } // _lockDB




    private function _unlockDB()
    {
        $rawData = $this->getRawData();
        $rawData['lockedBy'] = '';
        $rawData['lockTime'] = 0.0;
        $this->updateRawMetaData($rawData);
        return true;
    } // _unlockDB



    private function _awaitRecLockEnd($recId, $timeout, $checkOnLockedRecords = true)
    {
        if (!$timeout) {
            return !$this->isRecLocked($recId);
        }

        // wait for DB to be unlocked:
        if (!$this->_awaitDbLockEnd($timeout, $checkOnLockedRecords)) {
            return false;
        }
//        $timeout = min(LZY_MAX_AWAIT_LOCK_END_TIME, $timeout);
//        $till = microtime(true) + $timeout;
//        while (($locked = $this->_isDbLocked( false )) && $timeout && (microtime(true) < $till)) {
//            usleep($this->defaultPollingSleepTime);
//        }
//        if ($locked) {
//            return false;
//        }

        // wait for Rec to be unlocked:
//        $locked = $this->_isRecLocked($recId);
        $till = microtime(true) + $timeout;
//        while ($locked && $timeout && (microtime(true) < $till)) {
//            usleep($this->defaultPollingSleepTime);
//            $locked = $this->_isRecLocked($recId);
//            $now = microtime(true);
//        }
        while (($locked = $this->_isRecLocked($recId)) && (microtime(true) < $till)) {
            usleep($this->defaultPollingSleepTime);
        }
        return !$locked;
    } // _awaitRecLockEnd



    private function _isRecLocked( $recId )
    {
        $lockData = $this->getMetaElement('recLocks');
        if (!$lockData) {
            return null;
        }
        if (isset($lockData[$recId])) {
            $lockRec = $lockData[$recId];
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
        $lockData = $this->getMetaElement('recLocks');
        if (!$lockData) {
            return null;
        }
        $locked = false;
        foreach ($lockData as $recId => $lockRec) {
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



    public function _lockRec( $recId )
    {
//        if ($this->isDbLocked()) {
//            return false;
//        }
//        if (($recId = $this->fixRecId($recId)) === false) {
//            return false;
//        }
//
//
        if ($this->isRecLocked( $recId )) { // rec already locked
            return false;
        }
//
//        $mySessionID = $this->sessionId;
        $meta = $this->getMetaData();
        $lockData = $meta['recLocks'];
        $lockData[$recId] = [
            'lockTime' => microtime(true),
            'lockOwner' => $this->sessionId
        ];
        $this->updateMetaData('recLocks', $lockData);
        return true;
    } // _lockRec




    public function _unlockRec( $recId, $force = false )
    {
        $meta = $this->getMetaData();
        if (isset($meta['recLocks'][$recId])) {
            if (!$force && !$this->isMySessionID( $meta['recLocks'][$recId]['lockOwner'] )) {
                return false;
            }
            unset($meta['recLocks'][$recId]);
            $this->updateMetaData($meta);
        }
        return true;
    } // _unlockRec



    public function _unlockAllRecs( $force )
    {
        $meta = $this->getMetaData();
        $recLocs = $meta['recLocks'];
        foreach ($recLocs as $recId => $recLoc) {
            if (!$force && !$this->isMySessionID( $meta['recLocks'][$recId]['lockOwner'] )) {
                continue;
            }
            unset($meta['recLocks'][$recId]);
        }
        $this->updateMetaData($meta);
        return true;
    } // _unlockAllRecs





    // merge with new data:
    protected function updateDB($newData)
    {
        $data = $this->getData(true);
        if ($data) {
            $newData = array_merge($data, $newData);
        }
        $this->lowLevelWrite($newData);
    } // updateDB




    protected function updateElement($key, $value)
    {
        $data = $this->getData(true);
        if (preg_match('/^(\d+),(\d+)/', $key, $m)) {
            $data[$m[2]][$m[1]] = $value;
        } else {
            $data[$key] = $value;
        }
        $this->lowLevelWrite($data);

        if ($this->logModifTimes) {
            $this->updateMetaData('lastRecModif_'.$key, microtime(true));
        }
    } // updateElement




    // === Low Level Operations ===========================================================
    protected function getMetaElement($key)
    {
        $meta = $this->getMetaData();
        if (isset($meta[$key])) {
            return $meta[$key];
        } else {
            return null;
        }
    } // getMetaElement




    protected function getMetaData()
    {
        $query = "SELECT \"meta\" FROM \"{$this->tableName}\"";
        $metaData = $this->lzyDb->querySingle($query, true);
        $meta = $this->jsonDecode($metaData['meta']);
        return $meta;
    } // getMetaData




    protected function lowLevelWriteMeta($metaData)
    {
        $metaData = $this->jsonEncode($metaData);
        $sql = <<<EOT
UPDATE "{$this->tableName}" SET 
    'meta' = "$metaData";

EOT;

        $res = $this->lzyDb->query($sql);
        return $res;
    } // lowLevelWriteMeta



    protected function lowLevelWriteStructure()
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



    protected function lowLevelWrite($newData, $isJson = false, $markModified = true)
    {
        $this->openDbReadWrite();

        $json = $this->jsonEncode($newData, $isJson);
        if (is_string($json)) {
            $json = SQLite3::escapeString($json);
        }
        $ftime = microtime(true);
        $modified = $markModified ? 1 : 0;

        if ($markModified) {
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



//Todo: rename more applicable, META used differently!
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




    protected function writeMetaData( $metaData )
    {
        return $this->lowLevelWriteMeta($metaData);
    } // writeMetaData



    protected function updateMetaData($key, $value = null)
    {
        $metaData = $this->getMetaData();
        if (is_array($key)) {
            if (is_array($metaData)) {
                $metaData = array_merge($metaData, $key);
            } else {
                $metaData = $key;
            }
        } else {
            $metaData[$key] = $value;
        }
        return $this->lowLevelWriteMeta($metaData);
    } // updateMetaData



    //---------------------------------------------------------------------------
    protected function initLizzyDB()
    {
        if (!file_exists(LIZZY_DB)) {
            preparePath(LIZZY_DB);
            touch(LIZZY_DB);
        }
    } // initLizzyDB




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
            $this->createNewTable($tableName);
            $rawData = $this->getRawData();

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
        if ($rawData["structure"] === 'null') {
            $raw = $this->loadFile();
            $this->analyseStructure($this->data, $raw);
            $this->lowLevelWriteStructure();
        } else {
            $this->structure = $this->jsonDecode($rawData["structure"]);
        }

        return;
    } // initDbTable




    //--------------------------------------------------------------
    protected function importFromFile($initial = false)
    {
        $rawData = $this->loadFile();
        $json = $this->decode($rawData, false, true, $initial);
        $this->lowLevelWrite($json, true, false);
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

            $data = $this->getData(true);
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




    protected function getData( $force = false )
    {
        if ($this->data && !$force) {
            return $this->data;
        }
        $rawData = $this->getRawData();
        $json = $rawData['data'];
        $json = str_replace('⌑⌇⌑', '"', $json);
        $data = $this->jsonDecode($json);
        $this->data = $data;
        return $data;
    } // getData




    protected function getRawData( $rawElem = false)
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
        $this->logModifTimes = isset($args['logModifTimes']) ? $args['logModifTimes'] : false;
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
    protected function decode($rawData, $fileFormat = false, $outputAsJson = false, $analyzeStructure = false)
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
        $this->data = $data;
        if ($outputAsJson) {
            return $this->jsonEncode($data);
        }
        return $data;
    } // decode



    protected function analyseStructure($data, &$rawData, $fileFormat = false)
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
            if ($rawData[0] === '-') {
                $structure['key'] = 'index';
            } else {
                $key0 = substr($rawData, 0, strpos($rawData, ':'));
            }
        } elseif ($fileFormat === 'json') {
            if ($rawData[0] === '[') {
                $structure['key'] = 'index';
            } else {
                if (!preg_match('/^{"(.*?)"/', $rawData, $m)) {
                    exit("Error in json file");
                }
                $key0 = $m[1];
            }
        } else {    // csv
            $structure['key'] = 'index';
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
        $structure['labels'] = is_array($rec0) ? array_keys($rec0): [];
        $structure['types'] = array_fill(0, sizeof($rec0), 'string');
        // types only makes sense if supplied in '_structure' record within data.
        $this->structure = $structure;
        return $structure;
    } // analyseStructure



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
        return $this->sessionId;
    } // getSessionID




    protected function isMySessionID( $sid )
    {
        return ($sid === $this->sessionId);
    } // isMySessionID




    protected function deriveTableName()
    {
        $tableName = str_replace(['/', '.'], '_', $this->dataFile);
        $tableName = preg_replace('|^[\./_]*|', '', $tableName);
        return $tableName; // remove leading '../...'
    }



    private function fixRecId($recId, $allowNewRec = false, $supportedArgs = false)
    {
        if (is_array($recId)) {
            return $this->parseWriteArgs($recId, $supportedArgs);
        }
        if (isset($this->data[$recId])) {
            return $recId;
        }
        if (is_int($recId)) { // in case it was an index:
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
        }
//ToDo: what was that for?
//        if (($this->structure['key'] !== 'index') && !isset($this->data[$recId])) {
//            $recId = false;
//        }
        return $recId;
    } // fixRecId




    protected function parseWriteArgs($writeArgs, $args)
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



    protected function createNewTable($tableName)
    {
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
        $is2D = (stripos($origFileName, '.csv') !== false) ? 1 : 0;

        $sql = <<<EOT
INSERT INTO "$tableName" ("data", "meta", "lastUpdate", "structure", "2D", "origFile", "modified", "lockedBy", "lockTime")
VALUES ("", "", 0.0, "", $is2D, "$origFileName", 0, "", 0.0);
EOT;
        $stmt = $this->lzyDb->prepare($sql);
        $res = $stmt->execute();
        if ($res === false) {
            die("Error: unable to initialize table in lzyDB: '$tableName'");
        }

        $res = $this->importFromFile(true);
        if ($res === false) {
            die("Error: unable to populate table in lzyDB: '$tableName'");
        }
        $this->lowLevelWriteStructure();
    }




    protected function loadFile()
    {
        $lines = file($this->dataFile);
        $rawData = '';
        foreach ($lines as $line) {
            if ($line && ($line[0] !== '#') && ($line[0] !== "\n")) { // skip commented and empty lines
                $rawData .= $line;
            }
        }
        return $rawData;
    }




    private function parseElementSelector($key)
    {
        // syntax variant '[d3][d31][d312]' or ['d3']['d31']['d312']
        if (preg_match('/\[(.*)\]/', trim($key), $m)) {
            $key = str_replace('][', ',', $m[1]);
            $key = str_replace(['"', "'"], '', $key);
        }
        return $key;
    }

} // DataStorage

