<?php
/*
 *	Lizzy - small and fast web-page rendering engine
 *
 *	Macro(): includes an audio player
*/

// @info: renders an accessible media player in sound mode.


// Code injections:
$ablePlayerPath = "~sys/third-party/ableplayer/";
$page->addJsFiles(["{$ablePlayerPath}modernizr.custom.js","{$ablePlayerPath}js.cookie.js", "{$ablePlayerPath}ableplayer.min.js", "JQUERY"]);
$page->addCssFiles("{$ablePlayerPath}styles/ableplayer.min.css");


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Path to sound file', '');

    if (($src === '') || ($src === 'help')) {
		return '';
	}

	$src = resolvePath($src, true, true, false, true);

	$out = <<<EOT

	<audio id="audio_player$inx" class="ump-media audio_player" preload="auto" data-able-player data-translation-path="~sys/third-party/ableplayer/translations/">
		<source src="$src" type="audio/mpeg">
	</audio>

EOT;

	return $out;
});



