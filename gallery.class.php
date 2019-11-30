<?php
/*
**
**  based on http://unitegallery.net/
*/

define('GALLERY_META_FILENAME', 'gallery.yaml');

class ImageGallery
{
    private $options;
    private $imagePath;
    private $thumbsPath;
    private $sourceFiles;
    
    //-----------------------------------------------------------------------------
    public function __construct($galleryPath, $id, $options)
    {
        $this->galleryPath = resolvePath($galleryPath, true, false, false, true);
        $this->id = $id;
        $this->options = $options;
        if (!file_exists($this->galleryPath)) {
            $this->id = false;
            return;
        }

        if ($options['imageMaxSize']) {
            list($maxW, $maxH) = explode('x', $options['imageMaxSize']);
            $sourcePath = correctPath($options['sourcePath']);
            $destPath = correctPath($options['imagePath']);
            $this->sourceFiles = $this->getImageList($sourcePath);

            $this->updateShrunkImages($destPath, $maxW, $maxH);
            $imagePath = $destPath;
        } else {
            $imagePath = correctPath($options['imagePath']);
            $this->sourceFiles = $this->getImageList($imagePath);
        }
        if ($options['thumbnailPath']) {
            list($maxW, $maxH) = explode('x', $options['thumbnailSize']);
        } else {
            $maxW = 300;
            $maxH = 255;
        }
        $this->imagePath = $imagePath;
        $this->thumbsPath = $thumbsPath = correctPath($options['thumbnailPath']);
        $this->updateShrunkImages($thumbsPath, $maxW, $maxH);
        
        $l = strlen($this->galleryPath.$imagePath);
        array_walk($this->sourceFiles, function(&$value, $key, $l) {
            $value = substr($value, $l);
        }, $l); 
    } // __construct
       
    //-----------------------------------------------------------------------------
    public function render()
    {
        if (!$this->id) {
            return "<div class='lzy-album-error'><code>album()</code> Macro:<br />No images found in {$this->galleryPath}</div>";
        }
        $imagesPath = '~/'.$this->galleryPath.$this->imagePath;
        $thumbsPath = '~/'.$this->galleryPath.$this->thumbsPath;
        
        if (sizeof($this->sourceFiles) == 0) {
            return "\t<!-- Empty Album: no images found in '$imagesPath' -->\n";
        }
        $metaData = $this->getMetaData();
        $out = '';
        
        foreach ($this->sourceFiles as $file) {
            $f = str_replace("'", '&#39;', basename($file));
            $imageFile = $imagesPath.$f;
            $thumbFile = $thumbsPath.$f;
            $alt = '';
            $descr = '';
            if (isset($metaData[$file]['alt'])) {
                $alt = str_replace("'", '&#39;', $metaData[$file]['alt']);
            } else {
                $metaData[$file]['alt'] = '';
            }
            if (isset($metaData[$file]['descr'])) {
                $descr = str_replace("'", '&#39;', $metaData[$file]['descr']);
            } else {
                $metaData[$file]['descr'] = '';
            }
            $out .= "\t\t\t<img alt='$alt' src='$thumbFile' data-image='$imageFile' data-description='$descr' style='display:none'>\n\n";
       }
        $this->putMetaData($metaData);

        $out = <<<EOT

          <div id="{$this->id}" class="lzy-gallery" style='display: none'>
$out          </div>
EOT;
        return $out;
    } // render

    //-----------------------------------------------------------------------------
    private function updateShrunkImages($destPath, $maxW, $maxH)
    {
        $destPath = $this->galleryPath.$destPath;
        if (!file_exists($destPath)) {
            mkdir($destPath, MKDIR_MASK, true);
        }
        
        foreach ($this->sourceFiles as $file) {
            $targetFile = $destPath.basename($file);
            if (!file_exists($targetFile) || (filemtime($file) > filemtime($targetFile))) {
                $this->createShrunkImage($file, $targetFile, $maxW, $maxH);
            }
        }
    } // updateShrunkImages

    //-----------------------------------------------------------------------------
    private function createShrunkImage($sourceFile, $targetFile, $width, $height)
    {
        list($width_orig, $height_orig, $original_type) = getimagesize($sourceFile);

        $ratio_orig = $width_orig/$height_orig;
        
        if ($width/$height > $ratio_orig) {
           $width = $height*$ratio_orig;
        } else {
           $height = $width/$ratio_orig;
        }

        if ($original_type === 1) {
            $imgt = "ImageGIF";
            $imgcreatefrom = "ImageCreateFromGIF";
        } else if ($original_type === 2) {
            $imgt = "ImageJPEG";
            $imgcreatefrom = "ImageCreateFromJPEG";
        } else if ($original_type === 3) {
            $imgt = "ImagePNG";
            $imgcreatefrom = "ImageCreateFromPNG";
        } else {
            return false;
        }

        $new_image = imagecreatetruecolor($width, $height);
        $old_image = imagecreatefromjpeg($sourceFile);
        imagecopyresampled($new_image, $old_image, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);
        $imgt($new_image, $targetFile);
        return file_exists($targetFile);
 
    } // createShrunkImage

    //-----------------------------------------------------------------------------
    private function getImageList($path)
    {
        $files = glob($this->galleryPath.$path.'*.*');
        foreach ($files as $key => $file) {
            if (is_dir($file) || (strpos($file, '/#') !== false) || !preg_match('/(jpg|png|gif|tiff?)$/i', $file)) {
                unset($files[$key]);
            }
        }
        return $this->sourceFiles = $files;
    } // getImageList
    
    //-----------------------------------------------------------------------------
    private function getMetaData()
    {
        $metafile = $this->galleryPath.GALLERY_META_FILENAME;
        $metaData = getYamlFile($metafile);
        return $metaData;
    } // getMetaData

    //-----------------------------------------------------------------------------
    private function putMetaData($metaData)
    {
        $metafile = $this->galleryPath.GALLERY_META_FILENAME;
        writeToYamlFile($metafile, $metaData);
    } // putMetaData
} // ImageGallery