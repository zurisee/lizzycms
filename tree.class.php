<?php
/*
 * Tree Class
 *
 * translates between indented tree notation and nested parenthis syntax
 *  -> CSS-Markdown <=> CSS/SCSS
 */


class Tree
{
    public function __construct( $options = [] )
    {
        $this->options = $options;
        $this->openTag = @$options[ 'openTag' ]? $options[ 'openTag' ] : '{';
        $this->closeTag = @$options[ 'closeTag' ]? $options[ 'closeTag' ] : '}';
        $this->levelIndent = @$options[ 'levelIndent' ]? $options[ 'levelIndent' ] : '    ';
        $this->initialIndent = @$options[ 'initialIndent' ]? $options[ 'initialIndent' ] : '';
        $this->recTemplate = @$options[ 'recTemplate' ]? $options[ 'recTemplate' ] : [];
        $this->autoAppendSemicolon = @$options[ 'autoAppendSemicolon' ]? $options[ 'autoAppendSemicolon' ] : false;
        $this->tree = [];
    } // __construct



    public function toScss( $str )
    {
        if (!isset($this->options[ 'autoAppendSemicolon' ])) {
            $this->autoAppendSemicolon = true;
        }
        $this->parse( $str );
        return $this->render();
    } // toScss



    public function parse( $source )
    {
        $source = removeHashTypeComments( $source );
        $source = removeCStyleComments( $source );
        if (!$source) {
            return [];
        }

        $list = $this->parseSource( $source );
        $this->tree = $tree = $this->parseList( $list );
        return $tree;
    } // parse




    public function render()
    {
        $out = $this->_render(false, 0, $this->initialIndent);
        return $out;
    } // render




    private function _render($tree, $level, $indent)
    {
        $level++;
        $indent = str_replace('\t', $this->levelIndent, $indent);
        if (!$tree) {
            $tree = $this->tree;
        }
        $out = '';

        foreach($tree as $n => $elem) {
            if (!is_int($n)) { continue; } // skip attributes, i.e. non-children

            $name = $elem['name'];
            if (isset($elem[0])) {	// does it have children?

                // --- recursion:
                $out1 = $this->_render($elem, $level, "$indent$this->levelIndent");

                if ($out1) {
                    $out .= "$indent$name $this->openTag\n";

                    $out .= "$out1";
                    $out .= "$indent$this->closeTag\n";
                } else {
                    $name = rtrim( $name );
                    if ($this->autoAppendSemicolon && (strpos(';,', substr($name, -1)) === false)) {
                        $name .= ';';
                    }
                    $out .= "$indent$name\n";
                }

            } else {
                $name = rtrim( $name );
                if ($this->autoAppendSemicolon && (strpos(';,', substr($name, -1)) === false)) {
                    $name .= ';';
                }
                $out .= "$indent$name\n";
            }
        }
        return $out;
    } // _render




    private function parseSource( $source )
    {
        $list = [];
        if (is_array($source)) {
            $lines = $source;
        } else {
            $lines = explode("\n", $source);
        }

        $lastLevel = 0;
        $indent = '';
        foreach ($lines as $i => $line) {
            if (!$line) {
                continue;
            }
            $rec = $this->recTemplate;

            // extract name / visibleName and split from rest of line:
            // check whether page name in {{ }} -> to be translated later:
            if (preg_match('/^ (\s*) (.*) /x', $line, $m)) {
                $indent = $m[1];
                $name = $m[2];
            }
            $name = trim($name);
            if ($name) {
                $c1 = $name[ strlen($name) - 1];
                if ($c1 === $this->openTag) {
                    $name = substr($name, 0, -1);
                } elseif ($name[0] === $this->closeTag) {
                    $name = substr($name, 1);
                }
            }
            if (!$name) {
                continue;
            }
            $rec['name'] = $name;

            // determine level:
            $rec['level'] = $level = $this->determineLevel( $indent, $lastLevel, $line );
            $lastLevel = $level;

            // add to list:
            $list[] = $rec;

        } // /foreach
        return $list;
    } // parseSource




    private function parseList( $list )
    {
        $this->list = $list;
        $this->listInx = 0;
        $this->listSize = sizeof($this->list);
        $tree = $this->_parseList('', 0, null);
        return $tree;
    } // parseList




    private function _parseList($path, $parentLevel, $parentInx)
    {
        $listInx = &$this->listInx;
        $treeInx = 0;         // $treeInx -> counter within level
        $tree = [];
        while ($listInx < $this->listSize) {
            // create tree elem as ref to list elem:
            $tree[ $treeInx ] = &$this->list[$listInx];
            $currTreeElem = &$this->list[$listInx];

            $currLevel = $currTreeElem['level'];
            $currInx = $listInx;

            $currTreeElem['parentInx'] = $parentInx;

            $treeInx++;
            $listInx++;

            $nextLevel = @$this->list[ $listInx]['level'];
            $up = ($nextLevel < $currLevel);
            $down = ($nextLevel > $currLevel);
            if ($up) {
                return $tree;

            } elseif ($down) {
                $currTreeElem['hasChildren'] = true;

                $subtree = $this->_parseList($currTreeElem, $currLevel, $currInx);

                $currTreeElem = array_merge($currTreeElem, $subtree);
                $nextLevel = @$this->list[$listInx]['level'];
                $up = ($nextLevel < $currLevel);
                if ($up) {
                    return $tree;
                }
            }
        }
        return $tree;
    } // _parseList




    private function determineLevel( $indent, $lastLevel, $line )
    {
        // determine level:
        // idententation -> 4 blanks count as one tab = level
        if (strlen($indent) === 0) {
            $level = 0;
        } else {
            // convert every 4 blanks to a tab, then remove all remaining blanks => level
            $indent = str_replace(['    ', "\t"], "=", $indent);
            $indent = str_replace(' ', '', $indent);
            $level = strlen($indent);
        }
        if (($level - $lastLevel) > 1) {
            writeLogStr("Error in sitemap.txt: indentation on line $line (level: $level / lastLevel: $lastLevel)", true);
            $level = $lastLevel + 1;
        }
        return $level;
    } // determineLevel

} // Tree