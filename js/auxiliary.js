/*
 *  Lizzy's auxiliary functions
*/

var debug = false;
// handle screen-size and resize
(function ( $ ) {
    if ($(window).width() < screenSizeBreakpoint) {
        $('body').addClass('lzy-small-screen').removeClass('lzy-large-screen');
    } else {
        $('body').addClass('lzy-large-screen').removeClass('lzy-small-screen');
    }

    $(window).resize(function(){
        let w = $(this).width();
        if (w < screenSizeBreakpoint) {
            $('body').addClass('lzy-small-screen').removeClass('lzy-large-screen');
        } else {
            $('body').removeClass('lzy-small-screen').addClass('lzy-large-screen');
        }
    });

    debug = ($('body.debug').length !== 0);
    if ( $('body.touch').length ) {
        $('html').removeClass('no-touchevents').addClass('touchevents');
    }
}( jQuery ));



function isValidEmail(email) {
    let re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
    return re.test(String(email).toLowerCase());
}




function execAjax(payload, cmd, doneFun, url) {

    if (typeof url === 'undefined') {
        url = appRoot + '_lizzy/_ajax_server.php';
    }
    url = appendToUrl(url, cmd);
    let json = '';
    if (payload) {
        json = JSON.stringify(payload);
    }
    let txt = json? json: 'no arguments';
    mylog('execAjax: ' + cmd + ' ( ' + txt + ' )', false);
    ajaxHndl = $.ajax({
        method: 'POST',
        url: url,
        data: payload
    })
        .done(function ( json ) {
        if (typeof doneFun === 'function') {
            doneFun( json );
        }
    });
} // execAjax




function execAjaxPromise(cmd, options, url) {
    return new Promise(function(resolve) {

        if (typeof url === 'undefined') {
            url = appRoot + '_lizzy/_ajax_server.php';
        }
        url = appendToUrl(url, cmd);
        $.ajax({
            method: 'POST',
            url: url,
            data: options
        })
            .done(function ( json ) {
                resolve( json );
            });
    });

} // execAjax




function scrollToBottom( sel ) {
    setTimeout(function() {
        if (typeof sel === 'undefined') {
            sel = '.lzy-scroll-to-bottom';
        }
        let $elem = $( sel );
        $elem.animate({
            scrollTop: $elem.get(0).scrollHeight + 10
        }, 500);
    }, 100);
} // scrollToBottom




function scrollIntoView( selector, container ) {
    if (typeof container !== 'undefined') {
        $( container ).animate({
            scrollTop: $( selector ).offset().top
        }, 500);

    } else {
        $('html, body').animate({
            scrollTop: $( selector ).offset().top
        }, 500);
    }
} // scrollIntoView




Date.prototype.addHours = function(h) {
    this.setTime(this.getTime() + (h*60*60*1000));
    return this;
}; // addHours




// === Message Box =================================
if ($('.lzy-msgbox').length) {
    setupMessageHandler(800);
}
function setupMessageHandler( delay) {
    setTimeout(function () {
        $('.lzy-msgbox').addClass('lzy-msg-show');
    }, delay);
    setTimeout(function () {
        $('.lzy-msgbox').removeClass('lzy-msg-show');
    }, 5000);
    $('.lzy-msgbox').click(function () {
        $(this).toggleClass('lzy-msg-show');
    }).dblclick(function () {
        $(this).hide()
    });
    lzyMsgInitialized = true;
} // setupMessageHandler



function showMessage( txt ) {
    $('.lzy-msgbox').remove();
    $('body').prepend( '<div class="lzy-msgbox"><p>' + txt + '</p></div>' );
    setupMessageHandler(0);
} // showMessage




function appendToUrl(url, arg) {
    if (!arg) {
        return url;
    }
    arg = arg.replace(/^[\?&]/, '');
    if (url.match(/\?/)) {
        url = url + '&' + arg;
    } else {
        url = url + '?' + arg;
    }
    return url;
} // appendToUrl




function mylog(txt, notDebugOnly ) {
    notDebugOnly = ((typeof notDebugOnly === 'undefined') || notDebugOnly); // default true

    if ((typeof txt === 'string') && txt.match(/xdebug-error/)) {
        txt = txt.replace(/<[^>]*>?/gm, '');
    }

    if (debug || notDebugOnly) {
        console.log(txt);
    }

	if ( debug ) {
        let $log = $('#lzy-log');
        if (!$log.length) {
            $('body').append("<div id='lzy-log-placeholder'></div><div id='lzy-log'></div>");
            $log = $('#lzy-log');
        }
        text = String(txt).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        $log.append('<p>'+timeStamp()+'&nbsp;&nbsp;'+text+'</p>').animate({ scrollTop: $log.prop("scrollHeight")}, 100);
	}

    if ($('body').hasClass('logging-to-server')) {
        serverLog(txt);
    }
} // mylog




function serverLog(text, file) {
    file = (typeof file !== 'undefined')? file: '';
    if (text) {
        $.post( systemPath+'_ajax_server.php?log', { text: text, file: file } );
    }
} // serverLog




function isServerErrMsg(json) {
    if (json.match(/^</)) {
        console.log('- response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
        return true;
    }
    if ( json ) {
        console.log('- response: ' + json);
    }
    return false;
} // isServerErrMsg




function lzyReload( arg, url ) {
    let call = window.location.pathname.replace(/\?.*/, '');
    if (typeof url !== 'undefined') {
        call = url.trim();
    }
    if (typeof arg !== 'undefined') {
        call = appendToUrl(call, arg);
    }
    console.log('initiating page reload: "' + call + '"');
    window.location.replace(call);
} // lzyReload




function time() {
    const d = new Date();
    return d.getTime();
} // time




function timeStamp( long ) {
	const now = new Date();
	const time = [ now.getHours(), now.getMinutes(), now.getSeconds() ];
	for ( let i = 0; i < 3; i++ ) {
		if ( time[i] < 10 ) {
			time[i] = '0' + time[i];
		}
	}
	let out = time.join(':');
	if (typeof long !== 'undefined') {
        let day = [ now.getFullYear(), now.getMonth() + 1, now.getDate() ];
        for ( i = 1; i < 3; i++ ) {
            if ( day[i] < 10 ) {
                day[i] = '0' + day[i];
            }
        }
        out = day.join('.') + ' ' + out;
    }
	return out;
} // timeStamp




function timeToStr( UNIX_timestamp ){
    if (typeof UNIX_timestamp === 'undefined') {
        let a = new Date();
    } else {
        let a = new Date(UNIX_timestamp * 1000);
    }
    let months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    let year = a.getFullYear();
    let month = months[a.getMonth()];
    let date = a.getDate();
    let hour = a.getHours();
    let min = a.getMinutes();
    let sec = a.getSeconds();
    let time = date + ' ' + month + ' ' + year + ' ' + hour + ':' + min + ':' + sec ;
    return time;
}




function htmlEntities(str)
{
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}





// plug-in to get specified element selected
//  Usage: $('selector').selText();
jQuery.fn.selText = function() {
    this.find('input').each(function() {
        if($(this).prev().length === 0 || !$(this).prev().hasClass('p_copy')) {
            $('<p class="p_copy" style="position: absolute; z-index: -1;"></p>').insertBefore($(this));
        }
        $(this).prev().html($(this).val());
    });
    let doc = document;
    let element = this[0];
    if (doc.body.createTextRange) {
        let range = document.body.createTextRange();
        range.moveToElementText(element);
        range.select();
    } else if (window.getSelection) {
        let selection = window.getSelection();
        let range = document.createRange();
        range.selectNodeContents(element);
        selection.removeAllRanges();
        selection.addRange(range);
    }
};
