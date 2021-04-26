<?php
/*
 * ToDo: loggedInUsers
 */


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$source = $this->getArg($macroName, 'source', '[list,registeredUsers,&#95;group&#95;] Comma-separated-string that shall be rendered as a list. List elements may contain keywords "<em>registeredUsers</em>" or "<em>&#95;group&#95;</em>" which selects users of given group.', '');

    if ($source === 'help') {
        $this->getArg($macroName, 'text', 'Synonym for "source".', '');
        $this->getArg($macroName, 'prefix', '(optional) String added in front of every element.', '');
        $this->getArg($macroName, 'postfix', '(optional) String added after every element.', '');
        $this->getArg($macroName, 'separator', '(optional) String added between elements.', '');
        $this->getArg($macroName, 'wrapperTag', '(optional) HTML tag in which to wrap each element.', '');
        $this->getArg($macroName, 'wrapperClass', '(optional) Class to apply to each element.', '');
        $this->getArg($macroName, 'outerWrapperTag', '(optional) HTML tag to use as a wrapper around the rendered list of elements.', '');
        $this->getArg($macroName, 'outerWrapperClass', '(optional) Class to apply to wrapper.', '');
        $this->getArg($macroName, 'exclude', '(optional) Regex expression used to exclude specific elements from the list. E.g. "\\banon\\b"', '');
        $this->getArg($macroName, 'capitalize', '(optional) Capitalizes each element.', '');
        $this->getArg($macroName, 'sort', '[ascending,descending] If defined, sorting is applied to the list of elements.', '');
        $this->getArg($macroName, 'mode', '[ul,ol] Short-hand to render as HTML lists.', '');
        $this->getArg($macroName, 'options', '[capitalize,sort,ul,ol] Short-hand to invoke those options above.', '');
        $this->getArg($macroName, 'emptyListPlaceholder', '(optional) Text rended if list turns out to be empty', '-- empty list --');
        $this->getArg($macroName, 'disableCaching', 'If true, page caching will be disabled (this may be useful in case of elements that may change over time, e.g. loggedInUsers).', false);
        return '';
    }

	$args = $this->getArgsArray($macroName);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '', false);

    $lst = new OutputList($this->lzy);
    $str = $lst->render( $args );

    $this->optionAddNoComment = true;

	return $str;
});



class OutputList
{
    public function __construct($lzy)
    {
        $this->lzy = $lzy;
    }


    public function render( $args )
    {
        $source = @$args['source']? $args['source']: '';
        $source = str_replace('registeredUsers', '<em>all</em>', $source);
        $text = @$args['text']? $args['text']: '';
        $emptyListPlaceholder = @$args['emptyListPlaceholder']? $args['emptyListPlaceholder']: '-- empty list --';
        $out = '';

        $source = preg_replace('|</?em>|', '_', $source); // '_' may have been translated to '<em>' by MD compiler
        if (preg_match_all('|_(.*?)_|', $source, $m)) {
            foreach ($m[1] as $i => $group) {
                if ($group === 'all') {
                    $group = '';
                }
                $users = $this->lzy->auth->getListOfUsers( $group );
                $source = str_replace($m[0][$i], $users, $source);
            }
        }
        $text = $source.','.$text;
        $text = str_replace(',,', ',', $text);
        $text = rtrim($text, ', ');

        $sort = @$args['sort']? $args['sort']: '';
        if ($sort) {
            $elements = explodeTrim(',', $text);
            if (($sort === true) || ($sort && ($sort[0] !== 'd'))) {
                sort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            } else {
                rsort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            }
            $text = $elements;
        }

        if($text) {
            $out .= renderList($text, $args);
        } else {
            $out = $emptyListPlaceholder;
        }

        return $out;
    } // render

} // class OutputList
