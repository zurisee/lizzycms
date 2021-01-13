<?php
/*
**  Lizzy Entry Point
**
**  all requests pass through this file
*/

ob_start();

if (!file_exists('_lizzy/')) {
	die("Error: incomplete installation,\nFolder '_lizzy/' is missing.");
}
require_once '_lizzy/lizzy.class.php';

$website = new Lizzy();
$out = $website->render();

if (strlen($str = ob_get_clean ()) > 1) {
    file_put_contents('.#logs/output-buffer.txt', strip_tags($str));
}

exit( $out );
