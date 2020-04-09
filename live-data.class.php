<?php

define('DEFAULT_POLLING_TIME', 60);

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
        $dataSelector = $this->dataSelector;
        $dataSelectors = explodeTrim('|', $dataSelector);
        $tickRec = [];
        $value = '';

        // dataSelector can be scalar or array:
        if (sizeof($dataSelectors) === 1) {                  // scalar value:
            $this->addTicketRec($this->targetSelector, $dataSelector, $this->polltime, $tickRec);
            $value = $this->db->readElement($dataSelector);

        } else {                                            // array value:
            $n = sizeof($dataSelectors);
            $targetSelector = $this->targetSelector;
            $targetSelectors = explodeTrim('|', $targetSelector);
            if (sizeof($targetSelectors) > 1) {
                for ($i=sizeof($targetSelectors); $i<$n; $i++) {
                    $targetSelectors[$i] = "lzy-live-data-$i";
                }
            }
            foreach ($dataSelectors as $i => $dataSelector) {
                $targetSelector = isset($targetSelectors[$i])? $targetSelectors[$i]: $targetSelector;
                $this->addTicketRec($targetSelector, $dataSelector, $this->polltime, $tickRec);
                if ($i === 0) {     // only render first value, further values must be manually inserted into page:
                    $value = $this->db->readElement($dataSelector);
                }
            }
            $this->inx += sizeof($dataSelectors) - 1;
        }

        $ticket = $this->createOrUpdateTicket($tickRec);


//        $polltime = '';
//        if ($this->polltime !== false) {
//            $polltime = " data-live-data-polltime='$this->polltime'";
//        }
        $str = $this->renderHTML($ticket, $value);
        return $str;
    } // render

/*
'dataSource'
'dataSrc'
'file'
'dataSelector'
'dataSel'
'dynamicArg'
'targetSelector'
'targetSel'
'polltime'
'mode'

 */

    private function init($args)
    {
        if (isset($args['dataSource'])) {
            $this->dataSource = $args['dataSource'];
        } elseif (isset($args['dataSrc'])) {
            $this->dataSource = $args['dataSrc'];
        } else {
            $this->dataSource = (isset($args['file'])) ? $args['file'] : false;
        }

        if (!$this->dataSource) {
            exit("Error: argument 'dataSource' missing in call to LiveData");
        }
        $this->dataSource = makePathRelativeToPage($this->dataSource, true);

        if (isset($args['dataSelector'])) {
            $this->dataSelector = $args['dataSelector'];
        } else {
            $this->dataSelector = (isset($args['dataSel'])) ? $args['dataSel'] : false;
        }

        $this->dynamicArg = (isset($args['dynamicArg'])) ? $args['dynamicArg'] : false;

        if (isset($args['targetSelector'])) {
            $this->targetSelector = $args['targetSelector'];
        } else {
            $this->targetSelector = (isset($args['targetSel'])) ? $args['targetSel'] : false;
        }

        $this->polltime = (isset($args['polltime'])) ? $args['polltime'] : DEFAULT_POLLING_TIME;

        $this->mode = (isset($args['mode'])) ? $args['mode'] : false;
        $this->manual = (strpos($this->mode, 'manual') !== false);

        if ($this->manual) {
            if (($this->dataSelector === false) || ($this->dataSelector === '')) {
                exit( "Error: argument ``dataSelector`` not specified.");
            }
        }

        $this->db = new DataStorage2([ 'dataFile' => $this->dataSource ]);
    } // init



    private function addTicketRec($targetSelector, $dataSelector, $pollTime, &$tickRec)
    {
        $targetSelector = $this->deriveId($targetSelector, $dataSelector, $tickRec);
        $this->targetSelector = $targetSelector;
        $tickRec[] = [
            'dataSource' => $this->dataSource,
            'dataSelector' => $dataSelector,
            'targetSelector' => $targetSelector,
            'pollingTime' => $pollTime,
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



    private function deriveId($targetSelector, $dataSelector, $tickRec)
    {
        if (!$targetSelector) {
            $targetSelector = preg_replace('/\{(.*?)\},/', "$1", $dataSelector);
            $targetSelector = str_replace([',','][', '[', ']'], ['-','-','',''], $targetSelector);
            $targetSelector = '#liv-'.strtolower(str_replace(' ', '-', $targetSelector));
            if ($tickRec) {
                $existingIds = array_map(function ($e) { return $e['targetSelector']; }, $tickRec);
                if (in_array($targetSelector, $existingIds)) {
                    $targetSelector .= "-$this->inx";
                }
            }
        }
        return $targetSelector;
    } // deriveId

    private function renderHTML($ticket, $value)
    {
        $dynamicArg = '';
        if ($this->dynamicArg) {
            $dynamicArg = " data-live-data-param='$this->dynamicArg'";
        }

// normally, this macro renders visible output directly.
        // 'mode: manual' overrides this -> just renders infrastructure, you place the visible code manually into your page
        // e.g. <span id="my-id""></span>
        if ($this->manual) {
            $str = <<<EOT
<span class='lzy-live-data disp-no' data-live-data-ref="$ticket"$dynamicArg><!-- live-data manual mode --></span>
EOT;

        } else {
            $selector = substr($this->targetSelector, 1);
            if ($this->targetSelector[0] === '#') {
                $selector = "id='$selector' class='lzy-live-data'";
            } elseif ($this->targetSelector[0] === '.') {
                $selector = "class='lzy-live-data $selector'";
            } else {
                $selector = "id='$this->selector' class='lzy-live-data'";
            }
            $str = <<<EOT
<span $selector data-live-data-ref="$ticket"$dynamicArg>$value</span>
EOT;
        }
        return $str;
    }

} // class

