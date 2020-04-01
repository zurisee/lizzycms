<?php

$page->addModules('~sys/js/live_data.js');

$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

	// typical variables that might be useful:
	$inx = $this->invocationCounter[$macroName] + 1;

	// how to get access to macro arguments:
    $file = $this->getArg($macroName, 'file', 'Defines the data-source from which to retrieve the data element', '');
    $elementName = $this->getArg($macroName, 'elementName', 'Name of the element to be visualized', false);
    $id = $this->getArg($macroName, 'id', '(optional) Id of DOM element.', "lzy-live-data$inx");
    $polltime = $this->getArg($macroName, 'polltime', '(optional) Polling time, i.e. the time server waits for new data before giving up.', false);

    if ($file === 'help') {
        return '';
    }

    if (!$file) {
        $this->compileMd = true;
        return "**Error**: argument ``file`` not specified.";
    }
    if (($elementName === false) || ($elementName === '')) {
        $this->compileMd = true;
        return "**Error**: argument ``elementName`` not specified.";
    }

    $file = makePathRelativeToPage($file, true);
    $db = new DataStorage2([ 'dataFile' => $file ]);
    $value = $db->readElement( $elementName );

    $tickRec[$inx - 1] = [
        'file' => $file,
        'elementName' => $elementName,
        'id' => $id,
    ];

    $tick = new Ticketing();
    if (isset($_SESSION['lizzy']['liveDataTicket'])) {
        $ticket = $_SESSION['lizzy']['liveDataTicket'];
        $res = $tick->findTicket($ticket);
        if ($res) {     // yes, ticket found
            if (!isset($res[$inx - 1])) {
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

    if ($polltime !== false) {
        $polltime = " data-live-data-polltime='$polltime'";
    }

    $this->optionAddNoComment = true;
    $str = <<<EOT
<span id='$id' class='lzy-live-data' data-live-data-ref="$ticket"$polltime>$value</span>
EOT;

	return $str;
});
