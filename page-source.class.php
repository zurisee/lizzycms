<?php

/*
 *  When page-editor sends edited content, this module manages copies in the recycle bin(s)
 */

class PageSource
{

    public static function renderEditionSelector($filename)
    {
        if (sizeof(PageSource::getRecycledFilenames($filename)) === 0) {
            return '';
        }
        $currEd = abs(getUrlArg('ed', true));
        $targetFilename = getUrlArg('file', true);

        $select = "\t\t<option value=''></option>\n";
        $recF = PageSource::getRecycledFilenames($filename);
        foreach ($recF as $i => $f) {
            if (!preg_match('/\[(.*)\]/', $f, $m)) {
                continue;
            }
            if (($currEd === $i+1) && ($filename === $targetFilename)) {
                $selected = ' selected';
            } else {
                $selected = '';
            }
            $name = $m[1];
            $select .= "\t\t<option value='$i'$selected>$name</option>\n";
        }
        $select = "\t<span class='lzy-ed-dropdown'><select class='lzy-ed-dropdown' title='{{ PageSource Chose page edition }}'>$select</select></span>\n";

        if ($currEd !== null) {
            $nRecF = sizeof($recF);
            $currEd = abs($currEd);
            if ($currEd < 0) {
                $edPlus1 = false;
            } else {
                $edPlus1 = max(0, min($nRecF, $currEd - 1));
            }
            if ($currEd > $nRecF-1) {
                $edMinus1 = false;
            } else {
                $edMinus1 = max(0, min($nRecF, $currEd + 1));
            }

        } else {
            $edMinus1 = 1;
            $edPlus1 = false;
        }
        if ($edMinus1) {
            $edSelector = "<span class='lzy-ed-selector'><a href='?ed=-$edMinus1&file=$filename' title='{{ PageSource Load previous edition }}'>◀︎</a></span>";
        } else {
            $edSelector = "<span class='lzy-ed-selector lzy-ed-selector-inactive'>◀︎</span>";
        }
        if ($edPlus1) {
            $edSelector .= "<span class='lzy-ed-selector'><a href='?ed=-$edPlus1&file=$filename' title='{{ PageSource Load next edition }}'>▶︎</a></span>";
        } else {
            $edSelector .= "<span class='lzy-ed-selector lzy-ed-selector-inactive'>▶︎</span>";
        }
        if ($currEd !== 0) {
            $edSelectorButtons = "<span class='lzy-ed-selector-cancel'><a href='./' title='{{ PageSource cancel }}'>✗</a></span><span class='lzy-ed-selector-save'><a href='?ed-save=$currEd&file=$filename' title='{{ PageSource activate edition }}'>✓</a></span>";
        } else {
            $edSelectorButtons = "<span class='lzy-ed-selector-cancel lzy-ed-selector-inactive'>✗</span><span class='lzy-ed-selector-save lzy-ed-selector-inactive'>✓</span>";
        }
        return "<div class='lzy-ed-selector lzy-encapsulated' data-lzy-filename='$filename'><p class='lzy-ed-selector-prompt'>{{ Page-History: }}</p>$edSelector$select$edSelectorButtons</div>";
    } // renderEditionSelector



    //....................................................
    public static function getFileOfRequestedEdition($filename)
    {
        $ed = abs(getUrlArg('ed', true));
        $targetFilename = getUrlArg('file', true);
        if (($targetFilename === $filename) && $ed) {
            $previousVersions = PageSource::getRecycledFilenames($filename);
            $nVersions = sizeof($previousVersions);
            if ($nVersions === 0) {  // none available, return original file

            } elseif ($ed > $nVersions) {
                $filename = $previousVersions[$nVersions-1]; // return oldest

            } else {
                $ed--;
                if (isset($previousVersions[$ed])) {
                    $filename = $previousVersions[$ed];
                } else {
                    $filename = $previousVersions[0]; // not found
                }
            }
        }
        return getFile($filename, true);
    } // getFileOfRequestedEdition




    //....................................................
    public static function getRecycledFilename($filename, $edition = 0)
    {
        $pat = PageSource::composeRecycleFilename($filename, false).'*';
        $dir = array_reverse( glob($pat) );
        if (isset($dir[$edition])) {
            return $dir[$edition];
        }
        return false;
    } // getRecycledFilename




    //....................................................
    public static function getRecycledFilenames($filename, $recycleBin = false, $inOrigPath = false)
    {
        //        $pat = PageSource::composeRecycleFilename($filename, false, $recycleBin).'*';
        //        return array_reverse( getDir($pat) );
        $pat = PageSource::composeRecycleFilename($filename, false, $recycleBin, $inOrigPath).'*';
        return array_reverse( glob($pat) );
    } // getRecycledFilenames




    //....................................................
    public static function storeFile($filename, $content, $recycleBin = false)
    {
        PageSource::copyFileToRecycleBin($filename, $recycleBin);
        $content = urldecode($content);
        file_put_contents($filename, $content);
    } // storeFile




    //------------------------------------------------------------
    public static function rollBack($fileName, $msg = '')
    {
        // first save offending file locally to rollback name:
        $rolledBackName = RECYCLE_BIN_PATH.'#RolledBack '.basename( PageSource::composeRecycleFilename($fileName) );
        preparePath($rolledBackName);
        $rolledBackName = resolvePath($rolledBackName);
        preparePath($rolledBackName);
        copy($fileName, $rolledBackName);

        // second copy latest backup from recycle bin:
        if ($rollBackSrc = PageSource::getRecycledFilename($fileName)) {
            if (file_exists($rollBackSrc)) {
                copy($rollBackSrc, $fileName);
                if ($msg) {
                    die($msg);
                } else {
                    exit("Error found in file '$fileName'.<br> &rarr; Rolled back from from previous edition ($rollBackSrc).<br>Please reload page now.");
                }

            } else {
                die("rollBack( $fileName ): file not readable");
            }
        } else {
            if ($msg) {
                die($msg);
            } else {
                die("rollBack( $fileName ): no rollback file available");
            }
        }
    } // rollBackVersion



    //....................................................
    public static function saveEdition($edSave = false)
        // user chose to activate a previous edition of the current page
        // -> ?ed-save=<ed>&file=<file>
        // => determine whether current page has been saved, if not, do it now
        // => then copy requested edition back to page folder
    {
        if (!$edSave) {
            $edSave = getUrlArg('ed-save', true);
        }
        $origFilename = getUrlArg('file', true);
        if (($edSave !== null) && $origFilename) {
            $edSave = abs($edSave);
            $offs = PageSource::copyFileToRecycleBin($origFilename);
            PageSource::copyFileFromRecycleBin($origFilename, $edSave + $offs);
            reloadAgent();
        }
    } // restoreEdition




    public static function copyFileToRecycleBin($filename, $recycleBin = false, $inOrigPath = false)
    {
        if (file_exists($filename)) {
            $destFolder = ($recycleBin) ? $recycleBin : RECYCLE_BIN_PATH;
            if ($inOrigPath) {
                $destFolder = dir_name($filename) . basename(RECYCLE_BIN_PATH ) . '/';
            } else {
                $destFolder = resolvePath($destFolder);
            }
            preparePath($destFolder);

            $currContent = file_get_contents($filename);
            foreach (PageSource::getRecycledFilenames($filename, $recycleBin, $inOrigPath) as $f) {
                if ($currContent === file_get_contents($f)) {
                    return 0; // file already present in recycleBin
                }
            }
            $recycleFile = PageSource::composeRecycleFilename($filename, true, $recycleBin, $inOrigPath);
            copy($filename, $recycleFile);
            return 1;
        }
    } // copyFileToRecycleBin





    public static function copyFileFromRecycleBin($filename, $edition, $recycleBin = false)
    {
        $pat = PageSource::composeRecycleFilename($filename, false, $recycleBin).'*';
        $dir = array_reverse( glob($pat) );
        $edition--;
        if (isset($dir[$edition])) {
            copy($dir[$edition], $filename);
        }
    } // copyFileFromRecycleBin




    public static function composeRecycleFilename($filename, $appendTimestamp = true, $recycleBin = false, $inOrigPath = false)
    {
        if ($inOrigPath) {
            $recycleBin = dir_name($filename) . basename(RECYCLE_BIN_PATH ) . '/';
        } else {
            if (!$recycleBin) {
                $recycleBin = RECYCLE_BIN_PATH;
            } else {
                $recycleBin = fixPath($recycleBin);
            }
        }
        if (strpos($recycleBin, '~page/') === false) {
            $filename = trunkPath($filename, -1, false);
            $filename = $recycleBin.str_replace('/', ':', $filename);
        } else {
            $filename = $recycleBin.basename($filename);
        }
        $filename = resolvePath($filename);
        if ($appendTimestamp) {
            $filename .= ' ['.date('Y-m-d,H.i.s').']';
        }
        return $filename;
    } // composeRecycleFilename


} // PageSource