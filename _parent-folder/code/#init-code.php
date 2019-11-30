<?php

/*
 * User Init Code
 *
 * -> will be executed during rendering process, before page template is loaded.
 * -> may define variables, elements to include, page-overlay/-override etc.
 *
  * Note: to use this feature it must be enabled in config/config.yaml: custom_permitUserInitCode: true
 */


return;



// === Examples =================
$html = <<<EOT

<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8" />
	<title>Lizzy Replaced Page</title>

	<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">

</head>
<body>

    <main role="main">
		<h1>Substituted Page</h1>
		<p>This page was invoked by **lizzy_user_init.php** as a substitution of the original page.</p>
		<p>No modifications such as Markdown compilation or variable substitution are applied.</p>
    </main>

</body>
</html>

EOT;

// comment out to test page substitution:
//$this->page->substitutePage($html);

$this->page->addMessage("This is a Message from **lizzy_user_init.php**!");

$this->page->addOverlay("This is an overlay inserted by **lizzy_user_init.php**!");

$this->page->addOverride("The original content of this page has been overridden by **lizzy_user_init.php**!");

$this->page->addDebugMsg("This is a debug message inserted by **lizzy_user_init.php**!");

