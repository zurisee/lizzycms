<?php
// @info: Reads a folder and renders a list of files to be downloaded or opened.

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $path = $this->getArg($macroName, 'path', 'Selects the folder to be read', '');
    $this->getArg($macroName, 'pattern', 'The search-pattern with which to look for files (-> \'glob style\')', '');
    $this->getArg($macroName, 'deep', '[false,true,flat] Whether to recursively descend into sub-folders. ("flat" means deep as a non-hierarchical list.)', false);
    $this->getArg($macroName, 'showPath', '[false,true] Whether to render the entire path per file in deep:flat mode.', false);
    $this->getArg($macroName, 'order', '[reverse] Displays result in reversed order.', false);
    $this->getArg($macroName, 'class', 'class to be applied to the enclosing li-tag.', '');
    $this->getArg($macroName, 'target', '"target" attribute to be applied to the a-tag.', '');
    $this->getArg($macroName, 'maxAge', '[integer] Maximum age of file (in number of days).', '');
    $this->getArg($macroName, 'orderedList', 'If true, renders found objects as an ordered list (&lt;ol>).', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($path === 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $r = new DirRenderer($this->lzy, $args);
    $out = $r->render();
    return $out;
});






class DirRenderer
{
    public function __construct($lzy, $args)
    {
        $this->lzy = $lzy;
        $this->args = $args;
        $this->path = $this->getArg('path');
        $this->pattern = $this->getArg('pattern');
        $this->deep = $this->getArg('deep');
        $this->showPath = $this->getArg('showPath');
        $this->order = $this->getArg('order');
        $this->class = $this->getArg('class');
        $this->target = $this->getArg('target');
        $this->exclude = $this->getArg('exclude');
        $this->maxAge = $this->getArg('maxAge');
        $this->orderedList = $this->getArg('orderedList');
        if ($this->getArg('hierarchical')) {
            $this->deep = true;
        }
    }




    public function render()
    {
        if ((strpos(base_name($this->path), '*') === false)) {
            if (!$this->pattern) {
                $this->pattern = "*";
            }
        } else {
            if (!$this->pattern) {
                $this->pattern = base_name($this->path);
            }
            $this->path = dirname($this->path);
        }
        if (strpos($this->pattern, ',') !== false) {
            $this->pattern = explodeTrim(',', $this->pattern);
        }
        if ($this->path) {
            $this->path = fixPath($this->path);
            $this->path = makePathRelativeToPage($this->path);
        } else {
            $this->path = '~page/';
        }

        $this->exclPath = $this->path . $this->exclude;

        if ($this->target) {
            if ($this->target === 'newwin') {
                $this->targetAttr = " target='_blank'";
            } else {
                $this->targetAttr = " target='{$this->target}'";
            }
        } else {
            $this->targetAttr = '';
        }

        if ($this->class) {
            $this->class = " class='{$this->class}'";
        }
        $this->linkClass = '';
        if ($this->target) {
            $this->linkClass = " class='lzy-link lzy-newwin_link'";
        }

        $path = resolvePath($this->path);
        if ($this->deep) {
            if ($this->deep === 'flat') {
                if (is_array($this->pattern)) {
                    $dir = [];
                    foreach ($this->pattern as $pattern) {
                        $dir = array_merge($dir, getDirDeep($path . $pattern));
                    }
                } else {
                    $dir = getDirDeep($path . $this->pattern);
                }
                sort($dir);
                $str = $this->straightList($dir);
            } else {
                $this->pregPattern = '|'.str_replace(['.', '*'], ['\\.', '.*'], $this->pattern).'|';
                $str = $this->_hierarchicalList($path);
            }
        } else {
            if (is_array($this->pattern)) {
                $this->pattern = '{'.implode(',', $this->pattern).'}';
            }
            $dir = getDir($path.$this->pattern);
            $str = $this->straightList($dir);
        }

        return $str;
    } // render



    private function straightList($dir)
    {
        if (!$dir) {
            return "{{ nothing to display }}";
        }
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $maxAge = 0;
        if ($this->maxAge) {
            $maxAge = time() - 86400 * $this->maxAge;
        }
        foreach ($dir as $file) {
            if (is_dir($file) || (filemtime($file) < $maxAge)) {
                continue;
            }
            if (!$this->showPath) {
                $name = base_name($file);
            } else {
                $name = $file;
            }
            $url = $this->parseUrlFile($file);
            if ($url) { // it's a link file (.url or .webloc):
                $name = base_name($file, false);
                require_once SYSTEM_PATH.'link.class.php';
                $lnk = new CreateLink($this->lzy);
                $link = $lnk->render(['href' => $url, 'text' => $name, 'target' => $this->target]);
                $str .= "\t\t<li class='lzy-dir-file'>$link</li>\n";

            } else {    // it's regular local file:
                $url = '~/'.$file;
                $str .= "\t\t<li class='lzy-dir-file'><a href='$url'{$this->targetAttr}{$this->linkClass}>$name</a></li>\n";
            }
        }
        $tag = $this->orderedList? 'ol': 'ul';
        $str = <<<EOT

    <$tag{$this->class}>
$str   
    </$tag>
EOT;
        return $str;
    } // straightList




    private function _hierarchicalList($path, $lvl = 0)
    {
        $maxAge = 0;
        if ($this->maxAge) {
            $maxAge = time() - 86400 * $this->maxAge;
        }

        $dir = getDir("$path*");
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $indent = str_pad('', $lvl, "\t");
        foreach ($dir as $file) {   // loop over items on this level:

            if (is_dir($file)) {        // it's a dir -> decend:
                $name = basename($file);
                $nextPath = fixPath($file);
                $str1 = $this->_hierarchicalList($nextPath, $lvl+1);
                $str .= "\t\t$indent  <li class='lzy-dir-folder'><span>$name</span>\n$str1\n\t\t$indent  </li>\n";

            } else {                    // it's a file
                if (filemtime($file) < $maxAge) {   // check age, skip if too old
                    continue;
                }
                $name = base_name($file);
                $ext = fileExt($file);

                if ($this->pattern) {       // apply pattern:
                    if (!preg_match($this->pregPattern, $name)) {
                        continue;
                    }
                }

                if ($ext === 'url') {   // special case: file-ext 'url' -> render content as link
                    $href = file_get_contents($file);
                    $name = basename($file, '.url');

                } elseif ($ext === 'webloc') {   // special case: file-ext 'webloc' -> extract link
                    $href = str_replace("\n", ' ', file_get_contents($file));
                    if (preg_match('|\<string\>(https?\://.*)\</string\>|', $href, $m)) {
                        $href = $m[1];
                    }
                    $name = basename($file, '.webloc');

                } else {                // regular file:
                    $href = '~/' . $path . basename($file);
                }
                $str .= "\t\t$indent  <li class='lzy-dir-file'><a href='$href'{$this->targetAttr}{$this->linkClass}>$name</a></li>\n";
            }
        }
        $tag = $this->orderedList? 'ol': 'ul';
        $str = <<<EOT

\t\t$indent<$tag{$this->class}>
$str   
\t\t$indent</$tag>

EOT;

        return $str;

    } // _hierarchicalList



    private function parseUrlFile($file)
    {
        if (!file_exists($file)) {
            return false;
        }
        $str = file_get_contents($file);
        if (preg_match('|url=(.*)|ixm', $str, $m)) {    // Windows link
            $url = trim($m[1]);
        } elseif (preg_match('|<string>(.*)</string>|ixm', $str, $m)) { // Mac link
            $url = $m[1];
        } else {
            $url = false;
        }
        return $url;
    } // parseUrlFile



    private function getArg($name)
    {
        return (isset($this->args[$name])) ? $this->args[$name] : '';
    }

} // DirRenderer