<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Markdown Adaptation and Extension
*/

use Symfony\Component\Yaml\Yaml;
use cebe\markdown\Markdown;
use cebe\markdown\MarkdownExtra;

require_once SYSTEM_PATH.'markdown_extension.class.php';

class MyMarkdown
{
	private $page = null;
	private $md = null;
	private $variable;
	
	private $replaces = array(
		'\-\>' => '&rarr;',
		'\-&rarr;' => '-->',	// in case it was an HTML comment '-->'
		'\=\>' => '&rArr;',
		' \-\- ' => ' &ndash; ',
		'(?<!\.)\.\.\.(?!\.)' => '&hellip;',
		'\bEURO\b' => '&euro;',
		'\bBR\b' => '<br>',
		'\bNL\b' => '<br>&nbsp;',
		'\bSPACE\b' => '&nbsp;',
		'sS' => 'ß',
		'\[_\]' => '&nbsp;&nbsp;&nbsp;&nbsp;',
		'CLEAR' => '<div style="clear:both;"></div>',
	);

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



	public function __construct($trans = false)
    {
        $this->trans = $trans;
    }  // __construct




    //....................................................
	public function parse($str, $page)
	{
		$this->page = $page;
		$this->variable = array();

		$str = $this->doMDincludes($str);
		
		$this->setDefaults();
		
		if ($this->page->mdVariant == 'standard') {	// Markdown
			$str = $this->handeCodeBlocks($str);
			$this->md = new Markdown;
			$str = $this->md->parse($str);

		} elseif ($this->page->mdVariant == 'extra') {	// MarkdownExtra
			$str = $this->handeCodeBlocks($str);
			$this->md = new MarkdownExtra;
			$str = $this->md->parse($str);

		} else {										// Lizzy's MD extensions
			$str = $this->preprocess($str);
			if (isset($this->page->md) && ($this->page->md == false)) {
				$this->page->addContent($str);
				return $this->page;
			}

			$this->md = new MyExtendedMarkdown($page);
			$str = $this->md->parse($str);
			$str = $this->postprocess($str);
			
			$str = $this->doReplaces($str);
		}
		$this->page->addContent($str);
		return $this->page;
	} // parse





    //....................................................
    // public function
    public function parseStr($str, $page = false)
    {
        $str = $this->preprocess($str);

        $this->md = new MyExtendedMarkdown($page);
        $str = $this->md->parse($str);
        $str = $this->postprocess($str);

        $str = $this->doReplaces($str);
        return $str;
    } // parseStr




	//....................................................
	private function doMDincludes($str)
	{
		while (preg_match('/(.*) (?<!\\\\)\{\{ \s* include\( ([^)]*\.md) [\'"]? \) \s* \}\}(.*)/xms', $str, $m)) {
			$s1 = $m[1];
			$s2 = $m[3];
			$file = str_replace("'", '', $m[2]);
			$file = str_replace('"', '', $file);
			$file = resolvePath($file);
			if (file_exists($file)) {
				$s = getFile($file, true);
			} else {
				$s = "[include file '$file' not found]";
			}
			$str = $s1.$s.$s2;
		}
		return $str;
	} // doMDincludes





	//....................................................
	private function setDefaults()
	{
		if (!isset($this->page->mdVariant)) {		// 'mdVariant' or 'markdown' -> true, false, 'extended'
			if (!isset($this->page->markdown)) {
				$this->page->mdVariant = 'extended';
			} else {
				$this->page->mdVariant = $this->page->markdown;
			}
		}
		
		if (!isset($this->page->shieldHtml)) {		// shieldHtml -> true,false
			$this->page->shieldHtml = false;	// default
		} else {
			$this->page->shieldHtml = $this->page->shieldHtml;
		}
		
	} // setDefaults





	//....................................................
	private function doReplaces($str)
	{
		foreach ($this->replaces as $key => $value) {
			$str = preg_replace("/$key/", $value, $str);
		}
		return $str;
	} //doReplaces




	//....................................................
	private function handeCodeBlocks($str)
	{
		$lines = explode(PHP_EOL, $str);
		$out = '';
		foreach($lines as $l) {
			$out .= preg_replace('/```.*/', '```', $l)."\n";
		}
		return $out;
	} // handeCodeBlocks





	//....................................................
	private function preprocess($str)
	{
		if (preg_match("/\n\>\s/", $str, $m)) {	// is there a blockquote? ('> ' at beginning of line)
			$lines = explode("\n", $str);
			$lastBlockquoteLine = 0;
			foreach ($lines as $i => $l) {
				if ((($s = substr($l,0,2)) == '> ') || ($s == ">\t")) {
					$lines[$i] = '> '.str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', substr($l,2)).'<br>';
					$lastBlockquoteLine = $i;
				}
			}
			$lines[$lastBlockquoteLine] = rtrim($lines[$lastBlockquoteLine], "<br>\n");
			$str = implode("\n", $lines);
		}

		$str = $this->handleInTextVariableDefitions($str);

		$str = str_replace('\\<', '@/@\\lt@\\@', $str);       // shield \<
		$str = str_replace('\\[[', '@/@[@\\@', $str);       // shield \[[
		$str = str_replace(['\\{', '\\}'], ['&#123;', '&#125;'], $str);    // shield \{{
		$str = preg_replace('/(\n\{\{.*\}\}\n)/U', "\n$1\n", $str); // {{}} alone on line -> add LFs
		$str = stripNewlinesWithinTransvars($str);
		$str = $this->handleVariables($str);
		if ($this->page && $this->page->shieldHtml) {	// hide '<' from MD compiler
			$str = str_replace(['<', '>'], ['@/@lt@\\@', '@/@gt@\\@'], $str);
		}
        $str = $this->prepareTabulators($str);
        $str = preg_replace('/^(\d{1,2})\!\./m', "%@start=$1@%\n\n1.", $str);
		return $str;
	} // preprocess





	//....................................................
	private function prepareTabulators($str)
    {   // need to handle lists explicitly to avoid corruption in MD-parser
        // -> shield '-' and '1.' (and '1!.')
        $lines = explode(PHP_EOL, $str);
        foreach ($lines as $i => $l) {
            if (preg_match('/\{\{ \s* tab\b [^\}]* \s* \}\}/x', $l)) {
                if (preg_match('/^[-\*]\s/', $l)) { // contains - or *
                    $l = substr($l, 2);
                    $lines[$i] = "@/@ul@\\@$l";

                } elseif (preg_match('/^(\d+)(!)? \. \s+ (.*)/x', $l, $m)) {
                    $l = $m[3];
                    if ($m[2]) {
                        $lines[$i] = "@/@ol@\\@!{$m[1]}@$l";
                    } else {
                        $lines[$i] = "@/@ol@\\@$l";
                    }
                }
            }
        }
        $str = implode("\n", $lines);
        return $str;
    } // prepareTabulators





	//....................................................
	private function handleInTextVariableDefitions($str)
	{
	    list($p1, $p2) = strPosMatching($str, '{{{', '}}}');
	    if ($p1) {
            $md = new MarkdownExtra;
        }
	    while ($p1) {
            $before = substr($str, 0, $p1);
            $val = substr($str, $p1+3, $p2-$p1-3);
            if (preg_match('/^(.*)\b([\w\-]+)\s*$/ms', $before, $m)) {
                $before = $m[1];
                $var = $m[2];
                if ($val[0] == '&') {   // option to send variable content through the md-parser
                    $val = $md->parse($val);
                    $val = preg_replace('/^\<p>(.*)\<\/p>\n$/', "$1", $val);
                } else {
                    $val = substr($val, 1);
                }
                $this->trans->addVariable($var, $val);
            }
            $str = $before.substr($str, $p2+3);
            list($p1, $p2) = strPosMatching($str, '{{{', '}}}', $p1+1);
        }
	    return $str;
    } // handleInTextVariableDefitions




	//....................................................
	private function handleEOF($lines)
	{
		$out = array();
		for ($i=0; $i<sizeof($lines); $i++) {  // loop over lines
			$l = $lines[$i];
			if (strpos($l, '__END__') !== false) { 		// handle end-of-file
				break;
			}
			$out[] = $l;
		}
		return $out;
	} // handleEOF





	//....................................................
	private function handleFrontmatter($lines)
	{
		$yaml = '';
		$i = 0;
		while ($i < sizeof($lines)) {
			$l = $lines[$i];
			if (!preg_match('/^\s*$/', $l)) {
				break;
			}
			$i++;
		}
		if (!preg_match('/^---/', $l)) {
			return $lines;
		}
		$i++;
		$l = $lines[$i];
		while (($i < sizeof($lines)) && (!preg_match('/---/', $l))) {
			$yaml .= $l."\n";
			$i++;
			$l = $lines[$i];
		}
		$hdr = convertYaml($yaml);
		$lines = array_slice($lines, $i+1);
		return $lines;
	} // handleFrontmatter



	//....................................................
	private function handleVariables($str)
	{
		$out = '';
		$withinEot = false;
		$textBlock = '';
		$var = '';
		foreach (explode(PHP_EOL, $str) as $l) {
		    if ($withinEot) {
		        if (preg_match('/^EOT\s*$/', $l)) {
		            $withinEot = false;
                    $textBlock = str_replace("'", '&apos;', $textBlock);
                    $this->variable[$var] = $textBlock;
                } else {
                    $textBlock .= $l."\n";
                }
                continue;
            }
			if (preg_match('/^\$(\w+)\s?=\s*(.*)/', $l, $m)) { // variable definition
				$var = trim($m[1]);
				$val = trim($m[2]);
				if ($val == '<<<EOT') {         // handle <<<EOT
				    $withinEot = true;
                    $textBlock = '';
				    continue;
                }
				$this->variable[$var] = $this->replaceVariables($val);
				continue;
			}
			$l = $this->replaceVariables($l);
			$out .= $l."\n";
		}
		return $out;
	} // handleVariables



	//....................................................
	private function replaceVariables($l)
	{
	    if (!$this->variable) {
	        return $l;
        }
		foreach ($this->variable as $sym => $str) { // replace variables, supporting ++ and -- operators
			if (strpos($str, '$' . $sym) !== false) {
                fatalError("Warning: cyclic reference in variable '\$$sym'", 'File: '.__FILE__.' Line: '.__LINE__);
			}
			$p = 0;
			while (($p = strpos($l, '$' . $sym, $p)) !== false) {
				if (($p > 0) && (substr($l, $p-1, 1) == '\\') && (substr($l, $p-2, 1) != '\\')) { // symbol shielded with \
					$l = substr($l, 0, $p - 1) . substr($l, $p);
					continue;
				}
			
				if ($p > 1) { // get left operand
					$op_l = substr($l, $p - 2, 2);
				} else {
					$op_l = false;
				}
				if ($p <= strlen($l) - strlen($sym) - 3) { // get right operand
					$op_r = substr($l, $p + strlen($sym) + 1, 2);
				} else {
					$op_r = false;
				}
				$l1 = substr($l, 0, $p);	// leading text
				$l2 = substr($l, $p + strlen($sym) + 1);	// trailing text
				if ((substr($l1,-1) == '{') && (substr($l2,0,1) == '}')) {
					$l1 = substr($l1,0,-1);
					$l2 = substr($l2,1);
				}
				if ($op_l || $op_r) {
					if (preg_match('/^(\D*)(\d+)$/', $str, $mm)) {
						$mm2p = intval($mm[2]) + 1; // numerical
						$mm2m = intval($mm[2]) - 1;
					} else {
						if (strlen($str) > 1) { // alpha
							$mm2p = substr($str, 0, -1) . chr(ord(substr($str, -1)) + 1);
							$mm2m = substr($str, 0, -1) . chr(ord(substr($str, -1)) - 1);
						} else {
							$mm2p = chr(ord($str) + 1);
							$mm2m = chr(ord($str) - 1);
						}
						$mm = array(
							$str,
							''
						);
					}
				} else {
					$mm = array(
						$str,
						''
					);
				}
			
				if ($op_l == '++') {
					$this->variable[$sym] = $str = $mm2p;
					$l1             = substr($l1, 0, -2);
				} elseif ($op_l == '--') {
					$this->variable[$sym] = $str = $mm2m;
					$l1             = substr($l1, 0, -2);
				} elseif ($op_r == '++') {
					$this->variable[$sym] = $mm2p;
					$l2             = substr($l2, 2);
				} elseif ($op_r == '--') {
					$this->variable[$sym] = $mm2m;
					$l2             = substr($l2, 2);
				}
				$str = str_replace("\n", ' ', $str);
				$str = "{[{[$str]}]}";
				$l   = $l1 . $str . $l2;
				$str = $this->variable[$sym];
			
				$p++;
			}
		} // loop over variables
		return $l;
	} // replaceVariables



	//....................................................
	private function postprocess($str)
	{
		$out = '';
		$str = str_replace('&amp;', '&', $str);

		$lines = explode(PHP_EOL, $str);
		$preCode = false;
		$olStart = false;
		foreach ($lines as $l) {
			$l = $this->postprocessInlineStylings($l);
			if ($preCode && preg_match('|\</code\>\</pre\>|', $l)) {
				$preCode = false;
			} elseif (preg_match('/\<pre\>\<code\>/', $l)) {
				$preCode = true;
			}
			$l = $this->postprocessShieldedTags($l, $preCode);

            if (preg_match('|^<p>({{.*}})</p>$|', $l, $m)) { // remove <p> around variables/macros alone on a line
                $l = $m[1];
            }

            // if enum-list was marked with ! meaning set start value:
			if (preg_match('|(.*) \%\@start\=(\d+)\@\% (.*)|x', $l, $m)) {
                $olStart = $m[2];
			    continue;

            } elseif (($olStart !== false) && ($l == '<ol>')) {
			    $l = "<ol start='$olStart'>";
                $olStart = false;
            }
			$out .= $l."\n";
		}

        $out = $this->postprocessLiteralBlock($out); // ::: .box!

        $out = htmlspecialchars_decode($out);
        $out = str_replace(['@/@\\lt@\\@', '@/@\\gt@\\@'], ['&lt;', '&gt;'], $out); // shielded < and > (source: \< \>)

		return $out;
	} // postprocess



	//....................................................
	private function postprocessInlineStylings($line)    // [[ xy ]]
	{
	    if (strpos($line, '[[') === false) {
            $line = str_replace('@/@[@\\@', '[[', $line);
            return $line;
        }

		while (preg_match('/([^\[]*) \[\[ ([^\]]*) \]\] (.*)/x', $line, $m)) {
			$s1 = $m[1];
			$s2 = trim($m[2]);
			$id = '';
			$class = '';
			$attr = '';
			$style = '';
			$span = '';

			$c1 = trim($s2){0};
			if ($c1 == '"') {		                                                        // span
                if (preg_match('/([^"]*)"([^"]*)"(.*)/', $s2, $mm)) {	// "
                    $span = $mm[2];
                    $s2 = $mm[1] . $mm[3];
                }
            } elseif ($c1 == "'") {
                if (preg_match("/([^ ']*)'([^']*)'(.*)/", $s2, $mm)) {	 // '
                    $span = $mm[2];
                    $s2 = $mm[1] . $mm[3];
                }
            }

            $cl = '';
			while (preg_match('/([^\.]*)\.([\w\-\.]+)(.*)/', $s2, $mm)) {		// class
				$cl .= ' '.str_replace('.', ' ', $mm[2]);
				$s2 = $mm[1].$mm[3];
			}
			if ($cl) {
                $class .= " class='".trim($cl)."'";
            }

			if (preg_match('/([^\#]*)\#([\w\-]+)(.*)/', $s2, $mm)) {		// id
				$id = $mm[2];
				$id = " id='$id'";
				$s2 = $mm[1].$mm[3];
			}

			if (preg_match_all('/([\w\-]+):\s*([^;\s]*);?/', $s2, $mm)) {		// styles or attr
				foreach ($mm[0] as $s2) {
					$s2 = str_replace(' ', '', $s2);
					list($key, $val) = explode(':', $s2);
                    if ($this->isCssProperty($key)) {
                        $style .= rtrim($s2, ';') . ';';
                    } else {
                        $val = str_replace(';', '', $val);
                        $attr .= ' '.trim("$key:'$val'");
                    }
				}
				$style = $style ? " style='$style'" : '';
			}

			if ($span) {
                $s1 .= "<span$id$class$style$attr>$span</span>";
			} elseif (preg_match('/([^\<]*\<[^\>]*) \> (.*)/x', $s1, $mm)) {	// now insert into preceding tag
				$s1 = $mm[1] . "$id$class$style$attr>" . $mm[2] . $span;
			}
			$line = $s1.$m[3];
		}
		
		$line = str_replace('@/@[@\\@', '[[', $line);

		return $line;
	} // postprocessInlineStylings



	//....................................................
	private function postprocessShieldedTags($l, $preCode)
	{
		if ($l) {   // reverse HTML-Tag shields:
			if ($preCode) {
				$l = str_replace(['@/@lt@\\@', '@/@gt@\\@'], ['&lt;', '&gt;'], $l);
			} else {
                $l = str_replace(['@/@lt@\\@', '@/@gt@\\@'], ['<', '>'], $l);
			}
		}
		return $l;
	} // postprocessShieldedTags



    //....................................................
    private function postprocessLiteralBlock($str)    // <div data-lzy-literal-block="true">...</div>
    {
        $p1 = strpos($str, 'data-lzy-literal-block');
        while ($p1) {
            $p2 = strpos($str, '</div>', $p1);
            $literal = substr($str, $p1 + 34, $p2 - $p1 - 39);
            $literal = base64_decode($literal);
            $str = substr($str, 0, $p1) . '>' . $literal . substr($str, $p2);
            $p1 = strpos($str, 'data-lzy-literal-block');
        }
        return $str;
    } // handleLiteralBlock


    private function isCssProperty($str)
    {
        $res = array_filter($this->cssAttrNames, function($attr) use ($str) {return (substr_compare($attr, $str, 0, strlen($attr)) == 0); });
        return (sizeof($res) > 0);
    }


} // class MyMarkdown

