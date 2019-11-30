/*
**	JS for in-browser editor
*/


var lastMdUpdateTime = 0;
var $edWrapper = null;
var origText = '';
var simplemde = null;
var filename = '';
var $editBtn = null;

$( document ).ready(function() {
    prepareEditing();       // insert editing buttons
    setupEventHandlers();   // show-files-, edit-, cancel-buttons, page-history etc.
    setupKeyHandlers();     // F4, ESC, meta-s, meta-Return
}); // ready




function prepareEditing()
{
    $('.lzy-edit-sitemap').prepend('<button class="lzy-editor-btn"><span class=\'lzy-icon-edit\'></span></button>');
    $('body').addClass('lzy-editor-mode');
    $('.lzy-logged-in-user > a.lzy-toggle-edit-mode > span').addClass('lzy-negate');
} // prepareEditing




function setupEventHandlers()
{

    // content edit button:
    $('.lzy-src-wrapper:not(.lzy-edit-sitemap) .lzy-editor-btn').click(function() {
        $editBtn = $(this);
        startEditor( $editBtn );
    });


    // $('.lzy-editor-wrapper').click(function() {
    $('.lzy-editor-wrapper:not(.lzy-edit-sitemap)').click(function() {
        if (simplemde === null) {   // avoid starting editor if already started
            var $wrapper = $(this).parent();
            $editBtn = $('.lzy-editor-btn', $wrapper);
            startEditor( $editBtn );
        }
    });



    // sitemap edit button:
    $('.lzy-edit-sitemap .lzy-editor-btn').click(function() {
        startSitemapEditor();
    });

    // page-history drop-down list:
    $( "select" ).change(function() {       // handle edition-dropdown-list
        filename = $( this ).parent().parent().attr('data-lzy-filename');
        $( "select option:selected" ).each(function() {
            var href = $(this).val();
            if (href != '') {
                href = './?ed=-' + (parseInt($(this).val()) + 1) + '&file=' + filename;
                window.location.href = href;
            }
        });
    });

} // setupEventHandler




function setupKeyHandlers()
{
    document.onkeydown = function (e) {     // intercept cmd-s and ctrl-s to save
        var keyCode = (window.event) ? event.keyCode : e.keyCode;
        if ( simplemde !== null) {  // only when editor is active:
            if ((keyCode == 13) && (event.ctrlKey || macKeys.cmdKey)) {
                event.preventDefault();
                console.log('meta-s intercepted');
                saveData( true );
                return false;
            }

            if ((keyCode == 83) && (event.ctrlKey || macKeys.cmdKey)) {
                event.preventDefault();
                console.log('meta-s intercepted');
                saveData( false );
                return false;
            }

            if (keyCode == 27) {	// ESC -> end editing mode
                abortEditor();
                lzyReload();
                return false;
            }
        }

        if (keyCode == 27) {	// ESC -> end editing mode
            console.log('ESC intercepted');
            var call = window.location.pathname.replace(/\?.*/, '') + '?edit=false';
            window.location.assign(call);
        }

        if (keyCode == 115) {   // F4
            var $editBtns = $('main .lzy-editor-btn');
            $editBtn = $editBtns.first();
            if (simplemde === null) {
                startEditor( $editBtn );
            } else {
                $editBtn.show();
                simplemde.toTextArea();
                simplemde = null;
                $edWrapper.html(origText);
            }
        }

    };
} // setupKeyHandlers





function startEditor( $editBtn ) {
    $editBtn.hide();
    $('body').addClass('lzy-editing');
    var $section = $editBtn.parent();
    var $edWrapper0 = $('#lzy-editor-dock-wrapper');
    $edWrapper0.show();
    $edWrapper0.draggable({ handle: '.editor-toolbar' }).resizable();    // using jQueryIU

    $section.addClass('lzy-editing');
    filename = $section.attr('data-lzy-filename');
    $edWrapper = $('#lzy-editor-dock');
    $edWrapper.html('');
    $('.lzy-editing-filename').text(filename);

    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { lzy_filename: filename },
        success: function( data ) {
            var editingHtml = $('#lzy-editing-html').html();
            editingHtml = editingHtml.replace(/@data/, data);
            $edWrapper.html(editingHtml);
            simplemde = new SimpleMDE({
                element: $('textarea', $edWrapper)[0],
                spellChecker: false,
                placeholder: 'Write here...',
                autofocus: true,
                allowAtxHeaderWithoutSpace: true,
                previewRender: function(plainText, preview) { // Async method
                    if (lastMdUpdateTime) { // reset timer if there was one set
                        clearTimeout(lastMdUpdateTime);
                    }
                    lastMdUpdateTime = setTimeout(function(){   // invoke update with delay
                        customMarkdownParser(encodeURI(plainText), preview);
                    }, 1000);
                    return origText;
                },
                hideIcons: ["guide"],
                showIcons: ["code", "table", "horizontal-rule"],
                tabSize: 4,


            });
            $('.lzy-ed-selector').hide();

            $('.lzy-cancel-btn').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.lzy-save-btn').click(function() {
                saveData(this, false );
            });

            $('.lzy-done-btn').click(function() {
                saveData(this, true );
            });
        },
    });
} // startEditor




function startSitemapEditor() {
    $('body').addClass('lzy-editing');
    var $edWrapper0 = $('#lzy-editor-dock-wrapper');
    $edWrapper0.show();
    $edWrapper0.draggable({ handle: '.editor-toolbar' }).resizable();    // using jQueryIU

    $edWrapper = $('#lzy-editor-dock');
    $edWrapper.html('').addClass('lzy-edit-sitemap');

    filename = 'sitemap';
    $('.lzy-editing-filename').text('config/sitemap.txt');
    $.ajax({
        type: "POST",
        url: systemPath + '_ajax_server.php?getfile',
        data: { lzy_filename: filename },
        success: function( data ) {
            var editingHtml = $('#lzy-editing-html').html();
            editingHtml = editingHtml.replace(/@data/, data);
            $edWrapper.html(editingHtml);
            simplemde = new SimpleMDE({
                element: $('textarea', $edWrapper)[0],
                spellChecker: false,
                placeholder: 'Hier schreiben...',
                autofocus: true,
                allowAtxHeaderWithoutSpace: true,
                // toolbar: false,
                toolbar: ['fullscreen'],
                tabSize: 8,
            });
            $('.lzy-cancel-btn').click(function() {
                var href = window.location.pathname;
                window.location.href = href;
            });

            $('.lzy-save-btn').click(function() {
                saveData(this, false );
            });

            $('.lzy-done-btn').click(function() {
                saveData(this, true );
            });
        }
    });
} // startSitemapEditor





function abortEditor()
{
	$editBtn.show();
	simplemde.toTextArea();
	simplemde = null;
	$edWrapper.html(origText);
} // abortEditor




function saveSitemapData( leaveEditor )
{
	var sitemap = simplemde.value();
    sitemap = encodeURI(sitemap);   // -> php urldecode() to decode
	$.ajax({
		type: "POST",
		url: '?lzy-save',
		data: { lzy_sitemap: sitemap },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				location.href = location.pathname;
				// window.location.href = window.location.pathname;
			} else {
                $('textarea.lzy-editor').html(data);
			}
		},
	});
} // saveSitemapData



function saveData( obj, leaveEditor )
{
    var $el = $( obj ).parent().parent();
    var isSitemap = $el.hasClass('lzy-edit-sitemap');
    if (isSitemap) {
        return saveSitemapData( leaveEditor );
    }

	var mdStr = simplemde.value();
    mdStr = encodeURI(mdStr);   // -> php urldecode() to decode
	$.ajax({
		type: "POST",
		url: '?lzy-compile&lzy-save',
        'Content-Type': 'application/x-www-form-urlencoded; charset=utf-8',
		// url: systemPath + '?compile&save',
		data: { lzy_filename: filename, lzy_md: mdStr },
		success: function( data ) {
			if (leaveEditor) {
				// var href = window.location.pathname;
				// window.location.href = window.location.pathname;
                location.reload();
			} else {
                $('textarea.lzy-editor').html(data);
			}
		},
	});
} // saveData




function customMarkdownParser(mdStr, preview)
{   // requests the server to return compiled markdown
    $.ajax({
        type: "POST",
        url: systemPath + '?lzy-compile',
        data: { lzy_md: mdStr },
        async: true,
        success: function( html ) {
            preview.innerHTML = html;
            origText = html;
        }

    });
} // customMarkdownParser



