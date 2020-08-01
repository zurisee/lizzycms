/*
**  Lizzy Account Module
 */


$( document ).ready(function() {
    init();
    setGenericEventHandlers();
    // setupOnetimeLogin(); // ???

}); // ready

function init() {
    $('.lzy-login-username').focus();
    $('.lzy-signup-username').focus();

    // $('#lzy-panel-id101').addClass('lzy-panel-page-open');  // pre-open first accordion panel
}

function setGenericEventHandlers() {

    $('.lzy-form-show-password a').click(function(e) {
        e.preventDefault();
        if ($('.lzy-form-password').attr('type') == 'text') {
            $('.lzy-form-password').attr('type', 'password');
            $('.lzy-form-show-password lzy-icon-show:before').text('\006b');
            $('.lzy-form-show-password span').removeClass('lzy-icon-hide').addClass('lzy-icon-show');
        } else {
            $('.lzy-form-password').attr('type', 'text');
            $('.lzy-form-show-password span').removeClass('lzy-icon-show').addClass('lzy-icon-hide');
        }
    });

    $('.lzy-admin-show-info').click(function(e) {
        e.preventDefault();
        $('.lzy-admin-info', $(this).parent()).toggle();
    });
}


// function setupOnetimeLogin() {
//     // $('.lzy-show-onetimelogin-info').click(function(e) {
//     //     e.preventDefault();
//     //     $('.lzy-onetimelogin-info').toggle();
//     // });
// }

$('.lzy-show-un-pw-login-info').click(function(e) {
    e.preventDefault();
    $('.lzy-un-pw-login-info').toggle();
    // $('.lzy-pw-login-info').toggle();
});

$('.lzy-show-password-login-info').click(function(e) {
    e.preventDefault();
    $show = $('.lzy-password-login-info');
    if ($show.css('display') == 'block') {
        $show.css('display', 'none');
    } else {
        $show.css('display', 'block');
    }
    // $('.lzy-password-info').toggle();
});

/* SignUp Form: */
$('.lzy-show-signup-login-info').click(function(e) {
    e.preventDefault();
    $('.lzy-signup-login-info').toggle();
});

$('.lzy-show-signup-password-info').click(function(e) {
    e.preventDefault();
    $('.lzy-signup-password-info').toggle();
});

$('.lzy-show-signup-password-again-info').click(function(e) {
    e.preventDefault();
    $('.lzy-signup-password-again-info').toggle();
});

$('.lzy-show-signup-info').click(function(e) {
    e.preventDefault();
    $('.lzy-signup-info').toggle();
});
$('.lzy-show-add-user-login-info').click(function(e) {
    e.preventDefault();
    $('.lzy-add-user-info').toggle();
});





$('.lzy-admin-submit-button').click(function(e) {
    e.preventDefault();
    $(this).prop('disabled', true);
    var url = window.location.href.replace(/\?.*/, '');
    if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/))) {
        alert('{{ Warning insecure connection }}');
        return;
    }
    var $form = $(this).closest('form');
    $form.attr('action', url);
    var which = $(this).attr('class').replace('lzy-admin-submit-button', '').replace('lzy-button', '').trim();
    switch (which) {
        case 'lzy-signup-submit-button':
            break;

        case 'lzy-add-user-submit-button':
            break;

        case 'lzy-login-submit-button':
            break;

    }
    $form.submit();

});
// $('X.signup-submit-btn').click(function(e) {
//     e.preventDefault();
//     $(this).prop('disabled', true);
//     // var url = window.location.href.replace(/\?.*/, '');
//     var url = window.location.href;
//     if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/))) {
//         alert('{{ Warning insecure connection }}');
//         return;
//     }
//     var $form = $(this).closest('form');
//     // $form.attr('action', url);
//     // var which = $(this).attr('class').replace('lzy-admin-submit-button', '').replace('lzy-button', '').trim();
//     // switch (which) {
//     //     case 'lzy-signup-submit-button':
//     //         break;
//     //
//     //     case 'lzy-add-user-submit-button':
//     //         break;
//     //
//     //     case 'lzy-login-submit-button':
//     //         break;
//     //
//     // }
//     $form.submit();
//
// });


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
    var url = window.location.href;
    if (!(url.match(/^https:\/\//) || url.match(/^http:\/\/localhost/) || url.match(/^http:\/\/192\.168/) )) {
        alert('{{ Warning insecure connection }}');
        return;
    }
    $('.login_wrapper form').attr('action', url);
    $("#lzy-lbl-login-user output").text('');
    $("#lbl_login_password output").text('');
    var un = $('#fld_username').val();
    var pw = $('#fld_password').val();
    if (!un) {
        $("#lzy-lbl-login-user output").text('{{ Err empty username }}');
        return;
    }
    if (!pw) {
        $("#lbl_login_password output").text('{{ Err empty password }}');
        return;
    }
    if (false && (location.protocol != 'https:')) {
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
})

