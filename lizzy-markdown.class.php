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
	private $mdVariables = [];
	
	private $replaces = array(
		'(?<![\-\\\])\-\>'  => '&rarr;', // unless it's '-->'
		'(?<![\-\\\])\-&gt;'  => '&rarr;', // unless it's '-->'
		'\=\>'              => '&rArr;',
		'\=&gt;'              => '&rArr;',
		'(?<!\\\)([\d\s\.])\-\-([\d\s])' => '$1&ndash;$2',
		'(?<!\.)\.\.\.(?!\.)' => '&hellip;',
		'\bEURO\b'          => '&euro;',
		'\bBR\b'            => '<br>',
		'\bNL\b'            => '<br>&nbsp;',
		'\bSPACE\b'         => '&nbsp;&nbsp;&nbsp;&nbsp;',
		'(?<!\\\)sS'    => 'ß',
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
            'fieldset', 'figcaption', 'figure', 'figcaption', 'footer', 'form', 'iframe',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup', 'hr', 'noscript', 'ol', 'output',
            'p', 'pre', 'section', 'table', 'tfoot', 'ul'];

	public function __construct($lzy = null)
    {
        $this->lzy = $lzy;
        $this->trans = isset($this->lzy->trans) ? $this->lzy->trans: null;
    }  // __construct




	public function compile($str, $page)
	{
		$this->page = $page;

        $this->addReplacesFromFrontmatter($page);

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

		} elseif ($this->page->mdVariant !== false) {	// Lizzy's MD extensions
			$str = $this->preprocess($str);
			if (isset($this->page->md) && ($this->page->md === false)) {
				$this->page->addContent($str);
				return $this->page;
			}

            $this->md = new LizzyExtendedMarkdown($this, $page, $this->lzy);
            $str = $this->md->parse($str);
			$str = $this->postprocess($str);
			
			$str = $this->doReplaces($str);
		}
		$this->page->addContent($str);
		return $this->page;
	} // compile






    public function compileStr($str, $page = false)
    {
        $str = $this->preprocess($str);

        $this->md = new LizzyExtendedMarkdown($this, $page, $this->lzy);
        $str = $this->md->parse($str);
        $str = $this->postprocess($str);

        $str = $this->doReplaces($str);
        return $str;
    } // compileStr





	private function doMDincludes($str)
	{
		while (preg_match('/(.*) (?<!\\\\){{ \s* include\( ([^)]*\.md) [\'"]? \) \s* }}(.*)/xms', $str, $m)) {
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






	private function setDefaults()
	{
		if (!isset($this->page->mdVariant)) {		// 'mdVariant' or 'markdown' -> true, false, 'extended'
			if (!isset($this->page->markdown)) {
                if (isset($this->page->frontmatter['markdown'])) {  // Frontmatter "markdown: false"
                    $this->page->mdVariant = $this->page->frontmatter['markdown'];
                } else {
                    $this->page->mdVariant = 'extended';
                }
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






	private function doReplaces($str)
	{
	    // prepare modified patterns if it contains look-behind:
	    if (!isset($this->replaces2)) {
            foreach ($this->replaces as $key => $value) {
                if ($key[0] === '(') {
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





	private function handeCodeBlocks($str)
	{
		$lines = explode(PHP_EOL, $str);
		$out = '';
		foreach($lines as $l) {
			$out .= preg_replace('/```.*/', '```', $l)."\n";
		}
		return $out;
	} // handeCodeBlocks






	private function preprocess($str)
	{
        $str = $this->shieldLiteralTransvar($str);

        if (preg_match("/\n>\s/", $str, $m)) {	// is there a blockquote? ('> ' at beginning of line)
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

		$str = str_replace("\\\n", "  \n", $str);       // \ at end of line -> convert to 2-blanks
        $str = $this->convertMdLinksToMacroCalls($str);
        $str = $this->convertMdImagesToMacroCalls($str);

        // shield specific patterns: \<, \[[, \:, \{{
        $str = str_replace(['\\<','\\[[','\\:','\\{', '\\}'], ['@/@\\lt@\\@','@/@[@\\@','@/@:@\\@','&#123;', '&#125;'], $str);
		$str = preg_replace('/(\n{{.*}}\n)/U', "\n$1\n", $str); // {{}} alone on line -> add LFs around it
		$str = $this->stripNewlinesWithinTransvars($str);
		$str = $this->handleMdVariables($str);
		if (@$this->page && $this->page->shieldHtml) {	// hide '<' from MD compiler
			$str = str_replace(['<', '>'], ['@/@lt@\\@', '@/@gt@\\@'], $str);
		}
        $str = $this->prepareTabulators($str);
        $str = preg_replace('/^(\d{1,2})!\./m', "%@start=$1@%\n\n1.", $str);
		return $str;
	} // preprocess




    private function stripNewlinesWithinTransvars($str)
    {
        $p1 = strpos($str, '{{');
        if ($p1 === false) {
            return $str;
        }
        do {
            list($p1, $p2) =  strPosMatching($str, '{{',  '}}',$p1);

            if ($p1 === false) {
                break;
            }
            $s = substr($str, $p1, $p2-$p1+2);
            // macro call may be on multiple lines, if so flatten.
            // -> if so, a newline terminates an argument, even if ',' is missing:
            $s = preg_replace("/,\s*\n/ms", ',↵ ',$s);
            $s = preg_replace("/\n\s*/ms", ',↵ ',$s);
            $s = preg_replace("/\(\s*,↵/", '(↵ ',$s);

            $str = substr($str, 0, $p1) . $s . substr($str, $p2+2);
            $p1 += strlen($s);
        } while (strpos($str, '{{', $p1) !== false);

        return $str;
    } // stripNewlinesWithinTransvars





	private function prepareTabulators($str)
    {   // need to handle lists explicitly to avoid corruption in MD-parser
        // -> shield '-' and '1.' (and '1!.')

        // Alternative Syntax: ··>>·· or ··10em>>··  (where · == space)
        $str = preg_replace('/(\s\s|\t) ([.\d]{1,3}\w{1,2})? >> [\s\t]/x', "{{ tab($2) }}", $str);

        $lines = explode(PHP_EOL, $str);
        foreach ($lines as $i => $l) {
            if (preg_match('/{{ \s* tab\b [^}]* \s* }}/x', $l)) { // tab present?
                if (preg_match('/^[-*]\s/', $l)) { // UL?  (begins with - or *)
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






	private function handleMdVariables($str)
	{
		$out = '';
		$withinEot = false;
		$textBlock = '';
		$var = '';
		foreach (explode(PHP_EOL, $str) as $l) {
            // catch body of text-block, e.g. $var = <<<EOT ... EOT
		    if ($withinEot) {
		        if (preg_match('/^EOT\s*$/', $l)) {
		            $withinEot = false;
//                    $textBlock = str_replace("'", '&apos;', $textBlock); //??? check!
                    $this->trans->setVariable($var, $textBlock);
                } else {
                    $textBlock .= $l."\n";
                }
                continue;
            }

            if (!$l) {
                $out .= "\n";
                continue;
            }

            // catch in-text variable definitions, e.g. $var = xy
			if (preg_match('/^\$ ([\w.]+) \s? = \s* (.*)/x', $l, $m)) {
				$var = trim($m[1]);
				$val = trim($m[2]);

                // catch text-block, e.g. $var = <<<EOT ... EOT
				if ($val === '<<<EOT') {
				    $withinEot = true;
                    $textBlock = '';
				    continue;
                }
				// translate transvar/macro if there is any:
                $this->trans->setVariable($var, $val);
				continue;
			} else {
                $l = $this->replaceVariables($l);
            }
			$out .= $l."\n";
		}
		return $out;
	} // handleMdVariables




	public function replaceVariables( $str )
	{
	    // replaces $var or ${var} with its content, unless shielded as \$var
        //  if variable is not defined, leaves source string untouched. exception: one of below
        // additional functions:
        //  ${var=value}    -> defines variable on the fly
        //  $var++ or $var-- or ++$var or --$var    -> auto-increaces/decreaces numeric variable
        //  ${var++} or ${var--} or ${++var} or ${--var} -> dito

        while (preg_match('/(.*?) ( (?<!\\\) \$ .*)/xm', $str, $m0)) {
            $before = $m0[1];
            $str = $m0[2];

            // case ${var=newvalue}:
            if (preg_match('/^ \$ { (\w+ ) = (.*?) } (.*)/xm', $str, $m)) {
                $varName = $m[1];
                $val = $m[2];
                $str = $val . $m[3];
                $this->trans->setVariable($varName, $val);
            }

            // case ${var++} or ${var--}:
            elseif (preg_match('/^ \$ { (\w+ ) (--|\+\+) } (.*)/xm', $str, $m)) {
                $varName = $m[1];
                $val = $this->trans->getVariable( $varName );
                if ($val === false) {
                    $val = 0;
                }
                $str = $val . $m[3];
                $val = $this->incDecValue($val, $m[2]);
                $this->trans->setVariable($varName, $val);
            }

            // case ${++var} or ${--var}:
            elseif (preg_match('/^ \$ { (--|\+\+) (\w+ ) } (.*)/xm', $str, $m)) {
                $varName = $m[2];
                $val = $this->trans->getVariable( $varName );
                if ($val === false) {
                    $val = 0;
                }
                $val = $this->incDecValue($val, $m[1]);

                $this->trans->setVariable($varName, $val);
                $str = $val . $m[3];
            }

            // case ${var}:
            elseif (preg_match('/^ \$ { (\w+) } (.*)/xm', $str, $m)) {
                $varName = $m[1];
                $val = $this->trans->getVariable( $varName );
                if ($val === false) {
                    $val = 0;
                }
                $str = $val . $m[2];
            }

            // case $var:
            elseif (preg_match('/^ \$ (\w+) (.*)/xm', $str, $m)) {
                $varName = $m[1];
                $val = $this->trans->getVariable( $varName );
                if ($val !== false) {
                    $str = $val . $m[2];
                } else {
                    $str = "\\$$varName" . $m[2];
                }
            }
            $str = $before . $str;
        };
        return $str;
	} // replaceVariables




    private function convertMdImagesToMacroCalls($str)
    {
        // extracts MD style images and transforms it into macro calls {{ img() }}
        // takes into account optional dimension information as produced by pandoc
        // example:
        //      ![](~/media/image1.jpeg){width="4.588888888888889in height="3.5840277777777776in"}
        if (preg_match_all('/ !\[ (.*?) ]\( (.*?) \) ( { (.*?) })?/xms', $str, $m)) {
            $i = 0;
            while (isset($m[0][$i])) {
                $pat = $m[0][$i];
                $alt = $m[1][$i];
                $file = $m[2][$i];
                $dim = $m[4][$i];
                if (preg_match('/width="([\d.]*)/', $dim, $mm)) {
                    $w = intval($mm[1]) * 254;
                    $file = preg_replace('/^ (.*) \. (\w+) $/x', "$1[{$w}x].$2", $file);
                }
                $str = str_replace($pat, "{{ img('$file', alt:'$alt') }}", $str);
                $i++;
            }
        }
        return $str;
    } // convertMdImagesToMacroCalls





    private function convertMdLinksToMacroCalls($str)
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
            $lines = explode("\n", $str);
            foreach ($lines as $i => $l) {

                if (preg_match_all('/( (?<!["\']) [\w\-.]*?)@([\w\-.]*?\.\w{2,6}) (?!["\'])/x', $l, $m)) {
                    foreach ($m[0] as $addr) {
                        $lines[$i] = str_replace($addr, "<a href='mailto:$addr'>$addr</a>", $l);
                    }

                } elseif (preg_match_all('|( (?<!["\']) (https?://) ([\w\-.]+ \. [\w\-]{1,6}) [\w\-/.]* ) (?!["\'])|xi', $l, $m)) {
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
    } // convertMdLinksToMacroCalls





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
			if ($preCode && preg_match('|</code></pre>|', $l)) {
				$preCode = false;
			} elseif (preg_match('/<pre><code>/', $l)) {
				$preCode = true;
			}
			$l = $this->postprocessShieldedTags($l, $preCode);

            // remove <p> around variables/macros alone on a line:
            if (preg_match('|^<p>({{.*}})</p>$|', $l, $m)) {
                $l = $m[1];

                // remove <p> around pure HTML on a line:
            } elseif (preg_match('|^<p> (< (.*?) > .* </ \2 > ) </p>$|x', $l, $m)) {
                if (in_array($m[2], $this->blockLevelElements)) {
                    $l = $m[1];
                }
            } else {
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
            }

            // if enum-list was marked with ! meaning set start value:
			if (preg_match('|(.*) %@start=(\d+)@% (.*)|x', $l, $m)) {
                $olStart = $m[2];
			    continue;

            } elseif (($olStart !== false) && ($l === '<ol>')) {
			    $l = "<ol start='$olStart'>";
                $olStart = false;
            }
			$out .= $l."\n";
		}

        $out = $this->postprocessLiteralBlock($out); // ::: .box!

        $out = str_replace(['@/@\\lt@\\@', '@/@\\gt@\\@', '@/@:@\\@'], ['&lt;', '&gt;', '\\:'], $out); // shielded < and > (source: \< \>)

        $out = $this->unshieldLiteralTransvar($out);

		return $out;
	} // postprocess






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

        if (!preg_match('|(.*) (?<!/) \[\[ (.*?) ]] (.*)|x', $line, $m)) {
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
			$c1 = $args[0];
			if (strpbrk($args, '\'"') !== false) {
                if (preg_match("/([^$c1]*) $c1 (.*?) $c1 \s* ,? (.*)/x", $args, $mm)) {
                    $span = $mm[2];
                    $args = $mm[1] . $mm[3];
                }
            }
            list($tag, $attr, $lang, $comment, $literal, $mdCompile) = parseInlineBlockArguments($args);

			if ($lang && ($lang !== $this->lzy->config->lang)) {
                $head = $tail = $span = '';
            }
			if ($span) {
                if (!$tag) {
                    $tag = 'span';
                }
                $head .= "<$tag $attr>$span</$tag>";

			} elseif (preg_match('/([^<]*<[^>]*) > (.*)/x', $head, $m)) {	// now insert into preceding tag
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




    private function postprocessLiteralBlock($str)    // <div data-lzy-literal-block="true">...</div>
    {
        $p1 = strpos($str, 'data-lzy-literal-block');
        while ($p1) {
            $p1 = strpos($str, '>', $p1);
            $tmp = ltrim(substr($str, $p1+1));
            $p2 = strpos($tmp, "\n");
            if ($p2) {
                $head = substr($str, 0, $p1+1);
                $literal = substr($tmp, 0, $p2);
                $literal = base64_decode($literal);
                $tail = substr($tmp, $p2);
                $str = "$head\n$literal\n$tail";
                $p1 = $p1 + strlen($literal) + 2;
            }
            $p1 = strpos($str, 'data-lzy-literal-block', $p1);
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




    private function shieldLiteralTransvar($str)
    {
        if (preg_match_all('/ {{{ (.*?) }}} /xms', $str, $m)) {
            foreach ($m[1] as $i => $value) {
                $literal = base64_encode($value);
                $str = str_replace($value, $literal, $str);
            }
        }
        return $str;
    } // shieldLiteralTransvar




    private function unshieldLiteralTransvar($str)
    {
        if (preg_match_all('/ {{{ (.*?) }}} /xms', $str, $m)) {
            foreach ($m[1] as $i => $value) {
                $origStr = base64_decode($value);
                $str = str_replace($m[0][$i], "{{ $origStr }}", $str);
            }
        }
        return $str;
    } // unshieldLiteralTransvar



    private function incDecValue($val, $op)
    {
        // numeric value:
        if (preg_match('/(\D*) (\d+) (\D*)/x', $val, $m2)) {
            $v = $m2[2];
            if ($op === '++') {
                $val = (string)(intval($v) + 1);
            } else {
                $val = (string)(intval($v) - 1);
            }
            $val = $m2[1] . $val . $m2[3];

        // alpha value:
        } elseif (preg_match('/([a-zA-Z]*)/x', $val, $m2)) {
            $v = $m2[1];
            for ($i = (strlen($v) - 1); $i >= 0; $i--) {
                $n = ord($v[$i]);
                $n1 = $n & 31;
                // inc:
                if ($op === '++') {
                    if ($n1 < 26) {
                        $n++;
                        $val[$i] = chr($n);
                        break;
                    } else {
                        $val[$i] = chr($n - 25);
                        if ($i === 0) {
                            $ch = ($n > 95) ? 'a' : 'A';
                            $val = $ch . $val;
                            break;
                        }
                    }
                    // dec:
                } else {
                    if ($n1 > 1) {
                        $n--;
                        $val[$i] = chr($n);
                        break;
                    } else {
                        if ($i === 0) {
                            $val = str_pad('', strlen($v) - 1, ($n > 95) ? 'z' : 'Z');
                        } else {
                            $val[$i] = ($n > 95) ? 'z' : 'Z';
                        }
                    }
                }
            }

        }
        return $val;
    } // incDecValue


} // class MyMarkdown

