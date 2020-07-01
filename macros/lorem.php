<?php

// @info: Adds pseudo text to the page.


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);

    $min = $this->getArg($macroName, 'min', '(optional) defines the minimum of a randomly chosen number of words out of "lorem ipsum"', '');
    $max = $this->getArg($macroName, 'max', '(optional) defines the minimum of a randomly chosen number of words out of "lorem ipsum"', '');
    $dot = $this->getArg($macroName, 'dot', '[true,false] specifies whether generated text shall be terminated by a dot "."', true);
    $class = $this->getArg($macroName, 'class', '(optional) If set, wraps the string in a div and applies the class', '');
    $wrapper = $this->getArg($macroName, 'wrapper', '(optional) The tag to be placed around the output', 'div');
    $disablePageCaching = $this->getArg($macroName, 'disableCaching', '(true) Disables page caching. Note: only active if system-wide caching is enabled.', false);

    $lorem = 'Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.';

    if ($min === 'help') {
        return '';
    }

	if (!$min && !$max) {
		$str = $lorem;
		
	} else {
        $words = explode(' ', $lorem);
        $nWords = sizeof($words) - 1;
        $min = intval($min);
        if (!intval($max)) {
			$n = $min;
		} else {
            $max = intval($max);
            $n = rand($min, min($nWords, $max));
            $disablePageCaching = true;
		}
		
		$str = "";
		for ($i=0; $i<$n; $i++) {
			$str .= $words[rand(1, $nWords - 1 )].' ';
            $disablePageCaching = true;
        }
		$str = preg_replace('/\W$/', '', trim($str));
		if ($dot) {
			$str .= '.';
		}
		$str = strtoupper($str[0]).substr($str, 1);
	}
    if ($wrapper) {
        $class = trim("lzy-lorem $class");
        $str = "<$wrapper class='$class'>" . ucfirst($str) . "</$wrapper>";
    }
    $this->disablePageCaching = $disablePageCaching;
	return$str;
});
