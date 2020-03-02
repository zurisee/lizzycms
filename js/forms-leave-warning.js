// === forms.js

var pgLeaveWarnings = true;
var formEmpty = true;
$('.lzy-form').submit(function() {
    pgLeaveWarnings = false;
});
$(window).bind('beforeunload', function(e){
    $('.lzy-form .lzy-form-field-wrapper input').each(function() {
        if ( $(this).val() !== '') {
            formEmpty = false;
        }
    });
    $('.lzy-form textarea').each(function() {
        if ( $(this).text() !== '') {
            formEmpty = false;
        }
    });
    $('.lzy-form-continue').click(function() {
        pgLeaveWarnings = false;
    });
    if (!formEmpty && pgLeaveWarnings) {
        return true;
    }
});
