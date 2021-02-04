/*  JS-Popup */

"use strict";

var popupInx = 0;
var popupInstance = [];

function LzyPopup( options, index ) {
    this.inx = (typeof index !== 'undefined')? index : popupInx++;
    this.lzyPopupContent = null;
    this.triggerInitialized = false;
    var parent = this;

    this.init = function (options) {
        this.parseArgs( options );
        this.prepareButtons();
        this.prepareContent();
        if (this.draggable) {
            this.initDraggable();
        }
        this.setupTrigger();
        this.setupKeyHandler();
    }; // init



    this.parseArgs = function () {
        if (typeof options === 'string') {
            const str = options;
            options = null;
            options = { text: str };
        }
        this.options = options;
        this.text  = (typeof options.text !== 'undefined')? options.text : ''; // text synonym for content
        this.content  = (typeof options.content !== 'undefined')? options.content : this.text;

        this.id  = (typeof options.id !== 'undefined')? options.id : 'lzy-popup-' + this.inx;

        this.contentFrom = (typeof options.contentFrom !== 'undefined') ? options.contentFrom : ''; // contentFrom synonyme for contentRef
        this.contentRef = (typeof options.contentRef !== 'undefined') ? options.contentRef : '';

        this.header  = (typeof options.header !== 'undefined')? options.header : false;
        if ((this.header === '') || (this.header === true)) {
            this.header = '&nbsp;';
        }
        this.draggable  = (typeof options.draggable !== 'undefined')? options.draggable : (this.header !== false);
        if (this.draggable && (this.header === false)) {
            this.header = '&nbsp;';
        }

        if (this.content === 'help') {
            this.content = this.renderHelp();
            this.closeButton = true;
            this.header = 'Options for popup()';
        }

        this.trigger = (typeof options.trigger !== 'undefined') ? options.trigger : true; // default=autoopen
        this.trigger = (typeof options.triggerSource !== 'undefined') ? options.triggerSource : this.trigger;
        this.triggerEvent = (typeof options.triggerEvent !== 'undefined') ? options.triggerEvent : 'click';
        this.anker = (typeof options.anker !== 'undefined') ? options.anker : 'body';
        this.closeOnBgClick = (typeof options.closeOnBgClick !== 'undefined') ? options.closeOnBgClick : true;
        this.closeButton = (typeof options.closeButton !== 'undefined') ? options.closeButton : true;
        this.buttons = (typeof options.buttons === 'string') ? options.buttons.split(',') : [];

        // omit closeButton if buttons are defined (unless header is active):
        if ((typeof options.closeButton === 'undefined') && (this.buttons !== []) && !this.header) {
            this.closeButton = false;
        }
        this.callbacks = [];
        if (typeof options.callbacks === 'string') {
            this.callbacks =  options.callbacks.split(',');
        } else if (typeof options.callbacks === 'function') {
            this.callbacks[0] =  options.callbacks;
        }
        this.popupClass = (typeof options.class !== 'undefined') ? ' ' + options.class : '';
        this.popupClass = (typeof options.popupClass !== 'undefined') ? ' ' + options.popupClass : this.popupClass;
        if (this.closeButton) {
            this.popupClass += ' lzy-popup-closebtn';
        }
        this.buttonClass = (typeof options.buttonClass !== 'undefined') ? options.buttonClass : 'lzy-button';
        this.buttonClasses =  this.buttonClass.split(',');
        this.wrapperClass  = (typeof options.wrapperClass !== 'undefined')? options.wrapperClass : '';
    }; // parseArgs



    this.prepareButtons = function () {
        var k, i, id, bCl, button, callback;
        var buttonHtml = '';
        if (this.closeButton) {
            this.closeButton = '<button class="lzy-popup-close-button">&#x0D7;</button>';
        } else {
            this.closeButton = '';
        }

        if (typeof options.onConfirm !== 'undefined') {
            buttonHtml = '<button class="lzy-button lzy-popup-btn-cancel">{{ lzy-cancel }}</button> ';
            buttonHtml += '<button class="lzy-button lzy-popup-btn-confirm">{{ lzy-confirm }}</button> ';
            this.buttonHtml = '<div class="lzy-popup-buttons">' + buttonHtml + '</div>';
            return;
        }


        this.buttonHtml = '';
        if (typeof this.buttons === 'undefined') {
            return;
        }
        for ( i in this.buttons) {
            k = parseInt(i) + 1;
            id = 'lzy-popup-btn-' + this.inx + '-' + k;
            bCl = (typeof this.buttonClasses[ i ] !== 'undefined')? this.buttonClasses[ i ]: this.buttonClasses[ 0 ];

            if (typeof this.callbacks[i] !== 'undefined') {
                callback = ' onclick="return ' + this.callbacks[i] + '(this);"';
            } else {
                callback = ' onclick="lzyPopupClose()"';
            }

            button = (typeof this.buttons[ i ] !== 'undefined')? this.buttons[ i ].trim(): '';
            buttonHtml += '<button id="'+ id +'" class="lzy-popup-btn lzy-popup-btn-' + k + ' ' + bCl + '"' + callback + '>' + button + '</button> ';
        }
        if (buttonHtml) {
            this.buttonHtml = '<div class="lzy-popup-buttons">' + buttonHtml + '</div>';
        }

    }; // prepareButtons



    this.prepareContent = function () {
        var $cFrom = null;
        var contentFrom = this.contentFrom;
        var $popupElem = null;
        var data = ' data-popup-inx="' + this.inx + '"';
        var id = '';
        var cls = '';

        if ($('.lzy-popup-' + this.inx).length) {
            return ;
        }

        // activate closeOnBgClick if requested:
        if (this.closeOnBgClick) {
            this.popupClass = this.popupClass + ' lzy-close-on-bg-click';
        }

        var header = '';
        if (this.header !== false) {
            header = (this.header === true) ? ' ': this.header;
            cls = this.draggable? ' lzy-draggable': '';
            header = '\t\t<div class="lzy-popup-header' + cls + '"><div>' + header + '</div>'+ this.closeButton + '</div>\n';
            this.popupClass += ' lzy-popup-with-header';
        } else {
            header = this.closeButton;
        }

        if (contentFrom) {
            if (typeof contentFrom === 'string') {
                if ((contentFrom.charAt(0) !== '#') && (contentFrom.charAt(0) !== '.')) {
                    contentFrom = '#' + contentFrom;
                }
                $cFrom = $( contentFrom );

            } else if ( (typeof contentFrom[0] !== false)  && contentFrom.length) { // case jQ-object
                $cFrom = contentFrom;
            } else {
                alert('Error in popup.js:prepareContent() -> unable to handle contentFrom');
                return; // error
            }

            // get content and cache in a variable:
            if (!this.lzyPopupContent) { // not yet cached
                var tmp = '';
                tmp = $cFrom.html();
                this.lzyPopupContent = tmp; // save original HTML

                // shield IDs in original:
                tmp = tmp.replace(/id=(['"])/g, 'id=$1lzyPopupInitialized-');
                $cFrom.html( tmp );
            }
            this.content = this.content + this.lzyPopupContent;
        } // contentFrom

        // content supplied as literal or by contentFrom:
        var cls = 'lzy-popup-wrapper';
        if (this.content) {
            // add popup HTML to DOM (at end of body element):
            var style = '';

            // if option 'anker' is active: need to modify CSS on wrapper:
            if (this.anker !== 'body') {
                style += 'position: absolute;';
            }

            this.popupClass += ' lzy-popup-transient';
            if (this.trigger !== true) {
                style += 'display: none;';
            }
            if (style) {
                style = ' style="' + style + '"';
            }

            if (this.wrapperClass) {
                cls += ' ' + this.wrapperClass;
            }
            var html = '<div id="' + parent.id + '" class="lzy-popup-bg lzy-popup-' + this.inx + this.popupClass + '"' + style + data +'>\n' +
                '    <div class="' + cls + '" role="dialog"><div role="document">\n'+ header +
                '      <div class="lzy-popup-container">\n' +
                this.content + this.buttonHtml +
                '    </div></div>\n      </div>\n' +
                '</div>\n';
            $(this.anker).append( html );
            this.$pop = $( '#' + parent.id);


        } else if (this.contentRef) {       // content as reference to DOM element:
            if (typeof this.contentRef === 'object') {
                $popupElem = this.contentRef;
            } else {
                if ((this.contentRef.charAt(0) !== '#') && (this.contentRef.charAt(0) !== '.')) {
                    this.contentRef = '#' + this.contentRef;
                }
                $popupElem = $(this.contentRef);
            }

            var i = $('[data-popup-inx]', $popupElem).attr('data-popup-inx');
            if (typeof i !== 'undefined') {
                this.inx = parseInt( i );
            } else {
                i = this.inx;
            }

            if (!$popupElem.hasClass('lzy-popup-bg')) {
                id = $popupElem.attr('id');
                if (typeof id !== 'undefined') {
                    parent.id = id;
                } else {
                    $popupElem.attr('id', parent.id );
                }
                $popupElem.addClass('lzy-popup-bg').addClass('lzy-popup-' + this.inx);
                if (this.popupClass) {
                    $popupElem.addClass(this.popupClass);
                }
                var $popupContent = $('> div', $popupElem);
                cls = ' lzy-popup-wrapper-ref';
                if (this.wrapperClass) {
                    cls += ' ' + this.wrapperClass;
                }

                $popupContent.wrap('<div class="' + cls + '"' + data + '>');
                $popupContent.parent().prepend( header );
                $popupContent.wrap('<div class="lzy-popup-container">');
                $popupContent.append(this.buttonHtml);
            }
            this.$pop = $('.lzy-popup-wrapper', $popupElem);

        } else {    // if no content specified
            console.log('Error in lzyPopup: argument "text" or "contentFrom" required.');
            return false;
        }
    }; // prepareContent



    this.setupTrigger = function () {
        if (!this.trigger) {
            return;
        }
        if (this.trigger === true) {
            this.open();

        } else {
            if (this.triggerInitialized) {
                return;
            }

            var id = '#' + parent.id;
            var $triggerElem = $( this.trigger );
            if (!$triggerElem.length) {
                alert(`Error in lzyPopup: DOM element '${this.trigger}' not found.`);
            } else {
                $triggerElem.attr('data-lzy-inx', this.inx);
            }
            $('body').on(this.triggerEvent, this.trigger , function( e ){
                e.preventDefault();
                var inx = this.dataset.lzyInx;
                var obj = popupInstance[ inx ];
                if ($( id ).length) {
                    obj.open();
                } else {
                    obj.execute();
                }
            });
            this.triggerInitialized = true;
        }
    }; // setupTrigger



    this.setupKeyHandler = function () {
        $('body').on('keyup', function(e) {
            const key = e.which;
            if(key === 27) {                // ESC
                lzyPopupClose( this );
            }
        });
    }; // setupKeyHandler



    this.initDraggable = function () {
        if (typeof $.ueSetGlobalCb === 'undefined') {
            alert('Error: popup option "draggable" initiated, but module EVENT_UE not loaded');
        }
        this.lastX = 0;
        this.lastY = 0;
        $( '.lzy-popup-header > div', parent.$pop )
            .bind( 'udragstart', function(e) {
                e.stopPropagation();
            } )
            .bind( 'udragmove',  function(e) {
                e.stopPropagation();
                parent.$pop.css({ left: e.px_tdelta_x * -1, top: e.px_tdelta_y * -1 });
            })
            .bind( 'udragend',   function(e) {
                e.stopPropagation();
                parent.lastX -= e.px_tdelta_x;
                parent.lastY -= e.px_tdelta_y;
                parent.$pop.css({ transform: 'translate('+ parent.lastX +'px, ' + parent.lastY + 'px)', top:0,left:0 });
            })
            .bind( 'click',      function(e) {
                e.stopPropagation();
            })
        ;
    };



    this.renderHelp = function() {
        var help = '\t<dl>\n' +
            '\t<dt>text:</dt>\n' +
            '\t\t<dd>Text to be displayed in the popup (for small messages, otherwise use contentFrom). "content" works as synonym for "text". </dd>\n' +
            '\t<dt>contentFrom:</dt>\n' +
            '\t\t<dd>Selector that identifies content which will be imported and displayed in the popup (example: "#box"). </dd>\n' +
            '\t<dt>contentRef:</dt>\n' +
            '\t\t<dd>Selector that identifies content which will be wrapped and popped up.<br>\n' +
            '\t\t    (rather for internal use - event handlers are preserved, but usage is a bit tricky). </dd>\n' +
            '\t<dt>triggerSource:</dt>\n' +
            '\t\t<dd>If set, the popup opens upon activation of the trigger source element (example: "#btn"). </dd>\n' +
            '\t<dt>triggerEvent:</dt>\n' +
            '\t\t<dd>[click, right-click, dblclick, blur] Specifies the type of event that shall open the popup (default: click). </dd>\n' +
            '\t<dt>closeButton:</dt>\n' +
            '\t\t<dd>Specifies whether a close button shall be displayed in the upper right corner (default: true). </dd>\n' +
            '\t<dt>closeOnBgClick:</dt>\n' +
            '\t\t<dd>Specifies whether clicks on the background will close the popup (default: true). </dd>\n' +
            '\t<dt>buttons:</dt>\n' +
            '\t\t<dd>(Comma-separated-list of button labels) Example: "Cancel,Ok". </dd>\n' +
            '\t<dt>callbacks:</dt>\n' +
            '\t\t<dd>(Comma-separated-list of function names) Example: "onCancel,onOk". </dd>\n' +
            '\t<dt>id:</dt>\n' +
            '\t\t<dd>ID to be applied to the popup element. (Default: lzy-popup-N)</dd>\n' +
            '\t<dt>buttonClass:</dt>\n' +
            '\t\t<dd>(Comma-separated-list of classes). Will be applied to buttons defined by "buttons" argument.</dd>\n' +
            '\t<dt>wrapperClass:</dt>\n' +
            '\t\t<dd>Class(es) applied to wrapper around Popup element. </dd>\n' +
            '\t<dt>anker:</dt>\n' +
            '\t\t<dd>(selector) If defined, popup will be placed inside elemented selected by "anker". (Not available for "contentRef"). Default: "body". </dd>\n' +
            '\t</dl>\n';
        return help;
    }; // renderHelp



    this.open = function () {
        const $popBg = this.$pop.parent();
        if (this.header) {
            $('.lzy-popup-header > div', $popBg).text(this.header);
        }
        this.$pop.parent().show();
        $('body').addClass('lzy-no-scroll');
    }; // open



    this.init( options );
} // LzyPopup




function lzyPopup( options, popupHash ) {
    const hash = md5( JSON.stringify( options ) );
    popupHash = (typeof popupHash !== 'undefined')? popupHash: hash;
    if (typeof popupInstance[hash] === 'undefined') {
        popupInstance[hash] = new LzyPopup( options, popupHash );
        popupInstance[popupInx] = popupInstance[hash];
        popupInx++;
    } else {
        popupInstance[hash].init( options );
    }
} // lzyPopup




function lzyConfirm( prompt ) {
    var options = {};
    return new Promise(function(resolve, reject) {
        options.text = prompt;
        options.onConfirm = true;
        options.onCancel = true;
        options.closeOnBgClick = false;
        $('body').on('click','.lzy-popup-btn-confirm', function () {
            lzyPopupClose();
            $('body').off('click','.lzy-popup-btn-confirm').off('click','.lzy-popup-btn-cancel');
            resolve( true );
        });
        $('body').on('click','.lzy-popup-btn-cancel', function () {
            lzyPopupClose();
            $('body').off('click','.lzy-popup-btn-confirm').off('click','.lzy-popup-btn-cancel');
            reject( '' );
        });
        lzyPopup( options );
    });
} // lzyPopup




function lzyPopupClose( that ) {
    var $popup = null;
    $('body').removeClass('lzy-no-scroll');

    if (typeof that === 'undefined') {
        $popup = $('.lzy-popup-bg');
    } else {
        $popup = $(that);
        if ( !$popup.hasClass('lzy-popup-bg') ) {
            $popup = $popup.closest('.lzy-popup-bg');
        }
        if ( !$popup.length ) {
            $popup = $('.lzy-popup-bg');
        }
    }
    $popup.each(function () {
        if ($(this).hasClass('lzy-popup-transient')) {
            $(this).remove();
        } else {
            $(this).hide();
        }
    });
} // lzyPopupClose




$('body')
    .on('click', '.lzy-popup-close-button', function () {
        lzyPopupClose(this);
    })
    .on('click', '.lzy-popup-bg.lzy-close-on-bg-click', function (e) {
        const $el = $(e.target);
        if ( $el.hasClass('lzy-popup-container') ||
            $el.closest('.lzy-popup-container').length ||
            $el.closest('.lzy-popup-wrapper').length ) {
            return;
        }
        lzyPopupClose();
    });



(function( $ ){
    $.fn.lzyPopup = function( options ) {
        var $this = $(this);
        var sel = $this.attr('id');
        if (typeof sel !== 'undefined') {
            sel = '#' + sel;
        } else {
            sel = $this.attr('class');
            if (typeof sel !== 'undefined') {
                sel = '.' + sel.trim().replace(/(\s+)/g, '.');
            }
        }
        if (typeof options === 'undefined') {
            options = {};
        } else if (typeof options === 'string') {
            if (options === 'show') {
                options = {};
            } else if (options === 'hide') {
                lzyPopupClose( this );
                return;
            }
        }
        if (typeof options.contentRef !== 'undefined') {
            options.contentRef = $this;
        } else {
            options.contentFrom = sel;
        }
        lzyPopup( options );
        return this;
    }; // $.fn.lzyPopup



    $.fn.lzyPopupTrigger = function( options ) {
        var $this = $(this);

        if (typeof options === 'undefined') {
            options = {};
        }

        // if content not defined, try to get it from title attribute:
        if ((typeof options.text === 'undefined') &&
            (typeof options.contentFrom === 'undefined') &&
            (typeof options.contentRef === 'undefined')) {
            var str = $this.attr('title');
            if (typeof str !== 'undefined') {
                options.text = str;
            }
        }

        var sel = $this.attr('id');
        if (typeof sel !== 'undefined') {
            sel = '#' + sel;
        } else {
            sel = $this.attr('class');
            if (typeof sel !== 'undefined') {
                sel = '.' + sel.trim().replace(/(\s+)/g, '.');
            }
        }
        options.trigger = sel;
        lzyPopup( options );
        return this;
    }; // $.fn.lzyPopupTrigger

})( jQuery );

