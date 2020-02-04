<?php
define('EMOJINAMES_FILE', '_lizzy/rsc/emojis.json');

class LizzyExtendedMarkdown extends \cebe\markdown\MarkdownExtra
{
    private $cssAttrNames =
        ['align', 'all', 'animation', 'backface', 'background', 'border', 'bottom', 'box',
            'break', 'caption', 'caret', 'charset', 'clear', 'clip', 'color', 'column', 'columns',
            'content', 'counter', 'cursor', 'direction', 'display', 'empty', 'filter', 'flex',
            'float', 'font', 'grid', 'hanging', 'height', 'hyphens', 'image', 'import', 'isolation',
            'justify', 'keyframes', 'left', 'letter', 'line', 'list', 'margin', 'max', 'media', 'min',
            'mix', 'object', 'opacity', 'order', 'orphans', 'outline', 'overflow', 'Specifies',
            'padding', 'page', 'perspective', 'pointer', 'position', 'quotes', 'resize', 'right',
            'scroll', 'tab', 'table', 'text', 'top', 'transform', 'transition', 'unicode', 'user',
            'vertical', 'visibility', 'white', 'widows', 'width', 'word', 'writing', 'z-index'];

    public function __construct($mymd, $page = false)
    {
        $this->mymd = $mymd;
        $this->page = $page;
    } // __construct
    
    protected function identifyAuthorsDirective($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a fenced code block
        if (strpos($line, '((') !== false) {
            return 'authorsDirective';
        }
        return false;
    }
    
    protected function consumeAuthorsDirective($lines, $current)
    {
        // create block array
        $block = [
            'authorsDirective',
            'content' => [],
        ];
        $line = rtrim($lines[$current]);
    
        // detect class or id and fence length (can be more than 3 backticks)
        $p1 = strpos($line, '((');
        $p2 = strrpos($line, '))');
        $directive = trim(substr($line, $p1+2, $p2-$p1-2));
        $class = '';
        if (!empty($directive)) {
            if (preg_match('/^([\w\-\#\.]+)/', $directive, $m)) {
                $class = $m[1];
            }
        }
        $block['class'] = $class;
        $block['content'][] = substr($line, 0, $p1).substr($line, $p2+2);
        $block['directive'] = $directive;
    
        // consume all lines until \n\n
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            if (!empty($lines[$i]) || !empty($lines[$i-1])) {
                $block['content'][] = $lines[$i];
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }

    protected function renderAuthorsDirective($block)
    {
        $class = ' class="authors_directive '.$block['class'].'"';
        $out = implode("\n", $block['content']);
        $out = \cebe\markdown\Markdown::parse($out);
        $out = "\t<span class='authors_directive_tag'>(( {$block['directive']} ))</span>\n\t<div$class>\n$out\n\t</div>\n";
        return $out;
    }




    // ---------------------------------------------------------------
    protected function identifyAsciiTable($line, $lines, $current)
    {
        // asciiTable starts with '|==='
        if (strncmp($line, '|===', 4) === 0) {
            return 'asciiTable';
        }
        return false;
    }



    protected function consumeAsciiTable($lines, $current)
    {
        $block = [
            'asciiTable',
            'content' => [],
            'caption' => false,
            'args' => false
        ];
        $firstLine = $lines[$current];
        if (preg_match('/^\|===*\s+(.+)$/', $firstLine, $m)) {
            $block['args'] = $m[1];
        }
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (strncmp($line, '|===', 4) !== 0) {
                $block['content'][] = $line;
            } else {
                // stop consuming when code block is over
                break;
            }
        }
        return [$block, $i];
    }



    protected function renderAsciiTable($block)
    {
        $table = [];
        $nCols = 0;
        $row = 0;
        $col = -1;

        if (isset($this->mymd->trans->lzy->page->asciitable)) {
            $this->mymd->trans->lzy->page->asciitable++;
        } else {
            $this->mymd->trans->lzy->page->asciitable = 1;
        }
        $inx = $this->mymd->trans->lzy->page->asciitable;

        for ($i = 0; $i < sizeof($block['content']); $i++) {
            $line = $block['content'][$i];

            if (strncmp($line, '|---', 4) === 0) {  // new row
                $row++;
                $col = -1;
                continue;
            }

            if (isset($line[0]) && ($line[0] == '|')) {  // next cell starts
                $line = substr($line,1);
                $cells = preg_split('/\s(?<!\\\)\|/', $line); // pattern is ' |'
                foreach ($cells as $cell) {
                    $col++;
                    $table[$row][$col] = str_replace('\|','|', $cell);
                }

            } else {
                $table[$row][$col] .= "\n$line";
            }
            $nCols = max($nCols, $col);
        }
        $nCols++;
        $nRows = $row+1;


        $id = $class = $style = $attr = $text = $tag = '';
        if ($block['args']) {
            $args = parseInlineBlockArguments($block['args'], true);
            list($tag, $id, $class, $style, $attr, $text) = $args;
        }
        if (!$id) {
            $id = "lzy-table$inx";
        }
        if (!$class) {
            $class = "lzy-table$inx";
        }
        if ($style) {
            $style = " style='$style'";
        }
        if ($tag) {
            $text = $text ? "$tag $text": $tag;
        }

            // now render the table:
        $out = "\t<table id='$id' class='lzy-table $class'$style$attr><!-- asciiTable -->\n";
        if ($text) {
            $out .= "\t  <caption>$text</caption>\n";
        }

        // render header as defined in first row, e.g. |# H1|H2
        $row = 0;
        if (isset($table[0][0]) && ($table[0][0][0] === '#')) {
            $row = 1;
            $table[0][0] = substr($table[0][0],1);
            $out .= "\t  <thead>\n";
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[0][$col]) ? $table[0][$col] : '';
                $cell = compileMarkdownStr(trim($cell));
                $cell = trim($cell);
                if (preg_match('|^<p>(.*)</p>$|', $cell, $m)) {
                    $cell = $m[1];
                }
                $out .= "\t\t\t<th class='th$col'>$cell</th>\n";
            }
            $out .= "\t  </thead>\n";
        }

        $out .= "\t  <tbody>\n";
        for (; $row < $nRows; $row++) {
            $out .= "\t\t<tr>\n";
            $colspan = 1;
            for ($col = 0; $col < $nCols; $col++) {
                $cell = isset($table[$row][$col]) ? $table[$row][$col] : '';
                if ($cell === '>') {    // colspan?
                    $colspan++;
                    continue;
                } elseif ($cell) {
                    $cell = compileMarkdownStr(trim($cell));
                    $cell = trim($cell);
                    if (preg_match('|^<p>(.*)</p>$|', $cell, $m)) {
                        $cell = $m[1];
                    }
                }
                $colspanAttr = '';
                if ($colspan > 1) {
                    $colspanAttr = " colspan='$colspan'";
                }
                $out .= "\t\t\t<td class='row".($row+1)." col".($col+1)."'$colspanAttr>$cell</td>\n";
                $colspan = 1;
            }
            $out .= "\t\t</tr>\n";
        }

        $out .= "\t  </tbody>\n";
        $out .= "\t</table><!-- /asciiTable -->\n";

        return $out;
    } // AsciiTable




    // ---------------------------------------------------------------
    protected function identifyDivBlock($line, $lines, $current)
    {
        // if a line starts with at least 3 colons it is identified as a fenced code block
        if (strncmp($line, ':::', 3) === 0) {
            return 'fencedCode';
        }
        return false;
    } // identifyDivBlock
    
    protected function consumeDivBlock($lines, $current)
    {
        // create block array
        $block = [
            'divBlock',
            'content' => [],
            'tag' => 'div',
            'attributes' => '',
            'literal' => false
        ];
        $line = rtrim($lines[$current]);
    
        // detect class or id and fence length (can be more than 3 backticks)
        if (preg_match('/(:{3,10})(.*)/',$line, $m)) {
            $fence = $m[1];
            $rest = $m[2];
        } else {
            fatalError("Error in Markdown source line $current: $line", 'File: '.__FILE__.' Line: '.__LINE__);
        }

        list($tag, $attr, $lang, $comment, $literal, $mdCompile) = $this->parseInlineStyling($rest);

        $block['tag'] = $tag ? $tag : 'div';
        $block['attributes'] = $attr;
        $block['comment'] = $comment;
        $block['lang'] = $lang;
        $block['literal'] = $literal;
        $block['mdcompile'] = $mdCompile;

        // consume all lines until :::
        for($i = $current + 1, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (preg_match('/^(:{3,10})\s*(.*)/', $line, $m)) { // it's a potential fence line
                $fenceEndCandidate = $m[1];
                $rest = $m[2];
                if ($fence != $fenceEndCandidate) {
                    $block['content'][] = $line;
                } elseif ($rest != '') {
                    $i--;
                    break;
                } else {
                    break;
                }
            } else {
                $block['content'][] = $line;
            }
        }
        if ($block['literal']) {     // for shielding, we just encode the entire block to hide it from further processing
            $content = implode("\n", $block['content']);
            unset($block['content']);
            $content = str_replace(['@/@lt@\\@', '@/@gt@\\@'], ['<', '>'], $content);
            $block['content'][0] = base64_encode($content);
        }
        return [$block, $i];
    } // consumeDivBlock

    protected function renderDivBlock($block)
    {
        $tag = $block['tag'];
        $attrs = $block['attributes'];
        $comment = $block['comment'];

        // exclude blocks with lang option set but is not current language:
        if ($block["lang"] && ($block["lang"] !== $GLOBALS["globalParams"]["lang"])) {
            return '';
        }

        $out = implode("\n", $block['content']);

        if (!$block['literal'] && $block['mdcompile']) {  // within such a block we need to compile explicitly:
            $out = \cebe\markdown\Markdown::parse($out);
        }
        if ($block['literal']) {     // flag block for post-processing
            $attrs = trim("$attrs data-lzy-literal-block='true'");
        }
        return "\t\t<$tag $attrs>\n$out\n\t\t</$tag><!-- /$comment -->\n\n";
    } // renderDivBlock




    // ---------------------------------------------------------------
    protected function identifyTabulator($line, $lines, $current)
    {
        if (preg_match('/\{\{ \s* tab\b [^\}]* \s* \}\}/x', $line)) { // identify patterns like '{{ tab( 7em ) }}'
            return 'tabulator';
        }
        return false;
    } // identifyTabulator




    protected function consumeTabulator($lines, $current)
    {
        $block = [
            'tabulator',
            'content' => [],
        ];

        $last = $current;
        // consume following lines containing {tab}
        for($i = $current, $count = count($lines); $i < $count-1; $i++) {
            $line = $lines[$i];
            if (preg_match('/\{\{\s* tab\b[^\}]* \s*\}\}/x', $line, $m)) {
                $block['content'][] = $line;
                $last = $i;
            } elseif (empty($line)) {
                continue;
            } else {
                $i--;
                break;
            }
        }
        return [$block, $last];
    } // consumeTabulator




    protected function renderTabulator($block)
    {
        $out = '';
        $s = isset($block['content'][0]) ? $block['content'][0] : '';
        $wrapperAttr = '';
        if (strpos($s, "@/@ul@\\@") !== false) {        // handle 'ul'
            $tag = 'li';
            $wrapperTag = 'ul';

        } elseif (strpos($s, "@/@ol@\\@") !== false) {  // handle 'ol', optionally with start value
            if (preg_match("|^!(\d+)@(.*)|", substr($s, 8), $m)) {
                $wrapperTag = "ol";
                $wrapperAttr = " start='{$m[1]}'";
                $block['content'][0] = $m[2];
            } else {
                $wrapperTag = 'ol';
            }
            $tag = 'li';

        } else {        // all other cases:
            $tag = 'div';
            $wrapperTag = 'div';
        }

        foreach ($block['content'] as $l) {
            $l = preg_replace('/\{\{\s* tab\b \(? \s* ([^\)\s\}]*) \s* \)? \s*\}\}/x', "@@$1@@tab@@", $l);
            $parts = explode('@@tab@@', $l);
            $line = '';
            foreach ($parts as $n => $elem) {
                if (preg_match('/(.*)@@([\w\.]*)$/', $elem, $m)) {
                    $elem = $m[1];
                    if ($m[2]) {
                        $width[$n] = $m[2];
                    }
                }
                $style = (isset($width[$n])) ? " style='width:{$width[$n]};'" : '';
                $line .= "@/@lt@\\@span class='c".($n+1)."'$style@/@gt@\\@$elem@/@lt@\\@/span@/@gt@\\@";
            }
            $out .= "@/@lt@\\@$tag@/@gt@\\@$line@/@lt@\\@/$tag@/@gt@\\@\n";
        }
        $out = \cebe\markdown\Markdown::parse($out);
        $out = str_replace(['<p>', '</p>'], '', $out);
        $out = str_replace(['@/@ul@\\@', '@/@ol@\\@'], '', $out);
        return "<$wrapperTag$wrapperAttr class='tabulator_wrapper'>\n$out</$wrapperTag>\n";
    } // renderTabulator




    // ---------------------------------------------------------------
    protected function identifyCheckList($line, $lines, $current)
    {
        if (preg_match('/^ \s* - \s? \[ \s? x? \s? ] /x', $line)) {
            return 'checkList';
        }
        return false;
    } // identifyCheckList


    protected function consumeCheckList($lines, $current)
    {
        // create block array
        $block = [
            'checkList',
            'content' => [],
        ];
        // consume all lines until 2 empty line
        $nEmptyLines = 0;
        for($i = $current, $count = count($lines); $i < $count; $i++) {
            $line = $lines[$i];
            if (!preg_match('/\S/', $line)) {                               // line empty
                if ($nEmptyLines++ > 0) {                                           // already second empty line
                    break;
                }
                continue;
            } elseif ($line && !preg_match('/^\s* -\s? \[ \s? x? \s? ] /x', $line)) {  // no pattern [] or [x]
                $i--;
                break;
            }
            $block['content'][] = $lines[$i];
        }
        return [$block, $i];
    } // consumeCheckList


    protected function renderCheckList($block)
    {
        if (isset($this->mymd->trans->lzy->page->checklist)) {
            $this->mymd->trans->lzy->page->checklist++;
        } else {
            $this->mymd->trans->lzy->page->checklist = 1;
        }
        $inx = $this->mymd->trans->lzy->page->checklist;
        $cnt = 1;
        $line = $block['content'][0];
        list($line, $tag, $id, $class, $attr) = $this->mymd->postprocessInlineStylings($line, true);
        $i=0;
        if (!$id) {
            $id = "lzy-checklist-$inx";
        }

        $this->attr = $attr;

        $block['content'][0] = $line;
        $out = $this->_renderCheckList($i, $block['content'], $inx, 1, $cnt);
        $out = "\t<ul id='$id' class='lzy-checklist lzy-checklist-$inx' {$this->attr}>\n$out\t</ul>\n";
        return $out;
    } // renderCheckList




    private function _renderCheckList(&$i, $lines, $inx, $indent0, &$cnt)
    {
        $out = '';
        while ($i<sizeof($lines)) {
            $line = $lines[$i];
            if (preg_match('/^(\s*) -\s? \[ \s?(x?)\s? \]\s (.*) /x', $line, $m)) {
                $elem = $m[3];
                $checked = $m[2] ? ' checked': '';
                $lead = str_replace("\t", '    ', $m[1]);
                $indent = intval(strlen($lead) / 4) + 1;
                $indentStr = str_pad('', $indent * 4);
                $cls = "lzy-checklist-elem-$indent";
                $inpName = preg_replace('/\W/', '_', substr(trim($elem), 0, 8));
                $input = "\t$indentStr  <input type='checkbox' class='lzy-checklist-input lzy-checklist-input-$cnt' name='cb_{$inx}_{$cnt}_$inpName'$checked disabled />";
                $elem = "\t$indentStr  <span>$elem</span>";

                if ($indent0 == $indent) {                  // same level -> add elem
                    $out .= "\t$indentStr<li class='lzy-checklist-elem $cls'>\n$input\n$elem\n\t$indentStr</li>\n";
                    $cnt++;

                } elseif ($indent0 < $indent) {             // descend
                    $indent0 = intval(strlen($lead) / 4);
                    $indentStr0 = str_pad('', $indent0 * 4);
                    $out = substr($out, 0, -6)."\n";
                    $out .= "\t$indentStr0  <ul>\n";
                    $out .= $this->_renderCheckList($i, $lines, $inx, $indent, $cnt);
                    $indent = intval(strlen($lead) / 4);
                    $indentStr = str_pad('', $indent * 4);
                    $out .= "\t$indentStr0  </ul>\n";
                    $out .= "\t$indentStr</li>\n";
                    $i--;

                } else {                                    // ascend
                    return $out;
                }
            }
            $i++;
        }
        return $out;
    } // _renderCheckList




    // ---------------------------------------------------------------
    protected function identifyDefinitionList($line, $lines, $current)
    {
        // if next line starts with ': ', it's a dl:
        if (isset($lines[$current+1]) && strncmp($lines[$current+1], ': ', 2) === 0) {
            return 'definitionList';
        }
        return false;
    } // identifyDefinitionList



    
    protected function consumeDefinitionList($lines, $current)
    {
        // create block array
        $block = [
            'definitionList',
            'content' => [],
        ];

        // consume all lines until 2 empty line
        $nEmptyLines = 0;
        for($i = $current, $count = count($lines); $i < $count; $i++) {
            if (!preg_match('/\S/', $lines[$i])) {  // empty line
                if ($nEmptyLines++ > 0) {
                    break;
                }
            } else {
                $nEmptyLines = 0;
            }
            $block['content'][] = $lines[$i];
        }
        return [$block, $i];
    } // consumeDefinitionList




    protected function renderDefinitionList($block)
    {
        $out = '';
        $md = '';
        foreach ($block['content'] as $line) {
            if (!trim($line)) {                             // end of definitin item reached
                if ($md) {
                    if (preg_match('/\s\s$/', $md)) { // 2 blanks at end of line -> insert line break
                        $md .= "<br />\n";
                    }
                    $html = trim(compileMarkdownStr($md, true));
                    $html = "\t\t\t".str_replace("\n", "\n\t\t\t", $html);
                    $out .= $html;
                    $md = '';
                }
                $out .= "\n\t\t</dd>\n";

            } elseif (preg_match('/^: /', $line)) { // within dd block
                $md .= substr($line, 2);
                if (preg_match('/\s\s$/', $md)) { // 2 blanks at end of line -> insert line break
                    $md .= "<br />\n";
                }

            } else {                                        // new dt block starts
                $line = trim(compileMarkdownStr($line, true));
                $out .= "\t\t<dt>$line</dt><dd>\n";
            }
        }
        if ($md) {
            $html = compileMarkdownStr($md, true);
            $html = "\t\t\t".str_replace("\n", "\n\t\t\t", $html);
            $out .= substr($html, 0, -3);
        }
        $out = "\t<dl>\n$out\t</dl>\n";
        return $out;
    } // renderDefinitionList




     // ---------------------------------------------------------------
    /**
     * @marker ~~
     */

    protected function parseStrike($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~~)
        if (preg_match('/^~~(.+?)~~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['strike', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '~~'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderStrike($element)
    {
        return '<del>' . $this->renderAbsy($element[1]) . '</del>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ~
     */

    protected function parseSubscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^~(.{1,9}?)~/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['subscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~ we just return the marker and skip 1 character
        return [['text', '~'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSubscript($element)
    {
        return '<sub>' . $this->renderAbsy($element[1]) . '</sub>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ^
     */
    protected function parseSuperscript($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\^(.{1,20}?)\^/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['superscript', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', '^'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderSuperscript($element)
    {
        return '<sup>' . $this->renderAbsy($element[1]) . '</sup>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ==
     */
    protected function parseMarked($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ==)
        if (preg_match('/^==(.+?)==/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['marked', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '=='], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderMarked($element)
    {
        return '<mark>' . $this->renderAbsy($element[1]) . '</mark>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker ++
     */
    protected function parseInserted($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^\+\+(.+?)\+\+/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['inserted', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '++'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderInserted($element)
    {
        return '<ins>' . $this->renderAbsy($element[1]) . '</ins>';
    }




    // ---------------------------------------------------------------
    /**
     * @marker __
     */
    protected function parseUnderlined($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing ~)
        if (preg_match('/^__(.+?)__/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['underlined', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '__'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderUnderlined($element)
    {
        return '<span class="underline">' . $this->renderAbsy($element[1]) . '</span>';
    }



    // ---------------------------------------------------------------
    /**
     * @marker ``
     */
    protected function parseDoubleBacktick($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^``(.+?)``/', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['doublebacktick', $this->parseInline($matches[1])],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '``'], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderDoubleBacktick($element)
    {
        if (isset($this->page->doubleBacktick)) {
            $tag = $this->page->doubleBacktick;
        } else {
            $tag = 'samp';
        }
        return "<$tag>" . $this->renderAbsy($element[1]) .  "</$tag>";
    }




    // ---------------------------------------------------------------
    /**
     * @marker ![
     */
    // ![alt text](img.jpg "Caption...")
    protected function parseImage($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^!\[ ( .+? ) ]\( ( .+? ) \)/x', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['image', $matches[1].']('.$matches[2]],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ) we just return the marker and skip 2 characters
        return [['text', '!['], 2];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderImage($element)
    {
        list($alt, $src) = explode('](', $element[1]);
        if (preg_match('/^ (["\']) ( .+ ) \1 \s* /x', $alt, $m)) {
            $alt = $m[2];
        }
        if (preg_match('/^ (["\']) ( .+ ) \1 \s* /x', $src, $m)) {
            $src = $m[2];
        }
        $alt = str_replace(['"', "'"], '&quot;', $alt);
        $caption = '';
        if (preg_match('/^ (.*?) \s+ (.*) /x', $src, $m)) {
            $src = $m[1];
            $caption = $m[2];
            if (preg_match('/^ (["\']) ( .+ ) \1 \s* /x', $src, $mm)) {
                $src = $mm[2];
            }
            if (preg_match('/^ (["\']) ( .+ ) \1 \s* /x', $caption, $mm)) {
                $caption = $mm[2];
            }
            $caption = str_replace(['"', "'"], '&quot;', $caption);
        }
        $src = trim($src);
        if ($caption) {
            $caption = ", caption: \"$caption\"";
        }
        return "{{ img(src:\"$src\", alt:\"$alt\"$caption) }}";
    }




    // ---------------------------------------------------------------
    /**
     * @marker [
     */
    // [link text](https://www.google.com)
    protected function parseLink($markdown)
    {
        // check whether the marker really represents a strikethrough (i.e. there is a closing `)
        if (preg_match('/^\[ (["\']) ( .+? ) \1 ]\( ( .+? ) \)/x', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['link', $matches[2].']('.$matches[3]],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        } elseif (preg_match('/^\[ ( .+? ) ]\( ( .+? ) \)/x', $markdown, $matches)) {
            return [
                // return the parsed tag as an element of the abstract syntax tree and call `parseInline()` to allow
                // other inline markdown elements inside this tag
                ['link', $matches[1].']('.$matches[2]],
                // return the offset of the parsed text
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 2 characters
        return [['text', '['], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderLink($element)
    {
        list($text, $url) = explode('](', $element[1]);
        $text = str_replace('"', '&quot;', $text);
        $attr = '';
        if (stripos($url, 'http') === 0) {
            $attr = ", type:'extern'";
        }
        return "{{ link(\"$url\", \"$text\"$attr) }}";
    }




    // ---------------------------------------------------------------
    /**
     * @marker :
     */
    protected function parseEmoji($markdown)
    {
        // check whether the marker really represents a emoji/icon (i.e. there is a closing :)
        if (preg_match('/^ : ([a-z0-9_-]{2,35}) : /x', $markdown, $matches)) {
            return [
                ['emoji', $matches[1]],
                strlen($matches[0])
            ];
        }
        // in case we did not find a closing ~~ we just return the marker and skip 1 character
        return [['text', ':'], 1];
    }

    // rendering is the same as for block elements, we turn the abstract syntax array into a string.
    protected function renderEmoji($element)
    {
        if (!isset($this->emojiNames)) {
            $this->emojiNames = json_decode( file_get_contents(EMOJINAMES_FILE), true);
        }
        if (isset($this->emojiNames[$element[1]])) {
            $out = $this->emojiNames[$element[1]];
            $out = "<span class='lzy-emoji' data-icon='$out'>&#8203;</span>";
        } else {
            $out = "<span class='lzy-icon lzy-icon-{$element[1]}'></span>";
        }
        return $out;
    }



    //....................................................
    private function parseInlineStyling($line, $returnElements = false)
    {
        // examples: '.myclass.sndCls', '#myid', 'color:red; background: #ffe;'
        if (!$line) {
            return ['', '', '', '', false, true];
        }

        return parseInlineBlockArguments($line, $returnElements);
    } // parseInlineStyling


    private function isCssProperty($str)
    {
        $res = array_filter($this->cssAttrNames, function($attr) use ($str) {return (substr_compare($attr, $str, 0, strlen($attr)) == 0); });
        return (sizeof($res) > 0);
    }

} // class MyMarkdown
