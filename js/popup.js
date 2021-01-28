/*  JS-Popup
*   Options:
*       text            : text (html) to be displayed in popup
*       content         : synonyme for text
*       contentFrom     : selector of DOM element to import and popup
*       contentRef      : selector (or JQ object) of DOM element to directly popop (-> for internal use)
*       trigger         : how to open popup: true = immediately, false = not (i.e. opened elsewhere), string = selector of button or link
*       closeOnBgClick  : If true, clicks on background close the popup (default: true)
*       closeButton     : If true, activates a close button in the upper right corner (default: true)
*       buttons         : list of button texts (separated by ',')
*       callbacks       : list of button-callback functions (separated by ',' corresponding to buttons)
*       callbackArg     : optional argument passed on to button-callback functions
*       class           : class applied to popup container
*       buttonClass     : class(es) applied to buttons (-> optionally a list separated by ',') (default: 'lzy-button')
*/

"use strict";


var LzyPopup = new Object({
    lzyPopupInx: null,
    lzyPopupContent: [],
    content: '',
    $pop: null,
    lastX: 0,
    lastY: 0,


    execute: function( options ) {
        this.parseArgs( options );
        this.prepareButtons();
        this.prepareContent();
        if (this.draggable) {
            this.initDraggable();
        }
        this.setupTrigger();
    }, // execute


    parseArgs: function ( options ) {
        if (typeof options === 'string') {
            const str = options;
            options = null;
            options = { text: str };
        }
        this.text  = (typeof options.text !== 'undefined')? options.text : '';               // text synonym for content
        this.content  = (typeof options.content !== 'undefined')? options.content : this.text;

        this.contentFrom = (typeof options.contentFrom !== 'undefined') ? options.contentFrom : ''; // contentFrom synonyme for contentRef
        this.contentRef = (typeof options.contentRef !== 'undefined') ? options.contentRef : '';

        this.header  = (typeof options.header !== 'undefined')? options.header : false;
        if ((this.header === '') || (this.header === true)) {
            this.header = '&nbsp;';
        }
        this.draggable  = (typeof options.draggable !== 'undefined')? options.draggable : false;
        if (this.draggable && (this.header === false)) {
            this.header = '&nbsp;';
        }

        this.trigger = (typeof options.trigger !== 'undefined') ? options.trigger : true;
        this.trigger = (typeof options.triggerSource !== 'undefined') ? options.triggerSource : this.trigger;
        this.triggerEvent = (typeof options.triggerEvent !== 'undefined') ? options.triggerEvent : 'click';
        this.anker = 'body'; // (typeof options.anker !== 'undefined') ? options.anker : 'body';
        this.closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined') ? options.closeOnBgClick : true;
        this.closeButton = (typeof options.closeButton !== 'undefined') ? options.closeButton : true;
        this.buttons = (typeof options.buttons === 'string') ? options.buttons.split(',') : [];
        this.callbacks = [];
        if (typeof options.callbacks === 'string') {
            this.callbacks =  options.callbacks.split(',');
        } else if (typeof options.callbacks === 'function') {
            this.callbacks[0] =  options.callbacks;
        }
        this.callbackArg = (typeof options.callbackArg !== 'undefined') ? options.callbackArg : null;
        this.popupclass = (typeof options.class !== 'undefined') ? ' ' + options.class : '';
        if (this.closeButton) {
            this.popupclass += ' lzy-popup-closebtn';
        }
        this.buttonClass = (typeof options.buttonClass !== 'undefined') ? options.buttonClass : 'lzy-button';
        this.buttonClasses =  this.buttonClass.split(',');
        this.wrapperClass  = (typeof options.wrapperClass !== 'undefined')? options.wrapperClass : '';               // text synonym for content

        this.inx = this.lzyPopupInx = (this.lzyPopupInx === null)? 1 : this.lzyPopupInx + 1;
    }, // parseArgs



    prepareButtons: function () {
        this.buttonHtml = '';
        var k, i, id, bCl, button, callback;
        var buttonHtml = '';
        if (typeof this.buttons === 'undefined') {
            return;
        }
        for ( i in this.buttons) {
            k = parseInt(i) + 1;
            id = 'lzy-popup-btn-' + this.inx + '-' + k;
            bCl = (typeof this.buttonClasses[ i ] !== 'undefined')? this.buttonClasses[ i ]: this.buttonClasses[ 0 ];

            if (typeof this.callbacks[i] !== 'undefined') {
                callback = ' onclick="' + this.callbacks[i] + '()"';
            } else {
                callback = ' onclick="lzyPopupClose()"';
            }

            button = (typeof this.buttons[ i ] !== 'undefined')? this.buttons[ i ].trim(): '';
            buttonHtml += '<button id="'+ id +'" class="lzy-popup-btn lzy-popup-btn-' + k + ' ' + bCl + '"' + callback + '>' + button + '</button> ';
        }
        if (buttonHtml) {
            this.buttonHtml = '<div class="lzy-popup-buttons">' + buttonHtml + '</div>';
        }

        if (this.closeButton) {
            this.closeButton = '<button class="lzy-popup-close-button">&#x0D7;</button>';
        } else {
            this.closeButton = '';
        }
    }, // prepareButtons



    prepareContent: function () {
        var $cFrom = null;
        var lzyPopupFromStr = '';
        var contentFrom = this.contentFrom;
        var $popupElem = null;

        // activate closeOnBgClick if requested:
        if (this.closeOnBgClick) {
            this.popupclass = this.popupclass + ' lzy-close-on-bg-click';
        }

        var header = '';
        if (this.header !== false) {
            header = '\t\t<div class="lzy-popup-header"><div>' + this.header + '</div>'+ this.closeButton + '</div>\n';
            this.popupclass += ' lzy-popup-with-header';
        } else {
            header = this.closeButton;
        }

        if (contentFrom) {
            if (typeof contentFrom === 'string') {
                if ((contentFrom.charAt(0) !== '#') && (contentFrom.charAt(0) !== '.')) {
                    contentFrom = '#' + contentFrom;
                }
                lzyPopupFromStr = contentFrom;
                $cFrom = $( contentFrom );

            } else if ( contentFrom[0].length ) { // case JQ-object
                lzyPopupFromStr = contentFrom.attr('id');
                if (typeof lzyPopupFromStr === 'undefined') {
                    lzyPopupFromStr = contentFrom.attr('class');
                }
                $cFrom = contentFrom;
            } else {
                return; // error
            }

            // get content and cache in a variable:
            if (typeof this.lzyPopupContent[ lzyPopupFromStr ] === 'undefined') { // not yet cached
                var tmp = '';
                tmp = $cFrom.html();
                this.lzyPopupContent[ lzyPopupFromStr ] = tmp; // save original HTML

                // shield IDs in original:
                tmp = tmp.replace(/id=(['"])/g, 'id=$1lzyPopupInitialized-');
                $cFrom.html( tmp );
            }
            this.content = this.content + this.lzyPopupContent[ lzyPopupFromStr ];
        } // contentFrom

        // content supplied as literal or by contentFrom:
        if (this.content) {
            // add popup HTML to DOM (at end of body element):
            this.popupclass += ' lzy-popup-transient';
            // this.popupclass = this.popupclass + ' lzy-popup-transient';
            var style = '';
            if (this.trigger !== true) {
                style = ' style="display: none;"';
            }

            var html = '<div id="lzy-popup-' + this.inx + '" class="lzy-popup-bg lzy-popup-' + this.inx + this.popupclass + '"' + style + '>\n' +
                '    <div class="lzy-popup-wrapper">\n'+ header +
                '      <div class="lzy-popup-container">\n' +
                this.content + this.buttonHtml +
                '    </div>\n      </div>\n' +
                '</div>\n';
            $(this.anker).append( html );

        } else if (this.contentRef) {       // content as reference to DOM element:
            if (typeof this.contentRef === 'object') {
                $popupElem = this.contentRef;
            } else {
                if ((this.contentRef.charAt(0) !== '#') && (this.contentRef.charAt(0) !== '.')) {
                    this.contentRef = '#' + this.contentRef;
                }
                $popupElem = $(this.contentRef);
            }

            if (!$popupElem.hasClass('lzy-popup-bg')) {
                $popupElem.attr('id', 'lzy-popup-' + this.inx);
                $popupElem.addClass('lzy-popup-bg').addClass('lzy-popup-' + this.inx);
                if (this.popupclass) {
                    $popupElem.addClass(this.popupclass);
                }
                var $popupContent = $('> div', $popupElem);
                var cls = 'lzy-popup-wrapper lzy-popup-wrapper-ref';
                if (this.wrapperClass) {
                    cls += ' ' + this.wrapperClass;
                }

                $popupContent.wrap('<div class="' + cls + '">');
                $popupContent.parent().prepend( header );
                $popupContent.wrap('<div class="lzy-popup-container">');
                $popupContent.append(this.buttonHtml);
            }

        } else {    // if no content specified
            console.log('Error in lzyPopup: argument "text" or "contentFrom" required.');
            return false;
        }
    }, // prepareContent



    setupTrigger: function () {
        if (!this.trigger) {
            return;
        }
        if (this.trigger === true) {
            this.open();

        } else if (this.trigger !== true) {
            var id = '.lzy-popup-' + this.inx;
            var obj = this;
            $('body').on(this.triggerEvent, this.trigger , function( e ){
                if ($( id ).length) {
                    obj.open();
                } else {
                    obj.execute();
                }
            });
        }
    }, // setupTrigger



    open: function ( that ) {
        if (typeof that === 'undefined') {
            $('.lzy-popup-' + this.inx).show();
        } else {
            var $popElem = $(that).closest('.lzy-popup-bg');
            $popElem.show();
        }
        $('body').addClass('lzy-no-scroll');
    }, // open



    onDragstart: function (e) {
        e.stopPropagation();
        LzyPopup.$pop = $( '#lzy-popup-' + LzyPopup.lzyPopupInx + ' .lzy-popup-wrapper' );
    },



    onDragmove: function(e) {
        e.stopPropagation();
        LzyPopup.$pop.css({left: e.px_tdelta_x * -1,top: e.px_tdelta_y * -1, });
    },



    onDragend: function(e) {
        e.stopPropagation();
        LzyPopup.lastX -= e.px_tdelta_x;
        LzyPopup.lastY -= e.px_tdelta_y;
        LzyPopup.$pop.css({ transform: 'translate('+ LzyPopup.lastX +'px, ' + LzyPopup.lastY + 'px)', top:0,left:0 });
    },



    initDraggable: function (e) {
        if (typeof $.ueSetGlobalCb === 'undefined') {
            alert('Error: popup option "draggable" initiated, but module EVENT_UE not loaded');
        }
        LzyPopup.lastX = 0;
        LzyPopup.lastY = 0;
        $( '#lzy-popup-' + LzyPopup.lzyPopupInx + ' .lzy-popup-header :not(.lzy-popup-close-button)' )
            .bind( 'udragstart', LzyPopup.onDragstart )
            .bind( 'udragmove',  LzyPopup.onDragmove  )
            .bind( 'udragend',   LzyPopup.onDragend   )
            .bind( 'click',      function(e) { e.stopPropagation(); })
        ;
    }
});




function lzyPopup( options ) {
    LzyPopup.execute( options );
} // lzyPopup




function lzyPopupClose( that ) {
    var $popup = null;
    $('body').removeClass('lzy-no-scroll');

    if (typeof that === 'undefined') {
        $popup = $('.lzy-popup-bg');
    } else {
        $popup = $( that ).closest('.lzy-popup-bg');
    }
    if ($popup.hasClass('lzy-popup-transient')) {
        $popup.remove();
    } else {
        $popup.hide();
    }
} // lzyPopupClose




// setup close buttons
$('body')
    .on('click', '.lzy-popup-close-button', function () {
        lzyPopupClose(this);
    })
    .on('click', '.lzy-popup-bg.lzy-close-on-bg-click', function () {
        lzyPopupClose();
    });
