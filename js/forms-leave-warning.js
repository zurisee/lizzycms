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
        var presetVal = $(this).attr('data-value');
        presetVal = (typeof presetVal !== 'undefined')? presetVal: '';
        if ( ($(this).val() !== presetVal) && (type !== 'checkbox') && (type !== 'radio') && (type !== 'range') && (type !== 'hidden')) {
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
