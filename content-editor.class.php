<?php

class ContentEditor
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
        $this->page = $page = $lzy->page;
        $this->buttonCode = '';
        $this->edSelector = '';
        $this->p = 0;
    }




    public function injectEditor($filePath)
    {
        $this->html = $this->page->get('content');

        $inx = 0;

        while ($this->getNextSrcSegment()) {
            $inx++;

            $editorHeader = "\n\t\t\t  <button class='lzy-editor-btn' title='{{ Edit Section }}'><span class='lzy-icon lzy-icon-edit'></span></button>\n\t\t\t  <div id='lzy-editor-wrapper$inx' class='lzy-editor-wrapper'>\n\n";
            $editorFooter = "\n\t\t\t  </div><!-- /lzy-editor-wrapper -->\n";
            $edSelector = PageSource::renderEditionSelector($this->fileName);
            if ($edSelector) {
                $editionSelector = "\t\t\t$edSelector\n";
            } else {
                $editionSelector = '';
            }

            $this->html = $this->before . $this->srcSegmentTag . $editorHeader . $this->srcSegmentContent . $editorFooter . $editionSelector . $this->srcSegmentEndTag . $this->after;
            $this->p += strlen($editorHeader . $this->srcSegmentContent . $editorFooter . $this->srcSegmentEndTag);
        }

        if ($inx) {
            $this->loadEditorResources();
            $this->loadEditorButtons();
        }

        $this->page->addContent($this->html, true);
        $this->addEditorDock($filePath);
//        $this->page->addJQFiles('~sys/third-party/jqueryui/drag-resize/jquery-ui.min.js');
//        $this->page->addCssFiles('~sys/third-party/jqueryui/drag-resize/jquery-ui.min.css');
        $this->page->addModules('JQUERYUI');

    } // injectEditor



    private function addEditorDock()
    {
        $file = "<div class='lzy-editing-filename'></div>";
        $html = "\t<div id='lzy-editor-dock-wrapper' style='display: none;'>$file<div id='lzy-editor-dock'></div></div>\n";
        $this->page->addBody($html);
    } // addEditorDock




    private function loadEditorButtons()
    {
        $buttons = <<<EOT
                    <button class="lzy-cancel-btn" title='{{ Cancel }}'><span class="lzy-icon lzy-icon-cancel"></span></button>
                    <button class="lzy-save-btn" title='{{ Save }}'><span class="lzy-icon lzy-icon-save"></span></button>
                    <button class="lzy-done-btn" title='{{ Done }}'><span class="lzy-icon lzy-icon-ok"></span></button>

EOT;

        $this->html .= <<<EOT

            <div id='lzy-editing-html' style='display:none;'>
                <div class="lzy-edit-btns lzy-edit-btns1">
$buttons
                </div>
                <textarea class="lzy-editor">@data</textarea>
                <div class="lzy-edit-btns lzy-edit-btns2">
$buttons
                </div><!-- /lzy-edit-btns -->
            </div><!-- /lzy-editing-html -->

EOT;

    }




    private function loadEditorResources()
    {
        $this->page->addJqFiles("~sys/js/editor.js");
        $this->page->addCssFiles(['FONTAWESOME_CSS',"~sys/third-party/simplemde/simplemde.min.css"]);
        $this->page->addJsFiles("~sys/third-party/simplemde/simplemde.min.js");
    }




    private function getNextSrcSegment()
    {
        $p3 = false;
        $this->before = $this->srcSegmentTag = $this->srcSegmentContent = $this->srcSegmentEndTag = $this->after = $this->fileName = '';

        $this->p = strpos($this->html, 'class=\'lzy-src-wrapper', $this->p + 1);

        if ($this->p) {
            $p1 = strrpos($this->html, '<', $this->p - strlen($this->html));    // start of wrapper tag
            $p2 = strpos($this->html, '>', $this->p);                                  // end of wrapper tag

            if (($p1 !== false) && $p2) {
                $this->srcSegmentTag = substr($this->html, $p1, $p2 - $p1 + 1);

                // extract filename:
                if (preg_match("/data-lzy-filename='([^']+)'/", $this->srcSegmentTag, $m)) {
                    $this->fileName = $m[1];
                } else {
                    $this->fileName = false;
                    return false; // filename not found
                }
                if (preg_match("/\<(\w+)/", $this->srcSegmentTag, $m)) {
                    $segTag = $m[1];
                }
                $this->srcSegmentEndTag = "</$segTag><!-- /lzy-src-wrapper -->";
                $p3 = strpos($this->html, $this->srcSegmentEndTag, $p2); // start of wrapper end tag
                $p4 = $p3 + strlen($this->srcSegmentEndTag); // end of wrapper end tag

                if ($p3) {
                    $this->before = substr($this->html, 0, $p1);
                    $this->after = substr($this->html, $p4);
                    $this->srcSegmentContent = substr($this->html, $p2+1, $p3-$p2-1);
                    $this->srcSegmentEndTag = "\t\t\t{$this->srcSegmentEndTag}\n";
                }
            }
        }
        return ($p3 !== false);
    }

} // ContentEditor

