<?php

// type: [top, side, sitemap, in-page] Specifies the type of output to be rendered.
// layout: [horizontal, vertical] Specifies direction of top-level items.
// animation: [dropdown, slidedown, collapsable] Defines the type of animation applied to the rendered tree.
// options: [top-level, curr-branch, hidden] These are filters that render a subset of items.

define('NAV_ARROW', '&#9657;'); // '&#9013;'; // '&#9657;'; //'&#9656;';
define('NAV_ARROW_TOP', NAV_ARROW);
define('NAV_SMALL_TREE_THRESHOLD', 15); // if nav tree small larger, mobile nav will be fully expanded initially

$page->addJqFiles('TABBABLE');

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
    }




    public function render($options)
    {
        if ($this->tree == false) {     // it's a "one-pager", don't render any navigation
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
        if ($type == 'top') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-top-horizontal lzy-nav-indented lzy-nav-animated lzy-nav-colored lzy-nav-hoveropen lzy-encapsulated');
            $options['options'] .= " editable $primaryClass";

        } elseif ($type == 'side') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-indented lzy-nav-animated lzy-nav-open-current lzy-encapsulated');
            $options['options'] .= " editable $primaryClass";

        } elseif ($type == 'sitemap') {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-indented lzy-encapsulated');

        } elseif ($type == 'sitemap-accordion') {
            $options['navClass'] = trim($options['navClass'].'  lzy-nav-indented lzy-nav-animated lzy-nav-collapsed lzy-encapsulated');

        } elseif ($type == 'breadcrumb') {
            return $this->renderBreadcrumb($options);

        } elseif ($type != '') {
            return "<div style='background-color: yellow;'>Warning: <br />Nav-Renderer: unknown type '$type'.<br />Please chose one of [top, top-dropdown, side, sitemap-accordion, sitemap, breadcrumb, in-page]</div>";

        } else {
            $layout = $options['layout'] ? $options['layout'] : '';
            if ($layout == 'vertical') {
                $layoutClass = '';
            } elseif ($layout == 'horizontal') { // horizontal
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

        if (strpos($options['options'],'noDefaultColoring') !== false) {
            $options['navClass'] = str_replace('lzy-nav-colored', '', $options['navClass']);
        }
        if (strpos($options['theme'],'dark') !== false) {
            $options['navClass'] = str_replace('lzy-nav-colored', 'lzy-nav-dark-theme', $options['navClass']);
        }

        if (strpos($options['options'],'showTransition') !== false) {
            $options['navClass'] = trim($options['navClass'].' lzy-nav-animated');
        }
        if (($options['animation'] == 'slidedown') && (strpos($options['navClass'],'lzy-nav-hoveropen') === false)) {
            $options['navClass'] .= ' lzy-nav-hoveropen';
        }
        if (strpos($options['navClass'], 'lzy-nav-hoveropen') === false) {
            $options['navClass'] .= ' lzy-nav-clicktoopen';
        }

        if (sizeof($this->list) < NAV_SMALL_TREE_THRESHOLD) {   // automatically open nav if small
            $options['navClass'] .= ' lzy-nav-small-tree';
        }
        $this->options = $options;
        return $this->renderSitemap();
    } // render




    //....................................................
    public function renderSitemap()
    {
        $options = $this->options;
        $type = isset($options[0]) ? $options[0] :  (isset($options['type']) ? $options['type'] : '');
        $navClass = (isset($options['navClass'])) ? $options['navClass']: '';
        $navClass = str_replace('.', ' ', $navClass);
        $ulClass = (isset($options['ulClass'])) ? $options['ulClass']: '';
        $liClass = (isset($options['liClass'])) ? $options['liClass']: '';
        $hasChildrenClass = (isset($options['hasChildrenClass'])) ? $options['hasChildrenClass']: '';
        $aClass = (isset($options['aClass'])) ? $options['aClass']: '';
        $navWrapperClass = (isset($options['navWrapperClass'])) ? $options['navWrapperClass']: '';
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
        $this->openCurr = (strpos($navClass, 'lzy-nav-open-current') !== false);
        $this->collapse = (strpos($navClass, 'lzy-nav-collapsed') !== false);
        $this->currBranch = (strpos($navOptions, 'curr-branch') !== false);
        $this->currBranchEmpty = true;

        $nav = $this->_renderSitemap(false, $type, 0, "\t\t", '', $navOptions);

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
            $title = "<h1>$title</h1>";
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

        $out = <<<EOT

  <div{$this->id} class='{$editClass}lzy-nav-wrapper$navWrapperClass'$dataAttr>$edWrapper
$title	  <nav$navClass aria-label="$ariaLabel">
$nav
	  </nav>
$_edWrapper  </div>

EOT;

        $this->addNoScriptCode($navClass);

        return $out;
    } // renderSitemap




    //....................................................
    private function _renderSitemap($tree, $type, $level, $indent, $firstElem = '', $navOptions = false)
    {
        $level++;
        $indent = str_replace('\t', "\t", $indent);
        if ($indent == '') {
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
        if ($this->listTag == 'div') {
            $li = 'div';
        } else {
            $li = 'li';
        }

        $modif = false;
        $out = '';

        // in case curr folder has md content, firstElem is added on lower level to support touch devices:
        if ($firstElem) {
            $headingElem = "$indent1<$li class='lzy-lvl$level lzy-nav-for-touch-only'>$firstElem</$li>\n";
        } else {
            $headingElem = '';
        }

        $aClass = ($this->aClass) ? " class='{$this->aClass}'" : '';
        foreach($tree as $n => $elem) {
            if (!is_int($n)) { continue; }
            if ($mutliLang && isset($elem[$currLang])) {
                $name = $elem[$currLang];
            } else {
                $name = $elem['name'];
            }
            if (isset($elem['goto'])) {
                $targInx = $this->lzy->siteStructure->findSiteElem($elem['goto']);
                $list = $this->lzy->siteStructure->getSiteList();
                $targ = $list[$targInx];
                $name = $targ['name'];
                $path = $targ['folder'];
            } else {
                $path = (isset($elem['folder'])) ? $elem['folder'] : '';
            }
            if ($path == '') {
                $path = $GLOBALS['globalParams']['host'].substr($GLOBALS['globalParams']['appRoot'],1);

            } elseif ((($r=strpos($path, '~/')) !== 0) && ($r !== false)) {
                // theoretical case: ~/ appears somewhere within the path -> remove everything before
                $path = substr($path, $r);

            } elseif ($path{0} == '/') {
                // case path starts with '/', -> assume app-root, turn it into '~/':
                $path = '~'.$path;

            } elseif (substr($path, 0, 2) != '~/') {
                // case path starts without indicator, where it's rooted -> assume app-root:
                $path = '~/'.$path;
            }

            $liClassOpen = '';
            $liClass = $this->liClass." lzy-lvl$level";
            if ($elem['isCurrPage']) {
                $liClass .= ' curr active';
                if ($this->config->feature_selflinkAvoid) {
                    $path = '#main';
                }
            } elseif ($elem['active']) {
                $liClass .= ' active';
            }

            $aria1 = 'aria-expanded="false"';
            $aria = 'aria-hidden="true"';

            // tabindex:
            $tabindex = 'tabindex="-1"';
            if (($elem['parent'] === null) || ($this->list[$elem['parent']]['active'])) {
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

                    if ($this->horizTop) {
                        $firstElem = "<a href='$path'$aClass$target $tabindex>$name</a>";   // A1
                    }
                    if ($this->horizTop && ($level !== 1)) {
                        $aria1 = '';
                    }
                    $contentClass = $elem["noContent"] ? ' lzy-nav-no-content': ' lzy-nav-has-content';

                    // --- recursion:
                    $out1 = $this->_renderSitemap($elem, $type, $level, "$indent\t\t", $firstElem, $navOptions);

                    if ($out1) {
                        $liClass .= ' '.$this->hasChildrenClass."$liClassOpen$contentClass";
                        $liClass = trim($liClass);
                        $liClass = ($liClass) ? " class='$liClass'" : '';
                        $out .= "$indent1<$li$liClass>\n";

                        $out .= "$indent2<a onclick='return handleAccordion(this, event);' href='$path'$aClass$target $tabindex $aria1>$aElem</a>\n"; // A0

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
    } // _renderSitemap





    //....................................................
    public function renderBreadcrumb()
    {
        $elem = $this->lzy->siteStructure->currPageRec;
        $list = $this->lzy->siteStructure->getSiteList();
        $elems = [];
        while ($elem) {
            if ($elem['inx'] == 0) {
                break;
            }
            $elems[] = $elem;
            $parent = $elem['parent'];
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

        $out = <<<EOT
    <nav aria-label="breadcrumbs" class="lzy-nav-breadcrumb">
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
