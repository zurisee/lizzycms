/*
**  Lizzy Account Module
 */


$( document ).ready(function() {
    init();
    setGenericEventHandlers();
}); // ready

function init() {
    $('.lzy-login-username').focus();
    $('.lzy-signup-username').focus();
}

function setGenericEventHandlers() {

    $('.lzy-form-show-password a').click(function(e) {
        e.preventDefault();
        if ($('.lzy-form-password').attr('type') === 'text') {
            $('.lzy-form-password').attr('type', 'password');
            $('.lzy-form-show-password lzy-icon-show:before').text('\006b');
            $('.lzy-form-show-password span').removeClass('lzy-icon-hide').addClass('lzy-icon-show');
        } else {
            $('.lzy-form-password').attr('type', 'text');
            $('.lzy-form-show-password span').removeClass('lzy-icon-show').addClass('lzy-icon-hide');
        }
    });
} // setGenericEventHandlers


$('.lzy-form-password').on('input', function () {
    let pwIsSet = true;
    $('.lzy-form-password').each(function (){
        pwIsSet = pwIsSet && ($(this).val() !== '');
    });
    if (pwIsSet) {
        $('#btn_profile-change-pw-submit').prop('disabled', false).removeClass('lzy-disabled');
    } else {
        $('#btn_profile-change-pw-submit').prop('disabled', true).addClass('lzy-disabled');
    }
});

$('#btn_profile-change-pw-submit').click(function (e) {
    let pw1 = $('#fld_lzy-change-password-prompt_1').val();
    let pw2 = $('#fld_lzy-change-password2-prompt_1').val();
    if (pw1 !== pw2) {
        e.preventDefault();
        lzyPopup('{{ lzy-change-password-not-equal-response }}');
    }
});



$('.lzy-show-password-login-info').click(function(e) {
    e.preventDefault();
    $show = $('.lzy-password-login-info');
    if ($show.css('display') === 'block') {
        $show.css('display', 'none');
    } else {
        $show.css('display', 'block');
    }
});



$('.lzy-admin-submit-button').click(function(e) {
    const url = window.location.href.replace(/\?.*/, '');
    if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/))) {
        alert('{{ Warning insecure connection }}');
        e.preventDefault();
        return;
    }
});


$('.lzy-login-tab-label1').click(function() {
    setTimeout(function(){ $('#login_email').focus(); }, 20);
});


$('.lzy-login-tab-label2').click(function() {
    setTimeout(function(){ $('#fld_username').focus(); }, 20);
});


// === login simple-mode
$('.lzy-login-simple-mode #fld_username').focus();

$('.lzy-login-simple-mode #btn_lzy-login-submit').click(function(e) {
    e.preventDefault();
    const url = window.location.href;
    if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
        alert('{{ Warning insecure connection }}');
        return;
    }
    $('.login_wrapper form').attr('action', url);
    $('#lzy-lbl-login-user output').text('');
    $('#lbl_login_password output').text('');
    const un = $('#fld_username').val();
    const pw = $('#fld_password').val();
    if (!un) {
        $('#lzy-lbl-login-user output').text('{{ Err empty username }}');
        return;
    }
    if (!pw) {
        $('#lbl_login_password output').text('{{ Err empty password }}');
        return;
    }
    if (false && (location.protocol !== 'https:')) {
        alert('No HTTPS Connection!');
        return;
    }
    $('.lzy-login-simple-mode form').submit();
});


$('.lzy-invite-user-form').submit(function () {
    var txt = $('#lzy-textarea1').val();
    if (typeof txt !== 'undefined') {
        txt = txt.replace(/\n/, ';');
        $('#lzy-textarea1').val(txt);
    }

    txt = $('#lzy-textarea2').val();
    txt = txt.replace(/\n/g, ' BR ');
    $('#lzy-textarea2').val( txt );
});

