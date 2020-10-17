<?php

define('SYSTEM_PATH', 		'./');
define('PATH_TO_APP_ROOT', 	'../');		                            // root folder of web app
$timezone = isset($_SESSION['lizzy']['systemTimeZone']) ? $_SESSION['lizzy']['systemTimeZone'] : 'UCT';
date_default_timezone_set($timezone);

error_reporting(E_ALL | E_STRICT);
ob_start();

require_once SYSTEM_PATH.'/third-party/jquery-upload/server/php/UploadHandler.php';
require_once SYSTEM_PATH.'backend_aux.php';
require_once SYSTEM_PATH.'datastorage2.class.php';
require_once SYSTEM_PATH.'ticketing.class.php';

if (!isset($_REQUEST) || !$_REQUEST) {
    lzyExit('_upload_server.php has nothing to do...');
}
session_start();

$activityRestrectedTo = $_SESSION['lizzy']['isRestrictedPage'];
$loggedInUser = $_SESSION['lizzy']['user'];
if ($activityRestrectedTo && ($loggedInUser !== $activityRestrectedTo)) {   // check whether upload initiated by logged in user
    mylog("Upload-Server: unauthorized user tried to upload a file.");
    lzyExit('Error: not logged in');
}
$files = isset($_FILES) ? $_FILES : [];

$tickRec = getUploadParameters();

$user = $tickRec["user"];
$pagePath = $tickRec["pagePath"];
$dataPath = $tickRec["uploadPath"];
$pathToPage = $tickRec["pathToPage"];
$appRootUrl = $tickRec['appRootUrl']; // e.g. http://localhost/myapp/
$absAppRoot = $appRoot;


$actualUser = $_SESSION["lizzy"]["user"];

if ($actualUser !== $user) {
    mylog("Warning: user '$user' tried to upload picture(s), but is logged in as '$actualUser'.");
    lzyExit('{}');
}
session_abort();

if (isset($_POST["lzy-cmd"])) {
    $fname = isset($_POST["lzy-file-name"]) ? $_POST["lzy-file-name"] : '';
    if ($fname) {
        $fname = PATH_TO_APP_ROOT.$pathToPage.$fname;
        $text = '';
        if (preg_match('/(.*)\.md/', basename($fname), $m)) {
            $header = ucfirst(($m[1]));
            $text = "---\ncss: |\n---\n\n# $header\n\n";
        }
        file_put_contents($fname, $text);
    }
    lzyExit( json_encode('ok') );
}

$thumbnails = [
    '' => [
        'auto]_orient' => true,
    ],
    'thumbnail'=> [
        'upload_dir' => $absAppRoot.$dataPath.'_/thumbnails/',
        'upload_url' => $appRootUrl.$dataPath.'_/thumbnails/',
        'max_width' => 80,
        'max_height' => 80
    ],
];
$options = array(
    'upload_dir' => $absAppRoot.$dataPath,
    'upload_url' => $appRootUrl.$dataPath,
    'image_versions' => $thumbnails,
);

$reqMethod = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : false;



if (isset($_POST['lzy-rename-file'])) {
    $filename = isset($_POST['lzy-rename-file']) ? $_POST['lzy-rename-file'] : '';
    $filename = urldecode(basename($filename));
    $newName = isset($_POST['to']) ? $_POST['to'] : '';
    $newName = urldecode(basename($newName));
    $file = $absAppRoot.$dataPath.$filename;
    $dest = $absAppRoot.$dataPath.$newName;
    if (file_exists($file)) {
        if (file_exists($dest)) {
            mylog("_upload_server.php: [$dataPath] file '$newName' already exists, cannot rename '$filename'");
            lzyExit();
        }
        mylog("_upload_server.php: [$dataPath] file '$filename' renamed to '$newName'");
        rename($file, $dest);
    } else {
        mylog("_upload_server.php: [$dataPath] failed to rename file '$filename'");
    }
    $file = $absAppRoot.$dataPath.'_/thumbnails/'.$filename;
    $dest = $absAppRoot.$dataPath.'_/thumbnails/'.$newName;
    if (file_exists($file)) {
        mylog("_upload_server.php: [$dataPath] file '$filename' renamed to '$newName'");
        rename($file, $dest);
    }
    lzyExit();
}

// handle delete requests:
if ($reqMethod === 'DELETE') {
    lzyExit();
}
if (isset($_POST['lzy-delete-file'])) {
    $filename = isset($_POST['lzy-delete-file']) ? $_POST['lzy-delete-file'] : '';
    $filename = urldecode($filename);
    $file = $absAppRoot.$dataPath.$filename;
    $dest = $absAppRoot.$dataPath.'.#recycleBin/';
    if (!file_exists($dest)) {
        mkdir($dest, 0777, true);
    }
    $timestamp = date('Y-m-d_H.i.s_');
    rename($file, $dest.$timestamp.$filename);
    mylog("_upload_server.php: [$dataPath] file deleted: $filename");
    lzyExit();
}

$fname = implode(',', isset($files["files"]["name"]) ? $files["files"]["name"] : []);

$user = $user ? $user : 'anonymous';
//mylog("_upload_server.php: file received: '$fname' stored in: '$dataPath' from user '$user'");

$upload_handler = new UploadHandler($options);
lzyExit();


function getUploadParameters()
{
    $ticketRec = [];
    if (isset($_REQUEST['lzy-upload'])) {
        $dataRef = $_REQUEST['lzy-upload'];
        $_SESSION['lzy-backend']['dataRef'] = $dataRef;
        session_commit();

    } elseif (isset($_SESSION['lzy-backend']['dataRef'])) {
        $dataRef = $_SESSION['lzy-backend']['dataRef'];

    } else {
        lzyExit('nothing to do...');
    }
    if ($dataRef &&preg_match('/^[A-Z0-9]{4,20}$/', $dataRef)) {     // dataRef (=ticket hash) available
        $ticketing = new Ticketing();
        $ticketRec = $ticketing->consumeTicket($dataRef);
    }
    return $ticketRec;
}
