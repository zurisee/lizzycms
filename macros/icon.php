<?php
$macroName = basename(__FILE__, '.php');    // macro name normally the same as the file name


$this->addMacro($macroName, function () {
	$macroName = basename(__FILE__, '.php');
    $this->invocationCounter[$macroName] = (!isset($this->invocationCounter[$macroName])) ? 0 : ($this->invocationCounter[$macroName]+1);
    $inx = $this->invocationCounter[$macroName] + 1;

    $name = $this->getArg($macroName, 'name', 'Name of the requested icon', '');
    $class = $this->getArg($macroName, 'class', '(optional) Class to be applied to the icon', '');
    $id = $this->getArg($macroName, 'id', '(optional) Id to be applied to the icon', '');
    $sizeFactor = $this->getArg($macroName, 'sizeFactor', '(optional) Factor by which the icon will be scaled up. E.g. sizeFactor:1.5', false);
    $color = $this->getArg($macroName, 'color', '(optional) Color to be applied to the icon.', '');
    $tooltip = $this->getArg($macroName, 'tooltip', '(optional) Text to be shown in tooltip.', '');
    $title = $this->getArg($macroName, 'title', '(optional) synonym for tooltip.', '');
    if ($title) {
        $tooltip = $title;
    }

    $name = strtolower($name);
    $supportedIcons = ',calendar,error,user,settings,cloud,desktop,mobile,config,tel,geo,map,sms,info,doc,docs,trash,enlarge,reduce,smile,nosmile,paste2,link,menu,newwin,edit,mail,show2,enlarge2,reduce2,ok,cancel,locked,unlocked,exit,favorite,send,show,hide,source,search,up,down,slack,pdf,gsm,upload,download,globe,key,bubble,stack,attachment,heart,fullscreen,cut,copy,paste,cancel2,clock,danger,wait,speed,crosshairs,picture,pictures,movie,sync,reload,power,insert,wifi,vol-up,volume,vol-down,flag,play,stop,mute,rec,forward,backward,start,print,save,pause,end,rename,select,';
    if ($name === 'help') {
        $icons = explode(',', trim($supportedIcons, ','));
        $str = '';
        sort($icons);
        foreach ($icons as $icon) {
            $str .= "- :$icon: &nbsp; $icon\n";
        }
        $this->compileMd = true;
        return "::: .lzy-icon-help-list\n## Supported Icon Names:\n\n $str\n:::\n";
    }
    
    if (strpos($supportedIcons, ",$name,") === false) {
        return "<div class='lzy-warning'>Icon name unknown: '$name'";
    }
    if ($id) {
        $id = " id='$id'";
    }
    if ($class) {
        $class = " $class";
    }
    $style = '';
    if ($sizeFactor) {
        $style = "--lzy-icon-factor:$sizeFactor;";
    }
    if ($color) {
        $style = "$style color:$color;";
    }
    if ($style) {
        $style = " style='$style'";
    }
    if ($tooltip) {
        $tooltip = " title='$tooltip'";
        if ($inx === 1) {
            $this->page->addModules('QTIP');
            $jq = "$('.lzy-icon[title]').qtip({ content:{ attr: 'title' }})\n";
            $this->page->addJq($jq);
        }
    }
    $this->optionAddNoComment = true;

    $str = "<span$id class='lzy-icon lzy-icon-$name$class'$style$tooltip></span>";
	return $str;
});
