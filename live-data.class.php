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
        $dataSelectors = $dataSelectors = explodeTrim('|', $dataSelector);
        $tickRec = [];
        $values = [];
        $targetSelectors = [];

        // dataSelector can be scalar or array:
        if (sizeof($dataSelectors) === 1) {                  // scalar value:
            $this->addTicketRec($this->targetSelector, $dataSelector, $this->polltime, $tickRec);
            $values[] = $this->db->readElement($dataSelector);

        } else {                                            // array value:
            $n = sizeof($dataSelectors);
            $targetSelector = $this->targetSelector;
            $targetSelectors = explodeTrim('|', $targetSelector);
            $nT = sizeof($targetSelectors);
            for ($i=$nT; $i<$n; $i++) {
                $targetSelectors[$i] = "lzy-live-data-$i";
            }
            foreach ($dataSelectors as $i => $dataSelector) {
                $targetSelector = isset($targetSelectors[$i])? $targetSelectors[$i]: $targetSelector;
                $this->addTicketRec($targetSelector, $dataSelector, $this->polltime, $tickRec);
                $values[] = $this->db->readElement($dataSelector);
            }
            $this->inx += sizeof($dataSelectors) - 1;
        }

        $this->dataSelectors = $dataSelectors;
        $this->targetSelectors = $targetSelectors;

        $ticket = $this->createOrUpdateTicket($tickRec);

        $str = $this->renderHTML($ticket, $values);
        return $str;
    } // render





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

        $this->polltime = (isset($args['pollingTime'])) ? $args['pollingTime'] : DEFAULT_POLLING_TIME;

        $this->mode = (isset($args['mode'])) ? $args['mode'] : false;
        $this->manual = (strpos($this->mode, 'manual') !== false);
        $this->callback = (isset($args['callback'])) ? $args['callback'] : false;

        if ($this->manual) {
            if (($this->dataSelector === false) || ($this->dataSelector === '')) {
                exit( "Error: argument ``dataSelector`` not specified.");
            }
        }

        $this->db = new DataStorage2([ 'dataFile' => $this->dataSource ]);

        $_SESSION['lizzy']['hasLockedElements'] = false;
    } // init



    private function addTicketRec($targetSelector, $dataSelector, $pollTime, &$tickRec)
    {
        $targetSelector = $this->deriveTargetSelector($targetSelector, $dataSelector, $tickRec);
        $this->targetSelector = $targetSelector;
        $tickRec[] = [
            'dataSource' => $this->dataSource,
            'dataSelector' => $dataSelector,
            'targetSelector' => $targetSelector,
            'pollingTime' => intval($pollTime),
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



    private function deriveTargetSelector($targetSelector, $dataSelector, $tickRec)
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
        $c0 = $targetSelector[0];
        if (($c0 !== '#') && ($c0 !== '.')) {
            $targetSelector = "#$targetSelector";
        }
        return $targetSelector;
    } // deriveTargetSelector




    private function renderHTML($ticket, $values)
    {
        $str = '';
        $dynamicArg = '';
        if ($this->dynamicArg) {
            $dynamicArg = " data-live-data-param='$this->dynamicArg'";
        }

        $callback = '';
        if ($this->callback) {
            $callback = " data-live-callback='$this->callback'";
        }

        // normally, this macro renders visible output directly.
        // 'mode: manual' overrides this -> just renders infrastructure, you place the visible code manually into your page
        // e.g. <span id="my-id""></span>
        if ($this->manual) {
            $str = <<<EOT
<span class='lzy-live-data disp-no' data-live-data-ref="$ticket"$dynamicArg$callback><!-- live-data manual mode --></span>
EOT;

        } else {
            foreach ($this->targetSelectors as $i => $targetSelector) {
                $selector = substr($targetSelector, 1);
                if ($targetSelector[0] === '#') {
                    $selector = "id='$selector' class='lzy-live-data'";
                } elseif ($targetSelector[0] === '.') {
                    $selector = "class='lzy-live-data $selector'";
                } else {
                    $selector = "id='$targetSelector' class='lzy-live-data'";
                }
                $str .= <<<EOT
<span $selector data-live-data-ref="$ticket"$dynamicArg$callback>{$values[$i]}</span>

EOT;
            }
        }
        return $str;
    }

} // class

