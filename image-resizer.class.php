<?php

// creates image files used by srcset

define('IMAGE_TYPES' , 'jpg|jpeg|png|gif');

class ImageResizer
{
    // Tries to find all instances of image invocation and checks whether the corresponding file exists.
    // If it's a size variant (denoted by [WxH] in the file name) of a larger image file,
    // it creates resampled file copies.
    // Thus, macros dealing with images only need to handle the HTML code, not the images files.

    public function __construct($maxDimStr) {
        list($maxWidth, $maxHeight) = parseDimString($maxDimStr);
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }


    //--------------------------------------------------
    public function provideImages($html)
    {
        $modified = false;
        // find all img-tags in html:
        $p = strpos($html, '<img');
        $appRoot = $GLOBALS['globalParams']['appRoot'];
        $l = strlen($appRoot);
        $imgTypes = '|'.IMAGE_TYPES.'|';
        while ($p !== false) {

            // find src-attribute within img-tag:
            $p1 = strpos($html, 'src=', $p);
            $s = trim(substr($html, $p1+4));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $src = $m[1];
                $ext = fileExt($src);
                if (stripos($imgTypes, "|$ext|") === false) {   // ignore img of other type, e.g. svg
                    $p = strpos($html, '<img', $p+1);
                    continue;
                }
                if (strpos("$src", $appRoot) === 0) {
                    $src = substr($src, $l);
                } elseif ($src[0] === '~') {
                    $src = resolvePath($src);
                }

                if (!file_exists($src)) {
                    list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                    $this->resizeImage($orig, $src, $width, $height, $crop);
                    $modified = true;
                }
            }
            $origSrc = $src;

            // find srcset-attribute within img-tag:
            $p1 = strpos($html, '>', $p);
            $s1 = substr($html, $p, $p1-$p+1);
            $p1 = strpos($s1, 'srcset=');
            if (!$p1) {
                $p = strpos($html, '<img', $p+1);
                continue;
            }
            $s = trim(substr($html, $p+$p1+7));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $srcset = explode(',', $m[1]);
                foreach ($srcset as $src) {
                    $src = trim(preg_replace('/(\.('.IMAGE_TYPES.')).*$/i', "$1", $src));
                    if (strpos("$src", $appRoot) === 0) {
                        $src = substr($src, $l);
                    } elseif ($src[0] === '~') {
                        $src = resolvePath($src);
                    }
                    if (!file_exists($src)) {
                        list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                        $this->resizeImage($origSrc, $src, $width, $height, $crop);
                        $modified = true;
                    }
                }
            }

            $p = strpos($html, '<img', $p+1);
        }

        // find all source-tags:
        $p = strpos($html, '<source');
        while ($p !== false) {
            // find srcset-attribute within source-tag:
            $p1 = strpos($html, 'srcset=', $p);
            $s = trim(substr($html, $p1+7));
            $quote = $s[0];
            if (($p1 !== false) && preg_match("/$quote([^$quote]*)$quote/", $s, $m)) {
                $src = resolvePath($m[1]);

                if (!file_exists($src)) {
                    list($orig, $width, $height, $crop) = $this->deriveImageSize($src);
                    $this->resizeImage($orig, $src, $width, $height, $crop);
                    $modified = true;
                }
            }
            $p = strpos($html, '<source', $p+1);
        }
        return $modified;
    } // provideImages



    //----------------------------------------------------------------
    public function deriveImageSize($filename, $origFile = false)
    {
        $path_parts = pathinfo($filename);
        $fname = $path_parts['filename'];
        $crop = false;
        if (strpos($fname, '!')) {
            $crop = true;
            $fname = str_replace('!', '', $fname);
        }
        
        if (!preg_match('/([^\(]*) \( (\d*) x (\d*) \) $/x', $fname, $m)) {
            if (file_exists($filename) && list($w, $h) = getimagesize($filename)) {
                return [$filename, $w, $h, false ];
            } else {
                return [$filename, 0, 0, false ];
            }
        }
        $fname = $m[1];
        $width = $m[2];
        $height = $m[3];
        
        if (!$crop) {
            if (!$width) {
                if ($height && $origFile && file_exists($origFile)) {
                    list($w, $h) = getimagesize($origFile);
                    $width = floor($height / $w*$h);
                } else {
                    $width = 3000;
                }
            }
            if (!$height) {
                if ($width && $origFile && file_exists($origFile)) {
                    list($w, $h) = getimagesize($origFile);
                    $height = floor($width / $w*$h);
                } else {
                    $height = 2400;
                }
            }
        }
        
        $ext = $path_parts['extension'];
        $fpath = $path_parts['dirname'];
        $fpath = str_replace('/_', '', $fpath).'/';

        $orig = "$fpath$fname.$ext";
        return [$orig, $width, $height, $crop ];
    } // deriveImageSize



    //----------------------------------------------------------------
    public function resizeImage($src, $dst, $width = false, $height = false, $crop = false)
    {
        // remove appRoot if included in the path:
        if(!$src || !file_exists($src)) {   // nothing to do:
            return false;
        }
        $type = strtolower(substr(strrchr($src,"."),1));
        if ($type === 'svg') {
            copy($src, $dst);
            return true;
        }

        // if no width or height supplied, we need to optain it from the image:
        if (!$width || !$height) {
            if(!list($w, $h) = getimagesize($src)) {
                exit( "Unsupported picture type: $src");
            }
            if (!$width) {
                if ($height) {
                    $w = floor($height*$w/$h);
                }
                $width = min($w, $this->maxWidth);
            }
            if (!$height) {
                if ($width) {
                    $h = floor($width/$w*$h);
                }
                $height = min($h, $this->maxHeight);
            }
        }

        if(!list($w, $h) = getimagesize($src)) {
            return "Unsupported picture type!";
        }
        $dstPath = dirname($dst);
        if (!file_exists($dstPath)) {
            mkdir($dstPath, MKDIR_MASK2, true);
        }
        if ($type === 'jpeg') { $type = 'jpg'; }
        switch($type){
            case 'bmp': $img = imagecreatefromwbmp($src); break;
            case 'gif': $img = imagecreatefromgif($src); break;
            case 'jpg': $img = imagecreatefromjpeg($src); break;
            case 'png': $img = imagecreatefrompng($src); break;
            default : return "Unsupported picture type!";
        }
        
        // resize
        if($crop){
            if ($w < $width and $h < $height) {
               copy($src, $dst);
               return;
            }
            $ratio = max($width/$w, $height/$h);
            $h = $height / $ratio;
            $x = ($w - $width / $ratio) / 2;
            $w = $width / $ratio;
        } else {
            if ($w < $width and $h < $height) {
               copy($src, $dst);
               return;
            }
            $ratio = min($width/$w, $height/$h);
            $width = $w * $ratio;
            $height = $h * $ratio;
            $x = 0;
        }
        
        $new = imagecreatetruecolor($width, $height);
        
        // preserve transparency
        if ($type === "gif" or $type === "png") {
            imagecolortransparent($new, imagecolorallocatealpha($new, 0, 0, 0, 127));
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }
        
        imagecopyresampled($new, $img, 0, 0, $x, 0, $width, $height, $w, $h);
        
        $ok = false;
        switch($type){
            case 'bmp': imagewbmp($new, $dst); break;
            case 'gif': imagegif($new, $dst); break;
            case 'jpg':
                $ok = imageinterlace($new, true);
                imagejpeg($new, $dst);
                break;
            case 'png': imagepng($new, $dst); break;
        }
        imagedestroy($new);
        mylog("Image created: $dst ".($ok?'interlaced':'non interlaced'));
        return true;
    } // resizeImage



    public function createFavicons( $srcFile, $destPath )
    {
        // inspiration: https://sympli.io/blog/heres-everything-you-need-to-know-about-favicons-in-2020/
        $srcFile = resolvePath($srcFile);
        $destPath = resolvePath(fixPath($destPath));
        $faviconSet = [
            // generic:
            [
                'sizes' => [16, 32, 96],
                'out'   => "<link rel='icon' type='image/png' href='{$destPath}favicon-\$x$.png' sizes='\$x$'>",
            ],

            // Apple Touch:
            [
                'sizes' => [120,180,152,167],
                'out'   => "<link rel='apple-touch-icon' href='{$destPath}favicon-\$x$.png' sizes='\$x$'>",
            ],
            // Windows:
            [
                'sizes' => [70,270,310, '310x150'],
                'out'   => '',
            ],
        ];

        if (!fileExists( $srcFile )) {
            die("Error: Favicon source file '$srcFile' not found.");
        }

        $out = '';
        foreach ($faviconSet as $set) {
            $sizes = $set['sizes'];
            foreach ($sizes as $size) {
                if (is_int($size)) {
                    $str = str_replace('$', $size, $set['out']);
                    $out .= "\t$str\n";

                    $dst = $destPath . str_replace('$', $size, 'favicon-$x$.png');
                    if (!file_exists($dst)) {
                        $this->resizeImage($srcFile, $dst, $size, $size);
                    }
                } else {
                    list($w, $h) = parseDimString($size);
                    $dst = $destPath . "favicon-{$w}x{$h}.png";
                    if (!file_exists($dst)) {
                        $this->resizeImage($srcFile, $dst, $w, $h, true);
                    }
                }
            }
        }

        $manifestFilename = 'browserconfig.xml';
        if (!file_exists($manifestFilename)) {
            $manifest = <<<EOT
<?xml version="1.0" encoding="utf-8"?>
<browserconfig>
  <msapplication>
    <tile>
      <square70x70logo src="{$destPath}mstile-70x70.png"/>
      <square150x150logo src="{$destPath}mstile-270x270.png"/>
      <square310x310logo src="{$destPath}mstile-310x310.png"/>
      <wide310x150logo src="{$destPath}mstile-310x150.png"/>
      <TileColor>#2b5797</TileColor>
    </tile>
  </msapplication>
</browserconfig>

EOT;
            file_put_contents($manifestFilename, $manifest);
        }
        $out = rtrim($out) . "\n\t<link rel='manifest' href='$manifestFilename'>";
        return $out;
    } // createFavicons

} // class ImageResizer

