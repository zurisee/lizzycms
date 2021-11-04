<?php
/*
 **	Macro() Plug-in 
 ** displays a visible note in the shape of a "Post-it"sticker
*/
// @info: Renders content as sticky notes.


//$page->addHead("\t<link href='https://fonts.googleapis.com/css?family=Kalam' rel='stylesheet'>\n");

$str =  <<<EOT

			$('.post-it').click(function() {
			    $('.post-it').css('z-index', '');
			    $( this ).css('z-index', '99');
			});
			$('.post-it').panzoom();
			$('.post-it .close_icon').click(function() {
				$(this).parent().parent().hide();
			});

EOT;
$page->addJq($str);

$page->addCssFiles('~sys/css/_post-it.css');
//$page->addCssFiles('~sys/css/post-it.css');
$page->addJqFiles(['PANZOOM']);

$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = '~/'.$this->config->systemPath;

    $text = $this->getArg($macroName, 'text', 'Text to be displayed on the Post-it', '');
    $contentFrom = $this->getArg($macroName, 'contentFrom', 'CSS-Selector from which to import text', '');
    $left = $this->getArg($macroName, 'left', 'Where to place measured from left', false);
    $right = $this->getArg($macroName, 'right', 'Where to place measured from right', '50');
    $top = $this->getArg($macroName, 'top', 'Where to place measured from top ', false);
    $bottom = $this->getArg($macroName, 'bottom', 'Where to place measured from bottom ', false);
    $angle = $this->getArg($macroName, 'angle', 'Angle by which the Post-it is tilted', '');
    $bgColor = $this->getArg($macroName, 'color', 'Background-color for Post-it', '');
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    if ($text === 'help') {
        return '';
    }

    if ($text) {
        $text = str_replace(['&#34;', '&#39;'], ['"',"'"], $text);
    }
    if ($contentFrom) {
        $text .= "<div id='lzy-postit-wrapper$inx'></div>\n";
        $jq = <<<EOT
var html = $('$contentFrom').html();
$('#lzy-postit-wrapper$inx').append( html );
EOT;
        $this->page->addJq($jq);
    }

    $style = '';
	if ($top) {
		if (preg_match('/^[-\d]*$/', $top)) {
			$top .= 'px';
		}
		$style .= "top:$top;";
	} elseif ($bottom) {
		if (preg_match('/^[-\d]*$/', $bottom)) {
            $bottom .= 'px';
		}
		$style .= "bottom:$bottom;";
	} else {
        $style .= "top:50px;";
    }

	if ($left) {
		if (preg_match('/^(\-?)(\d+)(\w*)/', $left, $m)) {
			if ($m[1] === '-') {
                $edge = 'right';
			} else {
                $edge = 'left';
            }
			$val = $m[2];
			if ($m[3]) {
				$val .= $m[3];
			} else {
				$val .= 'px';
			}
		}
		$style .= "$edge:$val;";
	} elseif ($right) {
		if (preg_match('/^(\-?)(\d+)(\w*)/', $right, $m)) {
			if ($m[1] === '-') {
				$edge = 'left';
			} else {
				$edge = 'right';
			}
			$val = $m[2];
			if ($m[3]) {
				$val .= $m[3];
			} else {
				$val .= 'px';
			}
		}
		$style .= "$edge:$val;";
	}
	if ($angle) {
		if (preg_match('/^[-\d]*$/', $angle)) {
			$angle .= 'deg';
		}
		$style .= "-webkit-transform: rotate($angle);-ms-transform: rotate($angle);transform: rotate($angle);";


	}
    $styleWrapper = '';
	if ($bgColor) {
		$borderCol = darken($bgColor, 3);
		$styleWrapper = " style='background: $bgColor;border-left: 1px solid $borderCol;border-bottom: 1px solid $borderCol;'";
	}
	if ($style) {
		$style = " style='$style'";
	}
	$str = <<<EOT

<div id='post-it$inx' class='post-it'$style>
	<div class='post-it-bg'></div>
	<div class='post-it-wrapper'$styleWrapper>
		<a href='#' class='close_icon' title='Close'><img src='{$sys}rsc/close32.png' alt=''></a>
        <div class='post-it-content'>$text</div>
	</div>
</div> <!-- /post-it -->

EOT;
	return $str;
});



//------------------------------------------------------------------------------
function darken($hexColor, $decr)
{
    if (!preg_match('/^\#([\da-f])([\da-f])([\da-f])$/i', trim($hexColor), $m) &&
        !preg_match('/^\#([\da-f][\da-f])([\da-f][\da-f])([\da-f][\da-f])$/i', trim($hexColor), $m)) {
        return "#000 /*bad color value submitted to darken(): $hexColor*/";
    }
    if (!$decr) {
        $decr = 1;
    }
    if (strlen($m[1]) === 2) {
        $decr *= 16;
    }
    $r = dechex(hexdec($m[1]) - $decr);
    $g = dechex(hexdec($m[2]) - $decr);
    $b = dechex(hexdec($m[3]) - $decr);
    return "#$r$g$b";
} // darken



