<?php

$GLOBALS['liveDataInx'] = 1;

class LiveData
{
    public function __construct($lzy, $inx = false, $args = false)
    {
        $this->lzy = $lzy;
        if ($inx !== false) {
            $this->inx = $inx;
            $GLOBALS['liveDataInx'] = $inx;
        } else {
            $this->inx = $GLOBALS['liveDataInx']++;
        }
        $this->inx = ($inx !== false) ? $inx: $GLOBALS['liveDataInx']++;
        $this->init($args);
    }



    public function render()
    {
        $elementName = $this->elementName;
        $tickRec = [];
        $value = '';
        if (strpos($elementName, '|') === false) {
            $this->addTicketRec($this->id, $elementName, $tickRec);
            $value = $this->db->readElement($elementName);

        } else {
            $elementNames = preg_split('/\s*\|\s*/', $elementName);
            $n = sizeof($elementNames);
            $id = $this->id;
            if ($id && strpos($id, '|') !== false) {
                $ids = preg_split('/\s*\|\s*/', $id);
                for ($i=sizeof($ids); $i<$n; $i++) {
                    $ids[$i] = "lzy-live-data-$i";
                }
            }
            foreach ($elementNames as $i => $elementName) {
                $id = isset($ids[$i])? $ids[$i]: $id;
                $this->addTicketRec($id, $elementName, $tickRec);
                if ($i === 0) {
                    $value = $this->db->readElement($elementName);
                }
            }
        }

        $ticket = $this->createOrUpdateTicket($tickRec);

        $dynamicArg = '';
        if ($this->dynamicArg) {
            $dynamicArg = " data-live-data-param='$this->dynamicArg'";
        }

        $polltime = '';
        if ($this->polltime !== false) {
            $polltime = " data-live-data-polltime='$this->polltime'";
        }

        if (!$this->manual) {
            $str = <<<EOT
<span id='$this->id' class='lzy-live-data' data-live-data-ref="$ticket"$polltime$dynamicArg>$value</span>
EOT;
        } else {
            $str = <<<EOT
<span class='lzy-live-data disp-no' data-live-data-ref="$ticket"$polltime$dynamicArg><!-- live-data manual mode --></span>
EOT;
        }
        return $str;
    } // render



    private function init($args)
    {
        if (!isset($args['file'])) {
            exit("Error: argument 'file' missing in call to LiveData");
        }
        $this->file = makePathRelativeToPage($args['file'], true);
        $this->elementName = (isset($args['elementName'])) ? $args['elementName'] : false;
        $this->dynamicArg = (isset($args['dynamicArg'])) ? $args['dynamicArg'] : false;
        $this->id = (isset($args['id'])) ? $args['id'] : false;
        $this->polltime = (isset($args['polltime'])) ? $args['polltime'] : false;
        $this->mode = (isset($args['mode'])) ? $args['mode'] : false;
        $this->manual = (strpos($this->mode, 'manual') !== false);

        if ($this->manual) {
            if (($this->elementName === false) || ($this->elementName === '')) {
                exit( "Error: argument ``elementName`` not specified.");
            }
        }

        $this->db = new DataStorage2([ 'dataFile' => $this->file ]);
    }



    private function addTicketRec($id, $elementName, &$tickRec)
    {
        if (!$id) {
            $id = preg_replace('/\{.*\},/', '', $elementName);
            $id = 'liv-'.strtolower(str_replace(' ', '-', $id));
            if ($tickRec) {
                $existingIds = array_map(function ($e) { return $e['id']; }, $tickRec);
                if (in_array($id, $existingIds)) {
                    $id .= "-$this->inx";
                }
            }
        }
        $tickRec[] = [
            'file' => $this->file,
            'elementName' => $elementName,
            'id' => $id,
        ];
    } // addTicketRec



    private function createOrUpdateTicket(array $tickRec)
    {
        $tick = new Ticketing();
        if (isset($_SESSION['lizzy']['liveDataTicket'])) {
            $ticket = $_SESSION['lizzy']['liveDataTicket'];
            $res = $tick->findTicket($ticket);
            if ($res) {     // yes, ticket found
                if (!isset($res[$this->inx - 1])) {
                    $tick->updateTicket($ticket, $tickRec);
                }
            } else {    // it was some stray ticket
                $ticket = $tick->createTicket($tickRec, 99, 86400);
                $_SESSION['lizzy']['liveDataTicket'] = $ticket;
            }
        } else {
            $ticket = $tick->createTicket($tickRec, 99, 86400);
            $_SESSION['lizzy']['liveDataTicket'] = $ticket;
        }
        return $ticket;
    } // createOrUpdateTicket
}

