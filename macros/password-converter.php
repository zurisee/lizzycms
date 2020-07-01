<?php
// @info: -> one line description of macro <-


$macroName = basename(__FILE__, '.php');

$this->addMacro($macroName, function () {
    $macroName = basename(__FILE__, '.php');
    $overlay = $this->getArg($macroName, 'overlay', 'If true, the converter tool will be presented in an overlay.', true);
    $this->disablePageCaching = $this->getArg($macroName, 'disableCaching', '(false) Enables page caching (which is disabled for this macro by default). Note: only active if system-wide caching is enabled.', true);

    $html = <<<EOT
<h1>{{ Convert Password }}</h1>
<form class='lzy-password-converter'>
    <div>
        <label for='fld_password'>{{ Password }}</label>
        <input type='text' id='fld_password' name='password' value='' placeholder='{{ Password }}' />
        <button id='convert' class='lzy-form-form-button lzy-button'>{{ Convert }}</button>
    </div>
</form>
<p>{{ Hashed Password }}:</p>
<div id="lzy-hashed-password"></div>
<div id="lzy-password-converter-help" style="display: none;">&rarr; {{ Copy-paste the selected line }}</div>

EOT;

    $jq = <<<'EOT'
    setTimeout(function() { 
        $('#fld_password').focus();
    }, 200);
    $('#convert').click(function(e) {
        e.preventDefault();
        calcHash();
    });
    function calcHash() {
        var bcrypt = dcodeIO.bcrypt;
        var salt = bcrypt.genSaltSync(10);
        var pw = $('#fld_password').val();
        var hashed = bcrypt.hashSync(pw, salt);
        $('#lzy-hashed-password').text( 'password: ' + hashed ).selText();
        $('#lzy-password-converter-help').show();
    }
EOT;

    $css = <<<EOT
    #lzy-hashed-password {
        line-height: 2.5em;
        border: 1px solid gray;
        height: 2.5em;
        line-height: 2.5em;
        padding-left: 0.5em;
        width: 47em;
    }
    .lzy-password-converter button {
        height: 1.8em;
        padding: 0 1em;
    }
    .lzy-password-converter label {
        position: absolute;
        left: -1000vw;
    }
    .lzy-password-converter input {
        height: 1.4em;
        padding-left: 0.5em;
        margin-right: 0.5em;
        width: 20em;
    }

EOT;

    $this->page->addCss( $css );
    $this->page->addJq( $jq );
    $this->page->addModules( '~sys/third-party/bcrypt/bcrypt.min.js' );
    if ($overlay) {
        $this->page->addOverlay($html);
        $this->page->setOverlayMdCompile(false);

        return '';
    } else {
        return $html;
    }
});
