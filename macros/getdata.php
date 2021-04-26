<?php

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $dataSrc = $this->getArg($macroName, 'dataSrc', 'Filename of data source.', '');
    $dataSelector = $this->getArg($macroName, 'dataSelector', 'Selector for data element(s), e.g. "0,name" or "*,name"', '');
    $id = $this->getArg($macroName, 'id', '(optional) ID applied to rendered data element.', '');
    $class = $this->getArg($macroName, 'class', '(optional) Class applied to rendered data element.', '');
    $elemTag = $this->getArg($macroName, 'elemTag', '(optional) HTML-tag applied to rendered data element.', null);
    $wrapperTag = $this->getArg($macroName, 'wrapperTag', '(optional) HTML-tag used to wrap rendered data.', 'div');
    $wrapperClass = $this->getArg($macroName, 'wrapperClass', '(optional) Class applied to element wrapping rendered data.', '');
    $noDataResponse = $this->getArg($macroName, 'noDataResponse', '(optional) Response if no data was found.', "<div class='lzy-get-data-none-found'>{{ lzy-get-data-none-found }}</div>\n");
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    if (!isset($GLOBALS['globalParams']['get-data'][ $GLOBALS['globalParams']['pagePath'] ])) {
        $GLOBALS['globalParams']['get-data'][ $GLOBALS['globalParams']['pagePath']] = 0;
    }
    $inx = $GLOBALS['globalParams']['get-data'][$GLOBALS['globalParams']['pagePath']];

    if ($dataSrc === 'help') {
        return;
    }

    $db = new DataStorage2($dataSrc);

    $value = null;
    if ($dataSelector === 'next') {
        $n = $db->getNoOfRecords();
        if ($inx < $n - 1) {
            $inx = $inx + 1;
            $GLOBALS['globalParams']['get-data'][$GLOBALS['globalParams']['pagePath']]++;
        }
        $value = $db->readElement($inx);

    } elseif ($dataSelector === 'prev') {
        if ($inx > 0) {
            $inx = $inx - 1;
            $GLOBALS['globalParams']['get-data'][$GLOBALS['globalParams']['pagePath']]--;
        }
        $value = $db->readElement($inx);

    } elseif (($dataSelector === 'this') || !$dataSelector) {
        $value = $db->readElement($inx);
        if (!$dataSelector) {
            $GLOBALS['globalParams']['get-data'][$GLOBALS['globalParams']['pagePath']]++;
        }

    } else {
        $value = $db->readElement($dataSelector);
    }

    if ($value === null) {
        return $noDataResponse;
    }


    if ($class) {
        $class = " class='$class'";
    }

    if ($elemTag === null) {
        $elemTag = 'span';
    }

    if (stripos('ul,ol', $wrapperTag) !== false) {
        if ($elemTag === 'span') {
            $elemTag = 'li';
        }
    } elseif (($elemTag === 'li') && ($wrapperTag === 'div')) {
        $wrapperTag = 'ul';
    }

    $str = '';
    if (is_string($value)) {
        if ($id) {
            $id = " id='$id'";
        }
        if (is_array($value)) {
            $value = '<span class="lzy-array-elem">' . implode('</span><span class="lzy-array-elem">', $value) . '</span>';
        }
        if ("$elemTag$id$class") {
            $str = "<$elemTag$id$class>$value</$elemTag>";
        } else {
            $str = "$value\n";
        }
    } else {
        foreach ($value as $i => $item) {
            if ($id) {
                if (is_int($i)) {
                    $id1 = " id='$id" . ($i + 1) . "'";
                } else {
                    $id1 = " id='$id$i'";
                }
            }
            if (is_array($item)) {
                $item = '<span class="lzy-array-elem">' . implode('</span><span class="lzy-array-elem">', $item) . '</span>';
            }
            if ("$elemTag$id$class") {
                $str .= "<$elemTag$id1$class>$item</$elemTag>\n";
            } else {
                $str .= "$item\n";
            }
        }
    }

    if ($wrapperTag || $wrapperClass) {
        $str = "<$wrapperTag class='$wrapperClass'>\n$str\n</$wrapperTag>\n";
    }

    $this->optionAddNoComment = true;
	return $str;
});


