/*  JS-Popup  */

var lzyPopupInx = null;

function lzyPopup( options ) {
    var content = (typeof options.content !== 'undefined')? options.content : '';
    if (typeof options.contentFrom !== 'undefined') {
        content += $( options.contentFrom ).html();
    }
    var inx = (typeof options.index !== 'undefined') ? options.index : null;
    var trigger = (typeof options.trigger !== 'undefined') ? options.trigger : true;
    var anker = (typeof options.anker !== 'undefined') ? options.anker : 'body';
    var closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined') ? options.closeOnBgClick : true;
    var buttons = (typeof options.buttons !== 'undefined') ? options.buttons.split(',') : [];
    var callbacks = (typeof options.callbacks !== 'undefined') ? options.callbacks.split(',') : [];
    var popupclass = (typeof options.class !== 'undefined') ? ' ' + options.class : '';
    if (closeOnBgClick) {
        popupclass += ' lzy-popup-closebtn';
    }
    var buttonClass = (typeof options.buttonClass !== 'undefined') ? options.buttonClass : 'lzy-button';

    if (inx === null) {
        inx = (lzyPopupInx === null)? 1 : inx++;
    }

    // prepare buttons:
    var buttonHtml = '';
    for (var i in buttons) {
        var id = parseInt(i) + 1;
        id = 'lzy-popup-btn' + id;
        buttonHtml += '<button id="'+ id +'" class="lzy-popup-btn ' + buttonClass + '">' + buttons[i].trim() + '</button> ';
    }
    if (buttonHtml) {
        buttonHtml = '<div class="lzy-popup-buttons">' + buttonHtml + '</div>';
    }

    var style = '';
    if (trigger !== true) {
        style = ' style="display: none;"';
    }

    // add popup HTML to DOM:
    var html = '<div class="lzy-popup lzy-popup' + inx + popupclass + '"' + style + '>\n' +
        '    <div class="lzy-popup-wrapper">\n' +
        content + buttonHtml +
        '    </div>\n' +
        '</div>\n';
    $( anker ).append( html );

    // setup callback invokation:
    if (buttonHtml) {
        $('.lzy-popup-btn').click(function () {
            var id = $( this ).attr('id');
            var i = parseInt( id.substr(13) ) - 1;
            var cb = callbacks[ i ].trim();
            if (typeof window[cb] === 'function') {
                window[cb]( i,  buttons[i].trim());
            }
            $( this ).closest('.lzy-popup').hide();
        });
    }

    // setup close-on-bg-click:
    if (closeOnBgClick) {
        $('.lzy-popup').click(function () {
            $('.lzy-popup').hide();
        });
    }
    // setup close-button:
    $('.lzy-popup-close-button').click(function(){
        $('.lzy-popup').hide();
    });

    // setup trigger:
    if (trigger) {
        $(".trigger_popup").click(function(){
            $('.lzy-popup1').show();
        });
    }
} // lzyPopup

