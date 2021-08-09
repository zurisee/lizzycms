<?php

if (!defined ('DEFAULT_TICKETS_PATH')) {
    define('DEFAULT_TICKETS_PATH', '.#sys-cache/');
}
define ('DEFAULT_TICKET_STORAGE_FILE', PATH_TO_APP_ROOT . DEFAULT_TICKETS_PATH.'tickets.json');
//define ('DEFAULT_TICKET_STORAGE_FILE', PATH_TO_APP_ROOT . DEFAULT_TICKETS_PATH.'tickets.yaml'); // for testing

define ('DEFAULT_TICKET_HASH_SIZE', 6);
define ('DEFAULT_TICKET_VALIDITY_TIME', 900); // 15 min
define ('UNAMBIGUOUS_CHARACTERS', '3479ACDEFHJKLMNPQRTUVWXY'); // -> excludes '0O2Z1I5S6G8B'

/*
 * Purpose:
 *      like a locker: put some data into a storage and get a ticket in return
 *      use the ticket to retrieve the original data
 *      the ticket is a hash code that cannot be predicted -> usefull for embedding in web pages
 *
 * Options:
 * - hashSize
 * - unambiguous: creates tickets that avoid letters which could be mixed up with digits (e.g. O and 0)
 * - defaultValidityPeriod / validityPeriod: time after which tickets expire
 * - defaultMaxConsumptionCount / maxConsumptionCount: how many times a ticket may be used
 * (- type: not used yet)
 *
 * $validityPeriod:
 *      null = system default
 *      0    = infinite
 *      string
 */

class Ticketing
{
    private $lastError = '';

    public function __construct($options = [])
    {
        $dataSrc = isset($options['dataSrc']) ? $options['dataSrc'] : DEFAULT_TICKET_STORAGE_FILE;
        $this->hashSize = isset($options['hashSize']) ? $options['hashSize'] : DEFAULT_TICKET_HASH_SIZE;
        $this->defaultType = isset($options['defaultType']) ? $options['defaultType'] : 'generic';
        $this->unambiguous = isset($options['unambiguous']) ? $options['unambiguous'] : false;
        $this->defaultValidityPeriod = isset($options['defaultValidityPeriod']) ? $options['defaultValidityPeriod'] : DEFAULT_TICKET_VALIDITY_TIME;
        $this->validityPeriod = $this->defaultValidityPeriod;
        $this->defaultMaxConsumptionCount = isset($options['defaultMaxConsumptionCount']) ? $options['defaultMaxConsumptionCount'] : 1;
        if ($this->defaultMaxConsumptionCount < 0) {
            $this->defaultMaxConsumptionCount = false;
        }
        $this->ds = new DataStorage2([
            'dataSource' => $dataSrc,
            'exportInternalFields' => true,
        ]);
        $this->purgeExpiredTickets();
    } // __construct




    public function createTicket($rec = [], $maxConsumptionCount = false, $validityPeriod = null, $type = false, $givenHash = false)
    {
        $this->type = $type? $type : $this->defaultType;
        $maxConsumptionCount = $maxConsumptionCount ?$maxConsumptionCount : $this->defaultMaxConsumptionCount;
        $pathToPage = @$GLOBALS['globalParams']['pathToPage'];

        if ($validityPeriod !== null) {
            if ($validityPeriod > 0) {
                $this->validityPeriod = $validityPeriod;
            } else {
                $this->validityPeriod = false;
            }
        }

        if ($givenHash) {
            $ticketHash = $givenHash;
        } else {
            // first check whether ticket already exists for this page:
            $ticketHash = getStaticVariable("$pathToPage.tickets.$this->type");
        }
        if ($ticketHash !== null) {
            foreach (explodeTrim(',', $ticketHash) as $tHash) {
                $ticketRec = $this->ds->readRecord($tHash);
                if ($ticketRec && is_array($ticketRec)) {
                    if ($rec && is_array($rec)) {
                        $rec = array_merge($ticketRec, $rec);
                        if ($this->validityPeriod) {
                            $rec['_ticketValidTill'] = time() + $this->validityPeriod;
                        } else {
                            $rec['_ticketValidTill'] = false;
                        }
                        $this->updateTicket($tHash, $rec);
                    } else {
                        $this->updateTicket($tHash); // just update _ticketValidTill
                    }
                    return $tHash;
                }
            }
        }
        $ticketHash = $this->_createTicket($rec, $maxConsumptionCount, $validityPeriod, $type, $givenHash);
        setStaticVariable("$pathToPage.tickets.$this->type", $ticketHash, ',');
        return $ticketHash;
    } // createTicket




    public function _createTicket($rec, $maxConsumptionCount = false, $validityPeriod = null, $type = false, $givenHash = false)
    {
        $ticketRec = $rec;
        $ticketRec['_maxConsumptionCount'] = $maxConsumptionCount ?$maxConsumptionCount : $this->defaultMaxConsumptionCount;
        if ($ticketRec['_maxConsumptionCount'] !== false) {
            $ticketRec['_maxConsumptionCount'] = intval($ticketRec['_maxConsumptionCount']);
        }
        $ticketRec['_ticketType'] = $type ? $type : $this->defaultType;
        $ticketRec['_currPage'] = $GLOBALS['globalParams']['pageFolder'];
        $ticketRec['_dataPath'] = $GLOBALS['globalParams']['dataPath'];

        if ($validityPeriod === null) {
            $this->validityPeriod = $this->defaultValidityPeriod;

        } elseif ($validityPeriod <= 0) {
            $this->validityPeriod = false;

        } else {
            $this->validityPeriod = $validityPeriod;
        }
        if ($this->validityPeriod) {
            $ticketRec['_ticketValidTill'] = time() + $this->validityPeriod;
        } else {
            $ticketRec['_ticketValidTill'] = false;
        }

        if ($givenHash) {
            $ticketHash = $givenHash;
        } else {
            $ticketHash = $this->createHash();
        }

        $this->ds->writeRecord($ticketHash, $ticketRec);

        return $ticketHash;
    } // _createTicket




    public function findTicket($value, $key = false)
    {
        // finds a ticket that matches the given hash
        // if $key is provided, it finds a ticket that contains given data (i,e, key and value match)
        if ($value === null) {
            $pathToPage = @$GLOBALS['globalParams']['pathToPage'];
            $type = $key? $key : $this->defaultType;
            return getStaticVariable( "$pathToPage.tickets.$type" );
        }
        if ($key !== false) {
            return $this->ds->findRecByContent($key, $value, true);
        } else {
            return $this->ds->readRecord($value); // $value assumed to be the hash
        }
    } // findTicket




    public function updateTicket($ticketHash, $data = null, $overwrite = false)
    {
        $ticketRec = $this->ds->readRecord($ticketHash);
        if ($data === null) {
            $data = $ticketRec;
        } elseif (!$overwrite && $ticketRec) {
            $data = array_merge($ticketRec, $data);
        }

        // update _ticketValidTill:
        if ($this->validityPeriod) {
            $data['_ticketValidTill'] = time() + $this->validityPeriod;
        } else {
            $data['_ticketValidTill'] = false;
        }
        $this->ds->writeRecord($ticketHash, $data);
    } // updateTicket




    public function consumeTicket($ticketHash, $type = false)
    {
        $ticketHash = preg_replace('/:.*/', '', $ticketHash);
        $ticketRec = $this->ds->readRecord($ticketHash);

        if (!$ticketRec) {
            $this->lastError = 'code not recognized';
            return false;
        }

        if (is_string($type) && ($type !== $ticketRec['_ticketType'])) {
            $ticketRec = false;
            $this->lastError = 'ticket was of wrong type';

        } elseif (isset($ticketRec['_ticketValidTill']) && ($ticketRec['_ticketValidTill'] !== false) && ($ticketRec['_ticketValidTill'] < time())) {      // ticket expired
            $this->ds->deleteRecord($ticketHash);
            $ticketRec = false;
            $this->lastError = 'code timed out';

        } elseif (@$ticketRec['_maxConsumptionCount'] !== false) {
            $n = $ticketRec['_maxConsumptionCount'];
            if ($n > 1) {
                $ticketRec['_maxConsumptionCount'] = $n - 1;
                $this->ds->writeRecord($ticketHash, $ticketRec);
            } else {
                $this->ds->deleteRecord($ticketHash);
            }

            $_ticketType = $ticketRec['_ticketType'];

            unset($ticketRec['_maxConsumptionCount']);  // don't return private properties
            unset($ticketRec['_ticketValidTill']);

            if ($_ticketType === 'sessionVar') {     // type 'sessionVar': make ticket available in session variable
                $_SESSION['lizzy']['ticket'] = $ticketRec;
            }
        }
        if (isset( $GLOBALS['globalParams']['isBackend'])) {
            $GLOBALS['globalParams']['pageFolder'] = $ticketRec['_currPage'];
            $GLOBALS['globalParams']['dataPath'] = $ticketRec['_dataPath'];

        }
        unset($ticketRec['_currPage']);
        unset($ticketRec['_dataPath']);
        return $ticketRec;
    } // consumeTicket



    public function deleteTicket( $ticketHash )
    {
        @$this->ds->deleteRecord($ticketHash);
    }



    public function ticketExists( $ticketHash )
    {
        $ticketRec = $this->ds->readRecord($ticketHash);
        return (bool) $ticketRec;
    } // ticketExists



    public function previewTicket( $ticketHash, $wantedType = false )
    {
        $ticketRec = $this->ds->readRecord($ticketHash);
        if ($wantedType) {
            $ticketType = @$ticketRec['_ticketType'];
            if ($wantedType !== $ticketType) {
                return false;
            }
        }
        return $ticketRec;
    } // previewTicket



    public function getLastError()
    {
        return $this->lastError;
    }


    public function createHash( $checkExistingOfType = false)
    {
        // supply type in $checkExistingOfType if desired
        if ($checkExistingOfType) {
            $type = ($checkExistingOfType !== true)? $checkExistingOfType : $this->defaultType;
            $pathToPage = @$GLOBALS['globalParams']['pathToPage'];
            $ticketHash = getStaticVariable( "$pathToPage.tickets.$type" );
            if ($ticketHash && $this->ticketExists( $ticketHash )) {
                return $ticketHash;
            }
        }
        return createHash( $this->hashSize, $this->unambiguous );
    } // createHash



    private function purgeExpiredTickets()
    {
        if (!$this->ds->lockDB()) {
            $this->lastError = 'Tickets DB locked!';
            return false;
        }

        $tickets = $this->ds->read();
        $now = time();
        $ticketsToPurge = false;
        if ($tickets) {
            foreach ($tickets as $key => $ticket) {
                if (!isset($ticket['_ticketValidTill']) ||
                    ($ticket['_ticketValidTill'] !== false) && ($ticket['_ticketValidTill'] < $now)) {    // has expired
                    unset($tickets[$key]);
                    $ticketsToPurge = true;
                }
            }
            if ($ticketsToPurge) {
                $this->ds->write($tickets);
            }
        }
        $this->ds->unlockDB();
        return true;
    } // purgeExpiredTickets



    public function count()
    {
        $data = $this->ds->read();
        $count = 0;
        foreach ($data as $key => $rec) {
            if (@$rec['_ticketType'] === $this->defaultType) {
                $count++;
            }
        }
        return $count;
    } // count



    public function sum( $fieldName = false, $ignoreKey = false )
    {
        $sum = 0;
        $data = $this->ds->read();
        if ($data) {
            foreach ($data as $key => $rec) {
                // skip tickets of other type:
                if ($this->defaultType !== @$rec['_ticketType']) {
                    continue;
                }
                // skip user's own tickets:
                if ($ignoreKey && (strpos($ignoreKey, $key) !== false)) {
                    continue;
                }
                if (isset($rec[$fieldName])) {
                    $sum += intval($rec[$fieldName]);
                }
            }
        }
        return $sum;
    } // sum

} // Ticketing