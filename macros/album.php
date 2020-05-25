<?php

// @info: Presents images in a folder as an album.

$page->addCssFiles("~sys/third-party/unite_gallery/css/unite-gallery.css");
$page->addJqFiles(["JQUERY", "~sys/third-party/unite_gallery/js/unitegallery.min.js", "~sys/third-party/unite_gallery/themes/tiles/ug-theme-tiles.js"]);

$jq = <<<EOT

    $('.lzy-gallery').each(function() {
        $(this).unitegallery({ tiles_type:'justified', tile_enable_textpanel:true, tile_textpanel_title_text_align: 'center' });
    });
    
EOT;

$page->addJq($jq);

require_once SYSTEM_PATH.'gallery.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

    $galleryPath = $this->getArg($macroName, 'galleryPath', 'Path to folder where images reside.', '');
    if ($galleryPath === 'help') {
        return '';
    }
    $galleryPath = makePathDefaultToPage($galleryPath);

    $fullsizeImagePath = $this->getArg($macroName, 'fullsizeImagePath', '(optional) If you want to make images in full size accessible, add this path.', '');
    $imagePath = $this->getArg($macroName, 'imagePath', '(optional) Path to the image folder', '');
    $previewImgPath = $this->getArg($macroName, 'previewImgPath', '(optional) Path to preview images (default: thumbs/)', 'thumbs/');
    $previewImgSize = $this->getArg($macroName, 'previewImgSize', '(optional) Size of preview images (default: 512x384)', '512x384');

    
	if ($fullsizeImagePath) {
		$imagePath = ($imagePath) ? fixPath($imagePath) : 'images/';
		$options = [
			'sourcePath' 	=> $fullsizeImagePath,
			'imageMaxSize' 	=> '1600x1200',
			'imagePath' 	=> $imagePath,
			'thumbnailPath'	=> $previewImgPath,
			'thumbnailSize'	=> $previewImgSize,
		];
	} else {
		$options = [
			'sourcePath' 	=> '',
			'imageMaxSize' 	=> false,
			'imagePath' 	=> $imagePath,
			'thumbnailPath'	=> $previewImgPath,
			'thumbnailSize'	=> $previewImgSize,
		];
	}
	$id = 'lzy-gallery'.$inx;
	$album = new ImageGallery($galleryPath, $id, $options);
	$str = $album->render();

	return $str;
});
