<?php
/*
 * ToDo: loggedInUsers
 */


$macroName = basename(__FILE__, '.php');
$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
	$this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
	$inx = $this->invocationCounter[$macroName] + 1;

	$source = $this->getArg($macroName, 'source', '[registeredUsers] Specifies what elements shall be listed.', '');
    // $source = $this->getArg($macroName, 'source', '[registeredUsers,loggedInUsers] Specifies what elements shall be listed.', '');

    if ($source === 'help') {
        $this->getArg($macroName, 'text', '(optional) Comma-separated-string that shall be rendered as a list (alternative to arg "source")', '');
        $this->getArg($macroName, 'prefix', '(optional) String added in front of every element.', '');
        $this->getArg($macroName, 'postfix', '(optional) String added after every element.', '');
        $this->getArg($macroName, 'separator', '(optional) String added between elements.', '');
        $this->getArg($macroName, 'wrapperTag', '(optional) HTML tag in which to wrap each element.', '');
        $this->getArg($macroName, 'wrapperClass', '(optional) Class to apply to each element.', '');
        $this->getArg($macroName, 'outerWrapperTag', '(optional) HTML tag to use as a wrapper around the rendered list of elements.', '');
        $this->getArg($macroName, 'outerWrapperClass', '(optional) Class to apply to wrapper.', '');
        $this->getArg($macroName, 'options', '[capitalize] Modification to apply to every element.', '');
        $this->getArg($macroName, 'exclude', '(optional) Regex expression used to exclude specific elements from the list. E.g. "\\banon\\b"', '');
        $this->getArg($macroName, 'sort', '[ascending,descending] If defined, sorting is applied to the list of elements.', '');
        $this->getArg($macroName, 'mode', '[ul,ol] Short-hand.', '');
        $this->getArg($macroName, 'group', '(source=registeredUsers only) If defined, only users that are member of given group are listed.', '');
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
        $source = @$args['source']? $args['source']: '<em>all</em>';
        $text = @$args['text']? $args['text']: '';
        $out = '';

        if (preg_match_all('|<em>(.*?)</em>|', $source, $m)) {
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
            $elements = explode(',', $text);
            if (($sort === true) || ($sort && ($sort[0] !== 'd'))) {
                sort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            } else {
                rsort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            }
            $text = $elements;
        }

        if($text) {
            $out .= renderList($text, $args);
        }

        return $out;
    } // render

} // class OutputList
