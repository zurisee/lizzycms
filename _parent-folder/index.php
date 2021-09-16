<?php
/*
**  Lizzy Entry Point
**
**  all page requests pass through this file
*/

ob_start();

if (!file_exists('_lizzy/')) {
	die("Error: incomplete installation,\nFolder '_lizzy/' is missing.");
}
require_once '_lizzy/lizzy.class.php';

$lzy = new Lizzy();
$out = $lzy->render();

if (strlen($buff = ob_get_clean ()) > 1) {
    $buff = strip_tags( $buff );
	file_put_contents('.#logs/output-buffer.txt', $buff);
}

exit( $out );
