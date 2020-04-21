/*

    Inspired by: https://www.barrierefreies-webdesign.de/knowhow/tablist/tabpanel-links-tabindex.html

*/


if (typeof window.widthThreshold === 'undefined') {
    window.widthThreshold = 480;
}
panelWidgetInstance = 1;    // instance number
panelsInitialized = [];


function initializePanel( widgetSelector, preOpen )
{
    var $widgets = $( widgetSelector );
    $widgets.each(function () {
        var $this = $( this );
        if ($this.attr('data-lzy-panels')) {
            return;
        }
        if (widgetSelector.substr(0,1) !== '#') {
            $this.attr('id', 'lzy-panels-widget' + panelWidgetInstance);
        } else {
            $this.addClass('lzy-panels-widget');
        }
        $this.addClass('lzy-panels-widget lzy-panels-widget' + panelWidgetInstance);

        var panels = [];
        i = 0;
        $('> *', $this).each(function () {
            var $this = $( this );
            var hdrText = $('> *:first-child', $this).text();
            var body = '';
            $('> *:not(:first-child)', $this).each(function () {
                body += $(this)[0].outerHTML;
            });
            panels[i] = {hdrText: hdrText, body: body};
            i++;
        });
        $this.attr('data-lzy-panels', panels.length);

        var header = '';
        var body = '';

        for (i = 1; i <= panels.length; ++i) {
            var panel = panels[i-1];
            var i1 = panelWidgetInstance*100 + i;
            var tabindex = (i === 1) ? '0' : '-1';
            var aria = ' aria-setsize="' + panels.length + '" aria-posinset="' + i + '" aria-selected="false" aria-controls="lzy-panel-id' + i1 + '"';

            header += '\t\t<li id="lzy-tabs-mode-panel-header-id' + i1 + '" class="lzy-tabs-mode-panel-header" role="tab" tabindex="'+ tabindex +'"' + aria + '>' + panel.hdrText + '</li>\n';

            body += '\n\n\t<!-- === panel page ==== -->\n\t<div id="lzy-panel-id' + i1 + '" class="lzy-panel-page" role="tabpanel" tabindex="-1" aria-labelledby="lzy-tabs-mode-panel-header-panel-id' + i1 + '" aria-hidden="true">\n' +
                '\t\t<div class="lzy-accordion-mode-panel-header"><a id="#lzy-panel-controller' + i1 + '" href="#lzy-panel-id' + i1 + '" class="lzy-panel-link" aria-controls="lzy-panel-body-wrapper' + i1 + '" aria-expanded="false">' + panel.hdrText + '</a></div>\n' +
                '\t\t<div id="lzy-panel-body-wrapper' + i1 + '" class="lzy-panel-body-wrapper" aria-labelledby="lzy-panel-controller' + i1 + '" role="region">\n' +
                '\t\t\t<div class="lzy-panel-inner-wrapper">\n' +
                panel.body +
                '\t\t\t</div><!-- /lzy-panel-inner-wrapper -->\n' +
                '\t\t</div><!-- /lzy-panel-body-wrapper -->\n' +
                '\t</div><!-- /lzy-panel-page -->\n';
        }
        header = '\n\n<!-- === lzy-tabs-mode headers ==== -->\n\t<ul class="lzy-tabs-mode-panels-header-list" role="tablist">\n' + header + '\t</ul>\n\n';

        $this.html(header + body);
        $('.lzy-panel-page', $(this)).first().attr('aria-hidden', 'false');
        $('.lzy-tabs-mode-panel-header:first-child', $(this)).attr('aria-selected', 'true');
        $this.show();
        if (preOpen) {
            openPanel( '#lzy-panel-id' + (panelWidgetInstance*100 + parseInt(preOpen)));
        }
        if ($this.hasClass('lzy-accordion')) {
            $('.lzy-panel-inner-wrapper', $this).css('transition-duration', '0.4s');
        }
        panelWidgetInstance++;
    });
} // initializePanel





function setupEvents() {
    setupTabsHeaderEvents();
    setupAccordionHeaderEvents();
    setupKeyboardEvents();
}



function setupTabsHeaderEvents()
{
    $('.lzy-tabs-mode-panel-header').click(function() {
        var id = '#' + $( this ).attr('aria-controls');
        // console.log('tabs event: '+id);
        operatePanel( id, true);
    });
}



function setupAccordionHeaderEvents()
{
    var mousedown = false;
    var $accordionHeaders = $('.lzy-accordion-mode-panel-header');
    $accordionHeaders.on('mousedown', function () {
        mousedown = true;
    });
    $accordionHeaders.on('focusin', function () {
        if(!mousedown) {
            var id = $('a', $( this )).attr('href');
            // console.log('acc keyb event: '+id);
            operatePanel( id, false);
            return;
        }
        mousedown = false;
    });
    $accordionHeaders.click(function(e) {
        e.preventDefault();
        mousedown = false;
        var id = $('a', $( this )).attr('href');
        operatePanel( id, false);
    });
}




function operatePanel( id, tabClicked)
{
    var oneOpenOnly = ($(id).closest('.lzy-panels-widget.one-open-only').length > 0);

    if (tabClicked) {                   // Click/focus on Tab
        // close all, open id
        var tabsHdrId = id.replace(/panel-/, 'lzy-tabs-mode-panel-header-');
        var wasOpen = ($(tabsHdrId).attr('aria-selected') == 'true');
        if (!wasOpen) {
            closeAllPanels( id );
            openPanel( id );
        }

    } else {                            // click/focus on Accordion-header
        var wasOpen = $(id).hasClass('lzy-panel-page-open');
        if (wasOpen) {
            closePanel( id );
        } else {
            if (oneOpenOnly) {   // close all, open id
                closeAllPanels(id);
                openPanel( id );
            } else {            // close id
                openPanel( id );
            }
        }
    }
}


function closeAllPanels( id ) {
    closeAllTabs( id );
    var $thisWidget = $(id).closest('.lzy-panels-widget');

    var $panelHdrs = $('.lzy-panel-page', $thisWidget);
    $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
    $panelHdrs.removeClass('lzy-panel-page-open');
}


function closeAllTabs( id )
{
    var $thisWidget = $(id).closest('.lzy-panels-widget');
    var $tabsHdrs = $('.lzy-tabs-mode-panel-header', $thisWidget);
    $tabsHdrs.attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdrs = $('.lzy-panel-page', $thisWidget);
    $panelHdrs.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
}


function openPanel( id )
{
    closeAllTabs( id );
    openTab( id );

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'false', 'aria-expanded': 'true', 'aria-selected':'true'});
    $panelHdr.addClass('lzy-panel-page-open');
}


function openTab( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'true', 'tabindex': 0});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'false', 'aria-selected':'true'});
}



function closeTab( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'true', 'aria-selected':'false'});
}



function closePanel( id )
{
    var tabsHdrId = id.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-');
    $(tabsHdrId).attr({'aria-selected': 'false', 'tabindex': -1});

    var $panelHdr = $( id );
    $panelHdr.attr({ 'aria-hidden': 'true', 'aria-expanded': 'false', 'aria-selected':'false'});
    $panelHdr.removeClass('lzy-panel-page-open');

    var $thisWidget = $(id).closest('.lzy-panels-widget');
    var nSelected = $('.lzy-tabs-mode-panel-header[aria-selected=true]', $thisWidget).length;
    if (nSelected == 0) {
        id = id.substr(0,10) + '01';     // open first panel
        openTab( id );
    }
}




function onResize( withoutDelay ) {
    setMode( withoutDelay );  // Accordion/Tabs or auto depending on window width

    setPanelHeights();
} // onResize


var to = null;

function setMode( withoutDelay ) {
    $('.lzy-panels-widget').each(function () {
        $this = $( this );
        if ($this.hasClass('lzy-accordion')) {
            $this.removeClass('lzy-tab-mode');

        } else if ($this.hasClass('lzy-tabs')) {
            $this.addClass('lzy-tab-mode');

        } else {    // set automatically:
            if (to) {
                clearTimeout(to);
            }
            if ( withoutDelay === true ) {
                switchOnWidthThreshold( $this );
            } else {
                to = setTimeout(function () {
                    switchOnWidthThreshold($this);
                }, 250);
            }
        }
    });
} // setMode



function switchOnWidthThreshold( $panelWidget ) {

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
} // switchOnWidthThreshold




function setPanelHeights()
{
    $('.lzy-panels-widget:not(.lzy-tab-mode) .lzy-accordion-mode-panel-header').each(function(e) {
        var $this = $(this);
        var idBody = '#'+$('a', $this).attr('aria-controls');
        var $innerWrapper = $(idBody + ' .lzy-panel-inner-wrapper');
        var h = $innerWrapper.outerHeight();
        $innerWrapper.css('margin-top', '-' + h + 'px');
    });
} // setPanelHeights




function setupKeyboardEvents()
{
    // focus is on tab header -> switch between tabs:   left/right and home/end cursor keys
    $('.lzy-tabs-mode-panel-header').keydown( function( event ) {
        var keyCode = event.keyCode;
        var id = '#' + $( this ).attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
        id = id.substr(0, id.length-2);
        var idN = parseInt($( this ).attr('id').substr(-2));

        if (keyCode == 39) {    // right arrow
            event.preventDefault();
            var idN1 = (idN + 1);
            idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
            var id1 = id + idN1;
            if (!$( id1 ).length) {
                id1 = id + '01';
            }
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 37) {    // left arrow
            event.preventDefault();
            if (idN > 1) {
                var idN1 = (idN - 1);
                idN1 = (idN1 > 9) ? idN1 : '0' + idN1;
                var id1 = id + idN1;
            } else {
                $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
                var id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
            }
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 36) {    // home key
            event.preventDefault();
            var id1 = id + '01';
            openPanel( id1 );
            $( id1.replace(/lzy-panel-/, 'lzy-tabs-mode-panel-header-')).focus();

        } else if (keyCode == 35) {    // left arrow
            event.preventDefault();
            $last = $('.lzy-tabs-mode-panel-header:last-child', $(this).parent());
            var id1 = '#' + $last.attr('id').replace(/lzy-tabs-mode-panel-header-/, 'lzy-panel-');
            // console.log( id1 );
            openPanel( id1 );
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
} // setupKeyboardEvents




function scrollToWidget() {
    var hash = window.location.hash;
    if (hash && $(hash).length) {
        openPanel(hash);
        var $widget = $(hash).closest('.lzy-panels-widget');
        $widget[0].scrollIntoView();
    }
} // scrollToWidget




function openRequestedPanel() {
    if (window.location.hash) {
        var id = window.location.hash;
        if (id.match(/^\#\d+$/)) { // it was an index
            id = "#lzy-panel-id10" + id.substr(1);
        }
        openPanel( id );
    }
} // openRequestedPanel





function initLzyPanel( widgetSelector, preOpen )
{
    if (typeof panelsInitialized[widgetSelector] !== 'undefined') {
        return;
    }
    panelsInitialized[widgetSelector] = true;
    initializePanel(widgetSelector, preOpen);
    setPanelHeights();
    setupEvents();
    openRequestedPanel();

    $( window ).resize( onResize );
    onResize( true );

    scrollToWidget();
} // initPanel


// auto-initialize widgets marked by '.lzy-panels-widget':
// initLzyPanel( '.lzy-panels-widget', false );
