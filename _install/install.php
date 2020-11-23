<?php

/*********************************************************************************
 *  Lizzy Installation Script Part 2
 * -> to be executed right after cloning/downloading Lizzy in to _lizzy/
 * -> this script will be overwritten by _lizzy/_parent-folder/index.php, 
 *      which is the file for normal operation.
 *
 * For manual installation:
 *  -> copy all files in _lizzy/_parent-folder/ to the app-root folder and
 *  -> rename file 'htaccess' to '.htaccess'
 *********************************************************************************/


recursive_copy('_lizzy/_parent-folder/','./');
rename('htaccess', '.htaccess');

header("Location: ./");
exit;


function recursive_copy($src, $dst) {
    $dir = opendir($src);
    @mkdir($dst);
    while(( $file = readdir($dir)) ) {
        if (( $file != '.' ) && ( $file != '..' )) {
            if ( is_dir($src . '/' . $file) ) {
                recursive_copy($src .'/'. $file, $dst .'/'. $file);
            }
            else {
                copy($src .'/'. $file,$dst .'/'. $file);
            }
        }
    }
    closedir($dir);
}