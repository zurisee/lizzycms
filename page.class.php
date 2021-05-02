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
 *  Modules-Array: $file => [$rank, $type ]
 * $type: css, js
*/

define('MAX_ITERATION_DEPTH', 10);
define('EMOJINAMES_FILE', '_lizzy/rsc/emojis.json');



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
    private $jqStart = '';
    private $jqEnd = '';
    private $autoAttrFiles = '';
    private $bodyTagClasses = '';
    private $bodyTagInjections = '';
    private $bodyTopInjections = '';
    private $bodyLateInjections = '';
    private $bodyEndInjections = '';
    private $message = '';
    private $pageSubstitution = false;
    private $override = false;   // if set, will replace the page content
    private $overlay = [];    // if set, will add an overlay while the original page gets fully rendered
    private $debugMsg = false;
    private $redirect = false;
    public  $mdVariables = [];
    private $popupCnt = 0;

    private $mdCompileModifiedContent = false;
    private $wrapperTag = 'section';

    private $assembledCss = '';
    private $assembledJs = '';
    private $assembledJq = '';
    private $headerContentSecurityPolicy = ''; // -> http-header "Content-Security-Policy:"

    private $assembledCssFiles = '';
    private $assembledJsFiles = '';

    private $metaElements = ['lzy', 'trans', 'config', 'metaElements']; // items that shall not be merged


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
        } elseif (strpos($var, '.') !== false) {
            $a = explode('.', $var);
            $r = &$this;
            while ($a) {
                $k = array_shift($a);
                if (isset($r->$k)) {
                    $r = $r->$k;
                } elseif (is_array($r) && isset($r[$k])) {
                    $r = $r[$k];
                } else {
                    return null;
                }
            }
            return $r;
        } else {
            return '';
        }
    }



    public function updateFromFrontmatter($values)
    {
        // merge given values into the page object's properties:
        foreach ($values as $key => $value) {
            if (isset($this->$key)) {
                if (!$this->$key) {
                    $this->$key = $value;
                } else {
                    if (is_array($this->$key) && is_array($value)) {
                        $this->$key = array_merge($this->$key, $value);

                    } elseif (is_string($this->$key) && is_string($value)) {
                        if (strpos('jq,js', $key) !== false) {
                            $this->$key .= "\n$value";
                        } else {
                            $this->$key .= $value;
                        }

                    } elseif (is_int($this->$key) && is_int($value)) {
                        $this->$key += $value;

                    } else {
                        fatalError("page->updateFromFrontmatter: type clash or unsupported type: $value");
                    }
                }
            }
        }
    } // updateFromFrontmatter




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
                        if (strpos('jq,js', $key) !== false) {
                            $this->$key .= "\n$value";
                        } else {
                            $this->$key .= " $value";
                        }
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




    public function getEncoded()
    {
        $encoded = serialize($this);
        return $encoded;
    } // getEncoded




    public function addBody($str, $replace = false)
    {
        $this->addToProperty('bodyTopInjections', $str, $replace);
    } // addBody




    public function addBodyClasses($str, $replace = false)
    {
        $this->addToProperty('bodyTagClasses', ' '.$str, $replace);
    } // addBodyClasses




    public function addBodyTagAttributes($str, $replace = false)
    {
        $this->addToProperty('bodyTagInjections', $str, $replace);
    } // addBodyTagAttributes




    public function addTemplate($str)
    {
        $this->addToProperty('template', $str, true);
    } // addContent




    public function addContent($str, $replace = false)
    {
        $this->addToProperty('content', $str, $replace);
    } // addContent




    public function addHead($str, $replace = false)
    {
        $this->addToProperty('head', $str, $replace);
    } // addHead




    public function addKeywords($str, $replace = false)
    {
        $this->addToListProperty($this->keywords, $str, $replace);
    } // addKeywords




    public function addDescription($str, $replace = false)
    {
        $this->addToListProperty($this->description, $str, $replace);
    } // addDescription




    public function addCssFiles($str, $replace = false)
    {
        $this->addModules($str, $replace);
    } // cssFiles




    public function addCss($str, $replace = false)
    {
        $this->addToProperty('css', $str, $replace);
    } // addCss




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





    public function addJsFiles($str, $replace = false, $persisent = false)
    {
        $this->addModules($str, $replace);
		if ($persisent) {
			$_SESSION['lizzy']["lizzyPersistentJsFiles"] .= $str;
		}
    } // addJsFiles




    public function addAutoAttrFiles($str, $replace = false, $persisent = false)
    {
        $this->addToListProperty($this->autoAttrFiles, $str, $replace);
    } // addAutoAttrFiles




    public function addJs($str, $replace = false)
    {
        $this->addToProperty('js', $str, $replace);
    } // addJs




    public function addJQFiles($str, $replace = false)
    {
        $this->addModules($str, $replace);
    } // addJQFiles




    public function addJq($str, $replace = false)
    {
        //??? avoid adding 'lzy-editable' multiple times:
        if ((strpos($str, '.lzy-editable') !== false) && (strpos($this->jq, '.lzy-editable') !== false)) {
            return;
        }

        $str = trim($str, " \t\n");
        if ($replace === 'append') {
            $this->addToProperty('jqEnd', $str);

        } elseif ($replace === 'prepend') {
            $this->addToProperty('jqStart', $str);

        } else {
            $this->addToProperty('jq', $str, $replace);
        }
    } // addJq




    public function addBodyLateInjections($str, $replace = false)
    {
        $this->addToProperty('bodyLateInjections', $str, $replace);
    } // addBodyLateInjections




    public function addBodyEndInjections($str, $replace = false)
    {
        $this->addToProperty('bodyEndInjections', $str, $replace);
    } // addBodyEndInjections




    public function addMessage($str, $replace = false)
    {
        $str = str_replace("\n", '<br>', $str);
        $this->addToProperty('message', $str, $replace);
    } // addMessage




    public function addPopup($args)
    {
        if (is_string($args)) {
            $args = [ 'text' => $args ];
        }

        $out = '';
        // option 'triggerButton' -> render button to open popup:
        if (isset($args['triggerButton'])) {
            $this->popupCnt++;
            $label = $args['triggerButton'];
            $buttonId = "lzy-popup-trigger-$this->popupCnt";
            $out = "\t<button id='$buttonId' class='lzy-button lzy-show-source-btn'>$label</button>\n";
            unset($args['triggerButton']);
            $args['trigger'] = "#$buttonId";
            $args['closeButton'] = true;
        }

        $jsArgs = '';
        foreach ($args as $key => $value) {
            if (is_string(($key))) {
                if ($value === true) {
                    $jsArgs .= "\t$key: true,\n";
                } elseif ($value === false) {
                    $jsArgs .= "\t$key: false,\n";
                } else {
                    $value = str_replace("'", "\\'", $value);
                    $jsArgs .= "\t$key: '$value',\n";
                }
            }
        }

        $jq = <<<EOT

lzyPopup({
$jsArgs});

EOT;
        $this->addJq($jq);
        $this->addModules('POPUPS');

        return $out;
    } // addPopup




    public function addPageSubstitution($str)
    {
        $this->pageSubstitution = $str;
    } // addMessage




    public function removeModule($module, $str)
    {
        $mod = $this->$module;
        $mod = str_replace($str, '', $mod);
        $mod = str_replace(',,', ',', $mod);
        $this->$module = $mod;
    } // removeModule





    public function setOverrideMdCompile($mdCompile)
    {
        $this->mdCompileModifiedContent = $mdCompile;
    }




    public function addOverride($args, $replace = false, $mdCompile = null)
    {
        if (is_string($args)) {
            if (preg_match('/^fromFile\((.*)\)/', $args, $m)) {
                $args = ['fromFile' => $m[1], 'mdCompile' => $mdCompile];
            } else {
                $args = ['text' => $args, 'mdCompile' => $mdCompile];
            }
        }
        // save state of modules:
        $args['currState'] = [
            'js' => $this->js,
            'jsFiles' => $this->jsFiles,
            'jq' => $this->jq,
            'jqFiles' => $this->jqFiles,
            'modules' => $this->modules,
            'jsModules' => $this->jsModules
        ];
        $this->override = $args;

    } // addOverride




    public function setOverlayMdCompile($mdCompile)
    {
        $this->mdCompileModifiedContent = $mdCompile;
    }




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
        $args['trigger'] = isset($args['trigger']) ? $args['trigger'] : 'auto';
        if ($replace) {
            unset($this->overlay);
            $this->overlay[] = $args;
        } else {
            $this->overlay[] = $args;
        }
    } // addOverlay




    public function addDebugMsg($str, $replace = false)
    {
        $this->addToProperty('debugMsg', $str, $replace);
    } // addDebugMsg




    public function addRedirect($str)
    {
        $this->addToProperty('redirect', $str, true);
    } // addRedirect




    protected function addToProperty($key, $var, $replace = false)
    {
        if ($replace === 'prepend') {
            $this->$key = $var . $this->$key;
        } elseif ($replace) {
            $this->$key = $var;
        } else {
            if ($this->$key) {
                if (strpos('jq,js', substr($key,0,2)) !== false) {
                    $this->$key .= "\n$var";
                } else {
                    $this->$key .= $var;
                }
            } else {
                $this->$key = $var;
            }
        }
    } // addToProperty




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





    public function applyOverride()
    {
        $override = $this->override;
        $this->override = false;
        if (is_string($override)) {
            if (isset($this->mdCompileModifiedContent) && ($this->mdCompileModifiedContent || ($this->mdCompileModifiedContent === null) )) {
                $override = compileMarkdownStr($override);
            }
            $this->addContent($override, true);
            $this->addBodyClasses('lzy-page-override');
            return true;

        } else {
            // restore state at time of addOverride:
            foreach ($override['currState'] as $k => $v) {
                $this->$k = $v;
            }
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
            $text = "\t<section class='lzy-overridden'>$text</section>\n";
            $this->addContent($text, true);
            $this->addBodyClasses('lzy-page-override');
            return true;
        }
        return false;
    } // applyOverride





    public function setOverlayClosable($on = true)
    {
        $this->overlay['closable'] = $on;
    }




    public function applyOverlay()
    {
        if (!$this->overlay) {
            return false;
        }

        if (is_string($this->overlay)) {
            $overlays[0] = ['text' => $this->overlay, 'mdCompile' => true, 'closable' => true, 'id' => 'lzy-overlay', 'trigger' => 'auto'];
        } else {
            $overlays = $this->overlay;
        }

        $text = $jq = '';
        foreach ($overlays as $overlay) {
            if (isset($overlay['contentFrom']) && $overlay['contentFrom']) {
                $jq .= "$('#{$overlay['id']}').append( $( '{$overlay['contentFrom']}' ).html() );\n$('{$overlay['contentFrom']}' ).remove();";

            } else {
                if (isset($overlay['fromFile']) && $overlay['fromFile']) {
                    $file = resolvePath($overlay['fromFile'], true);
                    if (file_exists($file)) {
                        $t = getFile($file);
                    }

                } elseif (isset($overlay['text'])) {
                    $t = $overlay['text'];
                }
                if ((isset($overlay['mdCompile']) && $overlay['mdCompile']) || $this->mdCompileModifiedContent) {
                    $t = compileMarkdownStr($t);
                }
                $text .= $t;
            }
        }

        if (!isset($overlay['closable'])) {
            $overlay['closable'] = true;
        }
        if ($overlay['closable'] === 'reload') {
            $text = "<button id='lzy-close-overlay' class='lzy-close-overlay'>✕</button>\n".$text;
            // set ESC to close overlay:
            $jq .="\n$('body').keydown( function (e) { if (e.which === 27) { $('.lzy-overlay').hide(); } });\n".
                "$('.lzy-overlay .lzy-close-overlay').click(function(e) { lzyReload(); });\n";
        } else {
            $text = "<button id='lzy-close-overlay' class='lzy-close-overlay'>✕</button>\n".$text;
            // set ESC to close overlay:
            $jq .="\n$('body').keydown( function (e) { if (e.which === 27) { $('.lzy-overlay').hide(); } });\n".
                "$('.lzy-overlay .lzy-close-overlay').click(function() { $('.lzy-overlay').hide(); });\n";
        }

        $onOpen = '';
        if (isset($overlay['onOpen']) && $overlay['onOpen']) {
            $onOpen = " {$overlay['onOpen']}( {$overlay['id']} );";
        }

        $style = '';
        if ($overlay['trigger'] === 'none') {
            $style = " style='display: none'";
        } elseif ($overlay['trigger'] !== 'auto') {
            $style = " style='display: none'";
            $jq .= "$('{$overlay['trigger']}').click(function(){ $onOpen$('#{$overlay['id']}').show(); });";
        }
        if (isset($overlay['id'])) {
            $id = "id='{$overlay['id']}'";
        } else {
            $id = '';
        }

        $this->addJq($jq, 'prepend');
        $this->addBodyLateInjections("<div $id class='lzy-overlay'$style>$text</div>\n");

        $this->removeModule('jqFiles', 'PAGE_SWITCHER');
        $this->overlay = false;
        return true;
    } // applyOverlay





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




    public function applyDebugMsg()
    {
        if ($debugMsg = $this->debugMsg) {
            $debugMsg = compileMarkdownStr($debugMsg);
            $debugMsg = createDebugOutput($debugMsg);
            $debugMsg = "<div id='lzy-log-placeholder'></div>\n".$debugMsg;
            $this->addBodyLateInjections($debugMsg);
            $this->debugMsg = false;
            return true;
        }
        return false;
    } // applyDebugMsg





    public function applyMessage()
    {
        if ($msg = $this->message) {
            if (strpos($msg, '{{') !== false) {
                $msg = $this->trans->translate($msg);
            }
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

        if ($this->config->site_enableFilesCaching) {
            $this->getModules('css');
            $href = $this->exportCachedModule('css');
            $headInjections .= "\t<link href='$href' rel='stylesheet' />\n";

        } else {
            $headInjections .= $this->getModules('css');
        }

        if ($this->assembledCss) {
            $assembledCss = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledCss);
            $headInjections .= "\t<style>\n$assembledCss\n\t</style>\n";
        }

        if ($this->config->site_enableRelLinks) {
            $headInjections .= $this->renderRelLinks();
        }

        $headInjections = "\t<!-- head injections -->\n$headInjections\t<!-- /head injections -->";
        return $headInjections;
    } // getHeadInjections




    public function prepareBodyEndInjections()
    {
        // interatively collects snippets for css, js, jq
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

        if ($this->config->site_enableFilesCaching) {
            $this->getModules('js');
            $href = $this->exportCachedModule('js');
            $bodyEndInjections .= "\t<script src='$href'></script>\n";

        } else {
            $bodyEndInjections .= $this->getModules('js');
        }

        if ($this->config->debug_allowDebugInfo &&
            ((($this->config->debug_showDebugInfo)) || getUrlArgStatic('debug'))) {
            if ($this->config->isPrivileged) {
                $bodyEndInjections .= $this->renderDebugInfo();
            }
        }

        $this->assembleInlineJsAndJq( $bodyEndInjections );

        $bodyEndInjections = "<!-- body_end_injections -->\n$bodyEndInjections\n<!-- /body_end_injections -->";

        return $bodyEndInjections;
    } // getBodyEndInjections




    private function assembleInlineJsAndJq( &$bodyEndInjections )
    {
        $screenSizeBreakpoint = $this->config->feature_screenSizeBreakpoint;
        $pathToRoot = $this->lzy->pathToRoot;
        $rootJs  = <<<EOT
        var appRoot = '$pathToRoot';
        var systemPath = '$pathToRoot{$this->config->systemPath}';
        var screenSizeBreakpoint = $screenSizeBreakpoint
        var pagePath = '{$this->lzy->pagePath}';
EOT;

        $assembledJs = '';
        if ($rootJs.$this->assembledJs) {
            $assembledJs = "\t\t".preg_replace("/\n/", "\n\t\t", $this->assembledJs);
            $assembledJs = <<<EOT

$rootJs$assembledJs
\t
EOT;
            $assembledJs = $this->lzy->resolveAllPaths($assembledJs);

            $bodyEndInjections = <<<EOT
{$this->bodyLateInjections}
    <script>$assembledJs</script>
$bodyEndInjections
EOT;
        }


        $assembledJq = $this->assembledJq;
        if ($this->jqStart) {
            $assembledJq = $this->jqStart . $assembledJq;
        }
        if ($this->jqEnd) {
            $assembledJq .= $this->jqEnd;
        }

        if ($assembledJq) {
            if (strpos($assembledJq, '{{') !== false) {
                $assembledJq = $this->trans->translate($assembledJq);
            }
            $assembledJq = "\t\t\t".preg_replace("/\n/", "\n\t\t\t", $assembledJq);
            $assembledJq = <<<EOT

        $( document ).ready(function() {
$assembledJq
        });        
    
EOT;

            $bodyEndInjections .= <<<EOT
    <script>$assembledJq</script>
EOT;
        }

        $this->assembledJs = $assembledJs;

        $this->assembledJq = $assembledJq;
    } // assembleInlineJsAndJq


    

    public function applyContentSecurityPolicy()
    {
        // Content-Security-Policy:
        //   check using https://webbkoll.dataskydd.net/

        // CSP header code assembled in page.class -> send in header now if defined:
        if (!$this->config->site_ContentSecurityPolicy) {
            return;
        }

        $cspReportMode = ($this->config->site_ContentSecurityPolicy === 'report')? '-Report-Only' : '';

        $cspStr = "Content-Security-Policy$cspReportMode:";

        // CSP generic rule -> allow only objects from own host:
        $cspStr .= " default-src 'self';";

        // CSP for styles:
        $cspStr .= " style-src 'self' 'unsafe-inline';";

        // CSP for fonts:
        $cspStr .= " font-src 'self'";

        // special case: Calendar macro loaded:
        if (@$GLOBALS['lizzy']['calInitialized']) {
            // $fontStr = "data:application/x-font-ttf;charset=utf-8;base64,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";
            // $hash = base64_encode(hash('sha256', $fontStr, true));
            $hash = 'F5KvOoc41xMgBeFnHQMjO4D5c2zog/bWJ1I0bzCNPpQ='; // precomputed hash of above
            $cspStr .= " 'sha256-$hash'";
        }
        $cspStr .= ";";

        // CSP for FrameAncestors:
        if ($this->config->site_allowFrameAncestors) {
            $cspStr .= " frame-ancestors {$this->config->site_allowFrameAncestors};";
        }

        // misc CSP rules:
        $cspStr .= " base-uri 'self'; form-action 'self'; connect-src 'self';";


        // CSP for Scripts:
        $cspStr .= " script-src 'self'";
        if ($this->assembledJs) {
            $hash = base64_encode(hash('sha256', $this->assembledJs, true));
            $cspStr .= " 'sha256-$hash'";
        }
        if ($this->assembledJq) {
            $hash = base64_encode(hash('sha256', $this->assembledJq, true));
            $cspStr .= " 'sha256-$hash'";
        }
        $cspStr .= ";";

        // CSP for images:
        // Just a small fix for a problem with Chrome: loads this image when opening a date-input element.
        // $str = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPS
        // IxNiIgaGVpZ2h0PSIxNSIgdmlld0JveD0iMCAwIDI0IDI0Ij48cGF0aCBmaWxsPSJXaW5kb3dUZXh0IiBkPSJNMjAgM2gtMV
        // YxaC0ydjJIN1YxSDV2Mkg0Yy0xLjEgMC0yIC45LTIgMnYxNmMwIDEuMS45IDIgMiAyaDE2YzEuMSAwIDItLjkgMi0yVjVjMC
        // 0xLjEtLjktMi0yLTJ6bTAgMThINFY4aDE2djEzeiIvPjxwYXRoIGZpbGw9Im5vbmUiIGQ9Ik0wIDBoMjR2MjRIMHoiLz48L3
        // N2Zz4=';
        // $hash = base64_encode(hash('sha256', $str, true));
        $hash = 'BORJ0g0DZdiBpU7SSvftAP553Z+eYBJI9P13kwA5JPI='; // precomputed hash of above
        $cspStr .= " img-src 'self' 'sha256-$hash';";

        $this->lzy->setCspHeader( $cspStr ); // report back, required in caching
        header( $cspStr );

        // activate Strict-Transport-Security:
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');

    } // applyContentSecurityPolicy




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
                $item1 = resolvePath($item, true, true);
                $out .= "\t<link href='$item1' rel='stylesheet'$mediaType />\n";

                if ($this->config->site_enableFilesCaching) {
                    $this->assembledCssFiles .= $this->getFile( $item, true );
                }
            }

        } else { // js:
            foreach ($this->jsModules as $item) {
                $item1 = resolvePath($item, true, true);
                if ($this->config->isLocalhost && (strpos($item1, 'jquery-') !== false)) {
                    $item1 = str_replace('.min.', '.', $item1);
                }
                $out .= "\t<script src='$item1'></script>\n";

                if ($this->config->site_enableFilesCaching) {
                    $this->assembledJsFiles .= $this->getFile( $item );
                }
            }
        }

        return $out;
    } // getModules




    private function exportCachedModule( $type )
    {
        $pagePath = $GLOBALS['globalParams']['pagePath'];
        if ($type === 'css') {
            $filename = MODULES_CACHE_PATH . "{$pagePath}styles.css";
            $href = $GLOBALS['globalParams']['appRootUrl'] . $filename;

            // make sure target folder has sufficient access permissions:
            preparePath($filename, MKDIR_MASK_WEBACCESS);
            file_put_contents($filename, $this->assembledCssFiles);

        } else { // js
            $lang = $GLOBALS['globalParams']['lang'];
            $filename = MODULES_CACHE_PATH. "{$pagePath}scripts.{$lang}.js";
            $href = $GLOBALS['globalParams']['appRootUrl'] . $filename;

            // make sure target folder has sufficient access permissions:
            preparePath($filename, MKDIR_MASK_WEBACCESS);

            // translate variables embedded in js files:
            $assembledJsFiles = $this->assembledJsFiles;
            while (preg_match('/(?<!\\\) ( {{(.*?)}} ) /x', $assembledJsFiles, $m)) {
                $val = $this->lzy->trans->translateVariable( trim($m[2]), true );
                $assembledJsFiles = str_replace($m[1], $val, $assembledJsFiles);
            }
            file_put_contents($filename, $assembledJsFiles);
        }
        return $href;
    } // exportCachedModule




    private function getFile( $filename, $isCss = false )
    {
        $filename = resolvePath($filename);
        if ( !file_exists( $filename )) {
            die("Error in page.class::getFile: file '$filename' not found.");
        }
        $content = file_get_contents( $filename );

        // in CSS files we need to adapt 'url()' rules to reflect new file location:
        if ($isCss) {
            $path = dirname($filename) . '/';
            $cachePath = MODULES_CACHE_PATH . $GLOBALS['globalParams']['pagePath'];
            $upPath = preg_replace('|.*?/|', '../', $cachePath);
            $corrPath = $upPath . $path;
            $content = preg_replace('/url\( (["\']) /x', "url($1$corrPath", $content);
        }

        $out = "/* === File $filename =============== */\n$content\n\n\n\n";
        return $out;
    } // getFile




    public function prepareModuleLists()
    {
        $str = ','.$this->modules.','.$this->cssFiles.','.$this->jsFiles.','.$this->jqFiles;

        if (preg_match_all('/,(JQUERY\s?),/', $str, $m)) {
            if (sizeof($m) === 1) {
                $str = str_replace('JQUERY,', '', $str);
            }

        } elseif ($this->config->feature_autoLoadJQuery !== false) {
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
                // check whether source contains rank like {xy}:
                if (preg_match('/\{(\d+)\}/', $module, $m)) {
                    $rank = intval($m[1]);
                    $module = str_replace($m[0], '', $module);
                    $primaryModules[] = [$module, $rank];
                } else {
                    $modules[] = $module;
                }
            }
        }

        usort($primaryModules, function($a, $b) { return ($a[1] < $b[1]); });
        $primaryModules = array_column($primaryModules, 0);
        $modules = array_merge($primaryModules,$modules);
        $cssModules = [
            '~sys/css/__lizzy-core.css',
            '~sys/css/__lizzy-aux.css',
            '~/css/__styles.css'
        ];
        $jsModules = [];
        foreach ($modules as $mod) {
            if (preg_match('/\.css$/i', $mod)) {    // split between css and js files
                if (!in_array($mod, $cssModules)) {         // avoid doublets
                    $cssModules[] = $mod;
                }
            } elseif (preg_match('/\.js$/i', $mod)) {
                if (!in_array($mod, $jsModules)) {         // avoid doublets
                    $jsModules[] = $mod;
                }
            }
        }
        $this->cssModules = $cssModules;
        $this->jsModules = $jsModules;
        $this->modulesInitialized = true;

    } // prepareModuleLists





    public function render()
    {
        $n = 0;
        do {
            $modified = false;

            $modified |= $this->trans->supervisedTranslate($this, $this->template);
            $modified |= $this->trans->supervisedTranslate($this, $this->content);

            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJs);
            $modified |= $this->trans->supervisedTranslate($this, $this->assembledJq);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyTopInjections);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyLateInjections);
            $modified |= $this->trans->supervisedTranslate($this, $this->bodyEndInjections);

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

            // check, whether we need to auto-invoke modules based on classes:
            //            if ($this->config->feature_autoLoadClassBasedModules) {
            //                $modified |= $this->autoInvokeClassBasedModules($this->content);
            //                $modified |= $this->autoInvokeClassBasedModules($this->template);
            //            }

            // get and inject body-end elements, compile them first:
            $modified |= $this->prepareBodyEndInjections();

            $this->applyRedirect();

            if ($n++ >= MAX_ITERATION_DEPTH) {
                fatalError("Max. iteration depth exeeded.<br>Most likely cause: a recursive invokation of a macro or variable.");
            }
        } while ($modified);

        $html = $this->assembleHtml();

        if ($this->config->feature_replaceNLandTabChars) {
            $html = str_replace(['\n', '\t'], ["\n", "\t"], $html);
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

        if ($this->bodyTopInjections) {
            $bodyTopInjections = "<!-- body_top_injections -->\n{$this->bodyTopInjections}<!-- /body_top_injections -->\n\n";
        } else {
            $bodyTopInjections = '';
        }

        $tuples = [
            'body_classes' =>           trim($this->bodyTagClasses),
            'body_tag_attributes' =>    $this->bodyTagInjections,
            'head_injections' =>        $this->getHeadInjections(),
            'body_tag_injections' =>    $bodyTagInjections,
            'body_top_injections' =>    $bodyTopInjections,
            'content' =>                $this->content,
            'body_end_injections' =>    $this->getBodyEndInjections(),
        ];
        $html = $this->lateTranslateVariables($html, $tuples);

        $html = $this->translateEmojisAndIcons( $html ); // :icon:

        $this->injectAllowOrigin(); // send 'Access-Control-Allow-Origin' in header

        return $html;
    } // assembleHtml





    private function injectAllowOrigin()
    {
        if (isset($this->allowOrigin)) {  // from frontmatter
            $allowOrigin = $this->allowOrigin;
        } else {
            $allowOrigin = $this->config->site_enableAllowOrigin;
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
        if ($this->lzy->localHost && !isset($_SERVER['HTTP_ORIGIN'])) {
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




    private function translateEmojisAndIcons( $html )
    {
        $out = '';
        while (preg_match('/(.*?) : ([a-z] [a-z0-9_-]{1,35}) : (.*)/xms', $html, $m)) {
            if (substr($m[1], -1) === '\\') {
                $html = $m[3];
                $out .= substr($m[1], 0,-1) . ":{$m[2]}:";

            } else {
                $html = $m[3];
                $out .= $m[1];
                if (!isset($this->emojiNames)) {
                    $this->emojiNames = json_decode( file_get_contents(EMOJINAMES_FILE), true);
                }
                if (isset($this->emojiNames[ $m[2] ])) {
                    $icon = $this->emojiNames[ $m[2] ];
                    $out .= "<span class='lzy-emoji' data-icon='$icon'>&#8203;</span>";
                } else {
                    $out .= "<span class='lzy-icon lzy-icon-{$m[2]}'></span>";
                }
            }
        }
        return $out.$html;
    } // translateEmojisAndIcons



    private function lateTranslateVariables( $html, $tuples)
    {
        foreach ($tuples as $varName => $varValue) {
            $html = preg_replace("/(?<!\\\\) {@{@ \^? \s* $varName \s* @}@}/x", $varValue, $html);
        }
        return $html;
    } // lateTranslateVariables




    public function shieldVariablesForLateTranslation($str, $vars)
    {
        foreach ($vars as $varName) {
            $str = preg_replace("/(?<!\\\\) {{ \^? \s* $varName \s* }}/x", "{@{@$varName@}@}", $str);
        }
        return $str;
    } // shieldVariablesForLateTranslation




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
            $this->addJq('$(".scrollToBottom").scrollTop($(".scrollToBottom")[0].scrollHeight);');
        }

        $debugInfo .= "<div id='lzy-log'></div>";
        $debugInfo .= "<div id='lzy-php' style='margin-top: 2em;'>PHP-Version: ".phpversion()."</div>";

        $debugInfo = "\n<div id='debugInfo'><p><strong>DebugInfo:</strong></p>$debugInfo</div>\n";
        $debugInfo = str_replace('{', '&#123;', $debugInfo);
        return $debugInfo;
    } // renderDebugInfo




    public function lateApplyMessage($html, $msg)
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
    } // lateApplyMessage




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



    private function renderRelLinks()
    {
        $home = rtrim($GLOBALS['globalParams']['host'], '/') . $GLOBALS['globalParams']['appRoot'];
        $headInjections = "\t<link rel='home' title='Home' href='$home' >\n";
        if ($this->lzy->siteStructure->prevPage) {
            $headInjections .= "\t<link rel='prev' title='Previous' href='~/{$this->lzy->siteStructure->prevPage}' >\n";
        }
        if ($this->lzy->siteStructure->nextPage) {
            $headInjections .= "\t<link rel='next' title='Next' href='~/{$this->lzy->siteStructure->nextPage}' >\n";
        }
        return $headInjections;
    } // renderRelLinks




    public function extractFrontmatter($str)
    {
        return $this->_extractFrontmatter($str);
    } // extractFrontmatter



    private function extractSettings( &$hdr )
    {
        if (!$this->config) {
            return;
        }
        // directly apply any config properties 'feature_...' to the global config values:
        $featureConfigProps = $this->config->getConfigProperties('feature');
        foreach ($featureConfigProps as $key => $value) {
            $k = substr($key, 8);
            if (isset($hdr[$k])) {
                $this->config->setConfigValue($key, $hdr[$k]);
                // special case 'dataPath': activate immediately (i.e. copy to S_SESSION and $GLOBALS):
                if ($k === 'dataPath') {
                    $_SESSION['lizzy']['dataPath'] = $hdr['dataPath'];
                    $GLOBALS['globalParams']['dataPath'] = $hdr['dataPath'];
                }
            }
        }

        // runPHP:
        if (isset($hdr['runPHP'])) {
            if (!$this->config->custom_permitUserCode) {
                fatalError("Trying to use 'runPHP' in frontmatter, but config option 'custom_permitUserCode' is not enabled.");
            }
            $phpFile = $hdr['runPHP'];
            if ($phpFile[0] !== '-') {
                $phpFile = '-' . $phpFile;
            }
            $phpFile = USER_CODE_PATH . $phpFile;
            if (file_exists($phpFile)) {
                require $phpFile;
            } else {
                fatalError("Trying to use 'runPHP' in frontmatter, but file '$phpFile' not found.");
            }
            unset($hdr['runPHP']);
        }

        // runPHPonce:
        if (isset($hdr['runPHPonce'])) {
            if (!$this->config->custom_permitUserCode) {
                fatalError("Trying to use 'runPHPonce' in frontmatter, but config option 'custom_permitUserCode' is not enabled.");
            }
            $phpFile = $hdr['runPHPonce'];
            if ($phpFile[0] !== '-') {
                $phpFile = '-' . $phpFile;
            }
            if (!isset($GLOBALS['lizzy']['runPHPonce'][$phpFile])) {
                $GLOBALS['lizzy']['runPHPonce'][$phpFile] = true;
                $phpFile = USER_CODE_PATH . $phpFile;
                if (file_exists($phpFile)) {
                    require $phpFile;
                } else {
                    fatalError("Trying to use 'runPHP' in frontmatter, but file '$phpFile' not found.");
                }
                unset($hdr['runPHPonce']);
            }
        }

        // mdVariables:
        $mdVariables = [];
        if (isset($hdr['mdVariables'])) {
            foreach ($hdr['mdVariables'] as $key => $value) {
                $key = str_replace('$', '', $key);
                if (preg_match('/^ \s* (\w [\w\d]*) \( (.*?) \) $/x', $value, $m)) {
                    $funName = $m[1];
                    if (!$this->config->custom_permitUserCode) {
                        fatalError("Trying to use function '$funName()' in frontmatter, but config option 'custom_permitUserCode' is not enabled.");
                    }
                    if (function_exists($funName)) {
                        $value = $funName($m[2]);
                    } else {
                        fatalError("Trying to use function, '$funName()' is not defined.");
                    }
                }
                $mdVariables[$key] = $value;
            }
            unset($hdr['mdVariables']);
            $this->mdVariables = array_merge($this->mdVariables, $mdVariables);

        } else {
            foreach ($hdr as $key => $value) {
                if ($key[0] !== '$') {
                    continue;
                }
                $mdVariables[substr($key, 1)] = $value;
                unset($hdr[$key]);
            }
            $this->mdVariables = array_merge($this->mdVariables, $mdVariables);
        }

        // extract "locales" directive:
        if (isset($hdr['locales'])) {
            $this->trans->readTransvarsFromFiles( $hdr['locales'] );
        }

        // check whether there is a 'yaml' file in modules containing locales/transvars:
        if (isset($hdr['modules'])) {
            $modules = $hdr['modules'];
            if (strpos($modules, '.yaml') !== false) {
                $modules = explodeTrim(',', $modules);
                foreach ($modules as $module) {
                    if (strpos($module, '.yaml') !== false) {
                        $this->trans->readTransvarsFromFile( $module );
                    }
                }
            }
        }
    } // extractSettings




    public function extractHtmlBody($html)
    {
        $html = $this->_extractFrontmatter($html);

        if (($p1=strpos($html, '<body')) !== false) {
            $p1 = strpos($html, '>', $p1);
            if (($p2=strpos($html, '</body')) !== false) {
                $html = trim(substr($html, $p1+1, $p2-$p1-1));
            }
        }
        return $html;
    } // extractHtmlBody



    private function _extractFrontmatter($str) // $propagateToGlobalSettings = false
    {
        if (strpos($str, '---') !== 0) {
            return $str;
        }
        $p1 = strpos($str, "\n")+1;
        $p2 = strpos($str, "\n---", 4);
        if (!$p2) {
            return $str;
        }
        $yaml = substr($str, $p1, $p2-$p1);
        $str = substr($str, strpos($str, "\n", $p2+4));

        if ($yaml) {
            $yaml = str_replace("\t", '    ', $yaml);
            try {
                $hdr = convertYaml($yaml);
            } catch (Exception $e) {
                fatalError("Error in Yaml-Code: <pre>\n$yaml\n</pre>\n" . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
            }
        }
        if ($hdr && is_array($hdr)) {
            $this->extractSettings($hdr);
            $this->updateFromFrontmatter($hdr);
            $this->set('frontmatter', $hdr);

            if ($this->trans && isset($hdr['variables'])) {
                $this->trans->addVariables( $hdr['variables'] );
            }
        }
        return $str;
    } // _extractFrontmatter

} // Page
