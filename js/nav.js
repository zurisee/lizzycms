var largeScreenClasses = '';


(function ( $ ) {
    if (!$('#lzy').length) {
        alert("Warning: '#lzy'-Id missing within this page \n-> Lizzy's nav() objects not working.");
    }
    largeScreenClasses = $('.lzy-primary-nav .lzy-nav').attr('class');

    var isSmallScreen = ($(window).width() < screenSizeBreakpoint);
    adaptMainMenuToScreenSize(isSmallScreen);

    $(window).resize(function(){
        var w = $(this).width();
        var isSmallScreen = (w < screenSizeBreakpoint);
        adaptMainMenuToScreenSize(isSmallScreen);
        setHightOnHiddenElements();
    });


    if ($('.lzy-nav-collapsed, .lzy-nav-collapsible, .lzy-nav-top-horizontal').length) {
        setHightOnHiddenElements();
    }


    // menu button in mobile mode:
    $('#lzy-nav-menu-icon').click(function(e) {
        e.stopPropagation();
        operateMobileMenuPane();
    });


    // mouse:
    $('.lzy-has-children > * > .lzy-nav-arrow').dblclick(function(e) {        // double click -> open all
        e.stopPropagation();
        var $parentLi = $(this).closest('.lzy-has-children');
        toggleAccordion($parentLi, true, true);
        return false;
    });
    $('.lzy-has-children > a > .lzy-nav-arrow').click(function(e) {  // click arrow
        e.stopPropagation();
        var $parentLi = $(this).closest('.lzy-has-children');
        $parentLi.removeClass('lzy-hover');
        var deep = ($('.lzy-nav-top-horizontal').length !== 0);
        toggleAccordion($parentLi, deep);
        return false;
    });

    // hover:
   $('.lzy-nav-hoveropen .lzy-has-children').hover(
        function() {    // mouseover
            var $this = $(this);
            if ($('body').hasClass('touch') || $this.hasClass('lzy-open')) {
                return;
            }
            if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) { // top-nav:
                if ($this.hasClass('lzy-lvl1')) {
                    $this.addClass('lzy-hover');
                    openAccordion($this, true);
                }
            } else {        // side-nav or sitemap
                $this.addClass( 'lzy-hover' );
                openAccordion($this);
            }
        },

        function() {     // mouseout
           var $this = $(this);
            if ($('body').hasClass('touch')) {  // no-touch only
                return;
            }
            if ($this.hasClass('lzy-open')) {
               $this.removeClass('lzy-hover');
               return;
           }
           if ($this.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {  // top-nav
               if ($this.hasClass('lzy-lvl1')) {
                   setTimeout(function () {
                       $this.removeClass('lzy-hover');
                       closeAccordion($this, true);
                   }, 400);
               }
           } else {        // side-nav or sitemap
               $this.removeClass( 'lzy-hover' );
               closeAccordion($this);
           }
        }
   );


    // activate animations now (avoiding flicker)
    $('.lzy-nav-animated').each(function() {
        $( this ).removeClass('lzy-nav-animated').addClass('lzy-nav-animation-active');
    });

    if ($('body').hasClass('lzy-small-screen')) {
        operateMobileMenuPane( false );
    }

    setupKeyboardEvents();
    initPrimaryNav();
    openCurrentElement();
}( jQuery ));




// called from HTML <a onclick:
function handleAccordion( elem, ev ) {
    ev.stopPropagation();
// console.log('handleAccordion');
    if ($( 'body' ).hasClass('touch') || $('html').hasClass('touchevents')) {
        var $parentLi = $(elem).parent();
        if (!$parentLi.closest('.lzy-nav-top-horizontal').length) { // only active for horizontal nav:
            return true;
        }
        if ($parentLi.hasClass('lzy-lvl1')) {
            toggleAccordion($parentLi, true);
            return false;
        }
    }
} // handleAccordion





function initPrimaryNav() {
    // return;
    if ($('.lzy-large-screen .lzy-primary-nav .lzy-nav').hasClass('lzy-nav-collapsed')) {
        $('.lzy-large-screen .lzy-primary-nav .lzy-has-children').each(function () {
            closeAllAccordions($( this ), true);
            return;
            // var $elem = $(this);
            // var $nextDivs = $('div', $elem);
            // $elem.removeClass('lzy-open lzy-hover');
            // $('li', $elem).removeClass('lzy-open');       // close all li below parent li
            // $nextDivs.attr({'aria-hidden': 'true'});        // make all sub-elements hidden
            // $('a', $nextDivs).attr({'tabindex': '-1'}); // make sub-menus un-focusable
        });
    } else {
        $('.lzy-large-screen .lzy-primary-nav .lzy-nav').each(function() {
            if (!$( this ).hasClass('lzy-nav-collapsed')) {
                openAccordion($( this ), true, true);
            }
        });
    }
} // initPrimaryNav




function toggleAccordion($parentLi, deep, newState) {
    if (typeof newState === 'undefined') {
        var expanded = $parentLi.hasClass('lzy-open');
    } else {
        var expanded = !newState;
    }

    if ($parentLi.closest('.lzy-nav').hasClass('lzy-nav-top-horizontal')) {
        closeAllAccordions($parentLi, deep, true);

    } else if (expanded) { // -> close
        closeAccordion($parentLi, deep, true);

    }
    if (!expanded) { // -> open
        openAccordion($parentLi, deep, true);
        if ($( 'body' ).hasClass('touch')) {
            $('body').click(function (e) {
                e.stopPropagation();
                $('body').off('click');
                closeAccordion($parentLi, deep, true);
            });
        }
        return true;
    }
    return false;
} // toggleAccordion



function openAccordion($parentLi, deep, setOpenCls) {
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.addClass('lzy-open');
    }
    $('> a', $parentLi).attr({'aria-expanded': 'true'});  // make focusable
    if (deep === true) {
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        if (typeof setOpenCls !== 'undefined') {
            $('.lzy-has-children', $parentLi).addClass('lzy-open');       // parent li
        }
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable

    } else {
        $( '> div', $parentLi ).attr({'aria-hidden': 'false'});
        $('> div > ol > li > a', $parentLi).attr({'tabindex': ''});  // make focusable
    }
} // openAccordion




function closeAccordion($parentLi, deep, setOpenCls) {
    var $nextDiv = $('> div', $parentLi);
    if (deep === true) {
        $nextDiv = $('div', $parentLi);
    }
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $nextDiv.attr({'aria-hidden': 'true'});        // next div
    if (typeof setOpenCls !== 'undefined') {
        $parentLi.removeClass('lzy-open');       // parent li
        $('.lzy-open', $parentLi).removeClass('lzy-open');       // parent li
    }
    $('li > a', $parentLi).attr('tabindex', '-1');             // make un-focusable
} // closeAccordion




// all accordions includes siblings:
function closeAllAccordions($parentLi, setOpenCls) {
    var $nav = $parentLi.closest('.lzy-nav');
    $('> a', $parentLi).attr({'aria-expanded': 'false'});  // make focusable
    $('a', $parentLi).attr({'tabindex': ''});  // make focusable
    $('.lzy-has-children', $nav).each(function() {
        var $elem = $(this);
        var $nextDivs = $('div', $elem);
        if (typeof setOpenCls !== 'undefined') {
            $elem.removeClass('lzy-open');
            $('li', $elem ).removeClass('lzy-open');            // all li below parent li
        }
        $nextDivs.attr({'aria-hidden':'true' });        // next div
        $('a', $nextDivs).attr('tabindex', '-1');            // make un-focusable
    });
} // closeAllAccordions




function openCurrentElement() {
    $('.lzy-nav.lzy-nav-open-current .lzy-active').each(function () {
        $parentLi = $( this );
        $parentLi.addClass('lzy-open');
        $( 'div', $parentLi ).attr({'aria-hidden': 'false'});        // next div
        $('a', $parentLi).attr({'tabindex': ''});  // set aria-expanded and make focusable
    })
} // openCurrentElement




function adaptMainMenuToScreenSize( smallScreen ) {
    if (smallScreen) {
        $('.lzy-primary-nav .lzy-nav')
            .removeClass('lzy-nav-top-horizontal lzy-nav-hover lzy-nav-colored lzy-nav-dark-theme')
            .addClass('lzy-nav-collapsed lzy-nav-open-current');

        if ($('.lzy-nav-small-tree').length) {
            openAccordion($('.lzy-primary-nav .lzy-has-children'), true, true); // open all
        } else {
            $('.lzy-primary-nav .lzy-active').each(function() {
                console.log( $(this) );
                openAccordion( $(this), false, true );
            });
        }

    } else {
        $('.lzy-primary-nav .lzy-nav').attr('class', largeScreenClasses);
        $('.lzy-primary-nav .lzy-has-children').removeClass('lzy-open');
        $('body').removeClass('lzy-nav-mobile-open');
    }
}




function operateMobileMenuPane( newState ) {
    var $nav = $( '.lzy-nav-wrapper' );
    var expanded = ($nav.attr('aria-expanded') === 'true');
    if (typeof newState != 'undefined') {
        expanded = !newState;
    }
    if (expanded) {
        $nav.attr('aria-expanded', 'false');
        $('body').removeClass('lzy-nav-mobile-open');
        $('.lzy-primary-nav .lzy-nav a').attr('tabindex', '-1');            // make un-focusable

    } else {
        $nav.attr('aria-expanded', 'true');
        $('body').addClass('lzy-nav-mobile-open');
        var $primaryNav = $('.lzy-primary-nav .lzy-nav');
        $('> ol > li > a', $primaryNav).attr('tabindex', '0');            // make un-focusable
    }
} // operateMobileMenuPane




function setHightOnHiddenElements() {
    if (!($('html').hasClass('touchevents') || $('body').hasClass('touch'))) {
        $('#lzy .lzy-nav-accordion .lzy-has-children, ' +
            '#lzy .lzy-nav-top-horizontal .lzy-has-children, ' +
            '#lzy .lzy-nav-collapsed .lzy-has-children, ' +
            '#lzy .lzy-nav-collapsible .lzy-has-children').each(function () {
            var h = $('>div>ol', this).height() + 20 + 'px';                // set height to optimize animation
            $('>div>ol', this).css('margin-top', '-' + h);
        });
    }
} // setHightOnHiddenElements




function setupKeyboardEvents() {
    // supports: left/right, up/down, space and home
    $('.lzy-nav a').keydown(function (event) {
        var keyCode = event.keyCode;
        event.stopPropagation();
        var $this = $(this);

        if (keyCode == 39) {            // right arrow
            event.preventDefault();
            toggleAccordion($this.parent(),false, true);

        } else if (keyCode == 37) {    // left arrow
            event.preventDefault();
            var expanded = $this.parent().hasClass('lzy-open');
            if (expanded) { // if open -> close
                toggleAccordion($this.parent(),false, false);
            } else {    // if already closed -> jump to parent element
                $('> a', $this.parent().parent().closest('li')).focus();
            }

        } else if (keyCode == 36) {    // home
            event.preventDefault();
            $('.lzy-lvl1:first-child a').focus();

        } else if (keyCode == 38) {    // up
            event.preventDefault();
            $.tabPrev();

        } else if (keyCode == 40) {    // down
            event.preventDefault();
            $.tabNext();

        } else if (keyCode == 32) {    // space
            event.preventDefault();
            toggleAccordion($this.parent(), true);
        }
    });
} // setupKeyboardEvents

