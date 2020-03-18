// === forms.js

var pgLeaveWarnings = true;
var formEmpty = true;
$('.lzy-form').submit(function() {
    pgLeaveWarnings = false;
});
$('.lzy-form-continue *').click(function() {
    pgLeaveWarnings = false;
});
$(window).bind('beforeunload', function(e){
    $('.lzy-form .lzy-form-field-wrapper input').each(function() {
        var type = $(this).attr('type');
        if ( ($(this).val() !== '') && (type !== 'checkbox') && (type !== 'radio') && (type !== 'range') && (type !== 'hidden')) {
            formEmpty = false;
        }
    });
    $('.lzy-form textarea').each(function() {
        if ( $(this).text() != '') {
            formEmpty = false;
        }
    });
    if (!formEmpty && pgLeaveWarnings) {
        return true;
    }
});
