<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 * Simple file-based key-value database
 *
 * $db = new DataStorage($dbFile, $sid = '', $lockDB = false, $format = '')
 *
 * write($key, $value = null)
 *      -> $key == key && $value == value   -> store tuple
 *      -> $key == tuple [key =>value,...]  -> store array
 *      -> $key == array ** $value != null  -> replace data
 *
 * read($key = '*')
 *      -> $key == null || '*'  -> read all records, return array
 *      -> $key == key  -> return value
 *
 * lock($key)
 *
 * unlock($key = true)
 *      -> $key == true  -> unlock all owner's records
 *      -> $key == '*'  -> unlock all records
 *
 * convert($source, $destinationFormat)
 *
 * Modified:
 *      writeAll($data, $destFile = '')  -> write($data)
 *      readValue($key) -> read($key)
 *      readRec($key = '*') -> read('*')
*/

if (!defined('LOCK')) { define('LOCK', 0); }
if (!defined('SID')) { define('SID', 1); }

use Symfony\Component\Yaml\Yaml;

class DataStorage
{
	private $dataFile;
	private $sid;

 	//---------------------------------------------------------------------------
   public function __construct($dbFile, $sid = '', $lockDB = false, $format = '')
    {
//        if (file_exists($dbFile)) {
        $dbFile1 = $this->resolvePath( $dbFile );
        if (file_exists( $dbFile1 )) {
	        $this->dataFile = $dbFile1;

		} else {
			if (file_exists(pathinfo($dbFile, PATHINFO_DIRNAME))) {
		        $this->dataFile = $dbFile;
	            touch($this->dataFile);

			} else {
			    die("DataStorage: unable to create file '$dbFile'");
			}
        }
        $this->sid = $sid;
        $this->lockDB = $lockDB;
        $this->format = ($format) ? $format : pathinfo($dbFile, PATHINFO_EXTENSION) ;
    } // __construct



	//---------------------------------------------------------------------------
    public function write($key, $value = null)
    {
        $data = $this->lowLevelRead();
        if (is_array($key)) {
            if ($value) {
                $data = $key;   // overwrite all

            } else {
                $array = $key;
                foreach ($array as $key => $value) {
                    if (!isset($data['_meta_'][$key][LOCK])) {
                        $data[$key] = $value;
                    } elseif (isset($data['_meta_'][$key][SID]) && ($data['_meta_'][$key][SID] == $this->sid)) {
                        $data[$key] = $value;
                    } else {
                        return false;
                    }
                }
            }

        } else {
            $data[$key] = $value;
        }
        return $this->lowLevelWrite($data);
    } // write



	//---------------------------------------------------------------------------
    public function read($key = '*', $reportLockState = false)
    {
        $data = $this->lowLevelRead();
        if ($key === '*') {
            if ($reportLockState) {
                foreach ($data as $key => $value) {
                    if (($key != '_meta_') &&  isset($data['_meta_'][$key]) &&  // if locked
                        ($data['_meta_'][$key][SID] != $this->sid)) {           //but not by client that initiated the lock:
                            $data[$key] = str_replace('**LOCKED**', '', $value) . '**LOCKED**';
                    }
                }
            }
            if (isset($data['_meta_'])) {   // make sure no sessionIds are passed on
                unset($data['_meta_']);
            }
            $value = $data;

        } elseif (isset($data[$key])) {
            $value = $data[$key];

        } else {
            $value =  null;
        }
        return $value;
    } // read



    //---------------------------------------------------------------------------
    public function lock($key)
    {
        $data = $this->lowLevelRead();
        if (isset($data['_meta_'][$key][SID]) && ($data['_meta_'][$key][SID] != $this->sid)) {
            return false;
        }
        if (isset($data[$key])) {
            $data['_meta_'][$key][LOCK] = time();
            $data['_meta_'][$key][SID] = $this->sid;
        }
        return $this->lowLevelWrite($data);
    } // lock



    //---------------------------------------------------------------------------
    public function unlock($key = true)
    {
        $data = $this->lowLevelRead();
        if ($key === '*') {    // unlock all records
            if (isset($data['_meta_'])) {
                unset($data['_meta_']);
            }
        } elseif ($key === true) {    // unlock all owner's records
            foreach ($data as $key => $value) {
                if (isset($data['_meta_'][$key][SID]) && ($data['_meta_'][$key][SID] == $this->sid)) {
                    unset($data['_meta_'][$key]);
                }
            }
        } else {
            if (isset($data['_meta_'][$key])) {
                unset($data['_meta_'][$key]);
            }
        }
        return $this->lowLevelWrite($data);
    } // unlock



    //---------------------------------------------------------------------------
    public function unlockTimedOut($timeout = 600)
    {
        $modified = false;
        $data = $this->lowLevelRead();
        $timeLimit = time() - $timeout;
        foreach ($data as $key => $value) {
            if ($key == '_meta_') {
                continue;
            }
            if (isset($data['_meta_'][$key][LOCK]) && ($data['_meta_'][$key][LOCK] < $timeLimit)) {
                unset($data['_meta_'][$key]);
                $modified = true;
            }
        }
        if ($modified) {
            $this->lowLevelWrite($data);
        }
        return $modified;
    } // unlockTimedOut



    //---------------------------------------------------------------------------
    public function convert($source, $destinationFormat)
    {
        if (!file_exists($source)) {
            die("DataStorage::convert: file not found '$source'");
        }
        $this->dataFile = $source;
        $pi = pathinfo($source);
        $srcFormat = $pi['extension'];
        $data = $this->_read();
        $destFile = $pi['dirname'].'/'.$pi['filename'].'.'.$destinationFormat;
        $out = $this->encode($data, $destinationFormat);
        file_put_contents($destFile, $out);
    } // convert



    //---------------------------------------------------------------------------
    private function lowLevelWrite($data)
    {
        if ($this->lockDB) {
            clearstatcache();
            $fp = fopen($this->dataFile, "r+");
            $try = 3;
            while ($try > 0) {
                if (flock($fp, LOCK_EX)) {  // acquire an exclusive lock on the file
                    $rawData = $this->encode($data);
                    file_put_contents($this->dataFile, $rawData);
                    flock($fp, LOCK_UN);    // release the lock
                    fclose($fp);
                    $try = -1;
                } else {
                    $try--;
                    usleep(100);
                }
            }
            return ($try == -1);

        } else {    // skip locking DB
            $rawData = $this->encode($data);
            file_put_contents($this->dataFile, $rawData);
            return true;
        }
    } // lowLevelWrite



    //---------------------------------------------------------------------------
    private function lowLevelRead()
    {
        if (!$this->dataFile) {
            return false;
        }
        if ($this->lockDB) {
            clearstatcache();
            $fp = fopen($this->dataFile, 'r');
            if (flock($fp, LOCK_SH)) {
                $rawData = file_get_contents($this->dataFile);
                flock($fp, LOCK_UN);    // release the lock
                fclose($fp);
                $encod = mb_detect_encoding($rawData, 'UTF-8, ISO-8859-1');
                if ($encod == 'ISO-8859-1') {
                    $rawData = utf8_encode($rawData);
                }
                $data = $this->decode($rawData);
            } else {
                $data =  false;
            }

        } else {    // skip DB locking
            $rawData = file_get_contents($this->dataFile);
            $encod = mb_detect_encoding($rawData, 'UTF-8, ISO-8859-1');
            if ($encod == 'ISO-8859-1') {
                $rawData = utf8_encode($rawData);
            }
            $data = $this->decode($rawData);
        }

        return $data;
    } // lowLevelRead



    //---------------------------------------------------------------------------
    private function decode($rawData)
    {
        $data = false;
        if ($this->format == 'json') {
            $data = json_decode($rawData, true);

        } elseif ($this->format == 'yaml') {
            $data = convertYaml($rawData);

        } elseif (($this->format == 'csv') || ($this->format == 'txt')) {
            $data = parseCsv($rawData);
        }
        if (!$data) {
            $data = array();
        }
        return $data;
    } // decode



    //---------------------------------------------------------------------------
    private function encode($data, $format = false)
    {
        if (!$format) {
            $format = $this->format;
        }
        $encodedData = false;
        if ($format == 'json') {
            $encodedData = json_encode($data);
        } elseif ($format == 'yaml') {
            $encodedData = convertToYaml($data);
        } elseif ($format == 'csv') {
            $encodedData = arrayToCsv($data);
        }
        return $encodedData;
    } // encode


    //------------------------------------------------------------
    private function resolvePath($path)
    {
        global $globalParams;

        if (!$path) {
            return '';
        }

        if ((($ch1=$path[0]) != '/') && ($ch1 != '~') && ($ch1 != '.')/* && ($ch1 != '_')*/) {	//default to path local to page ???always ok?
            $path = '~/'.$path;
        }

        $path = preg_replace('|~/|', '', $path);
        $path = preg_replace('|~sys/|', SYSTEM_PATH, $path);
        return $path;
    } // resolvePath



} // DataStorage
