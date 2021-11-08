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
        $this->lzy = $lzy;
        $this->config = $lzy->config;
        $this->page = $lzy->page;
        $this->userCssPath = $lzy->config->path_stylesPath;
        $this->sysCssPath = SYSTEM_PATH . 'css/';
        $this->isPrivileged = $lzy->config->isPrivileged;
        $this->localHost = $lzy->localHost;
        $this->site_loadStyleSheets = $lzy->config->site_loadStyleSheets;
        $this->compiledStylesFilename = $lzy->config->site_compiledStylesFilename;
        $this->scss = false;
        $this->aggregatedCss = '';
        $this->aggregatedCssV2 = '';
        $this->aggregatedCssV2aux = '';
        $this->treeParser = null;
        $this->runTreeParser = $this->config->feature_enableScssTreeNotation;
        if ($this->config->feature_enableScssTreeNotation) {
            $this->treeParser = new Tree();
        }

        // check whether css/ folder is writable:
        if (!is_writable($this->sysCssPath)) {
            $this->page->addMessage("Warning: unable to update system style files");
            writeLog("Warning: failed to update css files in ".$this->sysCssPath);
        }

        if (isset($_GET['reset'])) {
            $this->deleteCache();
        }
    } // __construct



    public function compile($forceUpdate = false)
    {
        $this->forceUpdate = $forceUpdate;
        $namesOfCompiledFiles = '';

        // app specific styles:
        $namesOfCompiledFiles = $this->compileAppScss($namesOfCompiledFiles);


        // optional style sheet in page folder:
        $namesOfCompiledFiles .= $this->compilePageFolderStylesheet($forceUpdate);


        // compile system SCSS files:
        $namesOfCompiledFiles .= $this->compileSystemScss();


        // compile SCSS files in extension:
        $namesOfCompiledFiles .= $this->compileExtensionScss();


        if ($namesOfCompiledFiles) {
            writeLog("SCSS files compiled: ".rtrim($namesOfCompiledFiles, ', '));
            generateNewVersionCode();
        }
        return $namesOfCompiledFiles;
    } // compile



    private function doCompile($file, &$target, $saveToFile = false)
    {
        $filename = basename($file, '.scss');
        if ((fileExt($file) === 'css') && file_exists($file)) {
            $cssStr = file_get_contents($file);
        } else {
            if (!$this->scss) {
                $this->scss = new Compiler;
                //$this->scss->setLineNumberStyle(Compiler::LINE_COMMENTS);
                // see: https://scssphp.github.io/scssphp/docs/ -> Source Line Debugging
            }
            $scssStr = $this->getFile($file);
            if ($this->runTreeParser) {
                $scssStr = $this->treeParser->toScss($scssStr);
            }
            $scssStr = preg_replace('/#{([\d\s]+)}/', "XXX$1YYY", $scssStr);
            try {
                $cssStr = $this->scss->compile($scssStr);
            } catch (Exception $e) {
                fatalError("Error in SCSS-File '$file': " . $e->getMessage(), 'File: ' . __FILE__ . ' Line: ' . __LINE__);
            }
            $cssStr = preg_replace('/XXX(.*?)YYY/', "$1", $cssStr);

            if (!$this->compiledStylesFilename) {
                $cssStr = removeCStyleComments($cssStr);
                $cssStr = removeEmptyLines($cssStr);
            }
        }
        $cssStr = "/**** auto-created from '$file' - do not modify! ****/\n\n$cssStr";

        if ($saveToFile) {
            file_put_contents($saveToFile, $cssStr);
        } else {
            $target .= $cssStr . "\n\n\n";
        }
        return "$filename.scss, ";
    } // doCompile




    private function checkUpToDate($files, $compiledBundeledFilename)
    {
        if ($this->forceUpdate || !file_exists($compiledBundeledFilename)) {
            return true;
        }
        $compiledBundeledFilenameT = filemtime($compiledBundeledFilename);
        foreach ($files as $file) {
            $fileT = @filemtime($file);
            if ($fileT > $compiledBundeledFilenameT) {
                return true;
            }
        }
        return false;
    } // checkUpToDate


        
    private function getFile($file)
    {
        if ($this->localHost && ($this->config->debug_compileScssWithLineNumbers || @$_SESSION['lizzy']['debug'])) {
            $out = getFile($file);
            $fname = basename($file);
            $lines = explode(PHP_EOL, $out);
            $out = '';
            foreach ($lines as $i => $l) {
                $l = preg_replace('|^ (.*?) (?<!:)// .*|x', "$1", $l);

                if (preg_match('|^ [^/*]+ {|x', $l)) {  // add line-number in comment
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
        $files1 = getDir($this->sysCssPath . "_*.css");
        $files2 = getDir($this->userCssPath . "_*.css");
        $files = array_merge($files1, $files2);
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    } // deleteCache



    private function compilePageFolderStylesheet($forceUpdate)
    {
        if (!$this->config->feature_enableScssInPageFolder) {
            return '';
        }
        $namesOfCompiledFiles = '';
        $files = getDirDeep('pages/*.scss');
        foreach ($files as $file) {
            $cssFile = dirname($file) . '/' . base_name($file, false) . '.css';
            if ($forceUpdate || !file_exists($cssFile) || (filemtime($cssFile) < filemtime($file))) {
                $namesOfCompiledFiles .= $this->doCompile($this->userCssPath, $file, $cssFile);
            }
        }
    } // compilePageFolderStylesheet



    public function compileStr($scssStr)
    {
        if ($this->config->feature_enableScssTreeNotation) {
            $this->treeParser = new Tree();
            $scssStr = $this->treeParser->toScss($scssStr);
        }
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




    private function getCompiledFileName($file)
    {
        $baseName = base_name($file, false);
        $path = str_replace('scss/','',dirname($file).'/');
        $compiledFile = $path . '_' . $baseName . '.css';
        return $compiledFile;
    } // getCompiledFileName



    private function getFilesToCompile( $tThreshold )
    {
        $files = [];
        $filesLate = [];
        $filesSeparate = [];
        $needToCompile = false;
        $needToCompileLate = false;

        foreach ($this->site_loadStyleSheets as $file0 => $flag) {
            if (!$flag) {
                continue;
            }
            // $file0 can be explicit filepath or just filename presumed in _lizzy/css/, or in _lizzy/css/scss/:
            if (fileExists( $file0 )) {
                $file = $file0;
            } elseif (fileExists( SYSTEM_PATH . "css/scss/$file0" )) {
                $file = SYSTEM_PATH . "css/scss/$file0";
            } else {
                if (fileExt($file0) === 'scss') {
                    $file = "scss/$file0";
                } else {
                    $file = $file0;
                }
                $file = SYSTEM_PATH . "css/$file";
                if (!fileExists( $file )) {
                    exit("Error in SCssCompiler: file not found: $file");
                }
            }

            $t = @filemtime( $file );

            if ($flag === 1) {      // files to load immediately:
                $files[] = $file;
                if ($t >= $tThreshold) {
                    $needToCompile = true;
                }
            } elseif ($flag === 2) {    // files to load later:
                $filesLate[] = $file;
                if ($t >= $tThreshold) {
                    $needToCompileLate = true;
                }

            } elseif ($flag === 3) {    // files to prepare separately (-> loading needs to be done explicitly):
                $compiledFile = $this->getCompiledFileName( $file );
                if ( $this->forceUpdate ) {
                    $filesSeparate[ $file ] = $compiledFile;
                } else {
                    $t0 = @filemtime( $compiledFile );
                    if ($t >= $t0) {
                        $filesSeparate[ $file ] = $compiledFile;
                    }
                }
            }
        }

        if (!$needToCompile && !$this->forceUpdate) {
            $files = [];
        }
        if (!$needToCompileLate && !$this->forceUpdate) {
            $filesLate = [];
        }
        return [$files, $filesLate, $filesSeparate];
    } // getFilesToCompile



    private function compileAppScss()
    {
        $namesOfCompiledFiles = '';
        $aggregatedCss = '';
        $compiledFilename = $this->userCssPath.$this->compiledStylesFilename;
        $files = getDir($this->userCssPath.'scss/*.scss');
        $mustCompile = $this->checkUpToDate($files, $compiledFilename);
        if ($mustCompile) {
            $this->runTreeParser = $this->config->feature_enableScssTreeNotation;
            foreach ($files as $file) {
                $namesOfCompiledFiles .= $this->doCompile($file, $aggregatedCss);
            }
            file_put_contents($compiledFilename, $aggregatedCss);
        }
        return $namesOfCompiledFiles;
    } // compileAppScss




    private function compileSystemScss()
    {
        $namesOfCompiledFiles = '';
        $this->runTreeParser = false;
        $targetFile = SYSTEM_STYLESHEET;
        $targetFileLate = SYSTEM_STYLESHEET_LATE_LOAD;

        // determine whether compiling is required:
        $targetFileT = 0;
        if (file_exists($targetFile)) {
            $targetFileT = filemtime($targetFile);
        }
        if (file_exists($targetFileLate)) {
            $targetFileT = min($targetFileT, filemtime($targetFileLate));
        } else {
            $targetFileT = 0;
        }

        // compile files to compile for immediate loading:
        list($files, $filesLate, $filesSeparate) = $this->getFilesToCompile($targetFileT);
        if ($files) {
            $aggregatedCss = '';
            foreach ($files as $srcFile) {
                $namesOfCompiledFiles .= $this->doCompile($srcFile, $aggregatedCss);
            }
            file_put_contents($targetFile, $aggregatedCss);
        }

        // compile files to compile for async loading:
        if ($filesLate) {
            $aggregatedCss = '';
            foreach ($filesLate as $srcFile) {
                $namesOfCompiledFiles .= $this->doCompile($srcFile, $aggregatedCss);
            }
            file_put_contents($targetFileLate, $aggregatedCss);
        }

        // compile files to compile for separate files:
        if ($filesSeparate) {
            foreach ($filesSeparate as $srcFile => $targetFile) {
                $aggregatedCss = false;
                $namesOfCompiledFiles .= $this->doCompile($srcFile, $aggregatedCss);
                file_put_contents($targetFile, $aggregatedCss);
            }
        }
        return $namesOfCompiledFiles;
    } // compileSystemScss



    private function compileExtensionScss()
    {
        $namesOfCompiledFiles = '';

        if (!file_exists(EXTENSIONS_PATH)) {
            return '';
        }
        $files = getDirDeep( EXTENSIONS_PATH.'*.scss' );
        foreach ($files as $scssFile) {
            $scssFileT = filemtime($scssFile);
            $targetFile = $this->getCompiledFileName($scssFile);
            $targetFileT = @filemtime($targetFile);
            if ($scssFileT > $targetFileT) {
                $aggregatedCss = '';
                $namesOfCompiledFiles .= $this->doCompile($scssFile, $aggregatedCss, $targetFile);
            }
        }
        return $namesOfCompiledFiles;
    } // compileExtensionScss

} // SCssCompiler
