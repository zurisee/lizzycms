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
        $value = $this->db->readElement( $this->elementName );

        $tickRec[$this->inx - 1] = [
            'file' => $this->file,
            'elementName' => $this->elementName,
            'id' => $this->id,
        ];

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

        if ($this->polltime !== false) {
            $this->polltime = " data-live-data-polltime='$this->polltime'";
        }

        $this->optionAddNoComment = true;
        $str = <<<EOT
<span id='$this->id' class='lzy-live-data' data-live-data-ref="$ticket"$this->polltime>$value</span>
EOT;
        return $str;
    }



    private function init($args)
    {
        if (!isset($args['file'])) {
            exit("Error: argument 'file' missing in call to LiveData");
        }
        $this->file = makePathRelativeToPage($args['file'], true);

        $this->elementName = (isset($args['elementName'])) ? $args['elementName'] : false;
        $this->id = (isset($args['id'])) ? $args['id'] : false;
        $this->polltime = (isset($args['polltime'])) ? $args['polltime'] : false;
        $this->db = new DataStorage2([ 'dataFile' => $this->file ]);
    }
}

