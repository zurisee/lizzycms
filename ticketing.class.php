<?php

define ('DEFAULT_TICKET_STORAGE_FILE', DATA_PATH.'_tickets.yaml');
//define ('DEFAULT_TICKET_STORAGE_FILE', DATA_PATH.'_tickets.'.LZY_DEFAULT_FILE_TYPE);
define ('DEFAULT_TICKET_HASH_SIZE', 6);
define ('DEFAULT_TICKET_VALIDITY_TIME', 900);

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

        $this->ds->writeElement($ticketHash, $ticketRec);

        return $ticketHash;
    } // createTicket




    public function findTicket($value, $key = false)
    {
        // finds a ticket that matches the given hash
        // if $key is provided, it finds a ticket that contains given data (i,e, key and value match)
        if ($key !== false) {
            return $this->ds->findRecByContent($key, $value, true);
        } else {
            return $this->ds->readElement($value); // $value assumed to be the hash
        }
    } // findTicket




    public function updateTicket($ticketHash, $data, $overwrite = false)
    {
        $ticketRec = $this->ds->readElement($ticketHash);
        if (!$overwrite && $ticketRec) {
            $data = array_merge($ticketRec, $data);
        }
        $this->ds->writeElement($ticketHash, $data);
    } // updateTicket




    public function consumeTicket($ticketHash, $type = false)
    {
        $ticketRec = $this->ds->readElement($ticketHash);

        if (!$ticketRec) {
            $this->lastError = 'code not recognized';
            return false;
        }

        if ($type && ($type !== $ticketRec['lzy_ticketType'])) {
            $ticketRec = false;
            $this->lastError = 'ticket was of wrong type';

        } elseif (isset($ticketRec['lzy_ticketValidTill']) && ($ticketRec['lzy_ticketValidTill'] < time())) {      // ticket expired
            $this->ds->delete($ticketHash);
            $ticketRec = false;
            $this->lastError = 'code timed out';

        } elseif (isset($ticketRec['lzy_maxConsumptionCount'])) {
            $n = $ticketRec['lzy_maxConsumptionCount'];
            if ($n > 1) {
                $ticketRec['lzy_maxConsumptionCount'] = $n - 1;
                $this->ds->writeElement($ticketHash, $ticketRec);
            } else {
                $this->ds->delete($ticketHash);
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



    public function getLastError()
    {
        return $this->lastError;
    }


    private function createHash()
    {
        do {
            $hash = chr(random_int(65, 90));  // first always a letter
            $hash .= strtoupper(substr(sha1(random_int(0, PHP_INT_MAX)), 0, $this->hashSize - 1));  // letters and digits
        } while ($this->unambiguous && strpbrk($hash, '0O'));
        return $hash;
    } // createHash



    private function purgeExpiredTickets()
    {
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
    } // purgeExpiredTickets

} // Ticketing