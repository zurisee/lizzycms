"use strict";

var quickViewInx = 0;

function LzyQuickview() {}



LzyQuickview.prototype.init = function( $elem ) {
    $elem.each( function() {
        quickViewInx++;
        // let parent = this;
        var $this = $(this);
        let id = $this.attr('id');
        if (typeof id === 'undefined') {
            id = 'lzy-quickview-' + quickViewInx;
        }
        let lateImgLoading = $this.hasClass('lzy-late-loading');

        let qvSrc = $this.attr('data-qv-src');
        let qvWidth = $this.attr('data-qv-width');
        let qvHeight = $this.attr('data-qv-height');
        if ((typeof qvSrc === 'undefined') || (typeof qvWidth === 'undefined') || (typeof qvHeight === 'undefined')) {
            console.log('Error: data-attribute missing for ' + id);
            return;
        }

        // find srcset for inserting in quickview-overlay:
        let qvSrcset = $this.attr('srcset');
        if (typeof qvSrcset !== 'undefined') {
            if (lateImgLoading) {
                qvSrcset = ' data-srcset="' + qvSrcset + '"';
            } else {
                qvSrcset = ' srcset="' + qvSrcset + '"';
            }
        } else {
            qvSrcset = '';
        }

        // create quickview overlay:
        if (lateImgLoading) {
            $('body').append("<div id='" + id + "-quickview' class='lzy-quickview-overlay'><img class='lzy-laziest-load' data-src='" + qvSrc + "'" + qvSrcset + " width='" + qvWidth + "' height='" + qvHeight + "' aria-hidden='true' /><span class='sr-only'>This is only visual enhancement. No additional information is provided. Press Escape to go back.</span></div>");
        } else {
            $('body').append("<div id='" + id + "-quickview' class='lzy-quickview-overlay'><img src='" + qvSrc  + "'" + qvSrcset + " width='" + qvWidth + "' height='" + qvHeight + "' aria-hidden='true' /><span class='sr-only'>This is only visual enhancement. No additional information is provided. Press Escape to go back.</span></div>");
        }
    }); // each
}; // LzyQuickview.init




LzyQuickview.prototype.open = function( $elem ) {
    // if img embedded in A tag, don't quickview, instead open link directly
    if ( $elem.parent().prop('tagName') === 'A' ) {
        return;
    }

    let  id = $elem.attr('id');
    let  _id = '#' + id;
    let  $id = $( _id );
    let  _idQuickview = _id + '-quickview';
    let  $idQuickview = $( _idQuickview );
    let  $idQuickviewImg = $( _idQuickview + ' img' );

    let  vpWidth = $(window).width();
    let  vpHeight = $(window).height();
    let  padding = parseInt( Math.min(vpWidth, vpHeight) * 0.02);
    let  w = $id.width();
    let  h = $id.height();
    let  y = $id[0].getBoundingClientRect().top;
    let  x = $id[0].getBoundingClientRect().left;
    let  wOrig = parseInt($( _idQuickview + ' img' ).attr('width'));
    let  hOrig = parseInt($( _idQuickview + ' img' ).attr('height'));
    let  aRatio = hOrig / wOrig;
    let  wL = Math.min(wOrig, (vpWidth - 2*padding));
    let  hL = Math.min(hOrig, (vpHeight - 2*padding));
    if ((hL / wL) > aRatio) {
        hL = wL * aRatio;
    } else {
        wL = hL / aRatio;
    }
    let  xL = (vpWidth - wL) / 2;
    let  yL = (vpHeight - hL) / 2;
    $idQuickview.addClass('lzy-quickview-overlay-active').attr({ 'data-qv-x': x, 'data-qv-y': y, 'data-qv-w': w, 'data-qv-h': h });
    $idQuickviewImg.css({ left: x, top: y, width: w-10, height: h-20, zIndex: 9999 });
    $idQuickviewImg.animate({ width: wL, height: hL, left: xL, top: yL, opacity: 1 }, 200);

    // in late-loading mode: load fullscreen image only when invoked:
    if ($idQuickviewImg.hasClass('lzy-laziest-load')) {
        let  src = $idQuickviewImg.attr('data-src');
        if (typeof src !== 'undefined') {
            console.log('late loading image ' + $idQuickviewImg.attr('data-src'));
            $idQuickviewImg.attr({srcset: $idQuickviewImg.attr('data-srcset') }).removeAttr('data-srcset');
            $idQuickviewImg.attr({src: $idQuickviewImg.attr('data-src')}).removeAttr('data-src');
        }
    }
    $( 'body' ).keydown( function (e) {
        if (e.which === 27) {
            lzyQuickview.close();
            // qvClose();
        }
    });

}; // open



LzyQuickview.prototype.close = function() {
    $( '.lzy-quickview-overlay-active').each( function() {
        let  $this = $(this);
        let  $img = $( 'img', $this);
        let  x = $this.attr( 'data-qv-x' );
        let  y = $this.attr( 'data-qv-y' );
        let  w = $this.attr( 'data-qv-w' );
        let  h = $this.attr( 'data-qv-h' );
        $img.animate({ width: w-10, height: h-20, left: x, top: y }, 200);
        setTimeout( function() {
            $this.removeClass('lzy-quickview-overlay-active').attr('style', '');
            $img.css('opacity', 0).attr('style', '');
        }, 200);
    });
}; // close




var lzyQuickview = new LzyQuickview();


$.fn.quickview = function () {
    lzyQuickview.init( $( this ) );
}; // quickView


// open large Quickview image:
$('img.lzy-quickview').click(function() {
    lzyQuickview.open( $( this ) );
});


// set up close Quickview event handler:
$( 'body' ).on('click','.lzy-quickview-overlay, .lzy-quickview-overlay img', function () {	// click on image
    lzyQuickview.close();
});



// late-loading:
$('img.lzy-late-loading').each(function() {
    let  $this = $( this );
    console.log('late loading image ' + $this.attr('data-src'));
    $this.attr({src: $this.attr('data-src') }).removeAttr('data-src');
    $this.attr({srcset: $this.attr('data-srcset') }).removeAttr('data-srcset');
});

