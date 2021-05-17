<?php

// @info: Is a generic macro to add images to the page.


require_once SYSTEM_PATH.'image-tag.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName] + 1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Name of image-file. By default Lizzy assumes to find it in the page folder. Use "&#126;/path-to-file" if the image is stored somewhere else.');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($src === 'help') {
        $this->getArg($macroName, 'alt', 'Alt-text for image, i.e. a short text that describes the image.');
        $this->getArg($macroName, 'id', 'Id that will be applied to the image.');
        $this->getArg($macroName, 'class', 'Class-name that will be applied to the image.');
        $this->getArg($macroName, 'caption', 'Optional caption. If set, Lizzy will wrap the image into a &lt;figure> tag and wrap the caption itself in &lt;figcaption> tag.');
        $this->getArg($macroName, 'srcset', "Let's you override the automatic srcset mechanism.");
        $this->getArg($macroName, 'imgTagAttributes', "Supplied string is put into the &lt;img> tag as is. This way you can apply advanced attributes, such as 'sizes' or 'crossorigin', etc.");
        $this->getArg($macroName, 'quickview', "If true, activates the quickview mechanism (default: true). Quickview: click on the image to see in full size.");
        $this->getArg($macroName, 'lateImgLoading', "If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.");

        $this->getArg($macroName, 'link', "Wraps a &lt;a href='link-argument'> tag round the image..");
        $this->getArg($macroName, 'linkClass', "Class applied to &lt;a> tag");
        $this->getArg($macroName, 'linkTarget', "Target-attribute applied to &lt;a> tag, e.g. linkTarget:_blank");
        $this->getArg($macroName, 'linkTitle', "Title-attribute applied to &lt;a> tag, e.g. linkTitle:'opens new window'");
        $this->getArg($macroName, 'linkType', "Type of link (see link() macro).");
        return '';
    }

    $id = $this->getArg($macroName, 'id', '');
    $this->getArg($macroName, 'class', '');
    $this->getArg($macroName, 'alt', '');
    $caption = $this->getArg($macroName, 'caption', '');
    $this->getArg($macroName, 'srcset', '');
    $this->getArg($macroName, 'imgTagAttributes', '');
    $quickview = $this->getArg($macroName, 'quickview', "");
    $lateImgLoading = $this->getArg($macroName, 'lateImgLoading', '');

    $link = $this->getArg($macroName, 'link', '');
    $linkClass = $this->getArg($macroName, 'linkClass', '');
    $linkTarget = $this->getArg($macroName, 'linkTarget', '');
    $linkTitle = $this->getArg($macroName, 'linkTitle', '');
    $linkType = $this->getArg($macroName, 'linkType', '');

    $args = $this->getArgsArray($macroName);

    if (!$id) {
        $id = "img$inx";
    }

    $lateImgLoading = ($lateImgLoading || $this->config->feature_lateImgLoading || (strpos($args['class'], 'lzy-late-loading') !== false));

    $args['class'] .= ' lzy-img';

    // invoke quickview resources if required:
    if ($quickview !== false) {
        if ($this->config->feature_quickview || $quickview || (strpos($args['class'], 'lzy-quickview') !== false)) {
            $args['class'] .= ' lzy-quickview';
            if (!isset($this->page->quickviewLoaded)) {
                $this->page->addModules('QUICKVIEW');
                $this->page->addJq("\t$('img.lzy-quickview').quickview();");
                $this->page->quickviewLoaded = true;
            }
        } elseif ($lateImgLoading) {    // lateImgLading requires quickview.js
            $this->page->addJqFiles('QUICKVIEW');
        }
    }

    $args['origSrc'] = $args['src'];
    $src = prepareImageWorkingCopy($args['src'], $this->config->feature_ImgDefaultMaxDim);
    if (is_array( $src )) {
        return $src[1];
    } elseif (!$src) {
        return "\t<div class='lzy-warning'>&#123;&#123; Error: image file '{$args['origSrc']}' not found. }}</div>\n";
    } else {
        $args['src'] = $src;
    }


    // make sure img filename doesn't contain blanks:
    $args['srcFile'] = resolvePath($args['src']);

    $impTag = new ImageTag($this, $args);
    $str = $impTag->render($id);
    $class = ''; //???


    // link around img
    if ($link) {
        $link = resolvePath($link, true);
        require_once SYSTEM_PATH . 'link.class.php';
        $cl = new CreateLink($this->lzy);

        $str = str_replace('lzy-quickview', '', $str); // link overrides quickview

        $args = ['text' => $str, 'href' => $link, 'class' => "lzy-img-link $linkClass", 'type' => $linkType,
            'target' => $linkTarget, 'title' => $linkTitle, 'subject' => '', 'body' => ''];
        $str = $cl->render($args);
    }

    // figure with caption:
    if ($caption) {
        if (!isset($this->figureCounter)) {
            $this->figureCounter = 1;
        } else {
            $this->figureCounter++;
        }

        if (preg_match('/(.*)\#\#=(\d+)(.*)/', $caption, $m)) {
            $this->figureCounter = intval($m[2]);
            $caption = $m[1].'##'.$m[3];
        }
        // make ref to figure available:
        $this->addVariable("fig_$id",$this->figureCounter);

        $caption = str_replace('##', $this->figureCounter, $caption);
        $caption = "\t<figcaption>$caption</figcaption>\n";
        $str = "<figure id='figure_$id' class='lzy-figure $class'>\n\t$str\n$caption</figure>\n";
    }
    return $str;
});





function prepareImageWorkingCopy($reqImg, $imgDefaultMaxDim = IMG_DEFAULT_MAX_DIM)
{
    // takes the img from the page/ folder, resizes and copies it to _/
    // requested img may contain sizing code, e.g. xxx[300x200] -> ignored
    // returns path of working copy of image
    if (preg_match('/(.*) ( \[ .*? ] ) (\.\w{1,6}) $/x', $reqImg, $m)) {
        $reqImg = $m[1] . $m[3];
    } elseif (preg_match('/(.*) ( \( .*? \) ) (\.\w{1,6}) $/x', $reqImg, $m)) {
        $reqImg = $m[1] . $m[3];
    }

    list($reqImgFile, $errMsg) = resolvePathSecured($reqImg, true, false, false, null, 'img');
    if ($errMsg) {
        return [$reqImgFile, $errMsg];
    }


    $path = dir_name($reqImgFile);
    $fileName = base_name($reqImgFile);
    if (!file_exists($reqImgFile)) {
        $reqImgFile = $path.'#'.$fileName;
    }

    $fileName = str_replace(['(', ')', ' '], '_', $fileName);

    $workSrc = "~page/_/$fileName";
    $workSrcFile = resolvePath($workSrc);

    if (!file_exists($workSrcFile)) {
        if (file_exists($reqImgFile)) {
            $resizer = new ImageResizer($imgDefaultMaxDim);
            $resizer->resizeImage($reqImgFile, $workSrcFile);
        } else {
            return false;
        }
    }
    return $workSrc;
} // prepareImageWorkingCopy
