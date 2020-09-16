/*  JS-Popup
*   Options:
*       text            : text (html) to be displayed in popup
*       content         : synonyme for text
*       trigger         : how to open popup: true = immediately, false = not (i.e. opened elsewhere), string = selector of button or link
*       closeOnBgClick  : If true, clicks on background close the popup (default: true)
*       closeButton     : If true, activates a close button in the upper right corner (default: true)
*       buttons         : list of button texts (separated by ',')
*       callbacks       : list of button-callback functions (separated by ',' corresponding to buttons)
*       callbackArg     : optional argument passed on to button-callback functions
*       class           : class applied to popup container
*       buttonClass     : class(es) applied to buttons (-> optionally a list separated by ',') (default: 'lzy-button')
*/

var lzyPopupInx = null;
var transient = false;

function lzyPopup( options ) {
    var text  = (typeof options.text !== 'undefined')? options.text : '';               // text synonym for content
    var content  = (typeof options.content !== 'undefined')? options.content : text;

    var trigger = (typeof options.trigger !== 'undefined') ? options.trigger : true;
    var anker = 'body'; // (typeof options.anker !== 'undefined') ? options.anker : 'body';
    var closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined') ? options.closeOnBgClick : true;
    var closeButton = (typeof options.closeButton !== 'undefined') ? options.closeButton : true;
    var buttons = (typeof options.buttons === 'string') ? options.buttons.split(',') : [];
    var callbacks = [];
    if (typeof options.callbacks === 'string') {
        callbacks =  options.callbacks.split(',');
    } else if (typeof options.callbacks === 'function') {
        callbacks[0] =  options.callbacks;
    }
    var callbackArg = (typeof options.callbackArg !== 'undefined') ? options.callbackArg : null;
    var popupclass = (typeof options.class !== 'undefined') ? ' ' + options.class : '';
    if (closeButton) {
        popupclass += ' lzy-js-popup-closebtn';
    }
    var buttonClass = (typeof options.buttonClass !== 'undefined') ? options.buttonClass : 'lzy-button';
    var buttonClasses =  buttonClass.split(',');

    inx = (lzyPopupInx === null)? 1 : inx++;


    // prepare buttons:
    var buttonHtml = '';
    var bCl = '';
    for (var i in buttons) {
        var id = parseInt(i) + 1;
        id = 'lzy-js-popup-btn' + id;
        bCl = (typeof buttonClasses[ i ] !== 'undefined')? buttonClasses[ i ]: '';
        buttonHtml += '<button id="'+ id +'" class="lzy-js-popup-btn ' + bCl + '">' + buttons[i].trim() + '</button> ';
    }
    if (buttonHtml) {
        buttonHtml = '<div class="lzy-js-popup-buttons">' + buttonHtml + '</div>';
    }

    transient = true; // global var

    if (content) {      // content supplied as literal:
        // add popup HTML to DOM:
        var style = '';
        if (trigger !== true) {
            style = ' style="display: none;"';
        }
        var html = '<div class="lzy-js-popup lzy-js-popup' + inx + popupclass + '"' + style + '>\n' +
            '    <div class="lzy-js-popup-wrapper">\n' +
            content + buttonHtml +
            '    </div>\n' +
            '</div>\n';
        $(anker).append(html);

    } else {
        var contentRef = (typeof options.contentRef !== 'undefined') ? options.contentRef : '';
        if (contentRef) {       // content as reference to DOM element:
            transient = false;
            contentRef = '#' + contentRef.replace(/^[.#]/, '');
            var $popupElem = $( contentRef );
            if (!$popupElem.hasClass('lzy-js-popup')) {
                $popupElem.addClass('lzy-js-popup').addClass('lzy-js-popup' + inx);
                if (popupclass) {
                    $popupElem.addClass(popupclass);
                }
                var $popupContent = $('> div', $popupElem);
                $popupContent.wrap('<div class="lzy-js-popup-wrapper">');
                $popupContent.append(buttonHtml);
            }
            if (trigger === true) {
                $popupElem.show();
            }

        } else {    // if no content specified
            console.log('Error in lzyPopup: argument "text" or "contentRef" required.');
            return false;
        }
    }

    // setup callback invokation:
    if (buttonHtml) {
        $('.lzy-js-popup-btn').click(function () {
            var id = $( this ).attr('id');
            var i = parseInt( id.substr(16) ) - 1;
            if (typeof callbacks[ i ] === 'string') {
                var cb = callbacks[i].trim();
                var btn = (buttons[i] === 'string')? buttons[i].trim(): '';
                if (typeof window[cb] === 'function') {
                    if ( window[cb](i, btn, callbackArg) ) {
                        return;
                    }
                }
            } else if (typeof callbacks[ i ] === 'function') {
                var cb = callbacks[ i ];
                var btn = (buttons[ i ] === 'string')? buttons[i].trim(): '';
                if ( cb(i, btn, callbackArg) ) {
                    return;
                }
            }
            lzyPopupClose( this );
        });
    }

    // setup close-on-bg-click:
    if (closeOnBgClick) {
        $('.lzy-js-popup').click(function () {
            lzyPopupClose( this );
        });
        $('.lzy-js-popup-wrapper').click(function (e) {
            e.stopPropagation();
        });
    }
    // setup close-button:
    $('.lzy-js-popup-close-button').click(function(){
        lzyPopupClose( this );
    });

    // setup trigger:
    if (trigger) {
        $(".trigger_popup").click(function(){
            $('.lzy-js-popup').show();
        });
    }
} // lzyPopup




function lzyPopupClose( that ) {
    var $popup = null;
    if (typeof that === 'undefined') {
        $popup = $('.lzy-js-popup');
    } else {
        $popup = $( that ).closest('.lzy-js-popup');
    }
    if (transient) {
        $popup.remove();
    } else {
        $popup.hide();
    }
} // lzyPopupClose
