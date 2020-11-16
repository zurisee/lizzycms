<?php
// @info: -> one line description of macro <-

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $srcFile = $this->getArg($macroName, 'srcFile', 'Filename (incl. path) to icon source file, (default: "\~/assets/favicon/favicon.png")', '~/assets/favicon/favicon.png');
    $path = $this->getArg($macroName, 'path', 'Path to the favicon files if source file is located in a different folder, (default: "\~/assets/favicon/")', '~/assets/favicon/');

    if ($srcFile === 'help') {
        return '';
    }

    $path = fixPath($path);

    $ir = new ImageResizer( '240x240');
    $out = $ir->createFavicons( $srcFile, $path );

    $this->optionAddNoComment = true;

	return $out;
});
