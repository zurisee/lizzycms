<?php
// @info: From a selection of files determines the last modified and returns the timestamp

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $files = $this->getArg($macroName, 'files', 'Files that will be checked to determine the newest. Use "glob syntax", e.g. "&#126;/data/*". Separate multiple elements by comma.', '~page/*');
    $format = $this->getArg($macroName, 'format', 'Format in which the output will be rendered (see http://php.net/manual/en/function.date.php)', 'Y-m-d');
    $recursive = $this->getArg($macroName, 'recursive', 'If true, files in sub-folders will be included.)', false);
    $exclude = $this->getArg($macroName, 'exclude', 'Regex-pattern of elements to be excluded.', false);
    $file = $this->getArg($macroName, 'file', 'Synonym for "files"', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($files === 'help') {
        return;
    }

    if ($file) {
        $files = $file;
    }
    if (preg_match('/^\[(.*)\]$/', $files, $m)) {
        $files = $m[1];
    }
    $filePaths = explode(',', $files);

    $newest = 0;
    foreach ($filePaths as $path) {
        $path = makePathRelativeToPage($path);
        $newest = max( lastModified($path, $recursive, $exclude), $newest);
    }
    if ($newest === 0) {
        $filedate = '{{ unknown }}';
    } else {
        $filedate = date($format, $newest);
    }
    $this->optionAddNoComment = true;
	return $filedate;
});


