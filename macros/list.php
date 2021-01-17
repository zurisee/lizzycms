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
        $this->getArg($macroName, 'group', 'If defined, only users that are member of given group are listed.', '');
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

        if (stripos($this->source, 'registeredUsers') !== false) {
            $out = $this->lzy->auth->getListOfUsers( $this->args );

        } elseif (stripos($this->source, 'loggedInUsers') !== false) {
            // not implemented yet
            // -> requires maintaining state of logged in users
            return "Sorry, 'loggedInUsers' not implemented yet.";
        }

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
        $prefix = @$this->args['prefix']? $this->args['prefix']: '';
        $postfix = @$this->args['postfix']? $this->args['postfix']: '';
        $separator = @$this->args['separator']? $this->args['separator']: '';
        if ("$prefix$postfix$separator" === '') {
            $this->args['separator'] = ',';
        }
        $this->outerWrapperTag = @$this->args['outerWrapperTag']? $this->args['outerWrapperTag']: '';
        $this->outerWrapperClass = @$this->args['outerWrapperClass']? $this->args['outerWrapperClass']: '';

        $mode = @$this->args['mode']? $this->args['mode']: '';
        $options = @$this->args['options']? $this->args['options']: '';
        if (strpos($options, 'capitalize') !== false) {
            $this->args['capitalize'] = true;
        }

        // short-hands:
        if (($mode === 'ul') || ($mode === 'ol')) {
            $this->args['wrapperTag'] = 'li';
            $this->args['separator'] = '';
            $this->outerWrapperTag = $mode;
        }
    }
} // class OutputList
