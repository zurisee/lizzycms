<?php

define ('DEFAULT_TICKET_STORAGE_FILE', CACHE_PATH.'_tickets.yaml');
//define ('DEFAULT_TICKET_STORAGE_FILE', DATA_PATH.'_tickets.yaml');
define ('DEFAULT_TICKET_HASH_SIZE', 6);
define ('DEFAULT_TICKET_VALIDITY_TIME', 900); // 15 min
define ('UNAMBIGUOUS_CHARACTERS', '3479ACDEFHJKLMNPQRTUVWXY'); // -> excludes '0O2Z1I5S6G8B'

/*
 * Purpose:
 *      like a locker: put some data into a storage and get a ticket in return
 *      use the ticket to retrieve the original data
 *      the ticket is a hash code that cannot be predicted
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
        $this->defaultMaxConsumptionCount = isset($options['defaultMaxConsumptionCount']) ? $options['defaultMaxConsumptionCount'] : 1;
        $this->ds = new DataStorage2($dataSrc);
        $this->purgeExpiredTickets();
    } // __construct




    public function createTicket($rec, $maxConsumptionCount = false, $validityPeriod = null, $type = false)
    {
        $type = $type? $type : $this->defaultType;
        $pathToPage = @$GLOBALS['globalParams']['pathToPage'];

        $ticket = getStaticVariable( "$pathToPage.tickets.$type" );
        if ($ticket !== null) {
            if (!$this->findTicket($ticket)) {
                $ticket = false;
            }
        }
        if (!$ticket) {
            $ticket = $this->_createTicket($rec, $maxConsumptionCount, $validityPeriod, $type);
            setStaticVariable("$pathToPage.tickets.$type", $ticket);
        }
        return $ticket;
    } // createTicket




    private function _createTicket($rec, $maxConsumptionCount = false, $validityPeriod = null, $type = false)
    {
        $ticketRec = $rec;
        $ticketRec['lzy_maxConsumptionCount'] = ($maxConsumptionCount !== false) ?$maxConsumptionCount : $this->defaultMaxConsumptionCount;
        $ticketRec['lzy_ticketType'] = $type ? $type : $this->defaultType;

        if ($validityPeriod === null) {
            $ticketRec['lzy_ticketValidTill'] = time() + $this->defaultValidityPeriod;

        } elseif (($validityPeriod === false) || ($validityPeriod <= 0)) {
            $ticketRec['lzy_ticketValidTill'] = PHP_INT_MAX;

        } elseif (is_string($validityPeriod)) {
            $ticketRec['lzy_ticketValidTill'] = strtotime( $validityPeriod );
            mylog('ticket till: '.date('Y-m-d', $ticketRec['lzy_ticketValidTill']));

        } else {
            $ticketRec['lzy_ticketValidTill'] = time() + $validityPeriod;
        }
        $ticketHash = $this->createHash();

        $this->ds->writeRecord($ticketHash, $ticketRec);

        return $ticketHash;
    } // createTicket




    public function findTicket($value, $key = false)
    {
        // finds a ticket that matches the given hash
        // if $key is provided, it finds a ticket that contains given data (i,e, key and value match)
        if ($key !== false) {
            return $this->ds->findRecByContent($key, $value, true);
        } else {
            return $this->ds->readRecord($value); // $value assumed to be the hash
        }
    } // findTicket




    public function updateTicket($ticketHash, $data, $overwrite = false)
    {
        $ticketRec = $this->ds->readRecord($ticketHash);
        if (!$overwrite && $ticketRec) {
            $data = array_merge($ticketRec, $data);
        }
        $this->ds->writeRecord($ticketHash, $data);
    } // updateTicket




    public function consumeTicket($ticketHash, $type = false)
    {
        $ticketRec = $this->ds->readRecord($ticketHash);

        if (!$ticketRec) {
            $this->lastError = 'code not recognized';
            return false;
        }

        if ($type && ($type !== $ticketRec['lzy_ticketType'])) {
            $ticketRec = false;
            $this->lastError = 'ticket was of wrong type';

        } elseif (isset($ticketRec['lzy_ticketValidTill']) && ($ticketRec['lzy_ticketValidTill'] < time())) {      // ticket expired
            $this->ds->deleteRecord($ticketHash);
            $ticketRec = false;
            $this->lastError = 'code timed out';

        } elseif (isset($ticketRec['lzy_maxConsumptionCount'])) {
            $n = $ticketRec['lzy_maxConsumptionCount'];
            if ($n > 1) {
                $ticketRec['lzy_maxConsumptionCount'] = $n - 1;
                $this->ds->writeRecord($ticketHash, $ticketRec);
            } else {
                $this->ds->deleteRecord($ticketHash);
            }

            $lzy_ticketType = $ticketRec['lzy_ticketType'];

            unset($ticketRec['lzy_maxConsumptionCount']);  // don't return private properties
            unset($ticketRec['lzy_ticketValidTill']);

            if ($lzy_ticketType === 'sessionVar') {     // type 'sessionVar': make ticket available in session variable
                $_SESSION['lizzy']['ticket'] = $ticketRec;
            }
            unset($ticketRec['lzy_ticketType']);
        }
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
    }



    public function getLastError()
    {
        return $this->lastError;
    }


    public function createHash()
    {
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
                if (isset($ticket['lzy_ticketValidTill']) &&
                    ($ticket['lzy_ticketValidTill'] < $now)) {    // has expired
                    unset($tickets[$key]);
                    $ticketsToPurge = true;
                }
            }
            if ($ticketsToPurge) {
                $this->ds->write($tickets);
            }
        }
        $this->ds->unlockDB();
    } // purgeExpiredTickets



    public function count()
    {
        $data = $this->ds->read();
        $count = 0;
        foreach ($data as $key => $rec) {
            if (@$rec['lzy_ticketType'] === $this->defaultType) {
                $count++;
            }
        }
        return $count;
    } // count



    public function sum( $fieldName = false )
    {
        $sum = 0;
        $data = $this->ds->read();
        if ($data) {
            foreach ($data as $key => $rec) {
                if ($this->defaultType !== @$rec['lzy_ticketType']) {
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