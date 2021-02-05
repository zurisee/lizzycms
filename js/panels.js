/*

    Inspired by: https://www.barrierefreies-webdesign.de/knowhow/tablist/tabpanel-links-tabindex.html

*/

"use strict";


if (typeof window.widthThreshold === 'undefined') {
    window.widthThreshold = 480;
}

var panelWidgetInstance = 1;    // instance number


function LzyPanels()
{
    this.panelWidgetInstance = panelWidgetInstance;
    this.to = null;
    this.closeButton = ''; //ToDo: implement tab-close button



    this.init = function( widgetSelector, preOpen ) {
        this.initializePanel( widgetSelector, preOpen );
        this.setPanelHeights();
        this.setupEvents();
        this.openRequestedPanel();
        var parent = this;

        $( window ).resize( function() {
            parent.onResize( false, parent );
        });
        this.onResize( true, this );

        this.scrollToWidget();
    };



    this.setupCloseButtonHandler = function() {
        var parent = this;
        if (this.closeButton) {
            $('.lzy-panel-close-btn').unbind('click');
            $('.lzy-panel-close-btn').click(function() {
                var $thisLi = $(parent).closest('li');
                var $next = $thisLi.next();
                if (!$next.length) {
                    $next = $thisLi.prev();
                }
                if (!$next.length) {
                    return;
                }
                var thisInx = ($thisLi.attr('id').match(/\d+/))[0];
                var nextInx = ($next.attr('id').match(/\d+/))[0];

                setTimeout(function() {
                    parent.openPanel('#lzy-panel-id' + nextInx);
                    $thisLi.remove();
                    $('#lzy-panel-id' + thisInx).remove();
                    parent.updateAriaPosInfo();
                }, 10);
            });
        }
    }; // setupCloseButtonHandler



    this.initializePanel = function( widgetSelector, preOpen ) {
        var $widgets = $( widgetSelector );
        var parent = this;

        // loop over panels:
        $widgets.each(function() {
            var $thisWidget = $( this ); // this panel div
            if ($thisWidget.attr('data-lzy-panels')) {
                return;
            }
            if (widgetSelector.substr(0,1) !== '#') {
                $thisWidget.attr('id', 'lzy-panels-widget' + parent.panelWidgetInstance);
            } else {
                $thisWidget.addClass('lzy-panels-widget');
            }
            $thisWidget.addClass('lzy-panels-widget lzy-panels-widget' + parent.panelWidgetInstance);

            var panels = [];
            var i = 0;

            // loop over panel content:
            $('> *', $thisWidget).each(function() {
                var $thisPanel = $( this );
                var _i = (parent.panelWidgetInstance*100 + i + 1).toString();

                // 1st elem -> Tab header -> save and remove from DOM
                var $hdr = $('> *:first-child', $thisPanel);
                panels[i] = $hdr.html();
                $hdr.remove();

                var origPanelClass = $thisPanel.attr('class');
                $('<!-- === panel page '+ (i+1) + ': ' + origPanelClass + ' ==== -->').insertBefore( $thisPanel );
                // convert original div into panel-pale elem:
                $thisPanel
                    .attr('id', 'lzy-panel-id' + _i)
                    .addClass('lzy-panel-page')
                    .attr('role', 'tabpanel')
                    .attr('tabindex', '-1')
                    .attr('aria-hidden', 'true')
                    .attr('aria-selected', 'false')
                    .attr('aria-expanded', 'false')
                    .attr('aria-labelledby', 'lzy-tabs-mode-panel-header-panel-id' + _i)
                ;

                // wrap content in lzy-panel-body-wrapper:
                var id = 'lzy-panel-body-wrapper' + _i;
                $thisPanel
                    .wrapInner('<div class="lzy-panel-inner-wrapper" style="margin-top: -100vh;">')
                    .wrapInner('<div id="'+id+'" class="lzy-panel-body-wrapper" aria-labelledby="lzy-panel-controller' + _i+'" role="region">')
                ;

                // insert accordion header:
                $thisPanel.prepend('<div class="lzy-accordion-mode-panel-header"><a id="lzy-panel-controller' + _i+'" href="#lzy-panel-id' + _i+'" class="lzy-panel-link" aria-controls="lzy-panel-body-wrapper' + _i+'" aria-expanded="false">'+panels[i]+'</a></div>')
                i++;
            });
            $thisWidget.attr('data-lzy-panels', panels.length);

            var header = '';
            // if (typeof parent.closeButton === 'undefined') {
            //     parent.closeButton = '';
            // }

            // create tabs header row:
            for (i = 0; i < panels.length; ++i) {
                var hdrText = panels[i];
                var _i = (panelWidgetInstance*100 + i + 1).toString();
                var tabindex = (i === 0) ? '0' : '-1';
                var aria = ' aria-setsize="' + panels.length + '" aria-posinset="' + (i+1) + '" aria-selected="false" aria-controls="lzy-panel-id' + _i + '"';

                header += '\t\t<li id="lzy-tabs-mode-panel-header-id' + _i +
                    '" class="lzy-tabs-mode-panel-header" role="tab" tabindex="'+ tabindex +'"' + aria + '><div>' + hdrText +
                    '</div>' + parent.closeButton + '</li>\n';
            }
            header = '\n\n<!-- === lzy-tabs-mode headers ==== -->\n\t<ul class="lzy-tabs-mode-panels-header-list" role="tablist">\n' + header + '\t</ul>\n\n';

            $thisWidget.prepend(header);
            if (preOpen) {
                parent.openPanel( '#lzy-panel-id' + (panelWidgetInstance*100 + parseInt(preOpen)));
            }
            if ($thisWidget.hasClass('lzy-accordion')) {
                setTimeout( function() {
                    $('.lzy-panel-inner-wrapper', $thisWidget).css('transition-duration', '0.4s');
                }, 400);
            }
        });
        this.setupCloseButtonHandler();
    }; // initializePanel


    this.updateAriaPosInfo = function() {
        var nPanels = $('.lzy-panel-page').length;
        var j = 1;
        $('.lzy-tabs-mode-panels-header-list li').each(function() {
            $(this)
                .attr('aria-posinset', j++)
                .attr('aria-setsize', nPanels);
        });
        return nPanels;
    }; // updateAriaPosInfo



    this.cloneTab = function( id ) {
        var oldN = this.extractIdNumber(id);
        var id1 = '#lzy-panel-id' + extractIdNumber(id);
        var newN = 0;
        $('.lzy-panel-page').each(function() {
            var id2 = $( this ).attr('id');
            var n2 = this.extractIdNumber( id2 );
            newN = Math.max(newN, n2);
        });

        newN = parseInt( newN ) + 1;
        var newId = 'lzy-panel-id' + newN;
        var $thisWidget = $( id1 ).closest('.lzy-panels-widget');
        var $newPanel = $( id1 ).clone();
        $newPanel.attr('id', newId)
            .attr('aria-hidden', 'true')
            .attr('aria-selected', 'false')
            .attr('aria-labelledby', 'lzy-tabs-mode-panel-header-panel-id' + newN)
            .removeClass('lzy-panel-page-open')
        ;
        $('.lzy-panel-link', $newPanel)
            .attr('id', 'lzy-panel-controller' + newN)
            .attr('href', '#lzy-panel-id' + newN)
            .attr('aria-controls', 'lzy-panel-body-wrapper101' + newN)
        ;

        $('.lzy-panel-body-wrapper', $newPanel)
            .attr('id', 'lzy-panel-body-wrapper' + newN)
            .attr('aria-labelledby', 'lzy-panel-controller' + newN)
        $thisWidget.append( $newPanel );


        var $newH = $('#lzy-tabs-mode-panel-header-id' + oldN).clone();
        $newH
            .attr('id', 'lzy-tabs-mode-panel-header-id' + newN)
            .attr('aria-controls', 'lzy-panel-id' + newN)
            .attr('aria-selected', 'false')
        // .attr('aria-posinset', nPanels)
        ;
        $('.lzy-tabs-mode-panels-header-list', $thisWidget).append( $newH );

        this.updateAriaPosInfo();
        this.setupTabsHeaderEvents();
        this.setupCloseButtonHandler();
        this.setupAccordionHeaderEvents();

        this.setPanelHeights();

        this.operatePanel('#lzy-panel-id' + newN);
    }; // cloneTab


    this.setupEvents = function() {
        this.setupTabsHeaderEvents();
        this.setupAccordionHeaderEvents();
        this.setupKeyboardEvents();
    }; // setupEvents



    this.setupTabsHeaderEvents = function() {
        var parent = this;
        // click on tab header -> open tab:
        $('.lzy-tabs-mode-panel-header').unbind('click');
        $('.lzy-tabs-mode-panel-header').click(function() {
            var id = '#' + $( this ).attr('aria-controls');
            parent.operatePanel( id, true);
        });

        // show close button upon leaving tab header:
        $('.lzy-tabs-mode-panel-header').unbind('mouseleave');
        $('.lzy-tabs-mode-panel-header').mouseleave( function() {
            if (($( this ).attr('aria-selected') === 'true') && ($('.lzy-tabs-mode-panel-header').length > 1)) {
                $('.lzy-panel-close-btn', $( this )).show();
            }
        });
    }; // setupTabsHeaderEvents



    this.setupAccordionHeaderEvents = function() {
        var parent = this;
        var mousedown = false;
        var $accordionHeaders = $('.lzy-accordion-mode-panel-header');
        $accordionHeaders.unbind('mousedown');
        $accordionHeaders.on('mousedown', function() {
            mousedown = true;
        });
        $accordionHeaders.unbind('focusin');
        $accordionHeaders.on('focusin', function() {
            if(!mousedown) {
                var id = $('a', $( this )).attr('href');
                parent.operatePanel( id, false);
                return;
            }
            mousedown = false;
        });
        $accordionHeaders.unbind('click');
        $accordionHeaders.click(function(e) {
            e.preventDefault();
            mousedown = false;
            var id = $('a', $( this )).attr('href');
            parent.operatePanel( id, false);
        });
    }; // setupAccordionHeaderEvents



    this.operatePanel = function( id, tabClicked) {
        var oneOpenOnly = ($(id).closest('.lzy-panels-widget.one-open-only').length > 0);

        if (tabClicked) {                   // Click/focus on Tab
            // close all, open id
            var tabsHdrId = id.replace(/panel-/, 'lzy-tabs-mode-panel-header-');
            var wasOpen = ($(tabsHdrId).attr('aria-selected') === 'true');
            if (!wasOpen) {
                this.closeAllPanels( id );
                this.openPanel( id );
            }

        } else {                            // click/focus on Accordion-header
            var wasOpen = $(id).hasClass('lzy-panel-page-open');
            if (wasOpen) {
                this.closePanel( id );
            } else {
                if (oneOpenOnly) {   // close all, open id
                    this.closeAllPanels(id);
                    this.openPanel( id );
                } else {            // close id
                    this.openPanel( id );
                }
            }
        }
    }; // operatePanel



    this.closeAllPanels = function( id ) {
        this.closeAllTabs( id );
        var $thisWidget = $(id).closest('.lzy-panels-widget');

        var $panelHdrs = $('.lzy-panel-page', $thisWidget);
        $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
        $panelHdrs.removeClass('lzy-panel-page-open');
    }; // closeAllPanels



    this.closeAllTabs = function( id )
    {
        var $thisWidget = $(id).closest('.lzy-panels-widget');
        var $tabsHdrs = $('.lzy-tabs-mode-panel-header', $thisWidget);
        $tabsHdrs.attr({'aria-selected': 'false', 'tabindex': -1});

        var $panelHdrs = $('.lzy-panel-page', $thisWidget);
        $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
    }; // closeAllTabs



    this.openPanel = function( id ) {
        this.closeAllTabs( id );
        this.openTab( id );

        var $panelHdr = $( id );
        $panelHdr.attr({ 'aria-hidden': 'false', 'aria-expanded': 'true', 'aria-selected':'true'});
        $panelHdr.addClass('lzy-panel-page-open');
    }; // openPanel



    this.openTab = function( id ) {
        var $tabsHdr = $( id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-') );
        $tabsHdr.attr({'aria-selected': 'true', 'tabindex': 0});
        $('.lzy-panel-close-btn').hide();

        var $panelHdr = $( id );
        $panelHdr.attr({ 'aria-hidden': 'false', 'aria-selected':'true'});
    }; // openTab



    this.closeTab = function( id ) {
        var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
        $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

        var $panelHdr = $( id );
        $panelHdr.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
    }; // closeTab



    this.closePanel = function( id ) {
        var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
        $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

        var $panelHdr = $( id );
        $panelHdr.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
        $panelHdr.removeClass('lzy-panel-page-open');

        var $thisWidget = $(id).closest('.lzy-panels-widget');
        var nSelected = $('.lzy-tabs-mode-panel-header[aria-selected=true]', $thisWidget).length;
        if (nSelected === 0) {
            id = id.substr(0,10) + '01';     // open first panel
            this.openTab( id );
        }
    }; // closePanel



    this.onResize = function( withoutDelay, parent ) {
        parent.setMode( withoutDelay );  // Accordion/Tabs or auto depending on window width
        parent.setPanelHeights();
    }; // onResize



    this.setMode = function( withoutDelay ) {
        var parent = this;
        $('.lzy-panels-widget').each(function() {
            var $this = $( this );
            if ($this.hasClass('lzy-accordion')) {
                $this.removeClass('lzy-tab-mode');

            } else if ($this.hasClass('lzy-tabs')) {
                $this.addClass('lzy-tab-mode');

            } else {    // set automatically:
                if (parent.to) {
                    clearTimeout(parent.to);
                }
                if ( withoutDelay === true ) {
                    parent.switchOnWidthThreshold( $this );
                } else {
                    parent.to = setTimeout(function() {
                        parent.switchOnWidthThreshold($this);
                    }, 250);
                }
            }
        });
    }; // setMode



    this.switchOnWidthThreshold = function( $panelWidget ) {

        $panelWidget.addClass('lzy-tab-mode'); // accordian mode

        var windowWidth = $(window).width();
        var panelsH = $('.lzy-tabs-mode-panels-header-list', $panelWidget).outerHeight()-1;
        var panelElemH = $('.lzy-tabs-mode-panels-header-list li', $panelWidget).outerHeight();
        var threshold = parseInt(window.widthThreshold);

        if ((windowWidth < threshold) || (panelsH > panelElemH)) {
            $panelWidget.removeClass('lzy-tab-mode'); // narrow / accordian mode
        } else {
            $panelWidget.addClass('lzy-tab-mode');
        }
    }; // switchOnWidthThreshold



    this.setPanelHeights = function() {
        $('.lzy-panels-widget:not(.lzy-tab-mode) .lzy-accordion-mode-panel-header').each(function(e) {
            var $this = $(this);
            var idBody = '#'+$('a', $this).attr('aria-controls');
            var $innerWrapper = $(idBody + ' .lzy-panel-inner-wrapper');
            var h = $innerWrapper.outerHeight();
            $innerWrapper.css('margin-top', '-' + h + 'px');
        });
    }; // setPanelHeights



    this.setupKeyboardEvents = function() {
        // focus is on tab header -> switch between tabs:   left/right and home/end cursor keys
        $('.lzy-tabs-mode-panel-header').keydown( function( event ) {
            var keyCode = event.keyCode;
            var id = '#' + $( this ).attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
            id = id.substr(0, id.length-2);
            var idN = parseInt($( this ).attr('id').substr(-2));
            var idN1 = null;
            var id1 = null;

            if (keyCode === 39) {    // right arrow
                event.preventDefault();
                idN1 = (idN + 1);
                idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
                id1 = id + idN1;
                if (!$( id1 ).length) {
                    id1 = id + '01';
                }
                this.openPanel( id1 );
                $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

            } else if (keyCode === 37) {    // left arrow
                event.preventDefault();
                if (idN > 1) {
                    idN1 = (idN - 1);
                    idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
                    id1 = id + idN1;
                } else {
                    $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
                    id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
                }
                this.openPanel( id1 );
                $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

            } else if (keyCode === 36) {    // home key
                event.preventDefault();
                id1 = id + '01';
                this.openPanel( id1 );
                $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

            } else if (keyCode === 35) {    // left arrow
                event.preventDefault();
                $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
                id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
                this.openPanel( id1 );
                $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

            }
        });

        /*  To Do: add support for ^left/^up, ^right/^down when inside panel pages

            // focus is inside panel page -> switch between pages: ^left/^up, ^right/^down
            $('.lzy-panel-inner-wrapper').keydown( function( event ) {
                var keyCode = event.keyCode;
                var id = $( this ).closest('.lzy-panel-page').attr('id');
                console.log(id + ' : ' + keyCode);
                if ((event.ctrlKey || macKeys.ctrlKey) && keyCode == 39) {    // right arrow
                    event.preventDefault();
                    console.log(id + ' right arrow');
                }
            });
        */
    }; // setupKeyboardEvents



    this.scrollToWidget = function() {
        var hash = window.location.hash;
        if (hash && $(hash).length) {
            this.openPanel(hash);
            var $widget = $(hash).closest('.lzy-panels-widget');
            $widget[0].scrollIntoView();
        }
    }; // scrollToWidget



    this.openRequestedPanel = function() {
        if (window.location.hash) {
            var id = window.location.hash;
            if (id.match(/#lzy-panel-id/) && id.match(/^#\d+$/)) {
                id = '#lzy-panel-id10' + id.substr(1);
            }
            this.openPanel( id );
        }
    }; // openRequestedPanel



    this.extractIdNumber = function( id ) {
        var n = false;
        var m = id.match(/\d+$/);
        if (m.length > 0) { // it was an index
            n = m[0];
        }
        return n;
    }; // extractIdNumber

} // LzyPanels
