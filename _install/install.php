<?php

define('LIZZY_SOURCE_URL', 'https://codeload.github.com/zurisee/lizzycms/zip/master');
define('LIZZY_ZIP_FILE', 'lizzy-master.zip');
define('LIZZY_MASTER_PATH', 'lizzycms-master');

if (version_compare(PHP_VERSION, '7.1.0') < 0) {
    die("Error: Lizzy requires PHP version 7.1 or higher to run.");
}

if (!is_writable( './' )) {
    die("Error: no write permission in this folder: ".getcwd()." (current user: ". `whoami` . ")");
}

if (!file_exists(LIZZY_ZIP_FILE)) {
	downloadLizzy();
}

if (!file_exists('_lizzy')) {
    unpackLizzy();
}

installLizzy();

header("Location: ./");
exit;


function unpackLizzy()
{
    if (!class_exists('ZipArchive')) {
        die("Error: unable to extract Lizzy from downloaded archive.");
    }
    $zip = new ZipArchive;
    $zip->open(LIZZY_ZIP_FILE);
    $zip->extractTo('./');
    $zip->close();
    rename(LIZZY_MASTER_PATH, '_lizzy');
    unlink(LIZZY_ZIP_FILE);
}



function installLizzy()
{
    copyFolder('_lizzy/_parent-folder/', '.');
    rename('htaccess','.htaccess');
}


function downloadLizzy()
{
	$fp = fopen(LIZZY_ZIP_FILE, 'w+');
	if($fp === false){
		throw new Exception('Could not open: ' . LIZZY_ZIP_FILE);
	}
	if (!function_exists('curl_init')) {
	    die("Error: CURL module not available - unable to download Lizzy.");
    }
	$ch = curl_init( LIZZY_SOURCE_URL );
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	curl_exec($ch);
	if(curl_errno($ch)){
		throw new Exception(curl_error($ch));
	}
	$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	fclose($fp);

	if($statusCode !== 200){
		exit ("Status Code: " . $statusCode);
	}
}




function copyFolder( $source, $target ) {
    @mkdir( $target );
    $d = dir( $source );
    while ( FALSE !== ( $entry = $d->read() ) ) {
        if ( $entry == '.' || $entry == '..' ) {
            continue;
        }
        $Entry = $source . '/' . $entry;
        if ( is_dir( $Entry ) ) {
            copyFolder( $Entry, $target . '/' . $entry );
            continue;
        }
        copy( $Entry, $target . '/' . $entry );
    }
    $d->close();
}

