//--------------------------------------------------------------
// handle screen-size and resize
(function ( $ ) {
    if ($(window).width() < screenSizeBreakpoint) {
        $('body').addClass('lzy-small-screen');
    } else {
        $('body').addClass('lzy-large-screen');
    }

    $(window).resize(function(){
        var w = $(this).width();
        if (w < screenSizeBreakpoint) {
            $('body').addClass('lzy-small-screen').removeClass('lzy-large-screen');
        } else {
            $('body').removeClass('lzy-small-screen').addClass('lzy-large-screen');
        }
    });
}( jQuery ));



//--------------------------------------------------------------
function scrollIntoView( selector, container )
{
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



//--------------------------------------------------------------
function showMessage( txt )
{
    $('.MsgBox').remove();
    $('body').prepend( '<div class="MsgBox"><p>' + txt + '</p></div>' );
}



//--------------------------------------------------------------
function mylog(txt)
{
	console.log(txt);

	if ($('body').hasClass('debug')) {
        var $log = $('#lzy-log');
        if (!$log.length) {
            $('body').append("<div id='lzy-log-placeholder'></div><div id='lzy-log'></div>");
            $log = $('#lzy-log');
        }
        text = String(txt).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        $log.append('<p>'+timeStamp()+'&nbsp;&nbsp;'+text+'</p>').animate({ scrollTop: $log.prop("scrollHeight")}, 100);
	}

    if ($('body').hasClass('logging-to-server')) {
        serverLog(txt)
    }
} // mylog



//--------------------------------------------------------------
function serverLog(text)
{
    if (text) {
        $.post( systemPath+'_ajax_server.php?log', { text: text } );
    }
} // serverLog




//--------------------------------------------------------------
function isServerErrMsg(json)
{
    if (json.match(/^</)) {
        console.log('- response: ' + json.replace(/<(?:.|\n)*?>/gm, ''));
        return true;
    }
    console.log('- response: ' + json);
    return false;
} // isServerErrMsg




//--------------------------------------------------------------
function lzyReload( arg, url )
{
    var call = window.location.pathname.replace(/\?.*/, '');
    if (typeof url != 'undefined') {
        call = url.trim();
    }
    if (typeof arg != 'undefined') {
        call = call + arg;
    }
    console.log('initiating page reload: "' + call + '"');
    window.location.replace(call);
}



//--------------------------------------------------------------
function timeStamp()
{
	var now = new Date();
	var time = [ now.getHours(), now.getMinutes(), now.getSeconds() ];
	for ( var i = 1; i < 3; i++ ) {
		if ( time[i] < 10 ) {
			time[i] = "0" + time[i];
		}
	}
	return time.join(":");
} // timeStamp




//--------------------------------------------------------------
function htmlEntities(str)
{
    return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}




//--------------------------------------------------------------
// plug-in to get specified element selected
jQuery.fn.selText = function() {
    this.find('input').each(function() {
        if($(this).prev().length == 0 || !$(this).prev().hasClass('p_copy')) {
            $('<p class="p_copy" style="position: absolute; z-index: -1;"></p>').insertBefore($(this));
        }
        $(this).prev().html($(this).val());
    });
    var doc = document;
    var element = this[0];
    if (doc.body.createTextRange) {
        var range = document.body.createTextRange();
        range.moveToElementText(element);
        range.select();
    } else if (window.getSelection) {
        var selection = window.getSelection();
        var range = document.createRange();
        range.selectNodeContents(element);
        selection.removeAllRanges();
        selection.addRange(range);
    }
}
