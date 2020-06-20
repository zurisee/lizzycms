<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	SCSS Compiler Adapter
 *
 *  Resulting CSS code is stored a) in individual file (e.g. '_style.css') and
 *  aggregated in file '_styles.css'.
 *  Aggregation is skipped if the scss filename starts with a non-apha character, e.g. '@special.css'.
 *  -> Thus it's possible to distribute CSS rules over category files.
*/
use ScssPhp\ScssPhp\Compiler;

class SCssCompiler
{
    private $cssDestPath;

    public function __construct( $lzy )
    {
        $this->config = $lzy->config;
        $this->page = $lzy->page;
        $this->fromFiles = $lzy->config->path_stylesPath;
        $this->sysCssFiles = $lzy->config->systemPath.'css/';
        $this->isPrivileged = $lzy->config->isPrivileged;
        $this->localCall = $lzy->localCall;
        $this->compiledStylesFilename = $lzy->config->site_compiledStylesFilename;
        $this->compiledSysStylesFilename = '__lizzy.css';
        $this->scss = false;
        $this->aggregatedCss = '';

        if (isset($_GET['reset'])) {
            $this->deleteCache();
        }
    } // __construct




    public function compile($forceUpdate = false)
    {
        $this->forceUpdate = $forceUpdate;
        $namesOfCompiledFiles = '';

        // app specific styles:
        $compiledFilename = $this->fromFiles.$this->compiledStylesFilename;
        $files = getDir($this->fromFiles.'scss/*.scss');
        $mustCompile = $this->checkUpToDate($this->fromFiles, $files, $compiledFilename);
        if ($mustCompile) {
            foreach ($files as $file) {
                $namesOfCompiledFiles .= $this->doCompile($this->fromFiles, $file);
            }
            file_put_contents($compiledFilename, $this->aggregatedCss);

            // create minified version:
            //            $this->minify($compiledFilename);
        }


        // optional style sheet in page folder:
        if ($this->config->feature_enableScssInPageFolder) {
            $this->compilePageFolderStylesheet($forceUpdate, $namesOfCompiledFiles);
        }



        // system styles:
        $compiledFilename = $this->sysCssFiles.$this->compiledSysStylesFilename;

        $files = getDir($this->sysCssFiles.'scss/*.scss');
        $mustCompile = $this->checkUpToDate($this->sysCssFiles, $files, $compiledFilename);
        if ($mustCompile) {
            // check whether css/ folder is writable:
            if (!is_writable($this->sysCssFiles)) {
                $this->page->addMessage("Warning: unable to update system style files");
                if ($namesOfCompiledFiles) {
                    writeLog("SCSS files compiled: " . rtrim($namesOfCompiledFiles, ', '));
                }
                writeLog("Warning: failed to update css files in ".$this->sysCssFiles);
                return $namesOfCompiledFiles;
            }

            $this->aggregatedCss = '';
            foreach ($files as $file) {
                $namesOfCompiledFiles .= $this->doCompile($this->sysCssFiles, $file);
            }
            file_put_contents($compiledFilename, $this->aggregatedCss);
            // for compatibility, create copy under old name:
            copy($compiledFilename, $this->sysCssFiles.'_lizzy.css');

            // create minified version:
            //            $this->minify($compiledFilename);
        }

        if ($namesOfCompiledFiles) {
            writeLog("SCSS files compiled: ".rtrim($namesOfCompiledFiles, ', '));
            generateNewVersionCode();
        }
        return $namesOfCompiledFiles;
    } // compile


    
    
    private function checkUpToDate($path, $files, $compiledBundeledFilename)
    {
        if ($this->forceUpdate || !file_exists($compiledBundeledFilename)) {
            $this->forceUpdate = true;
            return true;
        }
        $t2 = filemtime($compiledBundeledFilename);
        foreach ($files as $file) {
            $baseName = basename($file, '.scss');
            if (preg_match('/^\W/', $baseName)) {
                $baseName = substr($baseName, 1);
            }
            $compiledFile = $path . '_' . $baseName . '.css';
            $t0 = filemtime($file);
            $t1 = (file_exists($compiledFile)) ? filemtime($compiledFile) : 0;
            if (($t0 > $t2) || ($t0 > $t1)) {
                $this->forceUpdate = true;
                return true;
            }
        }
        return false;
    } // checkUpToDate


        
    private function getFile($file)
    {
        if ($this->localCall && $this->config->debug_compileScssWithLineNumbers) {
            $out = getFile($file);
            $fname = basename($file);
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                $l = preg_replace('|^ (.*?) (?<!:)// .*|x', "$1", $l);

                if (preg_match('|^ [^/\*]+ \{|x', $l)) {  // add line-number in comment
                    $l .= " [[* content: '$fname:".($i+1)."'; *]]";
                }
                if ($l) {
                    $out .= $l . "\n";
                }
            }

            $p1 = strpos($out, '/*');
            while ($p1 !== false) {
                $p2 = strpos($out, '*/');
                if (($p2 !== false) && ($p1 < $p2)) {
                    $out = substr($out, 0, $p1) . substr($out, $p2 + 2);
                }
                $p1 = strpos($out, '/*', $p1 + 1);
            }
            $out = str_replace(['[[*', '*]]'], ['/*', '*/'], $out);
        } else {
            $out = getFile($file, true);
        }
        return $out . "\n";
    } // getFile



    private function deleteCache()
    {
        $files = getDir($this->cssDestPath . "*.css");
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }



    private function doCompile($toPath, $file, $targetFile = false)
    {
        if (!$this->scss) {
            $this->scss = new Compiler;
            //$this->scss->setLineNumberStyle(Compiler::LINE_COMMENTS);
            // see: https://scssphp.github.io/scssphp/docs/ -> Source Line Debugging
        }
        $includeFile = true;
        $fname = basename($file, '.scss');
        if (preg_match('/^\W/', $fname)) {
            $fname = substr($fname,1);
            $includeFile = false;
        }
        if (!$targetFile) {
            $targetFile = $toPath . "_$fname.css";
        }
        $t0 = filemtime($file);
        $scssStr = $this->getFile($file);
        $cssStr = '';
        try {
            $cssStr .= $this->scss->compile($scssStr);
        } catch (Exception $e) {
            fatalError("Error in SCSS-File '$file': " . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
        }

        if (!$this->compiledStylesFilename) {
            $cssStr = removeCStyleComments($cssStr);
            $cssStr = removeEmptyLines($cssStr);
        }
        $cssStr = "/**** auto-created from '$file' - do not modify! ****/\n\n$cssStr";

        file_put_contents($targetFile, $cssStr);

        if ($includeFile) {                 // assemble all generated CSS, unless its filename started with non-alpha char
            $this->aggregatedCss .= $cssStr . "\n\n\n";
        }

        return basename($file).", ";
    } // doCompile



    private function compilePageFolderStylesheet($forceUpdate, $namesOfCompiledFiles)
    {
        $files = getDirDeep('pages/*.scss');
        foreach ($files as $file) {
            $cssFile = dirname($file) . '/' . base_name($file, false) . '.css';
            if ($forceUpdate || !file_exists($cssFile) || (filemtime($cssFile) < filemtime($file))) {
                $namesOfCompiledFiles .= $this->doCompile($this->fromFiles, $file, $cssFile);
            }
        }
    } // compilePageFolderStylesheet



    public function compileStr($scssStr)
    {
        if (!$this->scss) {
            $this->scss = new Compiler;
        }
        try {
            $cssStr = $this->scss->compile($scssStr);
        } catch (Exception $e) {
            fatalError("Error in SCSS string: '$scssStr'.");
        }
        return $cssStr;
    } // compileStr




    private function minify( $compiledFilename )
    {
        require_once(SYSTEM_PATH . 'third-party/minifier/php-html-css-js-minifier.php');
        $__scss = minify_css( $this->aggregatedCss );
        $minifiedFileName = str_replace('.css', '.min.css', $compiledFilename);
        file_put_contents($minifiedFileName, $__scss);
    }

} // SCssCompiler