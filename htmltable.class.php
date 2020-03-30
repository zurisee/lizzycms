<?php

$GLOBALS['lzy']['tableCounter'] = 1;


class HtmlTable
{
    private $errMsg = '';

    public function __construct($lzy, $inx, $options)
    {
        global $tableCounter;
        $this->options      = $options;
        $this->lzy 		    = $lzy;
        $this->page 		= $lzy->page;
        $this->tableCounter = &$GLOBALS['lzy']['tableCounter'];
        $this->helpText = false;
        if ($options === 'help') {
            $this->helpText = [];
            $options = [];
        }

        $this->dataSource	        = $this->getOption('dataSource', '(optional if nCols is set) Name of file containing data. Format may be .cvs or .yaml and is expected be local to page folder.');
        $this->id 			        = $this->getOption('id', '(optional) Id applied to the table tag (resp. wrapping div tag if renderAsDiv is set)');
        $this->tableClass 	        = $this->getOption('tableClass', '(optional) Class applied to the table tag (resp. wrapping div tag if renderAsDiv is set)');
        $this->cellClass 	        = $this->getOption('cellClass', '(optional) Class applied to each table cell');
        $this->cellIds 	            = $this->getOption('cellIds', '(optional) If true, each cell gets an ID which is derived from the cellClass');
        $this->nRows 		        = $this->getOption('nRows', '(optional) Number of rows: if set the table is forced to this number of rows');
        $this->nCols 		        = $this->getOption('nCols', '(optional) Number of columns: if set the table is forced to this number of columns');
        $this->includeKeys          = $this->getOption('includeKeys', '[true|false] If true and not a .csv source: key elements will be included in data.');
        $this->interactive          = $this->getOption('interactive', '[true|false] If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.');
        $this->paging               = $this->getOption('paging', '[true|false] When using "Datatables": turns paging on or off (default is on)');
        $this->initialPageLength    = $this->getOption('initialPageLength', '[int] When using "Datatables": defines the initial page length (default is 10)');
        $this->excludeColumns       = $this->getOption('excludeColumns', '(optional) Allows to exclude specific columns, e.g. "excludeColumns:2,4-5"');
        $this->sort 		        = $this->getOption('sort', '(optional) Allows to sort the table on a given columns, e.g. "sort:3"');
        $this->sortExcludeHeader    = $this->getOption('sortExcludeHeader', '(optional) Allows to exclude the first row from sorting');
        $this->autoConvertLinks     = $this->getOption('autoConvertLinks', '(optional) If true, all data is scanned for patterns of URL, mail address or telephone numbers. If found the value is wrapped in a &lt;a> tag');
        $this->caption	            = $this->getOption('caption', '(optional) If set, a caption tag is added to the table. The caption text may contain the pattern "##" which will be replaced by a number.');
        $this->captionIndex         = $this->getOption('captionIndex', '(optional) If set, will override the automatically applied table counter');
        $this->headers	            = $this->getOption('headers', '(optional) Column headers may be supplied in the form [A|B|C...]');
        if (!$this->headers) {
            $this->headers          = $this->getOption('headersTop');   // synonyme for 'headers'
        }
        $this->headersLeft          = $this->getOption('headersLeft', '(optional) Row headers may be supplied in the form [A|B|C...]');
        $this->headersAsVars        = $this->getOption('headersAsVars', '(optional) If true, header elements will be rendered as variables (i.e. in curly brackets).');
        $this->showRowNumbers       = $this->getOption('showRowNumbers', '(optional) Adds a left most column showing row numbers.');
        $this->renderAsDiv	        = $this->getOption('renderAsDiv', '(optional) If set, the table is rendered as &lt;div> tags rather than &lt;table>');
        $this->tableDataAttr	    = $this->getOption('tableDataAttr', '(optional) ');
        $this->renderDivRows        = $this->getOption('renderDivRows', '(optional) If set, each row is wrapped in an additional &lt;div> tag. Omitting this may be useful in conjunction with CSS grid.');
        $this->includeCellRefs	    = $this->getOption('includeCellRefs', '(optional) If set, data-source and cell-coordinates are added as \'data-xy\' attributes');
//        $this->cellMask 	        = $this->getOption('cellMask', '(optional) Lets you define regions that a masked and thus will not get the cellClass. Selection code: rY -> row, cX -> column, eX,Y -> cell element.');
//        $this->cellMaskedClass      = $this->getOption('cellMaskedClass', '(optional) Class that will be applied to masked cells');
        $this->cellMask 	        = false;
        $this->cellMaskedClass      = false;
        $this->process	            = $this->getOption('process', '(optional) Provide name of a frontmatter variable to activate this feature. In the frontmatter area define an array containing instructions for manipulating table data. See <a href="https://getlizzy.net/macros/extensions/table/" target="_blank">Doc</a> for further details.');
        $this->processInstructionsFile	= $this->getOption('processInstructionsFile', 'The same as \'process\' except that instructions are retrieved from a .yaml file');
        $this->suppressError        = $this->getOption('suppressError', '(optional) Suppresses the error message in case dataSource is not available.');

        $this->checkArguments($inx);
        
        $this->handleDatatableOption($this->page);
        $tableCounter = $this->handleCaption($tableCounter);
    } // __construct




    public function render( $help = false)
    {
        if ($help) {
            return $this->helpText;
        }
        $this->loadData();

        $this->applyHeaders();

        $this->loadProcessingInstructions();
        $this->applyProcessingToData();

        $this->convertLinks();

        if ($this->renderAsDiv) {
            return $this->renderDiv();
        } else {
            return $this->renderHtmlTable();
        }
    } // render




    private function applyHeaders()
    {
        if (!$this->headers && !$this->headersLeft) {
            return;
        }

        $data = &$this->data;

        if ($this->headers === true) {
            array_unshift($data, $this->headerElems);

        } else
        if (($this->headers) && ($this->headers !== true)) {
            $headers = $this->extractList($this->headers, true);
            $headers = array_pad ( $headers , sizeof($data[0]) , '' );

            array_unshift($data, $headers);
            $this->nRows = sizeof($data);
        }

        if ($this->headersLeft) {
            $headers = $this->extractList($this->headersLeft, true);
            array_pad($headers, $this->nRows, '');
            for ($r = 0; $r < $this->nRows; $r++) {
                array_unshift($data[$r], $headers[$r]);
            }
        }
        return;
    } // applyHeaders




    private function renderHtmlTable()
    {
        $data = &$this->data;
        $header = ($this->headers != false);
        $tableClass = trim('lzy-table '.$this->tableClass);
        $thead = '';
        $tbody = '';
        $nRows = sizeof($data);
        $nCols = sizeof($data[0]);

        for ($r = 0; $r < $nRows; $r++) {
            if ($header && ($r == 0)) {
                $thead = "\t<thead>\n\t\t<tr>\n";
                if ($this->showRowNumbers) {
                    $thead .= "\t\t\t<th class='lzy-table-row-nr'></th>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($r, $c, 'th', true);
                    $thead .= "\t\t\t$cell\n";
                }
                $thead .= "\t\t</tr>\n\t</thead>\n";
            } else {
                $tbody .= "\t\t<tr>\n";
                if ($this->showRowNumbers) {
                    if ($this->headers) {
                        $n = $r;
                    } else {
                        $n = $r + 1;
                    }
                    $tbody .= "\t\t\t<td class='lzy-table-row-nr'>$n</td>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($r, $c);
                    $tbody .= "\t\t\t$cell\n";
                }
                $tbody .= "\t\t</tr>\n";
            }
        }

        if ($this->includeCellRefs && $this->dataSource) {
            $dataSource = " data-lzy-source='{$this->dataSource}'";
        } else {
            $dataSource = '';
        }

        $out = <<<EOT

<table id='{$this->id}' class='$tableClass'$dataSource{$this->tableDataAttr}>
{$this->caption}
$thead	<tbody>
$tbody	</tbody>
</table>

EOT;
        return $out;
    } // renderHtmlTable



    private function renderDiv()
    {
        $data = &$this->data;
        $header = ($this->headers != false);
        $body = '';
        $nRows = sizeof($data);
        $nCols = sizeof($data[0]);
        $tableClass = 'lzy-div-table';
        if (!$this->renderDivRows) {
            $tableClass .= ' lzy-grid-table';
        }
        $tableClass = trim($tableClass);

        for ($r = 0; $r < $nRows; $r++) {
            if ($this->renderDivRows) {
                $body .= "\t\t<div class='lzy-div-table-row'>\n";
            }
            if ($header && ($r == 0)) {
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($r, $c, 'div', true);
                    $body .= "\t\t\t$cell\n";
                }

            } else {
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($r, $c, 'div');
                    $body .= "\t\t\t$cell\n";
                }
            }
            if ($this->renderDivRows) {
                $body .= "\t\t</div>\n";
            }
        }

        if ($this->includeCellRefs && $this->dataSource) {
            $dataSource = " data-lzy-source='{$this->dataSource}'";
        } else {
            $dataSource = '';
        }

        $out = <<<EOT

<div id='{$this->id}' class='{$this->tableClass}'$dataSource{$this->tableDataAttr}>
{$this->caption}
    <div class="$tableClass">
$body
    </div>
</div>

EOT;
        return $out;
    } // renderDiv




    private function applyProcessingToData()
    {
        if (!$this->processingInstructions) {
            return;
        }
        foreach ($this->processingInstructions as $type => $cellInstructions) {
            if (is_int($type) && isset($cellInstructions['action'])) {
                $type = $cellInstructions['action'];
            }
            switch ($type) {
                case 'addCol':
                    $this->addCol($cellInstructions);
                    break;
                case 'removeCols':
                    $this->removeCols($cellInstructions);
                    break;
                case 'modifyCol':
                    $this->modifyCol($cellInstructions);
                    break;
                case 'addRow':
                    $this->addRow($cellInstructions);
                    break;
                case 'modifyCells':
                    $this->modifyCells($cellInstructions);
                    break;
            }
        }
    } // applyProcessingToData




    private function addCol($cellInstructions)
    {
        $data = &$this->data;
        $this->instructions = $cellInstructions;

        $newCol = $this->getArg('column'); // starting at 1
        if (!$newCol) {
            $newCol = sizeof($data)+1;
        } else {
            $newCol = min(sizeof($data[0])+1, max(1, $newCol));
        }
        $_newCol = $newCol - 1;     // starting at 0
        $content = $this->getArg('content');
        $header = $this->getArg('header');
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');

        $header1 = '';
        if ($class) {
            $content .= " @@$class@@";
            $header1 = "$header @@$class@@";
        }

        foreach ($data as $i => $row) {
            $content1 = $content;
            if (($i === 0) && $header) {
                $content1 = $header;
            }
            array_splice($data[$i], $_newCol, 0, $content1);
        }
        $this->nCols++;

        if ($phpExpr) {
            $this->applyCellInstructionsToColumn($_newCol, $phpExpr, !$header, $class);
        }
        if ($header1) {
            $data[0][$_newCol] = $header1;
        }
    } // addCol




    private function addRow($cellInstructions)
    {
        $data = &$this->data;
        $this->instructions = $cellInstructions;

        $content = $this->getArg('content');
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');

        $contents = false;
        if (is_array($content)) {
            $contents = $content;
            $content = '';
        }
        if ($class) {
            $content .= " @@$class@@";
        }

        $row = [];

        for ($_c = 0; $_c < sizeof($data[0]); $_c++) {
            $newCellVal = '';
            if ($this->phpExpr[$_c]) {
                $phpExpr1 = $this->precompilePhpExpr($this->phpExpr[$_c], $_c);
                try {
                    $newCellVal = eval($phpExpr1);
                } catch (Throwable $t) {
                    print_r($t);
                    exit;
                }
            } elseif ($phpExpr) {
                $phpExpr1 = $this->precompilePhpExpr($phpExpr, $_c);
                try {
                    $newCellVal = eval($phpExpr1);
                } catch (Throwable $t) {
                    print_r($t);
                    exit;
                }
            }
            if ($contents && isset($contents[$_c])) {
                $row[$_c] = $content.$contents[$_c].$newCellVal;

            } else {
                $row[$_c] = $content . $newCellVal;
            }
        }
        $data[] = $row;
    } // addRow




    private function removeCols($instructions)
    {
        $data = &$this->data;
        $this->instructions = $instructions;

        $colSpec = $this->getArg('columns');
        $columns = parseNumbersetDescriptor($colSpec);
        $columns = array_reverse($columns);

        foreach ($data as $r => $row) {
            foreach ($columns as $column) {
                array_splice($row, $column-1, 1);
            }
            $data[$r] = $row;
        }
    } // removeCols




    private function modifyCol($instructions)
    {
        $data = &$this->data;
        $this->instructions = $instructions;

        $col = $this->getArg('column');
        if (!$col) {
            die("Error: modifyCol() requires 'column' argument to be set.");
        }
        $col = min(sizeof($data[0]), max(1, $col));
        $_col = $col - 1;     // starting at 0
        $content = $this->getArg('content');
        $header = $this->getArg('header');
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');
        $inclHead = !(isset($this->headers) && $this->headers);

        if ($content) {
            $this->applyContentToColumn($_col, $content, $inclHead);
        }
        if ($phpExpr) {
            $this->applyCellInstructionsToColumn($_col, $phpExpr, $inclHead, $class);
        }
        if ($class) {
            $this->applyClassToColumn($_col, $class, $inclHead);
        }
        if ($header) {
            $data[0][$_col] = $header;
        }
    } // modifyCol





    private function modifyCells($cellInstructions)
    {
        $data = &$this->data;
        $this->instructions = $cellInstructions;

        $content = $this->getArg('content');
        if (!$header = $this->getArg('header')) {
            $header = $this->getArg('headers');
        }
        $class = $this->getArg('class');
        $phpExpr = $this->getArg('phpExpr');
        $inclHead = !(isset($this->headers) && $this->headers);

        $nCols = sizeof($data[0]);
        $_col = (isset($this->headersLeft) && $this->headersLeft) ? 1 : 0;
        for (; $_col < $nCols; $_col++) {
            if ($content) {
                $this->applyContentToColumn($_col, $content, $inclHead);
            }
            if ($phpExpr) {
                $this->applyCellInstructionsToColumn($_col, $phpExpr, $inclHead, $class);
            }
            if ($class) {
                $this->applyClassToColumn($_col, $class, $inclHead);
            }
            if ($header && isset($header[$_col])) {
                $data[0][$_col] = trim($header[$_col]);
            }
        }
    } // modifyCells



    private function applyClassToColumn($column, $class, $inclHead = false)
    {
        $c = $column;
        $data = &$this->data;
        $nCols = sizeof($data[0]);
        $class = $class ? " @@$class@@" : '';

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r == 0)) {
                continue;
            }
            $data[$r][$c] .= $class;
        }
    } // applyClassToColumn




    private function applyContentToColumn($column, $content, $inclHead = false)
    {
        $data = &$this->data;

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r == 0)) {
                continue;
            }
            if (is_array($content)) {
                $data[$r][$column] = (isset($content[$r]) ? $content[$r] : '');

            } else {
                $data[$r][$column] = $content;
            }
        }
    } // applyContentToColumn




    private function applyCellInstructionsToColumn($column, $phpExpr, $inclHead = false, $class = '')
    {
        if (!$phpExpr) {
            return;
        }
        if ($class) {
            $class = "@@$class@@";
        }
        $c = $column;
        $data = &$this->data;

        $phpExpr = $this->precompilePhpExpr($phpExpr);

        // iterate over rows and apply cell-instructions:
        foreach ($data as $r => $row) {
            if (!$inclHead && ($r == 0)) {
                continue;
            }
            try {
                $newCellVal = eval( $phpExpr );
            } catch (Throwable $t) {
                print_r($t);
                exit;
            }
            $data[$r][$c] = $newCellVal.$class;
        }
        return;
    } // applyCellInstructionsToColumn




    private function precompilePhpExpr($phpExpr, $_col = false)
    {
        if (!$phpExpr) {
            return '';
        }
        $data = &$this->data;
        $headers = $data[0];

        if (preg_match_all('/( (?<!\\\) \[\[ [^\]]* \]\] )/x', $phpExpr, $m)) {
            foreach ($m[1] as $cellRef) {
                $cellRef0 = $cellRef;
                $cellRef = trim(str_replace(['[[', ']]'], '', $cellRef));
                $cellVal = false;
                $ch1 = ($cellRef != '') ? $cellRef[0] : false;

                if (($ch1 == '"') || ($ch1 == "'")) {
                    $cellRef = preg_replace('/^ [\'"]? (.*) [\'"]? $/x', "$1", $cellRef);
                    if (($i = array_search($cellRef, $headers)) !== false) { // column name
                        $c = $i;

                    } else {
                        $cellVal = $cellRef;    // literal content
                    }
                } elseif (($cellRef == '') || ($cellRef === '0')) { // this cell
                    $c = '$c';

                } elseif (($ch1 == '-') || ($ch1 == '+')) { // relative index
                    $c = '$c + ' . $cellRef;

                } elseif (($i = array_search($cellRef, $headers)) !== false) { // column name
                    $c = $i;

                } elseif (intval($cellRef)) { // numerical index
                    $c = min(intval($cellRef) - 1, sizeof($this->data[0]) - 1);

                } else {
                    $cellVal = $cellRef;    // literal content
                }

                $cellVal = $cellVal ? $cellVal : "\$data[\$r][$c]";
                $phpExpr = str_replace($cellRef0, $cellVal, $phpExpr);
            }
        }
        $phpExpr = preg_replace('/^ \\\ \[ \[/x', '[[', $phpExpr);

        if ($_col !== false) {
            $r = (isset($this->headers) && $this->headers) ? 1 : 0;
            if (strpos($phpExpr, 'sum()') !== false) {
                $sum = 0;
                for (; $r<sizeof($data); $r++) {
                    $val = $data[$r][$_col];
                    if (preg_match('/^([\d\.]+)/', $val, $m)) {
                        $val = floatval($m[1]);
                        $sum += $val;
                    }
                }
                $phpExpr = str_replace('sum()', $sum, $phpExpr);

            }
            if (strpos($phpExpr, 'count()') !== false) {
                $count = 0;
                for (; $r<sizeof($data); $r++) {
                    $val = $data[$r][$_col];
                    if (preg_match('/\S/', $val)) {
                        $count++;
                    }
                }
                $phpExpr = str_replace('count()', $count, $phpExpr);
            }
        }

        if (strpos($phpExpr, 'return') === false) {
            $phpExpr = "return $phpExpr;";
        }
        return $phpExpr;
    } // precompilePhpExpr





    private function getOption( $name, $helpText = '', $default = false )
    {
        $value = isset($this->options[$name]) ? $this->options[$name] : $default;

        if ($value === 'false') {
            $value = false;
        } elseif ($value === 'true') {
            $value = true;
        }
        if ($helpText) {
            $this->helpText[] = ['option' => $name, 'text' => $helpText];
        }
        return $value;
    } // getOption




    private function getArg( $name )
    {
        if ($name === 'phpExpr') {
            $this->phpExpr = [];
            for ($c = 1; $c <= sizeof($this->data[0]); $c++) {
                if (isset($this->instructions["phpExpr[$c]"])) {
                    $this->phpExpr[$c-1] = $this->instructions["phpExpr[$c]"];
                } else {
                    $this->phpExpr[$c-1] = false;
                }
            }
        }

        if (!isset($this->instructions[$name])) {
            if (($name === 'columns') && isset($this->instructions['column'])) {
                $name = 'column';
            } else {
                $this->errMsg .= "Argument '$name' missing\n";
                return '';
            }
        }
        $value = $this->instructions[$name];
        $value = $this->extractList($value);
        return $value;
    } // getArg



    private function getDataElem($row, $col, $tag = 'td', $hdrElem = false)
    {
        $cell = $this->data[$row][$col];
        $col1 = $col + 1;
        if ($hdrElem) {
            $tdClass = $this->cellClass ? $this->cellClass.'-hdr' : 'lzy-div-table-hdr';
        } else {
            $tdClass = $this->cellClass;
        }
        $tdId = '';
        $ref = '';
        if (preg_match('/(@@ ([\w\- ]*) @@)/x', $cell, $m)) { // extract tdClass
            $tdClass = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
        }
        if (preg_match('/(\<\<([^\>]*)\>\>)/', $cell, $m)) {    // extract ref
            $ref = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
            $ref = " data-lzy-cell='$ref'";
        }
        if ($this->cellIds) {
             $tdId = " id='{$this->cellClass}_{$col}_{$row}'";
        }
        if ($this->cellMask && $this->cellMask[$row][$col]) {
            $tdClass = $this->cellMaskedClass;
        }
        $tdClass = trim(str_replace('  ', ' ', "$tdClass lzy-col-$col1"));
        $tdClass = " class='$tdClass'";
        $cell = str_replace("\n", '<br />', $cell);
        if ($this->headersAsVars && $hdrElem) {
            $cell = "{{ $cell }}";
        }
        return "<$tag$tdId$tdClass$ref><div>$cell</div></$tag>";
    } // getDataElem




    private function handleCaption($tableCounter)
    {
        if ($this->caption) {
            $tableCounter++;
            $this->tableCounter = $tableCounter;
            if ($this->captionIndex) {
                $this->tableCounter = $this->captionIndex;
            }
            if (preg_match('/(.*)\#\#=(\d+)(.*)/', $this->caption, $m)) {
                $this->tableCounter = intval($m[2]);
                $this->caption = $m[1] . '##' . $m[3];
            }
            $this->caption = str_replace('##', $this->tableCounter, $this->caption);

            if ($this->renderAsDiv) {
                $this->caption = "\t\t<div class='caption'>$this->caption</div>\n";
            } else {
                $this->caption = "\t\t<caption>$this->caption</caption>\n";
            }
        }
        return $tableCounter;
    } // handleCaption




    private function handleDatatableOption($page)
    {
        if ($this->interactive) {
            $page->addModules('DATATABLES');
            $this->tableClass = trim($this->tableClass.' lzy-datatable');
            $order = '';
            if ($this->sort) {
                $sortCols = csv_to_array($this->sort);
                $headers = $this->data[0];
                foreach ($sortCols as $sortCol) {
                    $sortCol = alphaIndexToInt($sortCol, $headers) - 1;
                    $order .= "[ $sortCol, 'asc' ],";
                }
                $order = rtrim($order, ',');
                $order = " 'order': [$order],";
            }
            $paging = '';
            if (!$this->paging) {
                $paging = ' paging: false,';
            }
            $pageLength = '';
            if ($this->initialPageLength) {
                $pageLength = " pageLength: {$this->initialPageLength},";
            }
            $jq = <<<EOT
lzyTable{$this->tableCounter} = $('.lzy-datatable').DataTable({
    'language':{'search':'{{QuickSearch}}:', 'info': '_TOTAL_ {{Records}}'},
    $order$paging$pageLength
});
EOT;

            $page->addJq($jq);
            if (!$this->headers) {
                $this->headers = true;
            }
        }
    } // handleDatatableOption




    private function loadData()
    {
        if ($this->dataSource) {
            if (is_string($this->dataSource)) {
                if (!file_exists($this->dataSource)) {
                    $this->dataSource = false;
                }
            } elseif (is_array($this->dataSource)) {
                $this->data = $this->dataSource;
                if ($this->headers === true) {
                    $this->headerElems = array_shift($this->data);
                } else {
                    $this->headerElems = $this->data[0];
                }
                $this->nCols = sizeof($this->data[0]);
                $this->nRows = sizeof($this->data);
                return;
            }
        } else {
            $this->dataSource = false;
        }

        $this->data = [[]];
        if ($this->dataSource) {
            $ds = new DataStorage2($this->options);
            $this->ds = $ds;
            $this->data = $ds->read();
            if ($this->headers === true) {
                $structure = $this->ds->getRecStructure();
                $this->headerElems = $structure['labels'];
            }

            //ToDo: workaround!
            foreach ($this->data as $r => $rec) {
                foreach ($rec as $c => $item) {
                    $this->data[$r][$c] = trim($item, '"\'');
                }
            }

            if ($ds->getSourceFormat() !== 'csv') {
                $this->convertTo2D( true );
//                $this->convertTo2D();

            } elseif ($this->headers === true) {
                array_shift($this->data);
            }
//            $this->arrangeData();???????
        }

        $this->adjustTableSize();
        $this->insertCellAddressAttributes();

        $this->excludeColumns();

        $this->nCols = sizeof($this->data[0]);
        $this->nRows = sizeof($this->data);

        $this->sortData();
        return;
    } // loadData




    private function loadProcessingInstructions()
    {
        if ($this->process && isset($this->page->frontmatter[$this->process])) {
            $this->processingInstructions = $this->page->frontmatter[$this->process];
        } elseif ($this->processInstructionsFile) {
            $file = resolvePath($this->processInstructionsFile, true);
            $this->processingInstructions = getYamlFile($file);
        } else {
            $this->processingInstructions = false;
        }
    } // loadProcessingInstructions




    private function checkArguments($inx)
    {
        if (!$this->id) {
            $this->id = 'lzy-table' . $inx;
        }
        if ($this->tableDataAttr) {
            list($name, $value) = explode('=', $this->tableDataAttr);
            if (strpos($name, 'data-') !== 0) {
                $name = "data-$name";
            }
            $this->tableDataAttr = " $name='$value'";
        }
    } // checkArguments




    private function extractList($value, $bracketsOptional = false)
    {
        if (!$value || is_array($value)) {
            return $value;
        }
        if (is_string($value) && ($bracketsOptional && ($value[0] != '['))) {
            $value = "[$value]";
        }
        if (is_string($value) && preg_match('/^(?<!\\\) \[ (?!\[) (.*) \] $/x', "$value", $m)) {
            $value = $m[1];
            $ch1 = isset($value[1]) ? $value[1] : '';
            if (!($ch1 == ',') && !($ch1 == '|')) {
                $ch1  = false;
                $comma = substr_count($value, ',');
                $bar = substr_count($value, '|');
            }
            if ($ch1 || $comma || $bar) {
                if ($ch1) {
                    $value = explode($ch1, $value);
                } elseif ($comma > $bar) {
                    $value = explode(',', $value);
                } else {
                    $value = explode('|', $value);
                }
            }
        }
        if (is_array($value)) {
            foreach ($value as $i => $val) {
                $value[$i] = preg_replace('/^ \\\ \[/x', '[', $val);
            }
        }
        return $value;
    } // extractList




    private function adjustTableSize()
    {
        $data = &$this->data;
        if (!isset($data[0])) {
            $data[0] = [];
        }

        $nCols = $this->nCols ? $this->nCols : sizeof($data[0]);
        $nRows = $this->nRows ? $this->nRows : sizeof($data);

        if ($nCols > sizeof($data[0])) { // increase size
            for ($r=0; $r < sizeof($data); $r++) {
                $data[$r] = array_pad([], $nCols, '');
            }
        } elseif ($nCols < sizeof($data[0])) { // reduce size
            for ($r=0; $r < sizeof($data); $r++) {
                $data[$r] = array_slice($data[$r], 0, $nCols);
            }
        }

        if ($nRows < sizeof($data)) { // reduce size
            $data = array_slice($data, 0, $nRows);

        } elseif ($nRows > sizeof($data)) { // increase size
            $emptyRow = array_pad([], $nCols, '');
            $data = array_pad($data, $nRows, $emptyRow);
        }
    } // adjustTableSize




    private function sortData()
    {
        if ($this->sort) {
            $data = &$this->data;
            $s = $this->sort - 1;
            if (($s >= 0) && ($s < $this->nCols)) {
                if ($this->sortExcludeHeader) {
                    $row0 = array_shift($data);
                }
                usort($data, function ($a, $b) use ($s) {
                    return ($a[$s] > $b[$s]);
                });
                if ($this->sortExcludeHeader) {
                    array_unshift($data, $row0);
                }
            }
        }
    } // sortData




    private function insertCellAddressAttributes()
    {
        if ($this->includeCellRefs) {
            $i = $this->tableCounter;
            for ($r = 0; $r < $this->nRows; $r++) {
                for ($c = 0; $c < $this->nCols; $c++) {
                    $this->data[$r][$c] .= "<<$c,$r>>";
                }
            }
        }
    } // insertCellAddressAttributes




    private function excludeColumns()
    {
        if ($this->excludeColumns) {
            $data = &$this->data;
            $exclColumns = explode(',', $this->excludeColumns);
            $totalExcluded = 0;
            foreach ($exclColumns as $descr) {
                $descr = str_replace(' ', '', $descr);
                if (preg_match('/(.*)\-(.*)/', $descr, $m)) {
                    $c = $m[1] ? intval($m[1]) - 1 : 0;
                    $to = $m[2] ? intval($m[2]) : 9999;
                    $len = $to - $c;
                } else {
                    $c = intval($descr) - 1;
                    $len = 1;
                }
                $c -= $totalExcluded;
                $nCols = sizeof($data[0]);
                $c = max(0, min($c, $nCols));
                $len = max(0, min($len, $nCols - $c));
                $totalExcluded += $len;

                foreach ($data as $r => $row) {
                    array_splice($data[$r], $c, $len);
                }
            }
        }
    } // excludeColumns




    private function convertLinks()
    {
        if ($this->autoConvertLinks) {
            $data = &$this->data;
            foreach ($data as $r => $row) {
                foreach ($data[$r] as $c => $col) {
                    $d = trim($data[$r][$c]);
                    if (preg_match_all('/ ([\w\-\.]*?) \@ ([\w\-\.]*?\.\w{2,6}) /x', $d, $m)) {
                        foreach ($m[0] as $addr) {
                            $d = str_replace($addr, "<a href='mailto:$addr'>$addr</a>", $d);
                        }

                    } elseif (preg_match('/^( \+? [\d\-\s\(\)]* )$/x', $d, $m)) {
                        $tel = preg_replace('/[^\d\+]/', '', $d);
                        if (strlen($tel) > 7) {
                            $d = "<a href='tel:$tel'>$d</a>";
                        }
                    } elseif (preg_match('|^( (https?://)? ([\w\-\.]+ \. [\w\-]{1,6}))$|xi', $d, $m)) {
                        if (!$m[2]) {
                            $url = "https://".$m[3];
                        } else {
                            $url = $m[1];
                        }
                        if (strlen($url) > 7) {
                            $d = "<a href='$url'>$d</a>";
                        }
                    }
                    $data[$r][$c] = $d;
                }
            }
        }
    } // convertLinks




    private function convertTo2D($includeKeys = null)
    {
        $data = [];
        if ($includeKeys === null) {
            $includeKeys = $this->includeKeys;
        }

        foreach ($this->data as $key => $rec) {
            if (is_array($rec)) {
                if (!$data && is_string((array_keys($rec))[0])) {
                    $c = 0;
                    $data[0][$c++] = 'Index';
                    foreach ($rec as $k => $v) {
                        $data[0][$c++] = $k;
                    }
                }
            }

            if ($includeKeys) {
                if (is_array($rec)) {
                    if (is_int($key) && ($key > 1546297200)) {
                        $key = date('Y-m-d H:i', $key);
                        if (substr($key, 12) === '00:00') {
                            $key = substr($key, 0, 12);
                        }
                    }
                    $rec = array_values($rec);
                    array_unshift($rec, $key);
                } else {
                    $rec = [$key, $rec];
                }
            }
            $data[] = $rec;
        }
        $this->data = $data;
    } // convertTo2D



    private function isNumericIndex($rec)
    {
        $i = 0;
        foreach ($rec as $inx => $item) {
            if ($inx !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    } // isNumericIndex

} // HtmlTable
