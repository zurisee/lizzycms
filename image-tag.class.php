<?php

// renders <img tag, handling alt, srcset, late-loading, quickview etc. as well as loading of required resourcs


class ImageTag
{
    private $aspRatio = null;
    private $imgFullsizeWidth = null;
    private $imgFullsizeHeight = null;

    public function __construct($obj, $args) {
        list($feature_image_default_max_width) = parseDimString($obj->config->feature_ImgDefaultMaxDim);
        $this->feature_SrcsetDefaultStepSize = $obj->config->feature_SrcsetDefaultStepSize;

        $this->feature_image_default_max_width = $feature_image_default_max_width;
        $this->feature_ImgDefaultMaxDim = $obj->config->feature_ImgDefaultMaxDim;
        $this->quickviewEnabled = $obj->config->feature_quickview;
        foreach ($args as $key => $value) {
            $this->$key = $value;
        }
        // convert [WxH] -> (WxH):
        if (preg_match('/( \[ [^\] ]* \] )/x', $this->src)) {
            $this->src = str_replace(['[', ']'], ['(', ')'], $this->src);
        }
    } // __construct



    public function render($id)
    {
        global $globalParams;
        // load late-loading code if not done yet:
        if ($this->lateImgLoading && (!isset($this->lateImgLoadingCodeLoaded))) {
            $this->lateImgLoadingCodeLoaded = true;
        }

        $this->getFileInfo();

        $qvDataAttr = $this->renderQuickview();

        $this->prepareLateLoading();


        // prepare srcset:
        $srcset = $this->renderSrcset();

        if ($class = $this->class) {
            $class = trim("$id $class");
        }

        $genericAttibs = $this->imgTagAttributes ? ' '.$this->imgTagAttributes : '';

        $src = $globalParams["appRoot"].$globalParams["pathToPage"].'_/'.base_name($this->src);


        // basic img code:
        $str = "<img id='$id' class='$class' {$this->lateImgLoadingPrefix}src='{$src}'{$srcset} title='{$this->alt}' alt='{$this->alt}'$genericAttibs $qvDataAttr />";

        return $str;
    } // render



    private function renderQuickview()
    {
        $this->qvDataAttr = '';
        if (($this->quickviewEnabled && !preg_match('/\blzy-noquickview\b/', $this->class)) // config setting, but no 'lzy-noquickview' override
            || preg_match('/\blzy-quickview\b/', $this->class)) {                    // or 'lzy-quickview' class

            if ($this->srcFile && file_exists($this->srcFile)) {
                list($w0, $h0) = getimagesize($this->srcFile);
                $this->imgFullsizeWidth = $w0;
                $this->imgFullsizeHeight = $h0;
                $this->qvDataAttr = " data-qv-src='~/{$this->srcFile}' data-qv-width='$w0' data-qv-height='$h0'";
            } else {
                $this->srcFile = false;
            }
        }
        return $this->qvDataAttr;
    } // renderQuickview



    private function prepareLateLoading()
    {
        $this->lateImgLoadingPrefix = '';
        if ($this->lateImgLoading) {
            $this->lateImgLoadingPrefix = 'data-';
            if (strpos($this->class, 'lzy-late-loading') === false) {
                $this->class .= ' lzy-late-loading';
            }
        }
    } // prepareLateLoading



    private function renderSrcset()
    {
        $srcFile = resolvePath($this->srcFile);
        $this->srcset = ($this->srcset === null) ? true : $this->srcset;

        $basename = '_/'.base_name($srcFile, false);
        $ext = '.'.fileExt($srcFile);
        $path = $GLOBALS["globalParams"]["appRoot"].$GLOBALS["globalParams"]["pathToPage"]; // absolute path from app root

        if ($this->srcset) {   // activate only if source file is largen than 50kb
            $w1 = ($this->w) ? $this->w : 300;
            $h1 = ($this->h) ? $this->h : round(300 * $this->aspRatio);
            $this->srcset = '';
            while (($w1 < $this->imgFullsizeWidth) && ($w1 < $this->feature_image_default_max_width)) {
                $f = $basename . "({$w1}x{$h1})" . $ext;
                $this->srcset .= "$path$f {$w1}w, ";
                $w1 += $this->feature_SrcsetDefaultStepSize;
                $h1 = round($w1 * $this->aspRatio);
            }
            if ($this->srcset) {
                $this->srcset = " {$this->lateImgLoadingPrefix}srcset='" . substr($this->srcset, 0, -2) . "'";
                $this->srcset .= ($this->w) ? " sizes='{$this->w}px'" : '';
            }

        } elseif (is_string($this->srcset)) {
            $this->srcset = " {$this->lateImgLoadingPrefix}srcset='{$this->srcset}'";

        } else {
            $this->srcset = '';
        }
        return $this->srcset;
    } // renderSrcset



    private function getFileInfo()
    {
        // aspect ratio
        // requested size
        // file type

        list($w, $h) = $this->parseFileName($this->origSrc);
        list($w0, $h0) = getimagesize($this->srcFile);
        if ($w && $h) {
            $aspectRatio = $h / $w;
        } else {
            $aspectRatio = $h0 / $w0;
        }
        if (!$w && $h) {
            $w = (int)round($h / $aspectRatio);
        } elseif ($w && !$h) {
            $h = (int)round($w * $aspectRatio);
        }
        $this->w = $w;
        $this->h = $h;
        $this->aspRatio = $aspectRatio;
        $this->imgFullsizeWidth = $w0;
        $this->imgFullsizeHeight = $h0;
    } // getFileInfo



    private function parseFileName($src)
    {
        $src = base_name($src, false);
        if (preg_match('/ \( (.*?) \) $/x', $src, $m)) {    // (WxH) size specifier present?
            return parseDimString($m[1]);
        } elseif (preg_match('/ \[ (.*?) \] $/x', $src, $m)) {    // (WxH) size specifier present?
            return parseDimString($m[1]);
        }

        return [null, null];
    } // parseFileName

} // class ImagePrep
