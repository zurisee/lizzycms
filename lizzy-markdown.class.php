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

class LizzyMarkdown
{
	private $page = null;
	private $md = null;
	private $variable;
	
	private $replaces = array(
		'(?<![\-\\\])\-\>'  => '&rarr;', // unless it's '-->'
		'(?<![\-\\\])\-&gt;'  => '&rarr;', // unless it's '-->'
		'\=\>'              => '&rArr;',
		'\=&gt;'              => '&rArr;',
		' \-\- '            => ' &ndash; ',
		'(?<!\.)\.\.\.(?!\.)' => '&hellip;',
		'\bEURO\b'          => '&euro;',
		'\bBR\b'            => '<br>',
		'\bNL\b'            => '<br>&nbsp;',
		'\bSPACE\b'         => '&nbsp;&nbsp;&nbsp;&nbsp;',
		'(?<![\-\\\])sS'    => 'ß',
		'CLEAR'             => '<div style="clear:both;"></div>',
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

    private $blockLevelElements =
        ['address', 'article', 'aside', 'audio', 'video', 'blockquote', 'canvas', 'dd', 'div', 'dl',
            'fieldset', 'figcaption', 'figure', 'figcaption', 'footer', 'form',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'noscript', 'ol', 'output',
            'p', 'pre', 'section', 'table', 'tfoot', 'ul'];

	public function __construct($trans = false)
    {
        $this->trans = $trans;
    }  // __construct




    //....................................................
	public function parse($str, $page)
	{
		$this->page = $page;
        $this->addReplacesFromFrontmatter($page);
        $this->variable = array();

		$str = $this->doMDincludes($str);
		
		$this->setDefaults();
		
		if ($this->page->mdVariant === 'standard') {	// Markdown
			$str = $this->handeCodeBlocks($str);
			$this->md = new Markdown;
			$str = $this->md->parse($str);

		} elseif ($this->page->mdVariant === 'extra') {	// MarkdownExtra
			$str = $this->handeCodeBlocks($str);
			$this->md = new MarkdownExtra;
			$str = $this->md->parse($str);

		} else {										// Lizzy's MD extensions
			$str = $this->preprocess($str);
			if (isset($this->page->md) && ($this->page->md === false)) {
				$this->page->addContent($str);
				return $this->page;
			}

            $this->md = new LizzyExtendedMarkdown($this, $page);
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

        $this->md = new LizzyExtendedMarkdown($this, $page);
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
	    // prepare modified patterns if it contains look-behind:
	    if (!isset($this->replaces2)) {
            foreach ($this->replaces as $key => $value) {
                if ($key{0} === '(') {
                    $k = str_replace('\\', '', substr($key, strpos($key, ')')+1));
                    $this->replaces2[$key] = $k;
                }
            }
        }
		foreach ($this->replaces as $key => $value) {
			$str = preg_replace("/$key/", $value, $str);

			if (isset($this->replaces2[$key])) {    // modified pattern exists:
			    $k = $this->replaces2[$key];
                $str = preg_replace("/\\\\$k/", $k, $str);  // remove shielding '\'
            }
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
				if ((($s = substr($l,0,2)) === '> ') || ($s === ">\t")) {
					$lines[$i] = '> '.str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', substr($l,2)).'<br>';
					$lastBlockquoteLine = $i;
				}
			}
			$lines[$lastBlockquoteLine] = rtrim($lines[$lastBlockquoteLine], "<br>\n");
			$str = implode("\n", $lines);
		}

        $str = $this->convertLinks($str);
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

        // Alternative Syntax: ··>>·· or ··10em>>··  (where · == space)
        $str = preg_replace('/(\s\s|\t) ([.\d]{1,3}\w{1,2})? >> [\s\t]/x', "{{ tab($2) }}", $str);

        $lines = explode(PHP_EOL, $str);
        foreach ($lines as $i => $l) {
            if (preg_match('/\{\{ \s* tab\b [^\}]* \s* \}\}/x', $l)) { // tab present?
                if (preg_match('/^[-\*]\s/', $l)) { // UL?  (begins with - or *)
                    $l = substr($l, 2);
                    $lines[$i] = "@/@ul@\\@$l";

                } elseif (preg_match('/^(\d+)(!)? \. \s+ (.*)/x', $l, $m)) { // OL??  (begins with digit.)
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
                if ($val[0] === '&') {   // option to send variable content through the md-parser
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
				if ($val === '<<<EOT') {         // handle <<<EOT
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
				if (($p > 0) && (substr($l, $p-1, 1) == '\\') && (substr($l, $p-2, 1) !== '\\')) { // symbol shielded with \
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
				if ((substr($l1,-1) === '{') && (substr($l2,0,1) === '}')) {
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
			
				if ($op_l === '++') {
					$this->variable[$sym] = $str = $mm2p;
					$l1             = substr($l1, 0, -2);
				} elseif ($op_l === '--') {
					$this->variable[$sym] = $str = $mm2m;
					$l1             = substr($l1, 0, -2);
				} elseif ($op_r === '++') {
					$this->variable[$sym] = $mm2p;
					$l2             = substr($l2, 2);
				} elseif ($op_r === '--') {
					$this->variable[$sym] = $mm2m;
					$l2             = substr($l2, 2);
				}
				$str = str_replace("\n", ' ', $str);
				$l   = $l1 . $str . $l2;
				$str = $this->variable[$sym];
			
				$p++;
			}
		} // loop over variables
		return $l;
	} // replaceVariables



    //....................................................
    private function convertLinks($str)
    {
        $enabled = false;
        if (isset($this->page->autoConvertLinks)) {
            $enabled = $this->page->autoConvertLinks;
            if (!$enabled) {
                return $str;
            }
        }
        $feature_autoConvertLinks = isset($this->trans->lzy->config->feature_autoConvertLinks)? $this->trans->lzy->config->feature_autoConvertLinks : false;
        if ($enabled || $feature_autoConvertLinks) {
//        if ($enabled || $this->trans->lzy->config->feature_autoConvertLinks) {
            $lines = explode("\n", $str);
            foreach ($lines as $i => $l) {

                if (preg_match_all('/( (?<!["\']) [\w-\.]*?)\@([\w-\.]*?\.\w{2,6}) (?!["\'])/x', $l, $m)) {
                    foreach ($m[0] as $addr) {
                        $lines[$i] = str_replace($addr, "<a href='mailto:$addr'>$addr</a>", $l);
                    }

                } elseif (preg_match_all('|( (?<!["\']) (https?://) ([\w-\.]+ \. [\w-]{1,6}) [\w-/]* ) (?!["\'])|xi', $l, $m)) {
                    foreach ($m[0] as $j => $tmp) {
                        if (!$m[2][$j]) {
                            $url = "https://" . $m[3][$j];
                        } else {
                            $url = $m[1][$j];
                        }
                        if (strlen($url) > 7) {
                            $l = str_replace( $m[0][$j], "<a href='$url'>$url</a>", $l);
                        }
                    }
                    $lines[$i] = $l;
                }
            }
            $str = implode("\n", $lines);
        }
        return $str;
    } // convertLinks




	//....................................................
	private function postprocess($str)
	{
		$out = '';
		$str = str_replace('&amp;', '&', $str);

		$lines = explode(PHP_EOL, $str);
		$preCode = false;
		$olStart = false;
		foreach ($lines as $l) {
		    if (!$l) {
		        $out .= "\n";
		        continue;
            }
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
            if (preg_match('|^<p> \s* ( </? ([^>\s]*) .* )|x', $l, $m)) { // remove <p> before pure HTML
                $tag = $m[2];
                if (in_array($tag, $this->blockLevelElements)) {
                    $l = $m[1];
                }
            }

            if (preg_match('|^( .* </? (\w+) [^>]* > ) </p>\s*$|x', $l, $m)) { // remove <p> before pure HTML
                $tag = $m[2];
                if (in_array($tag, $this->blockLevelElements)) {
                    $l = $m[1];
                }
            }

            // if enum-list was marked with ! meaning set start value:
			if (preg_match('|(.*) \%\@start\=(\d+)\@\% (.*)|x', $l, $m)) {
                $olStart = $m[2];
			    continue;

            } elseif (($olStart !== false) && ($l === '<ol>')) {
			    $l = "<ol start='$olStart'>";
                $olStart = false;
            }
			$out .= $l."\n";
		}

        $out = $this->postprocessLiteralBlock($out); // ::: .box!

        // $out = htmlspecialchars_decode($out); // conflicts with content like '&lt;x>'
        $out = str_replace(['@/@\\lt@\\@', '@/@\\gt@\\@'], ['&lt;', '&gt;'], $out); // shielded < and > (source: \< \>)

		return $out;
	} // postprocess





	//....................................................
	public function postprocessInlineStylings($line, $returnElements = false)    // [[ xy ]]
	{
	    $id = $class = '';
	    if (strpos($line, '[[') === false) {
            $line = str_replace('@/@[@\\@', '[[', $line);
            if ($returnElements) {
                return [$line, null, null, null, null];
            } else {
                return $line;
            }
        }

		if (!preg_match('/(.*) \[\[ (.*?) \]\] (.*)/x', $line, $m)) {
            if ($returnElements) {
                return [$line, null, null, null, null];
            } else {
                return $line;
            }
        }
        $head = $m[1];
        $args = trim($m[2]);
        $tail = $m[3];
        $span = '';

		if ($args) {
		    // extract part within single or double quotes:
			$c1 = $args{0};
			if (strpbrk($args, '\'"') !== false) {
                if (preg_match("/([^$c1]*) $c1 (.*?) $c1 \s* ,? (.*)/x", $args, $mm)) {
                    $span = $mm[2];
                    $args = $mm[1] . $mm[3];
                }
            }
            list($tag, $attr, $lang, $comment, $literal, $mdCompile) = parseInlineBlockArguments($args);

			if ($span) {
                if (!$tag) {
                    $tag = 'span';
                }
                $head .= "<$tag $attr>$span</$tag>";

			} elseif (preg_match('/([^\<]*\<[^\>]*) \> (.*)/x', $head, $m)) {	// now insert into preceding tag
			    if ($tag) {
			        $m[1] = "<$tag";
			        $tail = "</$tag>";
                }
				$head = $m[1] . "$attr>" . $m[2] . $span;
			}
			$line = $head.$tail;
		}

		$line = str_replace('@/@[@\\@', '[[', $line);

		if ($returnElements) {
		    return [$line, $tag, $id, $class, $attr];
        } else {
            return $line;
        }
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
            $p1 = strpos($str, '>', $p1);
            $tmp = ltrim(substr($str, $p1+1));
            if (preg_match('|\<p\> ([^\<]+) \</p\>(.*)|xms', $tmp, $m)) {
                $head = substr($str, 0, $p1+1);
                $literal = $m[1];
                $literal = base64_decode($literal);
                $tail = $m[2];
                $str = "$head\n$literal\n$tail";
                $p2 = $p1 + strlen($literal);

            } elseif (preg_match('|([^\<]+)(.*)|xms', $tmp, $m)) {
                $head = substr($str, 0, $p1+1);
                $literal = $m[1];
                $literal = base64_decode($literal);
                $tail = $m[2];
                $str = "$head\n$literal\n\n\t\t$tail";
                $p2 = $p1 + strlen($literal);

            } else {
                break;  // this case should be impossible
            }
            $p1 = strpos($str, 'data-lzy-literal-block', $p2);
        }
        return $str;
    } // handleLiteralBlock



    //    private function isCssProperty($str)
    //    {
    //        $res = array_filter($this->cssAttrNames, function($attr) use ($str) {return (substr_compare($attr, $str, 0, strlen($attr)) == 0); });
    //        return (sizeof($res) > 0);
    //    } // isCssProperty



    private function addReplacesFromFrontmatter($page)
    {
        if (isset($page->replace)) {
            $newReplaces = [];
            foreach ($page->replace as $pattern => $value) {
                $newReplaces[preg_quote($pattern)] = $value;
            }
            $this->replaces = array_merge($newReplaces, $this->replaces);
        }
    } // addReplacesFromFrontmatter


} // class MyMarkdown

