<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Substitution-Variables and Macros()
 *
 * Usage: {{ }}
 *      or {{x }} where x:
 *          ^   = omit if var is not defined
 *          #   = comment out, skip altogether
 *          !   = force late processing
 *          &   = force md compilation after evaluation
*/

use Symfony\Component\Yaml\Yaml;

define('MAX_TRANSVAR_ITERATION_DEPTH', 100);



class Transvar
{
	private $transvars = array();
	private $usedVars = array();
	private $undefinedVars = array();
	private $macros = array();
	private $macroInfo = array();
	private $invocationCounter = array();
	private $sysVariables = ['head_injections', 'content', 'body_end_injections'];
	private $filesLoaded = array();
	private $varCount = 0;
    public $page;
    public $notranslate = false;



	public function __construct($lzy)
	{
	    $this->lzy = $lzy;
		$this->page = new Page;
		$this->config = $lzy->config;

	} // __construct




    public function translate($html, $includeShieldedVariables = false)
    {
        if ($includeShieldedVariables) {
            $html = $this->unshieldVariablesForCache($html);
        }

        $this->doTranslate($html);
        return $html;
    } // translate




    public function supervisedTranslate($page, &$html)
    {
        $modified = $this->doTranslate($html);
        if ($modified) {
            $page->merge($this->page);
            $this->resetPageObj();
            return true;
        }
        return false;
    } // supervisedTranslate




    private function resetPageObj()
    {
        $this->page = new Page;
    }




	private function doTranslate(&$str, $iterationDepth = 0)
	{
	    // when called from lzy->checkAndRenderCachePage() set() is not defined:
        if (method_exists($this->page, 'set')) {
            $this->page->set('frontmatter', $this->lzy->page->get('frontmatter'));
        }
        if ($iterationDepth >= MAX_TRANSVAR_ITERATION_DEPTH) {
            fatalError("Max. iteration depth exeeded.<br>Most likely cause: a recursive invokation of a macro or variable.");
        }

        $modified = false;
        list($p1, $p2) = strPosMatching($str);
        $this->nestingDepth = 0;
		while (($p1 !== false)) {
            $this->varCount++;
            if ($this->nestingDepth++ >= MAX_TRANSVAR_ITERATION_DEPTH) {
                fatalError("Max. iteration depth exeeded.<br>Most likely cause: a recursive invokation of a macro or variable.");
            }
            $modified = true;
			$this->commmented = false;
			$this->optional = false;
            $this->dontCache = false;
            $this->compileMd = false;

			$var = trim(substr($str, $p1+2, $p2-$p1-2));
			if (!$var) {
			    $str = substr($str,0,$p1).substr($str, $p2+2);
                list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
                continue;
            }
            // handle macro-modifiers, e.g. {{# }}
            $var = $this->handleModifers($var);

            if ($this->commmented) {
                $str = substr($str, 0, $p1) . substr($str, $p2 + 2);

            } else { // not commented
                if (strpos($var, '{{') !== false) {     // nested transvar/macros
                    $modified |= $this->doTranslate($var, $iterationDepth + 1);
                }

                // special option 'showSource':
                if (preg_match('/^(\S+) \( (.*?) \)/x', $var, $m1) && preg_match('/showSource:\s*([-\w\s\'"]*),?\s*/ms',$var, $m2)) {
                    $macName = str_replace('-', '', $m1[1]);
                    $mode = $m2[1]; // popup or true
                    $skipMd = false;
                    if (preg_match('/skipmd/i', $mode, $m)) {
                        $mode = str_replace($m[0], '', $mode);
                        $skipMd = true;
                    }
                    $srcCode = str_replace($m2[0], '', $var);
                    if ($skipMd) {
                        $srcCode = preg_replace(['/(?<!\\\\)<strong>/', '/(?<!\\\\)<\/strong>/'], '**', $srcCode);
                        $srcCode = preg_replace(['/(?<!\\\\)<em>/', '/(?<!\\\\)<\/em>/'], '_', $srcCode);
                    } else {
                        $var = preg_replace(['/(?<!\\\\)<strong>/', '/(?<!\\\\)<\/strong>/'], '', $var);
                        $var = preg_replace(['/(?<!\\\\)<em>/', '/(?<!\\\\)<\/em>/'], '', $var);
                    }

                    // remove styling instructions from code: **
                    $var = preg_replace('|//.*?↵|', '', $var);

                    $this->macroSources[$macName][$this->varCount] = [
                        'text' => $srcCode,
                        'mode' => $mode,
                        'args' => trim( $m1[2] ),
                    ];
                }

                // remove newlines:
                if (strpos($var, "\n")) {
                    $var = str_replace("\n", '', $var);
                }

                // ----------------------------------------------------------------------- translate now:
                if (preg_match('/^([\w\-]+) \( (.*) \)/x', $var, $m)) {    // macro
                    $macro = $m[1];
                    $macro = str_replace('-', '', $macro);
                    $argStr = $m[2];
                    $val = $this->translateMacro($macro, $argStr);

                } else {                                        // variable
                    $val = $this->translateVariable($var);
                }

                // postprocessing:
                if (!$this->optional && ($val === false)) { // handle case when element unknown:
                    $str = substr($str, 0, $p1) . $var . substr($str, $p2 + 2);
                } else {
                    $before = substr($str, 0, $p1);
                    $after = substr($str, $p2 + 2);

                    // remove spurious <p></p>:
                    if ((substr($before, -3) === '<p>') && (substr($after, 0, 4) === '</p>')) {
                        $before = substr($before, 0, -3);
                        $after = substr($after, 4);
                    }
                    $str = $before . $val . $after;
                }
            }

            $this->nestingDepth--;
            list($p1, $p2) = strPosMatching($str, '{{', '}}', $p1+1);
		}
		return $modified;
	} // doTranslate




    public function translateVariable($varName, $returnKeyIfUnknown = false, $tryUserCodeIfUnknown = false)
    {
        if (!$this->notranslate || ($varName === 'content')) {
            $val = $this->getVariable($varName);
            if ($val === false) {
                if ($returnKeyIfUnknown) {
                    $val = $varName;
                } elseif ($tryUserCodeIfUnknown) {
                    $val = $this->doUserCode($varName, $this->config->custom_permitUserCode);
                    if (is_array($val)) {
                        fatalError($val[1]);
                    }
                }
            }
        } else {
            $val = "&#123;&#123; $varName }}";
        }
        return $val;
    } // translateVariable




    public function translateMacro($macro, $argStr)
    {
        $this->macroArgs[$macro] = parseArgumentStr($argStr);
        $this->macroInx = 0;

        if (!isset($this->macros[$macro])) {     // macro already loaded
            $this->loadMacro($macro);
        }

        if (isset($this->macros[$macro])) { // and try to execute it

            $this->optionAddNoComment = false;
            $this->disablePageCaching = false;

            $source = $this->injectMacroSource($macro);

            $val = $this->executeMacro($macro);

            if ($this->disablePageCaching) {
                $GLOBALS['globalParams']['cachingActive'] = false;
            }

            if (($val !== null) && ($this->config->isLocalhost || $this->config->isPrivileged) && !$this->optionAddNoComment) {
                $val = "\n\n<!-- Lizzy Macro: $macro() -->\n$val\n<!-- /$macro() -->\n\n\n";   // mark its output
            }

            if (trim($argStr) === 'help') {
                if ($val2 = $this->getMacroHelp($macro)) {
                    $val = $val2.$val;
                }
            }
            if ($this->compileMd) {
                $val = compileMarkdownStr($val);
            }
            if ($source) {
                $val = str_replace('<div>@#@</div>', $val, $source);
            }

        } elseif ($this->optional) {              // was marked as optional '{{^', so just skip it
            $val = '';

        } else {                            // macro not defined, raise error
            $msg ="Error: undefined macro: '$macro()'";
            logError($msg);
            if ($this->config->localHost || $this->config->isPrivileged) {
                $val = "<div class='error-msg'>$msg</div>";
            } else {
                $val = '';
            }
        }
        return $val;

    } // translateMacro




    private function executeMacro($macro)
    {
        $val = $this->macros[$macro]( $this->getArgsArray($macro) );
        return $val;                    // execute the macro
    } // executeMacro




    public function setMacroInfo($macroName, $info)
    {
        $this->macroInfo[] = [$macroName, $info];
    } // setMacroInfo




    public function getArg($macroName, $name, $help = '', $default = null, $removeNl = true /*, $dynamic = false*/)
    {
        $inx = $this->macroInx++;
        if (!$name) {
            $name = ' ';
        }

        $this->macroFieldnames[$macroName][$inx] = $name;
        if (preg_match('/^\d/', $name)) {
            $index = intval($name);
            if ($index < sizeof($this->macroArgs[$macroName])) {
                $out = array_values($this->macroArgs[$macroName])[$index];
            }

        } else {
            if (isset($this->macroArgs[$macroName][$name])) {
                $out = $this->macroArgs[$macroName][$name];

            } else {
                if (isset($this->macroArgs[$macroName][$inx])) {
                    $out = $this->macroArgs[$macroName][$inx];
                } elseif ($default !== null) {
                    $out = $default;
                } else {
                    $out = null;
                }
                $this->macroArgs[$macroName][$name] = $out; // prepare named option as well
            }
        }
        if ($removeNl && is_string($out)) {
            $out = str_replace('↵', '', $out);
        }

        $this->macroHelp[$macroName][$name] = $help;
        return $out;
    } // getArg




    private function getArgsArray($macroName, $removeNl = true, $argNames = false)
    {
    	//??? unfinished!
    	// -> removeNl in case of $argNames provided
    	
        $this->macroHelp[$macroName] = [];
        if ($argNames && is_array($argNames)) {
            $an = [];
            foreach ($argNames as $i => $argName) {
                if (isset($this->macroArgs[$macroName][$argName])) {
                    $an[$argName] = $this->macroArgs[$macroName][$argName];
                } elseif (isset($this->macroArgs[$macroName][$i])) {
                    $an[$argName] = $this->macroArgs[$macroName][$i];
                } else {
                    $an[$argName] = '';
                }
            }
            return $an;
        }
        if (!$this->macroArgs[$macroName]) {
            return [];
        }
        if ($removeNl) {
            $a = [];
            foreach ($this->macroArgs[$macroName] as $key => $value) {
                $key = trim(str_replace(['↵',"'"], '', $key));
                $a[$key] = $value;
            }
            return $a;
        } else {
            return $this->macroArgs[$macroName];
        }
    } // getArgsArray




    private function getMacroHelp($macroName)
    {
        $argsHelp = $this->macroHelp[$macroName];
        if (!$argsHelp) {       // don't show anything if there are no arguments listed
            return '';
        }
        $out = "<h2>Options for macro <em>$macroName()</em></h2>\n";
        if (!isset($argsHelp["noArguments"])) {       // don't show anything if there are no arguments listed
            $out .= "<dl>\n";
            foreach ($argsHelp as $name => $text) {
                $out .= "\t<dt>$name:</dt>\n\t\t<dd>$text</dd>\n";
            }
            $out .= "</dl>\n";
        }
        return $out;
    } // getMacroHelp





    public function getInvocationCounter($macroName)
    {
        if (!isset($this->invocationCounter[$macroName])) {
            $this->invocationCounter[$macroName] = 0;
        }
        $this->invocationCounter[$macroName]++;
        return $this->invocationCounter[$macroName];
    } // getInvocationCounter




    public function adaptBraces($str)
    {
        return str_replace(['{|{|','|}|}'], ['{{', '}}'], $str);
    } // adaptBraces





    public function loadAllMacros() {
        $page = $this->page = new Page;
        $sys = '~/'.$this->config->systemPath;
        $macrosPath = $this->config->macrosPath;
        $macros = [];
        $files = getDir($this->config->macrosPath.'*.php');
        foreach ($files as $file) {
            $moduleName = basename($file, '.php');

            $info = '';
            $lines = file($file);
            $l = array_filter($lines, function($v, $k) {
                return (strpos($v, '@info') !== false);
            }, ARRAY_FILTER_USE_BOTH);
            if ($l) {
                $info = preg_replace('/^[^:]*\s*:\s*/', '', array_pop($l));
            }

            $macros[$moduleName] = $info;
        }
        ksort($macros);
        $this->macroInfo = $macros;
    } // loadAllMacros




    private function loadMacro($macroName)
    {
        // variables to be available inside macros:
        $sys = '~/'.$this->config->systemPath;
        $page = &$this->page;

        $file = $this->config->macrosPath.$macroName.'.php';
        if (file_exists($file)) {	// filename == macroname
            require_once($file);

        } else {
            $file = $this->config->extensionsPath."$macroName/code/".$macroName.'.php';
            if (file_exists($file)) {    // filename == macroname
                require_once($file);

            } else {
                // check user-code: if macro.php is in code/ folder:
                $file = $this->config->path_userCodePath . $macroName . '.php';
                if (file_exists($file)) {
                    if ($this->config->custom_permitUserCode) {
                        $this->doUserCode($file);
                    } else {
                        fatalError("Execution of user macro '<strong>$macroName()</strong>' blocked.<br>".
                        "You need to modify permission in <strong>config/config.yaml</strong> (&rarr; <code>custom_permitUserCode: true</code>)");
                    }
                }
            }
        }
    } // loadMacro





    public function readTransvarsFromFiles($file, $markSource = false)
    {
        // read from multiple files
        if (is_array($file)) {
            $a = $file;
            foreach ($a as $f) {
                $files = glob($f);
                foreach ($files as $ff) {
                    if (substr(basename($ff), 0, 1) === '#') {
                        continue;
                    }
                    $this->readTransvarsFromFile($ff, $markSource);
                }
            }
        } elseif (strpos($file, '*') !== false) {
            $files = glob($file);
            foreach ($files as $f) {
                if (substr(basename($f), 0, 1) === '#') {
                    continue;
                }
                $this->readTransvarsFromFile($f, $markSource);
            }
        } else {
            $this->readTransvarsFromFile($file, $markSource);
        }
    } // readTransvarsFromFiles





    public function readTransvarsFromFile($file, $markSource = false, $dontOverwrite = false)
    {
        if ($file[0] === '~') {
            $file = resolvePath($file);
        }
        if (!file_exists($file)) {
            fatalError("File not found: '$file'", 'File: '.__FILE__.' Line: '.__LINE__);
        }
        if (!in_array($file, $this->filesLoaded)) { // avoid multiple loading of transvar files
            $this->filesLoaded[] = $file;

            $newVars = getYamlFile($file);
            if (is_array($newVars)) {
                $markSource = true;
                if ($this->config->debug_showVariablesUnreplaced) { // for debugging
                    array_walk($newVars, function (&$value, &$key, $file) {
                        if ($key !== 'page_title') {
                            $value = "<span title='$file'>&#123;&#123; $key }}</span>";
                        }
                    }, $file);
                    $markSource = false; // no need to also mark source
                }
                if ($markSource) {
                    foreach ($newVars as $key => $rec) {
                        if (!is_array($rec)) {
                            $v = $rec;
                            unset($newVars[$key]);
                            $newVars[$key]['_'] = $v;
                        }
                        $newVars[$key]['src'] = $file;
                    };
                }
                if ($dontOverwrite) {
                    foreach ($newVars as $k => $v) {
                        if (!isset($this->transvars[$k])) {
                            $this->transvars[$k] = $v;
                        }
                    }
                } else {
                    $this->transvars = array_merge($this->transvars, $newVars);
                }
            }
        }
    } // readTransvarsFromFile




    public function addVariable($key, $value, $lang = '')
    {
        if ($lang === false) {	// delete first
            $this->transvars[$key] = '';
        } else {
            $lang = ($lang) ? $lang : '_'; // fallback language
        }
        if ($lang) {
            if (!isset($this->transvars[$key][$lang])) {
                $this->transvars[$key][$lang] = $value;
            } else {
                $this->transvars[$key][$lang] .= $value;
            }

        } else {
            if (!isset($this->transvars[$key])) {
                $this->transvars[$key] = $value;
            } else {
                $this->transvars[$key] .= $value;
            }
        }
    } // addVariable



    public function addVariables($variables)
    {
        foreach ($variables as $key => $value) {
            $this->transvars[$key] = $value;
        }
    } // addVariables




    public function clearVariable($key)
    {
        if (isset($this->transvars[$key])) {
            $this->transvars[$key] = '';
        }
    } // clearVariable




    public function addMacro($macroName, $func)
    {
        $this->macros[$macroName] = $func;
    } // addMacro




    public function getVariable($key, $lang = '')
    {
        $lang = ($lang) ? $lang : $_SESSION["lizzy"]["lang"];
        $out = false;

        if (isset($this->transvars[$key])) {
            $entry = $this->transvars[$key];

            if (($key[0] !== '_') && (!in_array($key, $this->sysVariables))) {
                $this->usedVars['@:'.$key] = $entry;
            }
            if ($this->config->debug_monitorUnusedVariables && is_array($entry)) {
                if (isset($entry['uu'])) {
                    unset($this->transvars[$key]['uu']);
                }
            }
            if (!is_array($entry)) {
                $out = $entry;

            } elseif (isset($entry['dontCache']) && $entry['dontCache']) {
                $out = "{|{| $key |}|}";

            } elseif (isset($entry[$lang])) {
                $out = $entry[$lang];

            } elseif (isset($entry[$lang]) && ($entry[$lang] === null)) {
                fatalError("Error: transvar with empty value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);

            } elseif (isset($entry['_'])) {
                $out = $entry['_'];

            } elseif (isset($entry['*'])) {
                $out = $entry['*'];

            } elseif (isset($entry[$this->config->site_defaultLanguage])) {  // lang-rcs nor explicit default found -> use default-lang
                $out = $entry[$this->config->site_defaultLanguage];

            } else {    // this should only happen if a wrong value gets into $_SESSION
                fatalError("Error: transvar without propre value: '$key'", 'File: '.__FILE__.' Line: '.__LINE__);
            }
        } elseif ($this->config->debug_showUndefinedVariables) {
            if (!in_array($key, ['PageSource Load previous edition', 'PageSource Load next edition', 'PageSource cancel', 'PageSource activate edition', 'Page-History:'])) {
                $out = "<span class='mark-undefined-variable'>&#123;&#123; $key }}</span>";
                $this->undefinedVars[] = $key;
            }

        } else {
            if ((strlen($key) > 0) && ($key[0] !== '_') && (!in_array($key, $this->sysVariables))) {
                $this->usedVars['_@:'.$key] = '';
            }
        }
        return $out;
    } // getVariable




    public function exportUsedVariables()
    {
        ksort($this->usedVars);
        return convertToYaml($this->usedVars);
    } // exportUsedVariables





    public function loadUserComputedVariables()
    {
        $code = USER_VAR_DEF_FILE;
        if ($this->config->custom_permitUserVarDefs && file_exists($code)) {
            $this->doUserCode( basename($code, '.php'), $this->config->custom_permitUserVarDefs );
        }
    } // loadUserComputedVariables





    public function doUserCode($name, $execType = null, $breakOnError = false)
    {
        $out = false;
        if ($execType === null) {
            $execType = $this->config->custom_permitUserCode;
        }
        if ($execType) {
            $phpFile = $this->config->path_userCodePath.basename($name,'.php').'.php';
            if (file_exists($phpFile)) {
                $page = &$this->page;
                if (($execType === 'true') || ($execType === true)) {
                    $res =  require($phpFile);
                    if (is_array($res)) {
                        foreach ($res as $key => $value) {
                            $this->addVariable($key, $value);
                        }
                    } else {
                        $out = $res;
                    }
                } else {
                    fatalError("PHP-Sandbox feature currently not supported.");

                    // To re-enable, run
                    //  composer install corveda/php-sandbox
                    $sandbox = new MySandbox();
                    $vars['this'] = $this; // feed $trans into sandbox
                    return $sandbox->execute($phpFile, $this->config->configPath, $vars);
                }
            } elseif ($breakOnError) {
                return [false, "Requested file '$phpFile' not found."];
            }
        } elseif (function_exists($name)) {
            if (strpos(',header,', ",$name,") !== false) {
                return '';
            }
            $res = $name();
            return [true, $res];

        } elseif ($breakOnError) {
            return [false, "User-Code not enabled in config/config.yaml (option 'custom_permitUserCode')"];
        }
        return $out;
    } // doUserCode





	public function loadStandardVariables($siteStructure)
	{
	    $this->siteStructure = $siteStructure;
		$this->addVariable('pagetitle', $siteStructure->currPageRec['name']);
		
		if (isset($this->siteStructure->currPageRec['inx'])) {
			$this->addVariable('numberofpages', $siteStructure->getNumberOfPages());
			$this->addVariable('pagenumber', $siteStructure->currPageRec['inx'] + 1);
		} else {
			$this->addVariable('numberofpages', '');
			$this->addVariable('pagenumber', '');
		}
	} // loadStandardVariables




	public function readAll($lang = false)
	{
        $lang = ($lang) ? $lang : $_SESSION["lizzy"]["lang"];
		$vars = array();
		foreach ($this->transvars as $key => $entry) {
			if (!is_array($entry)) {
				$vars[$key] = $entry;
			} elseif (isset($entry[$lang])) {
				$vars[$key] = $entry[$lang];
			} elseif (isset($entry['_'])) {
				$vars[$key] = $entry['_'];
			} else {
                logError("Error: transvar without propre value: '$key'");
			}
		}
		return $vars;
	} // readAll




	public function renderAllTranslationObjects($lang = false)
	{
		$this->loadAllMacros();
		if ($lang) {
			$transvars = $this->readAll($lang);
		} else {
			$transvars = &$this->transvars;
		}

		$out = $this->renderAllVariables($transvars);

		$out .= $this->renderAllMacros();

		$out = str_replace('~', '&#126;', $out);
        return $out;

	}



    private function renderAllVariables($transvars)
    {
        uksort($transvars, "strnatcasecmp");
        $str = "\n\t<div class='lzy-list-transvars lzy-encapsulated'>\n\t\t<h1>Transvars:</h1>\n";
        foreach ($transvars as $key => $rec) {
            if (isset($rec['uu'])) {
                $inactive = ' unused';
                unset($rec['uu']);
            } else {
                $inactive = '';
            }
            if (isset($rec['src'])) {
                $src = " <span class='lzy-list-src'>({$rec['src']})</span>";
                unset($rec['src']);
            } else {
                $src = '';
            }
            $str .= <<<EOT

        <div class='lzy-list-entry$inactive'>
            <div class='lzy-list-line'><span class='lzy-list-var'>$key:</span>$src</div>
EOT;
            if (is_array($rec)) {
                foreach ($rec as $lang => $text) {
                    $text = htmlentities($text);
                    $str .= <<<EOT

            <div class='lzy-list-line'>
                <span class='lzy-list-attr-name'>$lang</span>:
                <span class='lzy-list-attr-value'>$text</span>
            </div>

EOT;
                }
            } else {
                $rec = htmlentities($rec);
                $str .= <<<EOT

            <div class='lzy-list-line'>
                <span class='lzy-list-scalar-value'>$rec</span>
            </div>

EOT;

            }
            $str .= <<<EOT
        </div>

EOT;
        }
        $str .= "\t</div>\n";
        return $str;
    }




    private function renderAllMacros()
    {
        $macros = $this->macroInfo;
        $out = '';
        foreach($macros as $name => $info) {
            $out .= "<div><span class='lzy-macro-name'>$name()</span>: <span class='lzy-macro-info'>$info</span></div>\n";
        }

        $out = "<h2>Macros</h2>\n<div class=' lzy-encapsulated'>$out</div>\n";
        return $out;
    }



    public function reset( $files )
    {
        $this->processFiles($files, 'resetFile');
    }




    private function resetFile($file)
    {
        copy($file, $file.'.0');
        $lines = file($file);
        $out = '';
        foreach ($lines as $i => $l) {
            $l1 = isset($lines[$i+1]) ? $lines[$i+1] : '';
            if (preg_match('/^[^#]*:\s*$/', $l) && (strpos($l1, 'uu: true') === false)) {
                $out .= "$l    uu: true\n";
            } else {
                $out .= $l;
            }
        }
        file_put_contents($file, $out);
    }




    public function postprocess()
    {
        $this->updateUndefinedVarsFile();
        return $this->processFiles($GLOBALS['files'], 'updateTransvarFile');
    }




    public function removeUnusedVariables()
    {
        return $this->processFiles($GLOBALS['files'], 'removeUnusedVariableFromFile');
    }




    private function removeUnusedVariableFromFile($filename)
    {
        $lines = file($filename);
        $out = '';
        $outInactive = '';
        $var = false;
        $end = false;
        $modified = false;
        $note = '';
        $rec = '';
        $unused = false;
        foreach ($lines as $line) {
            if (strpos($line, '__END__') === 0) {
                $end = true;
            }

            if (preg_match('/^([^#]*):\s*$/m', $line, $m)) {   // beginning of var
                if ($unused) {      // append previous rec:
                    $note .= "<p>$var</p>\n";
                    $outInactive .= $rec;
                } else {
                    $out .= $rec;
                }
                $var = $m[1];
                $rec = '';
                $unused = false;
            }
            if ((isset($line[0]) && ($line[0] !== '/')) && (!$end && strpos($line, 'uu: true') !== false)) {
                if (isset($this->transvars[$var]['uu'])) {
                    $unused = true;
                }
            }
            $rec .= $line;
        }

        if ($outInactive) {
            $out .= "\n\n__END__\n#############################################\n# Unused Variables:\n\n".$outInactive;
            $note = "<h2>File updated: $filename</h2>\n".$note;
            file_put_contents($filename . ".1", $out);
        }
        return $note;
    }



    public function renderUnusedVariables()
    {
        return $this->processFiles($GLOBALS['files'], 'renderUnusedInFile');
    }



    private function renderUnusedInFile($filename)
    {
        return "<p>renderUnusedInFile() not implemented yet [$filename]</p>";
    }



    private function updateTransvarFile($filename)
    {
        $lines = file($filename);
        $out = '';
        $var = false;
        $end = false;
        $modified = false;
        $note = '';
        foreach ($lines as $line) {
            if (strpos($line, '__END__') === 0) {
                $end = true;
            }
            if (preg_match('/^([^#]*):\s*$/m', $line, $m)) {
                $var = $m[1];
            }
            if ((isset($line[0]) && ($line[0] !== '/')) && (!$end && strpos($line, 'uu: true') !== false)) {
                if (isset($this->transvars[$var]['uu'])) {
                    $out .= $line;
                } else {
                    $note .= "used var: $var<br>\n";
                    $modified = true;
                }
            } else {
                $out .= $line;
            }
        }

        if ($modified) {
            $note = "<h2>$filename</h2>\n".$note;
            file_put_contents($filename, $out);
        }
        return $note;
    }
    
    
    
    private function processFiles($filenames, $fun)
    {
        $note = '';
        foreach($filenames as $file) {
            if (substr($file, -1) === '*') {
                $files2 = getDir($file);
                foreach ($files2 as $f) {
                    if (fileExt($f) === 'yaml') {
                        $note .= $this->$fun($f);
                    }
                }
            } else {
                $note .= $this->$fun($file);
            }
        }
        return $note;
    }



    private function updateUndefinedVarsFile()
    {
        if (!$this->undefinedVars) {
            return;
        }
        $undefinedVars = getYamlFile(UNDEFINED_VARS_FILE);
        $rec = [];
        $supportedLanguages = explode(',', str_replace(' ', '', $this->config->site_supportedLanguages ));
        foreach ($supportedLanguages as $l) {
            $rec[$l] = '';
        }

        foreach ($this->undefinedVars as $key) {
            $undefinedVars[$key] = $rec;
        }
        ksort($undefinedVars);
        writeToYamlFile(UNDEFINED_VARS_FILE, $undefinedVars);
    }


    public function getPageObject()
    {
        return $this->page;
    }



    private function handleModifers($var)
    {
        $c1 = $var[0];
        if (strpos('#^!&', $c1) !== false) {    // modifier? #, ^, !, &
            $var = trim(substr($var, 1));
            if ($c1 === '#') {
                $this->commmented = true;
            } elseif ($c1 === '!') {
                $this->dontCache = true;
            } elseif ($c1 === '&') {
                $this->compileMd = true;
            } else {
                $this->optional = true;
            }
        }
        return $var;
    }




    private function shieldVariableInstanceForCache(&$str, $p1, $var, $p2)
    {
        return substr($str, 0, $p1) . "{|{| $var |}|}" . substr($str, $p2 + 2);
    }



    public function hasVariablesShieldedFromCache($str)
    {
        return (strpos($str, '{|{|') !== false);
    }




    public function unshieldVariablesForCache($str)
    {
        return str_replace(["{|{|","|}|}"], ['{{', '}}'], $str);
    }




    private function injectMacroSource($macro)
    {
        if (!isset($this->macroSources[$macro][$this->varCount])) {
            return '';
        }

        // special option for macros: inject macro source, either as block or as popup:
        $source = '';
        $lines = explode("↵", $this->macroSources[$macro][$this->varCount]['text']);
        foreach ($lines as $l) {
            if (!$l) {
                continue;
            }
            if (preg_match('/^(\s+)(.*)/', $l, $m)) {
                $l = str_repeat('    ', strlen($m[1])) . $m[2];
            }
            $source .= "\t$l\n";
        }
        $source0 = $source;
        $source = ":::: .lzy-src-wrapper.lzy-src-wrapper{$this->varCount}\n::: .lzy-src-code\n## Code\n\t\{{ " .
            trim($source) .
            "\n\t}}\n::: .lzy-src-output\n## Output\n<div>@#@</div>\n:::\n::::\n";

        // compile markdown:
        $source = compileMarkdownStr($source);
        $source = preg_replace('/\n\s+</ms', "\n<", "\n$source"); // remove leading space
        $source = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], $source); // restore ** (strong)
        $source = preg_replace("/\n\s*\n/ms", "\n", $source);

        // case popup:
        if (strpos($this->macroSources[$macro][$this->varCount]['mode'], 'popup') !== false) {
            $popup = $this->page->addPopup([
                'contentFrom' => ".lzy-src-wrapper{$this->varCount} .lzy-src-code",
                'triggerButton' => '&lt;/&gt;',
                'draggable' => true,
                'header' => 'Macro Source',
                'id' => 'lzy-src-code-popup-'.$this->varCount,
            ]);
            $source = "<div class='lzy-src-code-popup'>$popup\n$source</div>";
        }

        if (strpos($this->macroSources[$macro][$this->varCount]['mode'], 'html') !== false) {
            $args = $this->macroSources[$macro][$this->varCount]['args'];
            unset( $this->macroSources[$macro][$this->varCount] );
            $source0 = $this->translateMacro($macro, $args);
            $source0 = str_replace(['&lt;strong&gt;', '&lt;/strong&gt;'], ['<strong>', '</strong>'], $source0); // restore ** (strong)
            $source0 = preg_replace("/\n\s*\n/ms", "\n", $source0);
            $html = str_replace('<', '&lt;', $source0);
            $html = str_replace("\n", "\n\t", $html);
            $source1 = <<<EOT
::: .lzy-src-html-wrapper
## HTML as Rendered
    $html
:::

EOT;
            $source .= compileMarkdownStr($source1);
        }


        return $source;
    } // injectMacroSource

} // Transvar

