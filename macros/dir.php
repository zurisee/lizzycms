<?php
// @info: Reads a folder and renders a list of files to be downloaded or opened.

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $pattern = $this->getArg($macroName, 'pattern', 'The search-pattern with which to look for files (-> \'glob style\')', '*');
    $path0 = $this->getArg($macroName, 'path', 'Selects the folder to be read', '~page/');
    $deep = $this->getArg($macroName, 'deep', 'Whether to recursively search sub-folders', false);
    $hierarchical = $this->getArg($macroName, 'hierarchical', 'If true, found files will be displayed in hierarchical view.', false);
    $class = $this->getArg($macroName, 'class', 'class to be applied to the enclosing li-tag', '');
    $target = $this->getArg($macroName, 'target', '"target" attribute to be applied to the a-tag', '');
//    $exclude = $this->getArg($macroName, 'exclude', 'pattern to be excluded (-> \'glob style\'), default is \'*.md\'', '');
    $order = $this->getArg($macroName, 'order', '[reversed] If true, directory objects will be output in reversed order', '');

    if ($pattern == 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    $r = new DirRenderer($args);
    $out = $r->render();
    return $out;
});






class DirRenderer
{
    public function __construct($args)
    {
        $this->args = $args;
        $this->pattern = $this->getArg('pattern');
        $this->path0 = $this->getArg('path');
        $this->deep = $this->getArg('deep');
        $this->hierarchical = $this->getArg('hierarchical');
        $this->class = $this->getArg('class');
        $this->target = $this->getArg('target');
        $this->exclude = $this->getArg('exclude');
        $this->order = $this->getArg('order');
    }




    public function render()
    {
        $this->path0 = fixPath($this->path0);
        $this->path1 = resolvePath($this->path0, true);
        $this->path = $this->path1 . $this->pattern;
        $this->exclPath = $this->path1 . $this->exclude;

        if ($this->target) {
            $this->target = " target='{$this->target}'";
        }

        if ($this->class) {
            $this->class = " class='{$this->class}'";
        }

        if ($this->hierarchical) {
            $str = $this->hierarchicalList($this->path);
            return $str;

        } elseif ($this->deep) {
            $dir = getDirDeep($this->path);
        } else {
            $dir = getDir($this->path);
        }
        $str = $this->straightList($dir);

        return $str;

    }



    private function straightList($dir)
    {
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        foreach ($dir as $file) {
//        if ($exclDir && in_array($file, $exclDir)) {
//            continue;
//        }
            if (is_dir($file)) {
                continue;
            }
            $name = base_name($file);
            $file = resolvePath($this->path0 . basename($file), true, true);
            $str .= "\t\t<li><a href='$file'{$this->target}>$name</a></li>\n";
        }
        $str = <<<EOT

    <ul{$this->class}>
$str   
    </ul>
EOT;
        return $str;
    } // straightList




    private function hierarchicalList($path, $lvl = 0)
    {
        if (isset($path[-1]) & ($path[-1] != '*')) {
            $path1 = fixPath($path).'*';
        } else {
            $path1 = $path;
            $path = substr($path,0 -1);
        }
        $dir = getDir($path1);
        if (strpos($this->order, 'revers') !== false) {
            $dir = array_reverse($dir);
        }
        $str = '';
        $indent = str_pad('', $lvl, "\t");
        foreach ($dir as $file) {
            if (is_dir($file)) {
                $name = basename($file);
                $str1 = $this->hierarchicalList(fixPath($file), $lvl+1);
                $str .= "\t\t$indent  <li><span>$name</span>\n$str1\n\t\t$indent  </li>\n";
            } else {
                $name = base_name($file);
                $ext = fileExt($file);
                if ($ext == 'url') {
                    $href = file_get_contents($file);
                    $name = basename($file, '.url');

                } elseif ($ext == 'webloc') {
                    $href = str_replace("\n", ' ', file_get_contents($file));
                    if (preg_match('|\<string\>(https?\://.*)\</string\>|', $href, $m)) {
                        $href = $m[1];
                    }
                    $name = basename($file, '.webloc');

                } else {
                    $href = '~/' . $path . basename($file);
                }
                $str .= "\t\t$indent  <li><a href='$href'{$this->target}>$name</a></li>\n";
            }
        }
        $str = <<<EOT

\t\t$indent<ul{$this->class}>
$str   
\t\t$indent</ul>

EOT;

        return $str;

    } // hierarchicalList




    private function getArg($name)
    {
        return (isset($this->args[$name])) ? $this->args[$name] : '';
    }
} // DirRenderer