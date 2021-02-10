<?php

$GLOBALS["globalParams"]['tableCounter'][ $GLOBALS["globalParams"]["pagePath"] ] = 0;

define('FORM_TYPES',
    ',string,text,password,email,textarea,radio,checkbox,'.
    'dropdown,button,url,date,time,datetime,month,number,range,tel,file,'.
    'fieldset,fieldset-end,reveal,hidden,literal,bypassed,');
define('SCALAR_TYPES',
    ',string,text,password,email,textarea,'.
    ',url,date,time,datetime,month,number,range,tel,');

define('DEFAULT_EDIT_FORM_TEMPLATE_FILE', '~page/-table_edit_form_template.md');
define('LZY_TABLE_SHOW_REC_ICON', "<span class='lzy-icon lzy-icon-show'></span>");



class HtmlTable
{
    private $errMsg = '';
    private $dataTableObj = null;
    private $strToAppend = '';

    public function __construct($lzy, $options)
    {
        $this->options      = $options;
        $this->lzy 		    = $lzy;
        $this->page 		= $lzy->page;
        $this->tableCounter = &$GLOBALS["globalParams"]['tableCounter'][ $GLOBALS["globalParams"]["pagePath"] ];
        $this->tableCounter++;
        $this->helpText     = false;
        $this->tickHash     = false;
        if ($options === 'help') {
            $this->helpText = [];
            $options = [];
        }
        $this->editFormRendered = false;

        $this->dataSource	        = $this->getOption('dataSource', '(optional if nCols is set) Name of file containing data. Format may be .cvs or .yaml and is expected be local to page folder.');
        $this->inMemoryData	        = $this->getOption('data', 'Alternative to "dataSource": provide data directly as array. E.g. data: $array,');
        $this->id 			        = $this->getOption('id', '(optional) Id applied to the table tag (resp. wrapping div tag if renderAsDiv is set)');
        $this->wrapperClass 	    = $this->getOption('wrapperClass', '(optional) Class applied to the table tag (resp. wrapping div tag if renderAsDiv is set). Use "lzy-table-default" to apply default styling.');
        $this->tableClass 	        = $this->getOption('tableClass', '(optional) Class applied to the table tag (resp. wrapping div tag if renderAsDiv is set). Use "lzy-table-default" to apply default styling.');
        $this->tableClass 	        = $this->getOption('class', 'Synonyme for tableClass', $this->tableClass);
        $this->cellClass 	        = $this->getOption('cellClass', '(optional) Class applied to each table cell');
        $this->cellWrapper 	        = $this->getOption('cellWrapper', '(optional) If true, each cell is wrapped in a DIV element; if it\'s a string, the cell is wrapped in a tag of given name.');
        $this->rowClass 	        = $this->getOption('rowClass', '(optional) Class applied to each table row', 'lzy-row-*');
        $this->cellIds 	            = $this->getOption('cellIds', '(optional) If true, each cell gets an ID which is derived from the cellClass');
        $this->nRows 		        = $this->getOption('nRows', '(optional) Number of rows: if set the table is forced to this number of rows');
        $this->nCols 		        = $this->getOption('nCols', '(optional) Number of columns: if set the table is forced to this number of columns');
        $this->includeKeys          = $this->getOption('includeKeys', '[true|false] If true and not a .csv source: key elements will be included in data.');
        $this->interactive          = $this->getOption('interactive', '[true|false] If true, module "Datatables" is activated, providing for interactive features such as sorting, searching etc.');
        $this->liveData             = $this->getOption('liveData', '[true|false] If true, Lizzy\'s "liveData" mechanism is activated. If the dataSource is modified on the server, changes are immediately mirrored in the webpage.');
        $this->dataSelector         = $this->getOption('dataSelector', '(optional string) If defined and "liveData" is activated, this option defines how to access data elements from the DB. (Default: \'*,*\')', '*,*');
        $this->targetSelector       = $this->getOption('targetSelector', '(optional string) If defined and "liveData" is activated, this option defines how to assign data elements to DOM-elements. (Default: \'[data-ref="*,*"]\')', '[data-ref="*,*"]');
        $this->editableBy           = $this->getOption('editableBy', '[false,true,loggedin,privileged,admins,editors] Defines who may edit data. Default: false. (only available when using option "dataSource")');
        $this->editMode             = $this->getOption('editMode', '[inline,form] Defines (Default: inline).', 'inline');
        $this->editFormArgs         = $this->getOption('editFormArgs', 'Arguments that will passed on to the forms-class.', false);
        $this->editFormTemplate     = $this->getOption('editFormTemplate', 'A markdown file that will be used for rendering the form.', false);
        $this->activityButtons      = $this->getOption('activityButtons', 'Activates a row of activity buttons related to the form. If in editMode, a "New Record" button will be added.', false);
        $this->labelColons          = $this->getOption('labelColons', 'If false, trailing colon of labels in editing-forms are omitted.', true);
        $this->customRowButtons     = $this->getOption('customRowButtons', '(optional comma-separated-list) Prepends a column to each row containing custom buttons. Buttons can be defined as names of icons or HTML code. E.g. "send,trash"', null);
        $this->recViewButtonsActive = $this->getOption('showRecViewButton', '[true|false] If true, a button to open a popup is added to each row. The popup presents the data record in form view.', false);
        $this->paging               = $this->getOption('paging', '[true|false] When using "Datatables": turns paging on or off (default is on)');
        $this->initialPageLength    = $this->getOption('initialPageLength', '[int] When using "Datatables": defines the initial page length (default is 10)');
        $this->excludeColumns       = $this->getOption('excludeColumns', '(optional) Allows to exclude specific columns, e.g. "excludeColumns:2,4-5"');
        $this->sort 		        = $this->getOption('sort', '(optional) Allows to sort the table on a given columns, e.g. "sort:3"');
        $this->sortExcludeHeader    = $this->getOption('sortExcludeHeader', '(optional) Allows to exclude the first row from sorting');
        $this->autoConvertLinks     = $this->getOption('autoConvertLinks', '(optional) If true, all data is scanned for patterns of URL, mail address or telephone numbers. If found the value is wrapped in a &lt;a> tag');
        $this->autoConvertTimestamps= $this->getOption('autoConvertTimestamps', '(optional) If true, integer values that could be timestamps (= min. 10 digits) are converted to time strings.');
        $this->caption	            = $this->getOption('caption', '(optional) If set, a caption tag is added to the table. The caption text may contain the pattern "##" which will be replaced by a number.');
        $this->captionIndex         = $this->getOption('captionIndex', '(optional) If set, will override the automatically applied table counter');
        $this->headers	            = $this->getOption('headers', '(optional) Column headers may be supplied in the form [A|B|C...]');
        if (!$this->headers) {
            $this->headers          = $this->getOption('headersTop');   // synonyme for 'headers'
        }
        $this->headersLeft          = $this->getOption('headersLeft', '(optional) Row headers may be supplied in the form [A|B|C...]');
        $this->headersAsVars        = $this->getOption('headersAsVars', '(optional) If true, header elements will be rendered as variables (i.e. in curly brackets).');
        $this->showRowNumbers       = $this->getOption('showRowNumbers', '(optional) Adds a left most column showing row numbers.');
        $this->hideMetaFields       = $this->getOption('hideMetaFields', '(optional) If true, system (or "meta") fields are not rendered (default: true).', true);
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
        $this->enableTooltips       = $this->getOption('enableTooltips', '(optional) Enables tooltips, e.g. for cells containing too much text. To use, apply a class-name containing "tooltip" to the targeted cell, e.g. "tooltip1".');

        $this->checkArguments();

        $this->handleDatatableOption($this->page);
        $this->handleCaption();
        $this->editingActive = checkPermission($this->editableBy, $this->lzy);
        $this->editableActive = false;
        if ($this->editingActive) {
            if (strpos($this->editMode, 'form') === false) {
                $this->editingActive = false;
                $this->editableActive = true;
            }
        }
    } // __construct




    public function render( $help = false)
    {
        if ($help) {
            return $this->helpText;
        }
        if (isset($_POST['_lizzy-form'])) {
            require_once SYSTEM_PATH.'forms.class.php';
            new Forms( $this->lzy, true );
            unset($_POST['_lizzy-form']);
        }

        // for "active tables": load modules and set class:
        if ($this->editingActive || $this->activityButtons || $this->recViewButtonsActive) {
            $this->page->addModules('POPUPS,HTMLTABLE,~sys/css/htmltables.css');
            $this->includeCellRefs = true;
            require_once SYSTEM_PATH.'forms.class.php';
            new Forms( $this->lzy, true );
            $this->tableClass .= ' lzy-active-table';
        }

        $this->loadData();

        $this->applyHeaders();

        $this->injectRowButtons();
        $this->loadProcessingInstructions();
        $this->applyProcessingToData();

 //        $this->applyMiscOptions();

        $out = '';

        // for "active tables": create ticket and set data-field:
        if ($this->editingActive || $this->editableActive || $this->activityButtons || $this->recViewButtonsActive) {
            $this->page->addModules('MD5');
            $tck = new Ticketing();
            $this->tickHash = $tck->createHash( 'lzy-form' );
            $this->tableDataAttr .= " data-table-hash='{$this->tickHash}' data-form-id='#lzy-edit-data-form-{$this->tableCounter}'";

            // utility feature to export form template based on data structure:
            if ($this->lzy->localCall && (getUrlArg('exportForm'))) {
                $this->exportForm();
            }
        }

        // option editable:
        if ($this->id) {
            $this->targetSelector = "#$this->id $this->targetSelector";
        } else {
            $this->targetSelector = ".lzy-table-{$this->tableCounter} $this->targetSelector";
        }

        if ($this->editingActive) {
            $out .= $this->activateEditingForm();
        } elseif ($this->editableActive) {
            $out .= $this->activateEditable();
        } else {
            if ($this->liveData) {            // option liveData:
                $out = $this->activateLiveData();
            }
            $this->editableBy = false;
        }

        if ($this->recViewButtonsActive) {
            $out .= $this->renderForm();
        }
        if ($this->editingActive || $this->editableActive ||
            $this->activityButtons || $this->recViewButtonsActive || @$this->showRecViewButton) {
            $this->renderTextResources();
        }

        $this->convertLinks();
        $this->convertTimestamps();
        $out .= $this->renderHtmlTable() . $this->strToAppend;
        $out = <<<EOT
  <div class='lzy-table-wrapper $this->wrapperClass'>
$out
  </div>
EOT;

        return $out;
    } // render




    private function activateEditable()
    {
        $file = SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
        if (!file_exists($file)) {
            die("Error: HTMLtables with activated EditableBy option requires Lizzy Extensions to be installed.");
        }
        require_once $file;

        $page = $this->page;
        require_once SYSTEM_PATH.'extensions/editable/code/editable.class.php';

        $this->lzy->trans->readTransvarsFromFile( SYSTEM_PATH.'extensions/editable/config/vars.yaml', false, true);

        $GLOBALS['lizzy']['editableLiveDataInitialized'] = false;
        $page->addModules('EDITABLE');

        // show buttons:
        if (isset( $this->options['showButtons'] )) {
            $this->options['showButton'] = $this->options['showButtons'];
        }
        if (isset( $this->options['showButton'] )) {
            if (($this->options['showButton'] === 'auto')) {
                $this->tableClass .= " lzy-editable-show-buttons";

            } elseif ($this->options['showButton'] !== false) {
                $this->tableClass .= " lzy-editable-show-buttons";
            }
        } else {
            $this->tableClass .= " lzy-editable-auto-show-button";
        }

        $initEditable = true;
        if (isset( $this->options['init'] )) {
            $initEditable = $this->options['init'];
            $this->cellClass .= " lzy-editable-inactive";
        } else {
            $this->cellClass .= " lzy-editable";
        }
        $edbl = new Editable( $this->lzy, [
            'dataSource' => '~/'. $this->dataSource,
            'dataSelector' => '*,*',
            'targetSelector' => $this->targetSelector,
            'output' => false,
            'init' => $initEditable,
            'editableBy' => $this->editableBy,
            'liveData' => $this->liveData,
        ] );

        $this->wrapperClass .= ' lzy-table-editable';
        $this->page->addJq("$('.lzy-table-editable').closest('.dataTables_wrapper').addClass('lzy-datatable-editable')\n");

        $out = $edbl->render();
        return $out;
    } // activateEditable




    private function activateLiveData()
    {
        $file = SYSTEM_PATH.'extensions/livedata/code/live-data.class.php';
        if (!file_exists($file)) {
            die("Error: HTMLtables with activated liveData option requires Lizzy Extensions to be installed.");
        }
        require_once $file;
        $this->page->addModules('~ext/livedata/js/live_data.js');

        // skipInitialUpdate: initLiveData( false );
        $jq = <<<EOT

if ($('[data-lzy-datasrc-ref]').length) {
    liveDataInit( false );
}

EOT;
        $this->page->addJq($jq);

        $js = '';
        if ($this->dataTableObj) {
            foreach ($this->dataTableObj as $dataTableObj) {
                $js .= "\t$dataTableObj.draw();\n";
            }
            $js = <<<EOT

function redrawTables() {
$js}

EOT;
            $this->page->addJs($js);
        }


        $args = [
            'dataSource' => '~/'.$this->dataSource,
            'dataSelector' => $this->dataSelector,
            'targetSelector' => $this->targetSelector,
            'manual' => 'silent',
            //'pollingTime' => 10, // for testing
        ];

        // if dataTables are active, make sure they are redrawn when new data arrives:
        if ($this->interactive) {
            $args['postUpdateCallback'] = 'redrawTables';
        }

        $ld = new LiveData($this->lzy, $args);
        return $ld->render() . "\n";
    } // activateLiveData




    //    private function applyMiscOptions()
    //    {
    //    } // applyMiscOptions



    
    private function applyHeaders()
    {
        if (!$this->headers && !$this->headersLeft) {
            return;
        }

        $data = &$this->data;

        if ($this->headers === true) {
            $data = array_merge(['hdr' => $this->headerElems], $data);

        } elseif (($this->headers) && ($this->headers !== true)) {
            $headers = $this->extractList($this->headers, true);
            $headers = array_pad ( $headers , sizeof(reset( $data )) , '' );

            array_unshift($data, $headers);
            $this->nRows = sizeof($data);
        }

        if ($this->headersLeft) {
            $headers = $this->extractList($this->headersLeft, true);
            if ($this->headers) {
                $key0 = array_keys($data)[0];
                array_splice($data[ $key0 ], 0, 0, ['']);
            }
            if ($this->headers === true) {
                $r = 1;
                $r1 = 0;
                $rEnd = $this->nRows + 1;
            } elseif (!$this->headers) {
                $r = 0;
                $r1 = 0;
                $rEnd = $this->nRows;
            } else {
                $r = 1;
                $r1 = 0;
                $rEnd = $this->nRows;
            }
            for (; $r < $rEnd; $r++) {
                if ($this->headersLeft === true) {
                    array_splice($data[$r], 0, 0, [$r1++ + 1]);
                } else {
                    array_splice($data[$r], 0, 0, [$headers[$r1++]]);
                }
            }
        }
        return;
    } // applyHeaders




    private function renderHtmlTable()
    {
        if ($this->editableBy) {
            $this->tableDataAttr .= " data-table-hash='{$this->tickHash}' data-form-id='#lzy-edit-data-form-{$this->tableCounter}'";
        }
        $data = &$this->data;

        $header = ($this->headers !== false);

        $tableClass = @$this->options['tableClass'] ? $this->options['tableClass']. ' ' : "lzy-table lzy-table-{$this->tableCounter} ";
        $tableClass .= $this->tableClass;
        $tableClass = trim($tableClass);
        $thead = '';
        $tbody = '';
        $nCols = sizeof(reset( $data ));
        $rowClass0 = $this->rowClass;

        $rInx = 1;
        $r = 0;
        foreach ($data as $recId => $rec) {
            if ($header && ($r === 0)) {
                $rowClass = 'lzy-hdr-row';
                $thead = "\t<thead>\n\t\t<tr class='$rowClass'>\n";
                if ($this->showRowNumbers) {
                    $thead .= "\t\t\t<th class='lzy-table-row-nr'></th>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $cell = $this->getDataElem($recId, $c, 'th', true);
                    if ($cell !== null) {
                        $thead .= "\t\t\t$cell\n";
                    }
                }
                $thead .= "\t\t</tr>\n\t</thead>\n";

            } else {    // render row:
                if ($recId === '') {
                    $recId = 'new-rec';
                }
                $recKey = " data-reckey='$recId'";
                $rowClass = str_replace('*', $rInx, $rowClass0);
                $tbody .= "\t\t<tr class='$rowClass'$recKey>\n";
                if ($this->showRowNumbers) {
                    if ($this->headers) {
                        $n = $r;
                    } else {
                        $n = $r + 1;
                    }
                    $tbody .= "\t\t\t<td class='lzy-table-row-nr'>$n</td>\n";
                }
                for ($c = 0; $c < $nCols; $c++) {
                    $tag = (($c === 0) && $this->headersLeft)? 'th': 'td';
                    $cell = $this->getDataElem($recId, $c, $tag);
                    if ($cell !== null) {
                        $tbody .= "\t\t\t$cell\n";
                    }
                }
                $tbody .= "\t\t</tr>\n";
                $rInx++;
            }
            $r++;
        }

        if ($this->includeCellRefs && $this->dataSource) {
            $dataSource = " data-lzy-source='{$this->dataSource}'";
        } else {
            $dataSource = '';
        }

        $out = <<<EOT

  <table id='{$this->id}' class='$tableClass'$dataSource{$this->tableDataAttr} data-inx="$this->tableCounter">
{$this->caption}
$thead	<tbody>
$tbody	</tbody>
  </table>

EOT;

        if ($this->activityButtons) {
            $out = $this->renderTableActionButtons() . $out;
        }
        return $out;
    } // renderHtmlTable




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
        if (!$data) {
            return;
        }
        $this->instructions = $cellInstructions;

        $newCol = $this->getArg('column'); // starting at 1
        if (!$newCol) {
            $newCol = sizeof($data)+1;
        } else {
            $newCol = min(sizeof(reset( $data ))+1, max(1, $newCol));
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
            if (($i === 'hdr') && $header) {
                $content1 = $header;
            }
            array_splice($data[$i], $_newCol, 0, $content1);
        }
        $this->nCols++;

        if ($phpExpr) {
            $this->applyCellInstructionsToColumn($_newCol, $phpExpr, !$header, $class);
        }
        if ($header1) {
            $key0 = array_keys( $data )[0];
            $data[ $key0 ][$_newCol] = $header1;
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

        for ($_c = 0; $_c < sizeof(reset( $data )); $_c++) {
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
        $col = min(sizeof(reset( $data )), max(1, $col));
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
            $key0 = array_keys( $data )[0];
            $data[ $key0 ][$_col] = $header;
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

        $nCols = sizeof(reset( $data ));
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
                $key0 = array_keys( $data )[0];
                $data[ $key0 ][$_col] = trim($header[$_col]);
            }
        }
    } // modifyCells



    private function applyClassToColumn($column, $class, $inclHead = false)
    {
        $c = $column;
        $data = &$this->data;
        $nCols = sizeof(reset( $data ));
        $class = $class ? " @@$class@@" : '';

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r === 0)) {
                continue;
            }
            $data[$r][$c] .= $class;
        }
    } // applyClassToColumn




    private function applyContentToColumn($column, $content, $inclHead = false)
    {
        $data = &$this->data;

        foreach ($data as $r => $row) {
            if (!$inclHead && ($r === 0)) {
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
            if (!$inclHead && ($r === 'hdr')) {
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
        $headers = reset( $data );

        $phpExpr = str_replace(['ʺ', 'ʹ'], ['"', "'"], $phpExpr);
        if (preg_match_all('/( (?<!\\\) \[\[ [^\]]* \]\] )/x', $phpExpr, $m)) {
            foreach ($m[1] as $cellRef) {
                $cellRef0 = $cellRef;
                $cellRef = trim(str_replace(['[[', ']]'], '', $cellRef));
                $cellVal = false;
                $ch1 = ($cellRef !== '') ? $cellRef[0] : false;

                if (($ch1 === '"') || ($ch1 === "'")) {
                    $cellRef = preg_replace('/^ [\'"]? (.*?) [\'"]? $/x', "$1", $cellRef);
                    if (($i = array_search($cellRef, $headers)) !== false) { // column name
                        $c = $i;

                    } else {
                        $cellVal = $cellRef;    // literal content
                    }
                } elseif (($cellRef === '') || ($cellRef === '0')) { // this cell
                    $c = '$c';

                } elseif (($ch1 === '-') || ($ch1 === '+')) { // relative index
                    $c = '$c + ' . $cellRef;

                } elseif (($i = array_search($cellRef, $headers)) !== false) { // column name
                    $c = $i;

                } elseif (intval($cellRef)) { // numerical index
                    $c = min(intval($cellRef) - 1, sizeof(reset($this->data)) - 1);

                } else {
                    $cellVal = $cellRef;    // literal content
                }

                $cellVal = $cellVal ? $cellVal : "{\$data[\$r][$c]}";
                $phpExpr = str_replace($cellRef0, $cellVal, $phpExpr);
            }
        }
        $phpExpr = preg_replace('/^ \\\ \[ \[/x', '[[', $phpExpr);

        if ($_col !== false) {
            $r = (isset($this->headers) && $this->headers) ? 1 : 0;
            if (strpos($phpExpr, 'sum()') !== false) {
                $sum = 0;
                for (; $r<sizeof($data); $r++) {
                    $val = @$data[$r][$_col];
                    if (preg_match('/^([\d.]+)/', $val, $m)) {
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
        if (!$this->data || !is_array($this->data)) {
            return '';
        }
        if ($name === 'phpExpr') {
            $this->phpExpr = [];
            for ($c = 1; $c <= sizeof(reset($this->data)); $c++) {
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
        if ($this->hideMetaFields && $this->data['hdr'][$col] && ($this->data['hdr'][$col][0] === '_')) {
            return null;
        }

        $cell = @$this->data[$row][$col];

        $col1 = $col + 1;
        if ($hdrElem) {
            $tdClass = $this->cellClass ? $this->cellClass . '-hdr' : 'lzy-div-table-hdr';
        } else {
            $tdClass = $this->cellClass;
        }
        $tdId = '';
        $ref = '';
        if (preg_match('/(@@ ([\w\- ]*) @@)/x', $cell, $m)) { // extract tdClass
            $tdClass = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
        }
        if (preg_match('/(<{<([^>]*)>}>)/', $cell, $m)) {    // extract ref
            $ref = trim($m[2]);
            $cell = trim(str_replace($m[1], '', $cell));
            $ref = " data-ref='$ref'";
        }
        if ($this->cellIds) {
            $tdId = " id='{$this->cellClass}_{$col}_{$row}'";
        }
        if ($this->cellMask && $this->cellMask[$row][$col]) {
            $tdClass = $this->cellMaskedClass;
        }

        $title = '';
        if ($this->enableTooltips && (strpos($tdClass, 'tooltip') !== false)) {
            $title = $cell;
            $title = preg_replace('|<br/?>|', "\n", $title);
            $title = strip_tags($title);
            $title = " title='$title'";
        }
        $tdClass = trim(str_replace('  ', ' ', "$tdClass lzy-col-$col1"));
        $tdClass = " class='$tdClass'";

        // handle option 'hide:true' from structure file:
        if (strpos($cell, '%%!off$$') !== false) {
            $cell = str_replace('%%!off$$', '', $cell);
            $tdClass .= " style='display:none;'";
        }

        // translate header elements:
        if (($hdrElem) && ($this->headersAsVars) ) {
            $cell = "{{ $cell }}";
        }

        if ($this->cellWrapper) {
            if (is_string( $this->cellWrapper)) {
                $cell = "<$this->cellWrapper>$cell</$this->cellWrapper>";
            } else {
                $cell = "<div>$cell</div>";
            }
        }
        return "<$tag$tdId$tdClass$ref$title>$cell</$tag>";
    } // getDataElem




    private function handleCaption()
    {
        if ($this->caption) {
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
    } // handleCaption




    private function handleDatatableOption($page)
    {
        if (!$this->interactive) {
            return;
        }
        $page->addModules('DATATABLES');
        $this->tableClass = trim($this->tableClass.' lzy-datatable');
        $order = '';
        if ($this->sort) {
            $sortCols = csv_to_array($this->sort);
            $headers = reset($this->data);
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

        $dataTableObj = $this->dataTableObj[$this->tableCounter] = "lzyTable[{$this->tableCounter}]";

        // launch init code:
        $jq = <<<EOT

$dataTableObj = $('#{$this->id}').DataTable({
'language':{'search':'{{ lzy-datatables-search-button }}:', 'info': '_TOTAL_ {{ lzy-datatables-records }}'},
$order$paging$pageLength
});

EOT;
        $page->addJq($jq);
        $page->addJs("\nvar lzyTable = [];");

        if (!$this->headers) {
            $this->headers = true;
        }
        $this->tableClass .= ' display';
    } // handleDatatableOption




    private function loadData()
    {
        $this->data = false;
        if ($this->inMemoryData && is_array($this->inMemoryData)) {
            $this->data = &$this->inMemoryData;
            $this->dataSource = false;
        } elseif (is_array($this->dataSource)) {    // for backward compatibility
            $this->data = $this->dataSource;
            $this->dataSource = false;

        }

        if ($this->data) {
            if ($this->headers === true) {
                $this->headerElems = array_shift($this->data);
            } else {
                $this->headerElems = explodeTrim(',|', $this->headers );
            }
            $this->nCols = isset($this->data)? sizeof( reset($this->data) ): 0;
            $this->nRows = sizeof($this->data);
            return;
        }

        if ($this->dataSource) {
                if (!file_exists($this->dataSource)) {
                    $this->dataSource = false;
                }
        }

        $this->loadDataFromFile();

        $this->sortData();

        $this->adjustTableSize();
        $this->insertCellAddressAttributes();

        $this->excludeColumns();

        $this->nCols = sizeof(reset($this->data));
        $this->nRows = sizeof($this->data);

        return;
    } // loadData



    private function activateEditingForm()
    {
        $this->tableClass .= ' lzy-table-editable';

        if ($this->inMemoryData) {
            die("Error: table->editableBy not possible when data supplied in-memory. Please use argument 'dataSource'.");
        }
        $this->page->addModules('POPUPS,HTMLTABLE');

        $out = $this->renderForm();

        return $out;
    } // renderEditingForm




    private function renderForm()
    {
        if ($this->editFormRendered) {
            return;
        }
        $this->editFormRendered = true;

        if ($this->editFormTemplate) {
            return $this->importForm();
        }

        if (@!$this->recStructure) {
            $recStructure = $this->ds->getStructure();
        }
        $form = new Forms( $this->lzy );

        // Form Head:
        $args = [
            'type' => 'form-head',
            'id' => 'lzy-edit-data-form-' . $this->tableCounter,
            'class' => 'lzy-form lzy-edit-data-form',
            'file' => '~/'.$this->dataSource,
            'warnLeavingPage' => false, //???
            'ticketHash' => $this->tickHash,
            'cancelButtonCallback' => false,
            'validate' => true,
            'labelColons' => $this->labelColons,
        ];
        if ($this->editFormArgs) {
            $args = array_merge($args, $this->editFormArgs);
        }
        $out = $form->render( $args );

        // Placeholder for rec-key:
        $out .= $form->render( [
            'type' => 'hidden',
            'name' => '_rec-key',
            'value' => '',
        ] );

        // Form Fields:
        $inx = 1;
        foreach ($recStructure['elements'] as $elemKey => $rec) {
            // ignore all data elems starting with '_':
            if (@$elemKey[0] === '_') {
                continue;
            }
            $rec['dataKey'] = $elemKey;
            $rec['label'] = isset($rec['formLabel']) ? $rec['formLabel'] : $elemKey;
            $rec['name'] = translateToIdentifier($elemKey, false, true, false);
            $rec['fieldWrapperAttr'] = "data-elem-inx='$inx'";
            $out .= $form->render($rec);
            $inx++;
        }

        // Delete:
        $out .= $form->render( [
            'type' => 'checkbox',
            'wrapperId' => "lzy-edit-rec-delete-checkbox-$this->tableCounter",
            'wrapperClass' => "lzy-edit-rec-delete-checkbox",
            'class' => "lzy-edit-rec-delete-checkbox",
            'label' => 'Delete',
            'name' => '_delete',
            'options' => '{{ lzy-edit-rec-delete-option }}',
        ] );

        // Form Buttons:
        $out .= $form->render( [
            'type' => 'button',
            'label' => '-lzy-edit-form-cancel | -lzy-edit-form-submit',
            'value' => 'cancel|submit',
        ] );

        // Form Tail:
        $out .= $form->render( ['type' => 'form-tail'] );
        $out = rtrim($out);
        $out = <<<EOT

  <div id='lzy-edit-rec-form-{$this->tableCounter}' class='lzy-edit-rec-form lzy-edit-rec-form-{$this->tableCounter}' style='display:none'>
$out
  </div><!-- /lzy-edit-rec-form -->

EOT;

        return $out;
    } // renderForm




    private function exportForm()
    {
        $exportFile = getUrlArg('exportForm', true);

        if (@!$this->recStructure) {
            $recStructure = $this->ds->getStructure();
        }

        $out = "// Form-Template for $this->dataSource\n\n";

        $formArgs = '';
        if (is_array($this->editFormArgs)) {
            foreach ($this->editFormArgs as $k => $v) {
                $formArgs .= "\n	$k: '$v',";
            }
        }
        $labelColons = $this->labelColons? 'true': 'false';

        // form head:
        $out .=<<<EOT

{{ formelem(
	type: "form-head", 
	id: 'lzy-edit-data-form-#tableCounter#',
	class: 'lzy-form lzy-edit-data-form',
	file: '\~/$this->dataSource',
	ticketHash: #tickHash#,
	cancelButtonCallback: false,
	validate: true,
	labelColons: $labelColons,$formArgs
	)
}}

EOT;
    //	warnLeavingPage: false, //???


        // Placeholder for rec-key:
        $out .= <<<EOT


{{ formelem(
	type: "hidden", 
	name: '_rec-key',
	value: '',
	)
}}

EOT;


        // Form Fields:
        foreach ($recStructure['elements'] as $elemKey => $rec) {

            // ignore all data elems starting with '_':
            if (@$elemKey[0] === '_') {
                continue;
            }
            $elems = '';
            foreach ($rec as $k => $v) {
                $elems .= "	$k: '$v',\n";
            }
            $label = isset($rec['formLabel']) ? $rec['formLabel'] : $elemKey;
            $out .= <<<EOT


{{ formelem(
	label: '$label',
$elems	dataKey: $elemKey,	
	)
}}

EOT;
        }


        // Delete:
        $out .= <<<EOT


{{ formelem(
	'type': 'checkbox',
	'wrapperId': "lzy-edit-rec-delete-checkbox-$this->tableCounter",
	'wrapperClass': "lzy-edit-rec-delete-checkbox",
	'class': "lzy-edit-rec-delete-checkbox",
	'label': 'Delete',
	'name': '_delete',
	'options': '{{ lzy-edit-rec-delete-option }}',
	)
}}

EOT;


        // Form Buttons:
        $out .= <<<EOT


{{ formelem(
	'type': 'button',
	'label': '-lzy-edit-form-cancel | -lzy-edit-form-submit',
	'value': 'cancel|submit',
	)
}}

EOT;

        // Form Tail:
        $out .= <<<EOT


{{ formelem( type: 'form-tail' ) }}

EOT;

        $writtenTo = '';
        if ($exportFile) {
            $exportFile = resolvePath($exportFile, true);
            file_put_contents($exportFile, $out);
            $this->page->addPopup("Form-Template written to '$exportFile'.");
            $writtenTo = "<p><br><em>Written to '$exportFile'</em></p>";
        }

        $out = str_replace(['{{','<'], ['&#123;{','&lt;'], $out);
        $this->page->addOverlay("<pre id='lzy-form-export'>$out</pre>$writtenTo");
        $this->page->addJq("$('#lzy-form-export').selText();");
    } // exportForm




    private function importForm()
    {
        if (is_string( $this->editFormTemplate )) {
            $file = $this->editFormTemplate;
        } else {
            $file = DEFAULT_EDIT_FORM_TEMPLATE_FILE;
        }
        $file = resolvePath($file, true);
        $out = getFile( $file );
        if ($out) {
            $out = removeCStyleComments( $out );
            $out = str_replace(['#tickHash#','#tableCounter#'], [$this->tickHash, $this->tableCounter], $out);
            $out = compileMarkdownStr( $out );
            $out = <<<EOT

<div class='lzy-edit-rec-form lzy-edit-rec-form-{$this->tableCounter}' style='display:none'>
$out
</div><!-- /lzy-edit-rec-form -->


EOT;
            return $out;
        } else {
            die("Error in htmltable.class.php::renderForm() file '$file' not found.");
        }
    } // importForm




    private function injectRowButtons()
    {
        if (!is_string( $this->customRowButtons )) {
            $this->customRowButtons = '';
        }
        $this->customRowButtons = str_replace(' ', '', $this->customRowButtons);
        $customRowButtons = ",$this->customRowButtons,";
        if ($this->editingActive && (strpos($customRowButtons, 'edit') === false)) {
            $this->customRowButtons = 'edit,'.$this->customRowButtons;
        }
        if ($this->recViewButtonsActive && (strpos(",$customRowButtons,", ',view,') === false)) {
            $this->customRowButtons = $customRowButtons = ',view,'.$this->customRowButtons;
        }

        if (!$this->customRowButtons) {
            return;
        }

        if (strpos(",$customRowButtons,", ',view,') !== false) {
            $this->tableClass .= ' lzy-rec-preview';
        }

        $cellContent = '';
        $customButtons = explodeTrim(',', $this->customRowButtons);
        foreach ($customButtons as $name) {
            if (!$name) { continue; }
            if ($name === 'view') {
                $icon = LZY_TABLE_SHOW_REC_ICON;
            } else {
                $icon = "<span class='lzy-icon lzy-icon-$name'></span>";
            }
            if (strpos($name, '<') !== false) {
                $cellContent .= $name;
            } else {
                $cellContent .= "\n\t\t\t\t<button class='lzy-table-control-btn lzy-table-$name-btn' title='{{ lzy-table-$name-btn }}'>$icon</span></button>";
            }
        }

        $cellInstructions = [
            'column' => 1,
            'header' => '&nbsp;',
            'content' => $cellContent,
        ];
        $this->addCol($cellInstructions);
    } // injectRowButtons




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




    private function checkArguments()
    {
        if (!$this->id) {
            $this->id = 'lzy-table-' . $this->tableCounter;
        }
        if ($this->tableDataAttr) {
            list($name, $value) = explodeTrim('=', $this->tableDataAttr);
            if (strpos($name, 'data-') !== 0) {
                $name = "data-$name";
            }
            $value = str_replace(['"', "'"], '', $value);
            $this->tableDataAttr = " $name='$value'";
        }

        if ($this->editableBy) {
            $this->includeCellRefs = true;
        }
    } // checkArguments




    private function extractList($value, $bracketsOptional = false)
    {
        if (!$value || is_array($value)) {
            return $value;
        }
        if (is_string($value) && ($bracketsOptional && ($value[0] !== '['))) {
            $value = "[$value]";
        }
        if (is_string($value) && preg_match('/^(?<!\\\) \[ (?!\[) (.*) \] $/x', "$value", $m)) {
            $value = $m[1];
            $ch1 = isset($value[1]) ? $value[1] : '';
            if (!($ch1 === ',') && !($ch1 === '|')) {
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
        if (!isset($data)) {
            $data['new-rec'] = [];
        }

        $nCols = sizeof( reset($data));
        $nRows = $this->nRows ? $this->nRows : sizeof($data);

        if ($this->nCols) {
            if ($this->nCols > $nCols) { // increase size
                for ($r = 0; $r < sizeof($data); $r++) {
                    $data[$r] = array_pad([], $nCols, '');
                }
            } elseif ($this->nCols < $nCols) { // reduce size
                for ($r = 0; $r < sizeof($data); $r++) {
                    $data[$r] = array_slice($data[$r], 0, $nCols);
                }
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
            if (($s >= 0) && ($s < sizeof($data))) {
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
        if ($this->liveData) {
            $this->includeCellRefs = true;
        }
        $nCols = sizeof( reset($this->data) );
        $nRows = sizeof($this->data);
        if ($this->includeCellRefs) {
            $r = 0;
            foreach ($this->data as $rKey => $rec) {
                $ic = 0;
                for ($c = 0; $c < $nCols; $c++) {
                    if ($this->includeKeys && (@$this->headerElems[$c] === '_key')) {
                        $this->data[$rKey][$c] .= "<{<$r,#>}>";
                    } else {
                        if (!isset($this->data[$rKey][$c])) {
                            $this->data[$rKey][$c] = "<{<$r,$ic>}>";
                        } else {
                            $this->data[$rKey][$c] .= "<{<$r,$ic>}>";
                        }
                        $ic++;
                    }
                }
                $r++;
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
                $nCols = sizeof( reset($data) );
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



    private function convertTimestamps()
    {
        if (!$this->autoConvertTimestamps) {
            return;
        }
        $data = &$this->data;

        $autoConvertTimestamps = $this->autoConvertTimestamps;
        if ($autoConvertTimestamps === true) {
            foreach ($data as $r => $row) {
                foreach ($data[$r] as $c => $col) {
                    $d = trim($data[$r][$c]);
                    if (preg_match('/^\d{9,}$/', $d)) {
                        $d = date('Y-m-d H:i:s', intval($d));
                        $data[$r][$c] = $d;
                    }
                }
            }

        } elseif (is_int($autoConvertTimestamps)) {
            $cInx = $autoConvertTimestamps;
            foreach ($data as $r => $row) {
                $d = trim($data[$r][$cInx]);
                if (preg_match('/^\d{9,}$/', $d)) {
                    $d = date('Y-m-d H:i:s', intval($d));
                    $data[$r][$cInx] = $d;
                }
            }

        } elseif (is_string($autoConvertTimestamps)) {
            $cInx = false;
            $rec0 = reset($data);
            foreach ($rec0 as $c => $col) {
                if ($col === $autoConvertTimestamps) {
                    $cInx = $c;
                    break;
                }
            }
            if ($cInx !== false) {
                foreach ($data as $r => $row) {
                    $d = trim($data[$r][$cInx]);
                    if (preg_match('/^\d{9,}$/', $d)) {
                        $d = date('Y-m-d H:i:s', intval($d));
                        $data[$r][$cInx] = $d;
                    }
                }
            }
        }
    } // convertTimestamps




    private function loadDataFromFile()
    {
        if (!$this->dataSource) {
            return;
        }

        if ($this->editableBy) {
            $this->options['includeTimestamp'] = true;
            $this->options['includeKeys'] = true;
        }
        $ds = new DataStorage2($this->options);
        $this->ds = $ds;
        $data0 = $ds->read();
        $this->structure = $structure = $ds->getStructure();

        $fields = $structure['elements'];
        if ($this->headers === true) {
            if (isset($structure['elemKeys'][0]) && is_int($structure['elemKeys'][0])) {
                $this->headerElems = array_shift($data);
            } else {
                $this->headerElems = $structure['elemKeys'];
            }
            if ($this->includeKeys && !isset($this->headerElems['_key'])) {
                $this->headerElems[] = '_key';
            }
        }
        $i = 0;
        foreach ($fields as $desc) {
            // handle option 'omit:true' from structure file:
            if (@$desc['omit']) {
                unset($this->headerElems[$i]);
            }
            // handle option 'hide:true' from structure file:
            if (@$desc['hide']) {
                $this->headerElems[$i] .= '%%!off$$';
            }
            $i++;
        }
        $data = [];
        $this->data = [];
        $ir = 0;
        foreach ($data0 as $r => $rec) {
            $ic = 0;
            // generally ignore all data keys starting with '_':
            if (!is_array($rec) || (@$r[0] === '_')) {
                continue;
            }
            foreach ($fields as $c => $desc) {

                // handle option 'omit:true' from structure file:
                if (@$desc['omit']) {
                    continue;
                }
                $item = isset($rec[ $c ])? $rec[ $c ]: (isset($rec[ $ic ])? $rec[ $ic ]: '');
                if (is_array($item)) {
                    if (isset($item[0])) {
                        $item = $item[0];
                    } else {
                        $item = '<span class="lzy-array-elem">' . implode('</span><span class="lzy-array-elem">', $item) . '</span>';
                    }
                }
                if (@$desc['type'] === 'password') {
                    $item = $item? PASSWORD_PLACEHOLDER: '';
                } else {
                    $item = trim($item, '"\'');
                    $item = str_replace("\n", '<br />', $item);
                }
                $item = trim($item, '"\'');

                // handle option 'hide:true' from structure file:
                if (@$desc['hide']) {
                    $item .= '%%!off$$';
                }
                $data[$r][$ic++] = $item;
            }
            $ir++;
        }

        if (!$data) {
            // add empty row:
            foreach ($structure['elemKeys'] as $c => $label) {
                $data['new-rec'][$c] = '';
            }
        }

        $this->data = $data;
    } // loadDataFromFile

    
    
    
    private function renderTableActionButtons()
    {
        $buttons = $jq = '';

        if ($this->editingActive && (($this->activityButtons === true) || (strpos(',edit,', $this->activityButtons) !== false))) {
            $buttons .= <<<EOT
    <button id='{$this->id}-add-rec' class='lzy-button lzy-table-add-rec-btn' title="{{ lzy-edit-form-new-rec }}">{{ lzy-table-add-rec-btn }}</button>
EOT;
            $jq .= <<<EOT

$('#{$this->id}-add-rec').click(function() {
    mylog('add rec');
    const \$tableWrapper = $(this).closest('.lzy-table-wrapper');
    const \$table = $('.lzy-table', \$tableWrapper);
    const tableInx = \$table.data('inx');
    lzyActiveTables[tableInx].openFormPopup( \$table );
    return;
});
EOT;
        }

        $out = <<<EOT
    
  <div class="lzy-table-action-btns">
$buttons  </div>

EOT;
            $this->page->addJq($jq);
            return $out;
    } // renderTableActionButtons




    private function renderTextResources()
    {
        if (!@$this->textResourcesRendered) {
            $this->strToAppend = <<<EOT

    <div style='display:none;'> <!-- text resources: -->
        <div id="lzy-edit-form-rec">{{ lzy-edit-form-rec }}</div>
        <div id="lzy-edit-form-new-rec">{{ lzy-edit-form-new-rec }}</div>
        <div id="lzy-edit-form-submit">{{ lzy-edit-form-submit }}</div>
        <div id="lzy-edit-form-close">{{ lzy-edit-form-close }}</div>
        <div id="lzy-edit-rec-delete-btn">{{ lzy-edit-rec-delete-btn }}</div>
        <div id="lzy-recview-header">{{ lzy-recview-header }}</div>
    </div>

EOT;
            $this->textResourcesRendered = true;
        }
    } // renderTextResources

} // HtmlTable
