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
    public function __construct($lzy, $args = [])
    {
        $this->lzy = $lzy;
        $this->args = $args;
    }


    public function render( $args = [] )
    {
        if ($args) {
            $this->args = $args;
        }
        $this->parseArgs();

        $elements = [];
        if (stripos($this->source, 'registeredUsers') !== false) {
            $elements = array_keys( $this->lzy->auth->getKnownUsers() );

        } elseif (stripos($this->source, 'loggedInUsers') !== false) {
            // not implemented yet
            // -> requires maintaining state of logged in users
            return "Sorry, 'loggedInUsers' not implemented yet.";
            $elements = array_keys( $this->lzy->auth->getKnownUsers() );
        }

        if ($this->sort) {
            if (($this->sort === true) || ($this->sort && ($this->sort[0] !== 'd'))) {
                sort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            } else {
                rsort($elements, SORT_NATURAL | SORT_FLAG_CASE);
            }
        }

        $out = '';
        foreach ($elements as $i => $element) {
            if ($this->exclude) {
                $pattern = $this->exclude;
                if (preg_match("/$pattern/", $element)) {
                    continue;
                }
            }
            if ($this->capitalize) {
                $element = ucfirst($element);
            }

            if ($this->prefix) {
                $element = "$this->prefix$element";
            }
            if ($this->postfix) {
                $element .= $this->postfix;
            }
            if ($this->separator) {
                $element .= $this->separator;
            }

            if ($this->wrapperTag) {
                $cls = $this->wrapperClass? " class='$this->wrapperClass'": '';
                $element = "\t\t<$this->wrapperTag$cls>$element</$this->wrapperTag>\n";
            }

            $out .= $element;
        }
        $out = substr($out, 0, - strlen($this->separator));

        if ($this->outerWrapperTag) {
            $cls = $this->outerWrapperClass? " class='$this->outerWrapperClass'": '';
            $out = <<<EOT
    <$this->outerWrapperTag$cls>
$out
    </$this->outerWrapperTag>

EOT;

        }

        return $out;
    } // render



    private function parseArgs()
    {
        $this->source = @$this->args['source']? $this->args['source']: '';
        $this->prefix = @$this->args['prefix']? $this->args['prefix']: '';
        $this->postfix = @$this->args['postfix']? $this->args['postfix']: '';
        $this->separator = @$this->args['separator']? $this->args['separator']: '';
        if ("$this->prefix$this->postfix$this->separator" === '') {
            $this->separator = ',';
        }
        $this->wrapperTag = @$this->args['wrapperTag']? $this->args['wrapperTag']: '';
        $this->wrapperClass = @$this->args['wrapperClass']? $this->args['wrapperClass']: '';
        $this->outerWrapperTag = @$this->args['outerWrapperTag']? $this->args['outerWrapperTag']: '';
        $this->outerWrapperClass = @$this->args['outerWrapperClass']? $this->args['outerWrapperClass']: '';

        $this->exclude = @$this->args['exclude']? $this->args['exclude']: '';
        $this->sort = @$this->args['sort']? $this->args['sort']: '';
        $this->mode = @$this->args['mode']? $this->args['mode']: '';
        $this->capitalize = @$this->args['capitalize']? $this->args['capitalize']: false;
        $this->options = @$this->args['options']? $this->args['options']: '';
        if (strpos($this->options, 'capitalize') !== false) {
            $this->capitalize = true;
        }

        // short-hands:
        if (($this->mode === 'ul') || ($this->mode === 'ol')) {
            $this->wrapperTag = 'li';
            $this->outerWrapperTag = $this->mode;
        }
    }

} // class OutputList
