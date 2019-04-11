<?php

// @info: Is a generic macro to add images to the page.


require_once SYSTEM_PATH.'image-tag.class.php';

$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName] + 1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $src = $this->getArg($macroName, 'src', 'Name of image-file. By default Lizzy assumes to find it in the page folder. Use "&#126;/path-to-file" if the image is stored somewhere else.');
    $alt = $this->getArg($macroName, 'alt', 'Alt-text for image, i.e. a short text that describes the image.');
    $id = $this->getArg($macroName, 'id', 'Id that will be applied to the image.');
    $class = $this->getArg($macroName, 'class', 'Class-name that will be applied to the image.');
    $caption = $this->getArg($macroName, 'caption', 'Optional caption. If set, Lizzy will wrap the image into a &lt;figure> tag and wrap the caption itself in &lt;figcaption> tag.');
    $srcset = $this->getArg($macroName, 'srcset', "Let's you override the automatic srcset mechanism.");
    $imgTagAttributes = $this->getArg($macroName, 'imgTagAttributes', "Supplied string is put into the &lt;img> tag as is. This way you can apply advanced attributes, such as 'sizes' or 'crossorigin', etc.");
    $quickview = $this->getArg($macroName, 'quickview', "If true, activates the quickview mechanism (default: true). Quickview: click on the image to see in full size.");
    $lateImgLoading = $this->getArg($macroName, 'lateImgLoading', "If true, activates the lazy-load mechanism: images get loaded after the page is ready otherwise.");

    $link = $this->getArg($macroName, 'link', "Wraps a &lt;a href='link-argument'> tag round the image..");
    $linkClass = $this->getArg($macroName, 'linkClass', "Class applied to &lt;a> tag");
    $linkTarget = $this->getArg($macroName, 'linkTarget', "Target-attribute applied to &lt;a> tag, e.g. linkTarget:_blank");
    $linkTitle = $this->getArg($macroName, 'linkTitle', "Title-attribute applied to &lt;a> tag, e.g. linkTitle:'opens new window'");
    $linkType = $this->getArg($macroName, 'linkType', "Type of link (see link() macro).");

    if ($src == 'help') {
        return '';
    }

    $args = $this->getArgsArray($macroName);

    if (!$id) {
        $id = "img$inx";
    }

    $lateImgLoading = ($lateImgLoading || $this->config->feature_lateImgLoading || (strpos($args['class'], 'lzy-late-loading') !== false));

    // invoke quickview resources if required:
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

    $impTag = new ImageTag($this, $args);
    $str = $impTag->render($id);
    $class = ''; //???


    // link around img
    if ($link) {
        require_once SYSTEM_PATH . 'link.class.php';
        $cl = new CreateLink();

        $str = str_replace('lzy-quickview', '', $str); // link overrides quickview

        $args = ['text' => $str, 'href' => $link, 'class' => "lzy-img-link $linkClass", 'type' => $linkType,
            'target' => $linkTarget, 'title' => $linkTitle, 'target' => '', 'subject' => '', 'body' => ''];
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
        $str = "<figure id='figure_$id' class='$class'>\n\t$str\n$caption</figure>\n";
    }
    return $str;
});
