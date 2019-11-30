<?php

// @info: Renders an accessible media player in video mode.

//
// Use H.264/MPEG-4 or YouTube
// Convert with MacX Video Converter Free Edition (http://www.macxdvd.com/mac-dvd-video-converter-how-to/free-mp4-converter-mac.htm)

// Code injections:
$ablePlayerPath = "{$sys}third-party/ableplayer/";
$page->addJsFiles(["{$ablePlayerPath}modernizr.custom.js", "{$ablePlayerPath}js.cookie.js", "{$ablePlayerPath}ableplayer.min.js", "JQUERY"]);
$page->addCssFiles("{$ablePlayerPath}styles/ableplayer.min.css");


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;
	$sys = $this->config->systemPath;

    $src = $this->getArg($macroName, 'src', 'Source folder from which to get video sources', '');
    $width = $this->getArg($macroName, 'width', '(optional) width of display (height will be automatically derived', '');
    $class = $this->getArg($macroName, 'class', '(optional) class applied to DIV around the player', '');

    if ($src == 'help') {
        return '';
    }

	if ($src == '') {
		return '';
	}
	if (!$class) {
		$class = "video_player $class";
	}

	if ((preg_match('|^https?\://(www\.)?youtube\.|', $src)) || (preg_match('|^https?\://(www\.)?youtu\.be|', $src))) {	// YouTube video
		if (preg_match('/v=(.+)/',$src, $m)) {
			$ytID0 = $m[1];
		} elseif (preg_match('|youtu.be/(.+)|',$src, $m)) {
			$ytID0 = $m[1];
		} else {
            fatalError("Error in Youtube Address: $src", 'File: '.__FILE__.' Line: '.__LINE__);
		}
		$ytID = preg_replace('/\#.*/', '', $ytID0);
		if (preg_match('/\#(t=)?(\d*)/', $ytID0, $m)) {
			$startTime = "data-start-time='{$m[2]}'";
		} else {
			$startTime = '';
		}

		$descrUrl = preg_replace('/\#.*/', '', "https://noembed.com/embed?url=$src");	// get youtube descriptor
		$ch = curl_init($descrUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$descr = curl_exec($ch);
		if (($descr !== false) && (strpos($descr, '"error"') === false)) {
			$descr = json_decode($descr);
			$size = "width='{$descr->width}' height='{$descr->height}'";
			$out = <<<EOT

		<div class="$class">
			<video id="video{$inx}" preload="auto" $size $startTime data-able-player data-translation-path="~sys/third-party/ableplayer/translations/" data-youtube-id="$ytID">
			</video>
		</div> <!-- /$class -->
EOT;
		} else {
			$out = "<div class='warning'>Requested Video is unavailable: $src</div>";
		}

	} else {
		if (preg_match('/([^\#]*)\#(t=)?(\d*)/', $src, $m)) {
			$startTime = "data-start-time=\"{$m[3]}\"";
			$src = $m[1];
		} else {
			$startTime = '';
		}

		$src0 = $src;
		$srcFile = resolvePath($src, true);
//		$srcUrl = resolvePath($src0, true, true);
		if (!file_exists($srcFile)) {
//			if (!file_exists($src)) {
				echo "<p>".getcwd()."</p>\n";
                fatalError("Error in video(): file <strong>'".basename($src)."'</strong> <br />\nnot found in <strong>".dirname($src)."</strong>", 'File: '.__FILE__.' Line: '.__LINE__);
//			}
		}
		$source = '';
		$vpath = dirname($srcFile).'/';
		$fname = $vpath.pathinfo($srcFile, PATHINFO_FILENAME);
//		$fext = fileExt($srcFile);
		$vtypes = array('mp4' => 'video/mp4', 'ogv' => 'video/ogg', 'ogg' => 'video/ogg', 'm4v' => 'video/webm', 'webm' => 'video/webm');
		
		foreach (array('mp4','ogv','ogg','m4v','webm') as $ext) {
			if (file_exists("$fname.$ext")) {
				$sign = '';
				$signFile = $fname.'_sign.'.$ext;
				if (file_exists($signFile)) {
					$sign = " data-sign-src='~/$signFile'";
				}
				
				$described = '';
				$describedFile = $fname.'_described.'.$ext;
				if (file_exists($describedFile)) {
					$described = " data-desc-src='~/$describedFile'";
				}

				$f = "$fname.$ext";
				if (isset($vtypes[$ext])) {
					$source .= "\t\t\t  <source src='~/$f' type='{$vtypes[$ext]}'$described$sign>\n";
				}
			}
		}
		$basename = $vpath.basename($fname);
		$langs = array('en' => 'English', 'de' => 'Deutsch', 'es' => 'Espanol');
		$aux_files = glob($vpath.'*.vtt');
		foreach ($aux_files as $file) {
			if (strpos($file, $basename) !== 0) {
				continue;
			}
			$f = '~/'.$file;
			$lang_attr = '';
			foreach ($langs as $lng => $language) {
				if (stripos($f, "_$lng.") !== false) {
					$lang_attr = " srclang='$lng' label='{$language}'";
					break;
				}
			}
			if (stripos($f, 'captions') !== false) {
					$source .= "\t\t\t  <track kind='captions' src='$f'$lang_attr>\n";
			}
			if (stripos($f, 'description') !== false) {
					$source .= "\t\t\t  <track kind='descriptions' src='$f'$lang_attr>\n";
			}
			if (stripos($f, 'chapters') !== false) {
					$source .= "\t\t\t  <track kind='chapters' src='$f'$lang_attr>\n";
			}
		}

		$preview = $vpath.pathinfo($src, PATHINFO_FILENAME).'.jpg';//???
		$_preview = $preview;
		$preview = $preview;
		if (!file_exists($_preview)) {
			$preview = '';
            fatalError("Error in video(): no preview image available in <strong>".dirname($src)."</strong> <br />\nfor video '<strong>".basename($src)."</strong>'", 'File: '.__FILE__.' Line: '.__LINE__);
		}
		
		list($width1, $height1, $type, $attr) = getimagesize($_preview);
		$ratio = $height1 / $width1;
		$width_corr = '';
		if ($width) {
			if ($width < 0) {
				$width_corr = " data-width-corr='$width'";
				$width = $width1;
			}
			$w = preg_replace('/\D/', '', $width);
			$height = intval($w * $ratio) . 'px';
		} else {
			$width = $width1;
			$height = $height1;
		}

		$out = <<<EOT

		<div class="$class">
			<video id="video{$inx}" preload="auto" width="$width"$width_corr height="$height" poster="~/$preview" data-able-player $startTime data-translation-path="~/{$sys}third-party/ableplayer/translations/">
$source
			</video>
		</div> <!-- /$class -->

EOT;
	}
	return $out;
});

