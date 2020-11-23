<?php

/*********************************************************************************
 *  Lizzy Installation Script Part 1
 * -> clones Lizzy from github and starts the installation process.
 * -> Lizzy will be installed into the current folder (aka 'app-root').
 *********************************************************************************/


// clone Lizzy:
if (!@$_GET['dev']) {
	shell_exec('git clone https://github.com/zurisee/lizzycms.git _lizzy');
} else {
	// clone Dev branch:
	shell_exec('git clone -b Dev https://github.com/zurisee/lizzycms.git _lizzy');
}


// copy Lizzy's install.php script to the app root naming it index.php
// (and thereby overwriting this initial installation file):
copy('_lizzy/_install/install.php', 'index.php');

// now force the browser to reload the page and start the local installation process:
header("Location: ./");
