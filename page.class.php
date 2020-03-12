<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Page and its Components
 *
 *  Modules-Array: $file => $rank
 *      -> derived from file-ext
 *      -> rank: counter / from defaults
 *          -> same rank -> replace previous entry
// *  Modules-Array: $file => [$rank, $type ]
// * $type: css, js
*/

define('MAX_ITERATION_DEPTH', 10);



class Page
{
    private $template = '';
    private $content = '';
    private $head = '';
    private $description = '';
    private $keywords = '';
    private $modulesInitialized = false;
    private $cssModules = false;
    private $jsModules = false;
    private $modules = '';
    private $cssFiles = '';
    private $css = '';
    private $scss = '';
    private $jsFiles = '';
    private $js = '';
    private $jqFiles = '';
    private $jq = '';
    private $jqEnd = '';
    private $autoAttrFiles = '';
    private $bodyTagClasses = '';
    private $bodyTagInjections = '';
    private $bodyTopInjections = '';
    private $bodyEndInjections = '';
    private $message = '';
    private $popup = false;
    private $popupInx = false;
    private $pageSubstitution = false;
    private $override = false;   // if set, will replace the page content
    private $overlay = false;    // if set, will add an overlay while the original page gets fully rendered
    private $debugMsg = false;
    private $redirect = false;

    private $mdCompileModifiedContent = false;
    private $wrapperTag = 'section';

    private $assembledCss = '';
    private $assembledJs = '';
    private $assembledJq = '';

    private $metaElements = ['lzy', 'trans', 'config', 'metaElements', 'popupInstance']; // items that shall not be merged


    public function __construct($lzy = false)
    {
        if ($lzy) {
            $this->lzy = $lzy;
            $this->trans = $lzy->trans;
            $this->config = $lzy->config;
        } else {
            $this->lzy = null;
            $this->trans = null;
            $this->config = false;
        }
        $this->popupInstance = new PopupWidget($this);
    }



    public function set($varname, $val) {
        $this->$varname = $val;
    }



    public function get($var, $reset = false) {
        if (isset($this->$var)) {
            $val = $this->$var;
            if ($reset) {
                $this->$var = '';
            }
            return $val;
        } else {
            return '';
        }
    }



    private function appendValue($key, $value, $replace = false)
    {
        if ($replace) {
            $this->$key = $value;
            return true;
        }

        if (isset($this->$key)) {   // property already set
            if ($this->$key) {
                if ($value) {
                    if (strpos(',jqFiles,jsFiles,cssFiles,', ",$key,") !== false) {
                        $this->$key .= ',' . $value;

                    } elseif (is_array($value)) {
                        if (is_array($this->$key)) {
                            $this->$key = array_merge($this->$key, $value);
                        } else {
                            $this->$key = $value;
                        }

                    } else {
                        $this->$key = $this->$key ? "{$this->$key} $value" : $value;
                    }
                }
            } else {
                $this->$key = $value;
            }
            return true;

        } else {    // new property
            $this->$key = $value;
            return false;
        }
    } // appendValue


    

    //-----------------------------------------------------------------------
    public function merge($page, $propertiesToReplace = '')
    {
        if (!(is_object($page) || is_array($page))) {
            return;
        }
        foreach ($page as $key => $value) {
            if (in_array($key, $this->metaElements)) { // skip properties that are not page-elements
                continue;
            }

            if ($key === 'modules') {
                $value = ','.$value;
            }

            if (($key === 'wrapperTag') || (strpos($propertiesToReplace, $key) !== false)) {
                $this->appendValue($key, $value, true);
            } else {
                $this->appendValue($key, $value);
            }
        }
    } // merge



    //-----------------------------------------------------------------------
    public function getEncoded()
    {
        $encoded = serialize($this);
        return $encoded;
    } // getEncoded



    //-----------------------------------------------------------------------
    public function addBody($str, $replace = false)
    {
        $this->addToProperty('bodyTopInjections', $str, $replace);
    } // addBody



    //-----------------------------------------------------------------------
    public function addBodyClasses($str, $replace = false)
    {
        $this->addToProperty('bodyTagClasses', ' '.$str, $replace);
    } // addBodyClasses



    //-----------------------------------------------------------------------
    public function addBodyTagAttributes($str, $replace = false)
    {
        $this->addToProperty('bodyTagInjections', $str, $replace);
    } // addBodyTagAttributes



    //-----------------------------------------------------------------------
    public function addTemplate($str)
    {
        $this->addToProperty('template', $str, true);
    } // addContent



    //-----------------------------------------------------------------------
    public function addContent($str, $replace = false)
    {
        $this->addToProperty('content', $str, $replace);
    } // addContent



    //-----------------------------------------------------------------------
    public function addHead($str, $replace = false)
    {
        $this->addToProperty('head', $str, $replace);
    } // addHead



    //-----------------------------------------------------------------------
    public function addKeywords($str, $replace = false)
    {
        $this->addToListProperty($this->keywords, $str, $replace);
    } // addKeywords



    //-----------------------------------------------------------------------
    public function addDescription($str, $replace = false)
    {
        $this->addToListProperty($this->description, $str, $replace);
    } // addDescription



    //-----------------------------------------------------------------------
    public function addCssFiles($str, $replace = false)
    {
        $this->addModules($str, $replace);
    } // cssFiles



    //-----------------------------------------------------------------------
    public function addCss($str, $replace = false)
    {
        $this->addToProperty('css', $str, $replace);
    } // addCss



    //-----------------------------------------------------------------------
    public function addModules($modules, $replace = false)
    {
        if ($replace) {
            $this->modules = '';
        }

        if (is_string($modules)) {
            $this->modules .= ','.$modules;

        } elseif (is_array($modules)) {
            foreach ($modules as $item) {
                if (is_string($item)) {
                    $this->modules .= ','.$item;
                }
            }
        }
    } // addModules




    //-----------------------------------------------------------------------
    public function addJsFiles($str, $replace = false, $persisent = false)
    {
        $this->addModules($str, $replace);
		if ($persisent) {
			$_SESSION['lizzy']["lizzyPersistentJsFiles"] .= $str;
		}
    } // addJsFiles



    //-----------------------------------------------------------------------
    public function addAutoAttrFiles($str, $replace = false, $persisent = false)
    {
        $this->addToListProperty($this->autoAttrFiles, $str, $replace);
    } // addAutoAttrFiles



    //-----------------------------------------------------------------------
    public function addJs($str, $replace = false)
    {
        $this->addToProperty('js', $str, $replace);
    } // addJs



    //-----------------------------------------------------------------------
    public function addJQFiles($str, $replace = false)
    {
        $this->addModules($str, $replace);
    } // addJQFiles



    //-----------------------------------------------------------------------
    public function addJQ($str, $replace = false)
    {
        //??? avoid adding 'lzy-editable' multiple times:
        if ((strpos($str, '.lzy-editable') !== false) && (strpos($this->jq, '.lzy-editable') !== false)) {
            return;
        }

        if ($replace === 'append') {
            $this->addToProperty('jqEnd', $str);
        } else {
            $this->addToProperty('jq', $str, $replace);
        }
    } // addJQ



    //-----------------------------------------------------------------------
    public function addBodyEndInjections($str, $replace = false)
    {
        $this->addToProperty('bodyEndInjections', $str, $replace);
    } // addBodyEndInjections



    //-----------------------------------------------------------------------
    public function addMessage($str, $replace = false)
    {
        $this->addToProperty('message', $str, $replace);
    } // addMessage



    //-----------------------------------------------------------------------
    public function addPopup($args)
    {
        $this->popupInstance->addPopup($args);
    } // addPopup



    //-----------------------------------------------------------------------
    public function addPageSubstitution($str)
    {
        $this->pageSubstitution = $str;
    } // addMessage



    //-----------------------------------------------------------------------
    public function removeModule($module, $str)
    {
        $mod = $this->$module;
        $mod = str_replace($str, '', $mod);
        $mod = str_replace(',,', ',', $mod);
        $this->$module = $mod;
    } // removeModule




    //-----------------------------------------------------------------------
    public function setOverrideMdCompile($mdCompile)
    {
        $this->mdCompileModifiedContent = $mdCompile;
    }



    //-----------------------------------------------------------------------
    public function addOverride($args, $replace = false, $mdCompile = null)
    {
        if (is_string($args)) {
            if (preg_match('/^fromFile\((.*)\)/', $args, $m)) {
                $args = ['fromFile' => $m[1], 'mdCompile' => $mdCompile];
            } else {
                $args = ['text' => $args, 'mdCompile' => $mdCompile];
            }
        }
        $this->override = $args;

    } // addOverride



    //-----------------------------------------------------------------------
    public function setOverlayMdCompile($mdCompile)
    {
        $this->mdCompileModifiedContent = $mdCompile;
    }



    //-----------------------------------------------------------------------
    public function addOverlay($args, $replace = false, $mdCompile = null, $closable = true)
    {
        if (is_string($args)) {
            if (preg_match('/^contentFrom\((.*)\)/', $args, $m)) {
                $args = ['contentFrom' => $m[1], 'mdCompile' => $mdCompile, 'closable' => $closable];
            } elseif (preg_match('/^fromFile\((.*)\)/', $args, $m)) {
                $args = ['fromFile' => $m[1], 'mdCompile' => $mdCompile, 'closable' => $closable];
            } else {
                $args = ['text' => $args, 'mdCompile' => $mdCompile, 'closable' => $closable];
            }
        }
        $args['closable'] = isset($args['closable']) ? $args['closable'] : false;
        if (!$this->overlay) {
            $this->overlay = $args;

        } else {
            $this->overlay['text'] .= $args['text'];
            $this->overlay['mdCompile'] |= $args['mdCompile'];
            $this->overlay['closable'] |= $args['closable'];

        }
    } // addOverlay



    //-----------------------------------------------------------------------
    public function addDebugMsg($str, $replace = false)
    {
        $this->addToProperty('debugMsg', $str, $replace);
    } // addDebugMsg



    //-----------------------------------------------------------------------
    public function addRedirect($str)
    {
        $this->addToProperty('redirect', $str, true);
    } // addRedirect



    //-----------------------------------------------------------------------
    protected function addToProperty($key, $var, $replace = false)
    {
        if ($replace) {
            $this->$key = $var;
        } else {
            $this->$key .= $var;
        }
    } // addToProperty



    //-----------------------------------------------------------------------
    protected function addToListProperty(&$property, $var, $replace = false)
    {
        if (is_array($var)) {
            if ($replace) {
                $property = '';
            }
            if ($property) {
                $property .= ','.implode(',', $var);
            } else {
                $property = implode(',', $var);
            }
        } else {
            if (!$property || $replace) {
                $property = $var;
            } else {
                if (strpos($property, $var) === false) {    // avoid duplication
                    $property .= ','.$var;
                }
            }
        }
    } // addToListProperty




    //....................................................
    public function applyOverride()
    {
        $override = $this->override;
        $this->override = false;
        if (is_string($override)) {
            if (isset($this->mdCompileModifiedContent) && ($this->mdCompileModifiedContent || ($this->mdCompileModifiedContent === null) )) {
                $override = compileMarkdownStr($override);
            }
            $this->addContent($override, true);
            return true;

        } else {
            if (isset($this->mdCompileModifiedContent) && ($this->mdCompileModifiedContent || ($this->mdCompileModifiedContent === null) )) {
                $override['mdCompile'] = true;
            }
            $text = '';

            if (isset($override['text']) && $override['text']) {
                $text = $override['text']."\n";
            }
            if (isset($override['fromFile']) && $override['fromFile']) {
                $file = resolvePath($override['fromFile'], true);
                if (file_exists($file)) {
                    $text .= getFile($file);
                }
            }
            if ((isset($override['mdCompile']) && $override['mdCompile']) || $this->mdCompileModifiedContent) {
                $text = compileMarkdownStr($text);
            }
            $this->addContent($text, true);
            return true;
        }
        return false;
    } // applyOverride




    //....................................................
    public function setOverlayClosable($on = true)
    {
        $this->overlay['closable'] = $on;
    }



    //....................................................
    public function applyOverlay()
    {
        if (!$this->overlay) {
            return false;
        }

        $text = $jq = '';
        $overlay = $this->overlay;
        if (is_string($overlay)) {
            $overlay = ['text' => $overlay, 'mdCompile' => true, 'closable' => true];
        }

        if (isset($overlay['contentFrom']) && $overlay['contentFrom']) {
            $jq = "$('#lzy-overlay').append( $( '{$overlay['contentFrom']}' ).html() )\n";

        } elseif (isset($overlay['fromFile']) && $overlay['fromFile']) {
            $file = resolvePath($overlay['fromFile'], true);
            if (file_exists($file)) {
                $text = getFile($file);
                if ((isset($overlay['mdCompile']) && $overlay['mdCompile']) || $this->mdCompileModifiedContent) {
                    $text = compileMarkdownStr($text);
                }
            }

        } elseif (isset($overlay['text'])) {
            $text = $overlay['text'];

            if ((isset($overlay['mdCompile']) && $overlay['mdCompile']) || $this->mdCompileModifiedContent) {
                $text = compileMarkdownStr($text);
            }
        }

        if (!isset($overlay['closable'])) {
            $overlay['closable'] = true;
        }
        if ($overlay['closable'] === 'reload') {
            $text = "<button id='lzy-close-overlay' class='lzy-close-overlay'>✕</button>\n".$text;
            // set ESC to close overlay:
            $jq .="\n$('body').keydown( function (e) { if (e.which == 27) { $('.lzy-overlay').hide(); } });\n".
                "$('#lzy-close-overlay').click(function(e) { lzyReload(); });\n";
        } else {
            $text = "<button id='lzy-close-overlay' class='lzy-close-overlay'>✕</button>\n".$text;
            // set ESC to close overlay:
            $jq .="\n$('body').keydown( function (e) { if (e.which == 27) { $('.lzy-overlay').hide(); } });\n".
                "$('#lzy-close-overlay').click(function() { $('.lzy-overlay').hide(); });\n";
        }
        $this->addJq($jq);
        $this->addBody("<div id='lzy-overlay' class='lzy-overlay'>$text</div>\n");
        $this->removeModule('jqFiles', 'PAGE_SWITCHER');
        $this->overlay = false;
        return true;
    } // applyOverlay




    //....................................................
    public function applySubstitution()
    {
        $str = $this->pageSubstitution;
        $this->pageSubstitution = false;

        if (preg_match('/^fromFile\((.*)\)/', $str, $m)) {
            $str = "file: {$m[1]}";
        }
        if (preg_match('/^file:(.*)/', $str, $m)) {
            $file = resolvePath(trim($m[1]), true);
            if (file_exists($file)) {
                $str = getFile($file, true);
                if (fileExt($file) === 'md') {
                    $str = compileMarkdownStr($str);
                    $str = <<<EOT
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
</head>
<body>
$str
</body>
</html>

EOT;
                }
            }
        }
        return $str;
    } // applySubstitution



    //....................................................
    public function applyDebugMsg()
    {
        if ($debugMsg = $this->debugMsg) {
            $debugMsg = compileMarkdownStr($debugMsg);
            $debugMsg = createDebugOutput($debugMsg);
            $debugMsg = "<div id='lzy-log-placeholder'></div>\n".$debugMsg;
            $this->addBodyEndInjections($debugMsg);
            $this->debugMsg = false;
            return true;
        }
        return false;
    } // applyDebugMsg




    //....................................................
    public function applyMessage()
    {
        if ($msg = $this->message) {
            $msg = compileMarkdownStr($msg);
            $msg = createWarning($msg);
            $this->addBody($msg);
            $this->message = false;
            return true;
        }
        return false;
    } // applyMessage



    public function applyRedirect()
    {
        if ($this->redirect) {
            $url = resolvePath($this->redirect, true, true);

            header('Location: ' . $url);
            exit;
        }
    }


    //....................................................
    public function autoInvokeClassBasedModules($content)
    {
        $modified = false;
        $class1 = '';
        foreach ($this->config->classBasedModules as $class => $modules) {
            $varname = 'class_'.$class;
            if (isset($this->config->$varname) && ($class !== $this->config->$varname)) {
                $class1 = $this->config->$varname;
            }
            if (preg_match("/class\=.*['\"\s] $class1 ['\"\s]/x", $content, $m)) {
                foreach ($modules as $module => $rsc) {
                    $modified = true;
                    if ($module === 'cssFiles') {
                        $this->addCssFiles($rsc);

                    } elseif ($module === 'css') {
                        $this->addCss($rsc);

                    } elseif ($module === 'jqFiles') {
                        $this->addJQFiles($rsc);

                    } elseif ($module === 'jq') {
                        $this->addJq($rsc);

                    } elseif ($module === 'jsFiles') {
                        $this->addJsFiles($rsc);

                    } elseif ($module === 'jq') {
                        $this->addJq($rsc);
                    }
                }
                unset($this->config->classBasedModules[$class]); // avoid loading module multiple times
            }
        }
        return $modified;
    } // autoInvokeClassBasedModules




    //....................................................
    private function getHeadInjections()
    {
        $headInjections = $this->head;

        if ($this->config->site_robots || (isset($this->site_robots) && $this->site_robots)) {
            $headInjections .= "\t<meta name='robots' content='noindex,nofollow'>\n";
        }

        $keywords = $this->keywords;
        if ($keywords) {
            $keywords = "\t<meta name='keywords' content='$keywords' />\n";
        }

        $description = $this->description;
        if ($description) {
            $description = "\t<meta name='description' content='$description' />\n";
        }
        $headInjections .= $keywords.$description;

        $headInjections .= $this->getModules('css', $this->cssFiles);

        if ($this->assembledCss) {
            $assembledCss = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledCss);
            $headInjections .= "\t<style>\n{$assembledCss}\n\t</style>\n";
        }

        if ($this->config->site_enableRelLinks) {
            $headInjections .= $this->renderRelLinks();
        }

        $headInjections = "\t<!-- head injections -->\n$headInjections\t<!-- /head injections -->";
        return $headInjections;
    } // getHeadInjections



    //....................................................
    public function prepareBodyEndInjections()
    // interatively collects snippets for css, js, jq
    {
        $modified = false;

        if ($this->css) {
            $this->assembledCss .= $this->css;
            $this->css = '';
            $modified = true;
        }

        if ($this->js) {
            $this->assembledJs .= $this->js;
            $this->js = '';
            $modified = true;
        }

        if ($this->jq) {
            $this->assembledJq .= $this->jq;
            $this->jq = '';
            $modified = true;
        }

        return $modified;
    } // prepareBodyEndInjections




    public function getBodyEndInjections()
    {
        $bodyEndInjections = $this->bodyEndInjections;

        $bodyEndInjections .= $this->getModules('js');

        $screenSizeBreakpoint = $this->config->feature_screenSizeBreakpoint;
        $pathToRoot = $this->lzy->pathToRoot;
        $rootJs  = "\t\tvar appRoot = '$pathToRoot';\n";
        $rootJs .= "\t\tvar systemPath = '$pathToRoot{$this->config->systemPath}';\n";
        $rootJs .= "\t\tvar screenSizeBreakpoint = $screenSizeBreakpoint;\n";
        $rootJs .= "\t\tvar pagePath = '{$this->lzy->pagePath}';\n";

        if (($this->config->debug_allowDebugInfo) &&
            (($this->config->debug_showDebugInfo)) || getUrlArgStatic('debug')) {
            if ($this->config->isPrivileged) {
                $bodyEndInjections .= $this->renderDebugInfo();
            }
        }

        if ($rootJs.$this->assembledJs) {
            $assembledJs = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledJs);
            $bodyEndInjections = <<<EOT
    <script>
$rootJs$assembledJs
    </script>
$bodyEndInjections
EOT;
        }

        if ($this->jqEnd) {
            $this->assembledJq .= $this->jqEnd;
        }

        if ($this->assembledJq) {
            $assembledJq = "\t\t\t".preg_replace("/\n/", "\n\t\t\t", $this->assembledJq);
            $bodyEndInjections .= <<<EOT
    <script>
        $( document ).ready(function() {
$assembledJq
        });        
    </script>
EOT;
        }


        $bodyEndInjections = "<!-- body_end_injections -->\n$bodyEndInjections\n<!-- /body_end_injections -->";

        return $bodyEndInjections;
    } // getBodyEndInjections



    //....................................................
    private function getModules($type)
    {
        $out = '';
        if (!$this->modulesInitialized) {
            $this->prepareModuleLists();
        }

        if ($type === 'css') {
            foreach ($this->cssModules as $item) {
                $mediaType = '';
                if (preg_match('/^(.*?)\@(\w+)/', $item, $m)) {
                    $item = $m[1];
                    $mediaType = " media=\"{$m[2]}\"";
                }
                $item = resolvePath($item, true, true);
                $out .= "\t<link href='$item' rel='stylesheet'$mediaType />\n";
            }

        } else {
            foreach ($this->jsModules as $item) {
                $item = resolvePath($item, true, true);
                $out .= "\t<script src='$item'></script>\n";
            }

        }

        return $out;
    } // getModules




    //-----------------------------------------------------------------------
    public function prepareModuleLists()
    {
        $str = ','.$this->modules.','.$this->cssFiles.','.$this->jsFiles.','.$this->jqFiles;

        if (preg_match_all('/,(JQUERY\s?),/', $str, $m)) {
            if (sizeof($m) === 1) {
                $str = str_replace('JQUERY,', '', $str);
            }

        } elseif ($this->config->feature_autoLoadJQuery != false) {
            $str = ','.$this->config->feature_jQueryModule . ','.$str;
        }

        // Invoke jQuery version 1 if support for legacy browsers is required:
        if ($this->config->isLegacyBrowser) {
            $str = str_replace(',JQUERY,',',JQUERY1,', $str);
        }

        $str = str_replace(',,', ',', trim($str, ', '));
        $rawModules = preg_split('/\s*,+\s*/', $str);

        $modules = [];
        $primaryModules = [];
        foreach ($rawModules as $i => $module) {
            if (!$module) {
                continue;
            }
            if (isset($this->config->loadModules[$module])) {
                $str = $this->config->loadModules[$module]['module'];
                $rank = $this->config->loadModules[$module]['weight'];
                if (strpos($str, ',') !== false) {
                    $mods = preg_split('/\s*,+\s*/', $str);
                    foreach ($mods as $j => $mod) {
                        if (($mod[0] !== '~') && (strpos($mod, '//') === false)) {
                            $mod = '~sys/'.$mod;
                        }
                        $primaryModules[] = [$mod, $rank];
                    }
                } else {
                    if (($str[0] !== '~') && (strpos($str, '//') === false)) {
                        $str = '~sys/'.$str;
                    }
                    $primaryModules[] = [$str, $rank];
                }
            } else {
                $modules[] = $module;
            }
        }

        usort($primaryModules, function($a, $b) { return ($a[1] < $b[1]); });
        $primaryModules = array_column($primaryModules, 0);
        $modules = array_merge($primaryModules,$modules);
        $cssModules = [];
        $jsModules = [];
        foreach ($modules as $mod) {
            if (preg_match('/\.css(\@\w+)?$/i', $mod)) {    // split between css and js files
                if (!in_array($mod, $cssModules)) {         // avoid doublets
                    $cssModules[] = $mod;
                }
            } else {
                if (!in_array($mod, $jsModules)) {         // avoid doublets
                    $jsModules[] = $mod;
                }
            }
        }
        $this->cssModules = $cssModules;
        $this->jsModules = $jsModules;
        $this->modulesInitialized = true;

    } // prepareModuleLists




    //....................................................
    public function render($processShieldedElements = false)
    {
        $n = 0;
        $writeToCache = $this->config->cachingActive;
        if (!$processShieldedElements) {
            $processShieldedElements = !$writeToCache;
        }

        do {
            $modified = false;

            $modified |= $this->trans->supervisedTranslate($this, $this->template, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->content, $processShieldedElements);

            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJs, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJq, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyTopInjections, $processShieldedElements);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyEndInjections, $processShieldedElements);

            // pageSubstitution replaces everything, including template. I.e. no elements of original page shall remain
            if ($this->pageSubstitution) {
                return $this->applySubstitution();
            }

            // inject html just after <body> tag:
            $modified |= $this->applyDebugMsg();
            $modified |= $this->applyMessage();

            // get and inject content, taking into account override and overlay:
            if ($this->override) {
                $this->applyOverride();
                $modified = true;
            } else {
                if ($this->overlay) {
                    $this->applyOverlay();
                    $modified = true;
                }
            }

            $modified |= $this->popupInstance->applyPopup();

            // check, whether we need to auto-invoke modules based on classes:
            if ($this->config->feature_autoLoadClassBasedModules) {
                $modified |= $this->autoInvokeClassBasedModules($this->content);
                $modified |= $this->autoInvokeClassBasedModules($this->template);
            }

            // get and inject body-end elements, compile them first:
            $modified |= $this->prepareBodyEndInjections();

            $this->applyRedirect();

            if ($n++ >= MAX_ITERATION_DEPTH) {
                fatalError("Max. iteration depth exeeded.<br>Most likely cause: a recursive invokation of a macro or variable.");
            }
        } while ($modified);

        if ($writeToCache) {
            $this->writeToCache();
        }
        $html = $this->assembleHtml();

        if ($this->trans->shieldedVariablePresent($html)) {
            $html = $this->render(true);
        }

        return $html;
    } // render




    private function assembleHtml()
    {
        $html = $this->template;

        $html = $this->trans->adaptBraces($html);

        $bodyTagInjections = $this->bodyTagInjections;
        if ($this->bodyTagClasses) {
            $bodyTagInjections = rtrim(" class='".trim($this->bodyTagClasses)."' ".$bodyTagInjections);
        }

        $html = $this->injectValue($html, 'head_injections',        $this->getHeadInjections());
        $html = $this->injectValue($html, 'body_tag_injections',    $bodyTagInjections);
        if ($this->bodyTopInjections) {
            $bodyTopInjections = "<!-- body_top_injections -->\n{$this->bodyTopInjections}<!-- /body_top_injections -->\n\n";
        } else {
            $bodyTopInjections = '';
        }
        $html = $this->injectValue($html, 'body_top_injections', $bodyTopInjections);
        $html = $this->injectValue($html, 'content',                $this->content);
        $html = $this->injectValue($html, 'body_end_injections',    $this->getBodyEndInjections());

        $this->injectAllowOrigin(); // send 'Access-Control-Allow-Origin' in header

        return $html;
    } // assembleHtml




    //....................................................
    private function injectAllowOrigin()
    {
        if (isset($this->allowOrigin)) {  // from frontmatter
            $allowOrigin = $this->allowOrigin;
        } else {
            $allowOrigin = $this->config->feature_enableAllowOrigin;
        }

        if (is_bool($allowOrigin)) {
            if ($allowOrigin) {
                $allowOrigin = '*';
            } else {
                return;
            }
        }

        if (($allowOrigin === '*') || ($allowOrigin === 'true') || ($allowOrigin === 'all') || ($allowOrigin === 'any')) {
            header('Access-Control-Allow-Origin: *');
            return;
        }
        if (!$allowOrigin) {
            return;
        }
        if ($this->lzy->localCall && !isset($_SERVER['HTTP_ORIGIN'])) {
            header('Access-Control-Allow-Origin: *');
            return;
        }

        $allowedOrigins = str_replace(' ', '', ",$allowOrigin,");
        $currRequestOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] . ',': '';
        $currRequestOrigin1 = ',' . $currRequestOrigin;
        if (strpos($allowedOrigins, $currRequestOrigin1) !== false) {
            header('Access-Control-Allow-Origin: ' . $currRequestOrigin);
        }
    } // injectAllowOrigin



    //....................................................
    private function injectValue( $html, $varName, $varValue)
    {
        return str_replace("@@$varName@@", $varValue, $html);
    } // injectValue



    //....................................................
    public function shieldVariable($str, $varName)
    {
        $str = preg_replace("/\{\{\^?\s*$varName\s*\}\}/", "@@$varName@@", $str);
        return $str;
    } // shieldVariable



    //....................................................
    public function renderDebugInfo()
    {
        global $globalParams;
        $debugInfo = var_r($_SESSION, '$_SESSION');
        $globalParams['whoami'] = trim(shell_exec('whoami')).':'.trim(shell_exec('groups'));
        $debugInfo .= var_r($globalParams, '$globalParams');


        if (file_exists(ERROR_LOG)) {
            $errLog = file_get_contents(ERROR_LOG);
            $errLog = substr($errLog, -1000);
            $errLog = str_replace("\n", "<br>\n", $errLog);
            $debugInfo .= "\n<p><strong>Error Log:</strong></p><div class='log scrollToBottom'>$errLog</div>\n";
        }

        if (file_exists(LOGS_PATH . LOGIN_LOG_FILENAME)) {
            $failedLogins = file_get_contents(LOGS_PATH . LOGIN_LOG_FILENAME);
            $failedLogins = substr($failedLogins, -1000);
            $failedLogins = str_replace("\n", "<br>\n", $failedLogins);
            $debugInfo .= "\n<p><strong>Log-ins:</strong></p><div class='log scrollToBottom'>$failedLogins</div>\n";
        }

        if (file_exists(LOGS_PATH . 'log.txt')) {
            $accessLog = file_get_contents(LOGS_PATH . 'log.txt');
            $accessLog = substr($accessLog, -1000);
            $accessLog = str_replace("\n", "<br>\n", $accessLog);
            $debugInfo .= "\n<p><strong>Access Log:</strong></p><div class='log scrollToBottom'>$accessLog</div>\n";
        }

        if (strpos($debugInfo, 'scrollToBottom') !== false) {
            $this->addJQ('$(".scrollToBottom").scrollTop($(".scrollToBottom")[0].scrollHeight);');
        }

        $debugInfo .= "<div id='lzy-log'></div>";
        $debugInfo = "\n<div id='debugInfo'><p><strong>DebugInfo:</strong></p>$debugInfo</div>\n";
        $debugInfo = str_replace('{', '&#123;', $debugInfo);
        return $debugInfo;
    } // renderDebugInfo



    //....................................................
    public function lateApplyMessag($html, $msg)
    {
        $msg = createWarning($msg);
        $p = strpos($html, '<body');
        if ($p) {
            $p = strpos($html, '>', $p);
            if (!$p) {  // syntax error, body tag not closed
                return $html;
            }
            $p++;
            $html = substr($html, 0, $p).$msg.substr($html, $p);
        }
        return $html;
    }



    //....................................................
    public function lateApplyDebugMsg($html, $msg)
    {
        if ((($p = strpos($html, '<div id="lzy-log">')) !== false) ||
            (($p = strpos($html, "<div id='lzy-log'>")) !== false)) {
            $p += strlen('<div id="lzy-log">');
            $before = substr($html, 0, $p);
            $after = substr($html, $p);
            $msg = "<p>$msg</p>";
            $html = $before . $msg . $after;
        } else {
            $p = strpos($html, '</body>');
            if ($p !== false) {
                $before = substr($html, 0, $p);
                $after = substr($html, $p);
                $html = $before . "<div id=\"log\"><p>$msg</p></div>" . $after;
            }
        }
        return $html;
    } // lateApplyDebugMsg



    private function writeToCache()
    {
        $pg2 = clone $this;
        foreach ($pg2 as $key => $value) {
            if (is_object($value)) {
                unset( $pg2->$key );
            }
        }
        writeToCache($pg2);
    } // writeToCache



    public function readFromCache()
    {
        $pg = readFromCache();
        if (!$pg) {
            return false;
        }
        $this->merge($pg);
        return true;
    }



    private function renderRelLinks()
    {
        $home = rtrim($GLOBALS["globalParams"]["host"], '/') . $GLOBALS["globalParams"]["appRoot"];
        $headInjections = "\t<link rel='home' title='Home' href='$home' >\n";
        if ($this->lzy->siteStructure->prevPage) {
            $headInjections .= "\t<link rel='prev' title='Previous' href='~/{$this->lzy->siteStructure->prevPage}' >\n";
        }
        if ($this->lzy->siteStructure->nextPage) {
            $headInjections .= "\t<link rel='next' title='Next' href='~/{$this->lzy->siteStructure->nextPage}' >\n";
        }
        return $headInjections;
    } // renderRelLinks

} // Page
