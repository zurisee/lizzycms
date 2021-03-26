<?php
/*
 *    type: [top, side, sitemap, in-page] Specifies the type of output to be rendered.
 *    layout: [horizontal, vertical] Specifies direction of top-level items.
 *    animation: [dropdown, slidedown, collapsible] Defines the type of animation applied to the rendered tree.
 *    options: [top-level, curr-branch, hidden] These are filters that render a subset of items.
*/

define('NAV_ARROW', '<span class=\'lzy-icon lzy-icon-triangle\'></span>'); // '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';
define('NAV_ARROW_TOP', NAV_ARROW);
define('NAV_SMALL_TREE_THRESHOLD', 15); // if nav tree small larger, mobile nav will be fully expanded initially


class NavRenderer
{
    public function __construct($lzy, $inx)
    {
        $this->lzy = $lzy;
        $this->tree = $lzy->siteStructure->getSiteTree();
        $this->list = $lzy->siteStructure->getSiteList();
        $this->page = $lzy->page;
        $this->config = $lzy->config;
        $this->inx = $inx;
        $this->page->addModules( 'TABBABLE' );
    }




    public function render($options)
    {
        if ($this->tree === false) {     // it's a "one-pager", don't render any navigation
            return '';
        }
        $type =  trim($options['type']);

        if ($type) {

            // if a php-file exists with that name, execute it:
            $rendererFile = "nav-renderer-$type.php";
            if (file_exists($this->config->path_userCodePath . "$rendererFile")) {
                require_once($this->config->path_userCodePath . "$rendererFile");
                return renderMenu($this, $this->page, $options);

            } elseif (file_exists(SYSTEM_PATH . $rendererFile)) {
                require_once(SYSTEM_PATH . $rendererFile);
                return renderMenu($this, $this->page, $options);

            }
        }

        $options['listWrapper'] = ($options['listWrapper']) ? $options['listWrapper'] : 'div'; // listWrapper by default
        $layoutClass = '';

        $this->id = '';
        $primaryClass = '';
        if ($options['primary'] === null) {
            if ($this->inx === 1) {
                $primaryClass = ' lzy-primary-nav';
                $options['ariaLabel'] = $options['ariaLabel']? $options['ariaLabel'] : '{{ Main Menu }}';
                $this->id = ' id="lzy-primary-nav"';
            }
        } elseif ($options['primary']) {
            $primaryClass = ' lzy-primary-nav';
            $options['ariaLabel'] = $options['ariaLabel']? $options['ariaLabel'] : '{{ Main Menu }}';
        }

        // no specific php-file, so render standard output of predefined types:
        if ($type === 'top') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-top-horizontal lzy-nav-indented lzy-nav-accordion lzy-nav-collapsed lzy-nav-animated lzy-nav-hoveropen lzy-encapsulated');
            $options['options'] .= " editable $primaryClass";

        } elseif ($type === 'side') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-side lzy-nav-vertical lzy-nav-indented lzy-nav-collapsible lzy-nav-open-current lzy-nav-animated lzy-encapsulated');
            $options['options'] .= " editable $primaryClass";

        } elseif ($type === 'side-accordion') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-side lzy-nav-vertical lzy-nav-indented lzy-nav-animated lzy-nav-accordion lzy-nav-collapsed lzy-nav-open-current lzy-encapsulated');
            $options['options'] .= " editable $primaryClass";

        } elseif ($type === 'sitemap') {
            $options['navClass'] = trim($options['navClass'].' lzy-sitemap lzy-nav-vertical lzy-nav-indented lzy-encapsulated');

        } elseif ($type === 'sitemap-accordion') {
            $options['navClass'] = trim($options['navClass'].' lzy-sitemap lzy-nav-vertical lzy-nav-indented lzy-nav-animated lzy-nav-collapsed lzy-encapsulated');

        } elseif ($type === 'breadcrumb') {
            return $this->renderBreadcrumb($options);

        } elseif ($type !== '') {
            return "<div style='background-color: yellow;'>Warning: <br />Nav-Renderer: unknown type '$type'.<br />Please chose one of [top, top-dropdown, side, side-accordion, sitemap-accordion, sitemap, breadcrumb, in-page]</div>";

        } else {
            $layout = $options['layout'] ? $options['layout'] : '';
            if ($layout === 'vertical') {
                $layoutClass = '';
            } elseif ($layout === 'horizontal') { // horizontal
                $layoutClass = 'lzy-nav-top-horizontal';
            }

            $animation = $options['animation'] ? $options['animation'] : '';
            if ($animation) {
                $animClass = ' lzy-nav-animated';
            } else {
                $animClass = '';
            }

            $options['navClass'] = trim($options['navClass']."$layoutClass$animClass");
        }


        if (strpos($options['options'],'showTransition') !== false) {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-animated');
        }
        if (($options['animation'] === 'slidedown') && (strpos($options['navClass'],'lzy-nav-hoveropen') === false)) {
            $options['navClass'] .= ' lzy-nav-hoveropen';
        }
        if (strpos($options['navClass'], 'lzy-nav-hoveropen') === false) {
            $options['navClass'] .= ' lzy-nav-clicktoopen';
        }

        if (sizeof($this->list) < NAV_SMALL_TREE_THRESHOLD) {   // automatically open nav if small
            $options['navClass'] .= ' lzy-nav-small-tree';
        }
        $this->options = $options;
        return $this->renderNav();
    } // render




    //....................................................
    public function renderNav()
    {
        $options = $this->options;
        $type = isset($options[0]) ? $options[0] :  (isset($options['type']) ? $options['type'] : '');
        $navClass = (isset($options['navClass'])) ? $options['navClass']: '';
        $navClass = str_replace('.', ' ', $navClass);
        $ulClass = (isset($options['ulClass'])) ? $options['ulClass']: '';
        $liClass = (isset($options['liClass'])) ? $options['liClass']: '';
        $hasChildrenClass = (isset($options['hasChildrenClass'])) ? $options['hasChildrenClass']: '';
        $aClass = (isset($options['aClass'])) ? $options['aClass']: '';
        $navWrapperClass = (isset($options['wrapperClass'])) ? $options['wrapperClass']: '';
        $navWrapperClass = (isset($options['navWrapperClass'])) ? $options['navWrapperClass']: $navWrapperClass;
        $navOptions = (isset($options['options'])) ? $options['options']: '';
        $title = (isset($options['title'])) ? $options['title']: '';
        $ariaLabel = (isset($options['ariaLabel'])) ? $options['ariaLabel']: $title;
        $this->listTag = (isset($options['listTag'])) ? $options['listTag']: 'ol';
        $this->listWrapper = (isset($options['listWrapper'])) ? $options['listWrapper']: '';
        $this->arrow = (isset($options['arrow'])) ? $options['arrow']: NAV_ARROW;

        $this->ulClass = $ulClass;
        $this->liClass = $liClass;
        $this->hasChildrenClass = ($hasChildrenClass) ? $hasChildrenClass : 'lzy-has-children';
        $this->aClass  = $aClass;
        $this->horizTop = (strpos($navClass, 'lzy-nav-top-horizontal') !== false);
        if ($this->horizTop) {
            $this->arrow = NAV_ARROW_TOP;
        }
        $this->accordion = ((strpos($navClass, 'lzy-nav-accordion') !== false) ||
            (strpos($navClass, 'lzy-nav-collapsible') !== false));
        $this->collapse = (strpos($navClass, 'lzy-nav-collapsed') !== false);
        $this->openCurr = (strpos($navClass, 'lzy-nav-open-current') !== false);
        $this->currBranch = (strpos($navOptions, 'curr-branch') !== false);
        $this->currBranchEmpty = true;

        $nav = $this->_renderNav(false, $type, 0, "\t\t", $navOptions);

        if ($this->currBranch && $this->currBranchEmpty) {
            return null;
        }

        $navClass = trim('lzy-nav '.$navClass);
        $navClass = " class='$navClass'";
        $navWrapperClass = $navWrapperClass ? " $navWrapperClass" : '';
        if (strpos($options['options'], 'lzy-primary-nav') !== false) {
            $navWrapperClass .= ' lzy-primary-nav';
        }
        if ($title && (strpos($title, '<') === false)) {
            $title = "<h1 class='lzy-nav-title'>$title</h1>";
        }
        if (($this->lzy->getEditingMode()) && (strpos($options['options'], 'editable') !== false)) {
            $dataAttr = " data-lzy-filename='sitemap'";
            $editClass = 'lzy-src-wrapper lzy-edit-sitemap ';
            $edWrapper = <<<EOT

      <div id='lzy-editor-wrapper1' class='lzy-sitemap-editor-wrapper'>

EOT;
            $_edWrapper = <<<EOT

      </div><!-- /lzy-editor-wrapper -->

EOT;

        } else {
            $dataAttr = '';
            $editClass = '';
            $edWrapper = '';
            $_edWrapper = '';
        }

        if (strpos($navWrapperClass, 'lzy-primary-nav') !== false) {
            if (!$ariaLabel) {
                $ariaLabel = '{{ lzy-nav-main-title }}';
            }
        }
        if ($ariaLabel) {
            $ariaLabel = " aria-label='$ariaLabel'";
        }

        $out = <<<EOT

  <div{$this->id} class='{$editClass}lzy-nav-wrapper$navWrapperClass'$dataAttr>$edWrapper
$title	  <nav$navClass$ariaLabel>
$nav
	  </nav>
$_edWrapper  </div>

EOT;

        $this->addNoScriptCode($navClass);

        return $out;
    } // renderNav




    //....................................................
    private function _renderNav($tree, $type, $level, $indent, $navOptions = false)
    {
        $level++;
        $indent = str_replace('\t', "\t", $indent);
        if ($indent === '') {
            $indent = "\t";
        }
        $indent1 = "$indent   ";
        $indent2 = "$indent      ";
        if (!$tree) {
            $tree = $this->tree;
        }

        $stop = false;
        if (strpos($navOptions,'top-level') !== false) {
            $stop = true;
        }
        $showHidden = false;
        if ((strpos($navOptions,'hidden') !== false) || (stripos($navOptions,'showall') !== false)) {
            $showHidden = true;
        }
        $currBranch = false;
        if (strpos($navOptions,'curr-branch') !== false) {
            $currBranch = true;
        }


        if ($mutliLang = $this->config->site_multiLanguageSupport) {
            $currLang = $this->config->lang;
        }
        if ($this->listTag === 'div') {
            $li = 'div';
        } else {
            $li = 'li';
        }

        $modif = false;
        $out = '';

        $headingElem = '';

        $aClass = ($this->aClass) ? " class='{$this->aClass}'" : '';
        foreach($tree as $n => $elem) {
            if (!is_int($n)) { continue; }
            if ($mutliLang && isset($elem[$currLang])) {
                $name = $elem[$currLang];
            } else {
                $name = $elem['name'];
            }
            if (isset($elem['goto'])) {
                $path = $elem['goto'];    // render goto url
            } elseif ($elem['urlpath'] !== false) {
                $path = $elem['urlpath']? $elem['urlpath']: '~/';    // render goto url
            } else {
                $path = (isset($elem['folder'])) ? $elem['folder'] : '';
            }
            if ($path === '') {
                $path = $GLOBALS['globalParams']['host'].substr($GLOBALS['globalParams']['appRoot'],1);

            } elseif ((($r=strpos($path, '~/')) !== 0) && ($r !== false)) {
                // theoretical case: ~/ appears somewhere within the path -> remove everything before
                $path = substr($path, $r);

            } elseif ($path[0] === '/') {
                // case path starts with '/', -> assume app-root, turn it into '~/':
                $path = '~'.$path;

            } elseif ((substr($path, 0, 2) !== '~/') &&
                (stripos($path, 'http') !== 0)) {
                // case path starts without indicator, where it's rooted -> assume app-root:
                $path = '~/'.$path;
            }

            $liClassOpen = '';
            $liClass = $this->liClass." lzy-lvl$level";
            if ($elem['isCurrPage']) {
                $liClass .= ' lzy-curr lzy-active';
                if ($this->config->feature_selflinkAvoid) {
                    $path = '#main';
                }
            } elseif ($elem['active']) {
                $liClass .= ' lzy-active';
            }

            $aria1 = 'aria-expanded="false"';
            $aria = 'aria-hidden="true"';

            // tabindex:
            $tabindex = 'tabindex="-1"';
            if (($elem['parentInx'] === null) || ($this->list[$elem['parentInx']]['active'])) {
                $tabindex = '';
            }

            if (!$this->collapse) {
                $tabindex = '';

            } elseif ($elem['active'] && $this->openCurr) {
                $tabindex = '';
            }

            if (isset($elem['target'])) {
                $target = " target='{$elem['target']}'";
            } else {
                $target = '';
            }
            $activeAncestor = $this->lzy->siteStructure->hasActiveAncestor($elem);
            if ($currBranch && !$activeAncestor) {
                continue;
            }
            if ($level > 1) {
                $this->currBranchEmpty = false;
            }

            if ($this->listWrapper) {
                $listWrapper = "<{$this->listWrapper} class='lzy-nav-sub-wrapper' $aria>";
                $_listWrapper = "</{$this->listWrapper}>";
            } else {
                $listWrapper = '';
                $_listWrapper = '';
            }


            $aElem = "<span class='lzy-nav-label'>$name</span><span class='lzy-nav-arrow' aria-hidden='true'>{$this->arrow}</span>";
            if ((!$elem['hide!']) || $showHidden) {
                if ($elem['hide!']) {
                    $liClass .= ' lzy-nav-hidden-elem';
                }
                if (!$stop && isset($elem[0])) {	// does it have children?
                    if ($this->horizTop && ($level !== 1)) {
                        $aria1 = '';
                    }
                    $contentClass = $elem["noContent"] ? ' lzy-nav-no-content': ' lzy-nav-has-content';

                    // --- recursion:
                    $out1 = $this->_renderNav($elem, $type, $level, "$indent\t\t", $navOptions);

                    if ($out1) {
                        $liClass .= ' '.$this->hasChildrenClass."$liClassOpen$contentClass";
                        $liClass = trim($liClass);
                        $liClass = ($liClass) ? " class='$liClass'" : '';
                        $out .= "$indent1<$li$liClass>\n";

                        $out .= "$indent2<a href='$path' class='$this->aClass lzy-handle-accordion'$target $tabindex $aria1>$aElem</a>\n"; // A0

                        $out .= "$indent2$listWrapper\n";
                        $out .= "$out1";
                        $out .= "$indent2$_listWrapper\n";
                        $out .= "$indent1</$li>\n";
                        $modif = true;
                    } else {
                        $liClass .= ' '.$liClassOpen;
                        $liClass = trim($liClass);
                        $liClass = ($liClass) ? " class='$liClass'" : '';
                        $out .= "$indent1<$li$liClass><a href='$path'$aClass$target $tabindex>$name</a></$li>\n";
                        $modif = true;
                    }

                } else {
                    $liClass = trim($liClass);
                    $liClass = ($liClass) ? " class='{$liClass}'" : '';
                    $out .= "$indent1<$li$liClass><a href='$path'$aClass$target $tabindex>$name</a></$li>\n";
                    $modif = true;
                }
            }
        }

        if ($modif) {
            $out = "$headingElem$out";
            $ulClass = ($this->ulClass) ? " class='{$this->ulClass}'" : '';
            $navClass = $this->options['navClass'];

            // apply top-margin for expanding variants: lzy-nav-accordion, collapsed, collapsible and lzy-nav-top-horizontal:
            if (($level > 1) && ((strpos($navClass, 'lzy-nav-top-horizontal') !== false) ||
                    (strpos($navClass, 'lzy-nav-accordion') !== false))) {
                $out = "$indent<{$this->listTag}$ulClass style='margin-top:-40em;'>\n".$out;

            } elseif (($level > 1) && ((strpos($navClass, 'lzy-nav-top-horizontal') === false) &&
                    ((strpos($navClass, 'lzy-nav-accordion') !== false)) ||
                    (strpos($navClass, 'lzy-nav-collaps') !== false))) {
                $out = "$indent<{$this->listTag}$ulClass style='margin-top:-40em;'>\n".$out;

            } else {
                $out = "$indent<{$this->listTag}$ulClass>\n".$out;
            }
            $out .= "$indent</{$this->listTag}>\n";
        }
        return $out;
    } // _renderNav





    //....................................................
    public function renderBreadcrumb($options)
    {
        $ariaLabel = (isset($options['ariaLabel'])) ? $options['ariaLabel']: ''; //$title;
        $elem = $this->lzy->siteStructure->currPageRec;
        $list = $this->lzy->siteStructure->getSiteList();
        $elems = [];
        while ($elem) {
            if ($elem['listInx'] === 0) {
                break;
            }
            $elems[] = $elem;
            $parent = $elem['parentInx'];
            if ($parent === null) {
                break;
            }
            $elem = isset($list[$parent]) ? $list[$parent] : null;
        }
        $elems = array_reverse($elems);

        $sep = "<span class='lzy-nav-breadcrumb-separator'>&gt;</span>";

        $link = "<a href='~/{$list[0]['folder']}'>{$list[0]['name']}</a>";
        $out = "<li>$link</li>";
        foreach ($elems as $rec) {
            $aria = ($rec['isCurrPage']) ? ' aria-current="page"' : '';
            $link = "<a href='~/{$rec['folder']}'$aria>{$rec['name']}</a>";
            $out .= "<li>$sep$link</li>";
        }

        if (!$ariaLabel) {
            $ariaLabel = '{{ lzy-breadcrumb-title }}';
        }

        $out = <<<EOT
    <nav id='lzy-nav-breadcrumb{$this->inx}' aria-label="$ariaLabel" class="lzy-nav-breadcrumb">
      <ol class='lzy-nav-breadcrumb'>$out</ol>
    </nav>
EOT;

        return $out;
    } // renderBreadcrumb




    //....................................................
    private function addNoScriptCode($navClass)
    {
        if (isset($GLOBALS['globalParams']['noScriptAdded'])) {
            return;
        }
        if ((strpos($navClass, 'lzy-nav-accordion') === false) &&
            (strpos($navClass, 'lzy-nav-top-horizontal') === false) ) {
            return;
        }

        $noscript = <<<EOT
    <noscript>
        <style>
          #lzy .lzy-nav-accordion li.lzy-has-children ol { margin-top: 0!important; }
          #lzy .lzy-nav-top-horizontal li:focus-within ol { margin-top: 0!important; }
          #lzy .lzy-nav li label { display: none; }
        </style>
    </noscript>

EOT;

        $GLOBALS['globalParams']['noScriptAdded'] = true;
        $this->page->addHead($noscript);
    }
} // class
