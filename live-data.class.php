<?php

$GLOBALS['liveDataInx'][ $GLOBALS["globalParams"]["pagePath"] ] = 0;

class LiveData
{
    public function __construct($lzy, $inx = false, $args = false)
    {
        $this->lzy = $lzy;
        $this->inx = &$GLOBALS['liveDataInx'][ $GLOBALS["globalParams"]["pagePath"] ];
        $this->inx++;
        $this->init($args);
    } // __construct



    public function render()
    {
        $elementName = $this->elementName;
        $elementNames = explodeTrim('|', $elementName);
        $tickRec = [];
        $value = '';

        // elementName can be scalar or array:
        if (sizeof($elementNames) === 1) {                  // scalar value:
            $this->addTicketRec($this->id, $elementName, $tickRec);
            $value = $this->db->readElement($elementName);

        } else {                                            // array value:
            $n = sizeof($elementNames);
            $id = $this->id;
            $ids = explodeTrim('|', $id);
            if (sizeof($ids) > 1) {
                for ($i=sizeof($ids); $i<$n; $i++) {
                    $ids[$i] = "lzy-live-data-$i";
                }
            }
            foreach ($elementNames as $i => $elementName) {
                $id = isset($ids[$i])? $ids[$i]: $id;
                $this->addTicketRec($id, $elementName, $tickRec);
                if ($i === 0) {     // only render first value, further values must be manually inserted into page:
                    $value = $this->db->readElement($elementName);
                }
            }
            $this->inx += sizeof($elementNames) - 1;
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

        // normally, this macro renders visible output directly.
        // 'mode: manual' overrides this -> just renders infrastructure, you place the visible code manually into your page
        // e.g. <span id="my-id""></span>
        if ($this->manual) {
            $str = <<<EOT
<span class='lzy-live-data disp-no' data-live-data-ref="$ticket"$polltime$dynamicArg><!-- live-data manual mode --></span>
EOT;

        } else {
            $str = <<<EOT
<span id='$this->id' class='lzy-live-data' data-live-data-ref="$ticket"$polltime$dynamicArg>$value</span>
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
    } // init



    private function addTicketRec($id, $elementName, &$tickRec)
    {
        $id = $this->deriveId($id, $elementName, $tickRec);
        $this->id = $id;
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



    private function deriveId($id, $elementName, $tickRec)
    {
        if (!$id) {
            $id = preg_replace('/\{.*\},/', '', $elementName);
            $id = str_replace([',','][', '[', ']'], ['-','-','',''], $id);
            $id = 'liv-'.strtolower(str_replace(' ', '-', $id));
            if ($tickRec) {
                $existingIds = array_map(function ($e) { return $e['id']; }, $tickRec);
                if (in_array($id, $existingIds)) {
                    $id .= "-$this->inx";
                }
            }
        }
        return $id;
    } // deriveId

} // class

