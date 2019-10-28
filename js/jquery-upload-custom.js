/*
 *  Adapted for Lizzy
 */

/*
 * jQuery File Upload Plugin JS Example
 * https://github.com/blueimp/jQuery-File-Upload
 *
 * Copyright 2010, Sebastian Tschan
 * https://blueimp.net
 *
 * Licensed under the MIT license:
 * https://opensource.org/licenses/MIT
 */


/* global $, window */

function createFile(ticket, url)
{
    var fname = $('#lzy-editor-new-file-input').val();
    if (!fname) {
        alert('Please enter file name');
        return;
    }
    $.ajax({
        url: url,
        method: 'POST',
        data: { 'lzy-upload': ticket, 'lzy-cmd': 'new-file', 'lzy-file-name': fname },
        dataType: 'json',
    }).done(function(data) {
        console.log('new file created: ' + data);
        lzyReload();
    });
}


function deleteFiles()
{
    var needReload = false;
    $('#lzy-fileupload .files .lzy-editor-delete-file').each(function () {
        var file = $( this ).attr('data-url');
        file = file.replace(/^.*file=/, '');
        var checked = $( this ).next().prop('checked');
        if (checked) {
            if (file.match(/\.md/)) {
                if (confirm('Are you sure you want to delete a markdown file?')) {
                    needReload = needReload || deleteFile( file );
                }
            } else {
                needReload = deleteFile( file ) || needReload;
            }
        }
    });
    if (needReload) {
        lzyReload();
    }
}

function deleteFile( file )
{
    console.log( 'deleting file: ' + file );
    var ticket = $('#lzy-upload-id').val();
    $.ajax({
        url: $('#lzy-fileupload').fileupload('option', 'url'),
        method: 'POST',
        data: { 'lzy-upload': ticket, 'lzy-delete-file': file},
        dataType: 'json',
        context: $('#lzy-fileupload')[0]
    });
    return true;
}


function renameFile( ref ) {
    var file = $(ref).attr('data-url');
    file = file.replace(/^.*file=/, '');
    file = decodeURI(file);

    // $(ref).closest('tr').after('<tr><td><input type="text" /></td><td></td><td></td><td></td><td></td></tr>');
    var $elem = $('td:nth-child(2)', $(ref).closest('tr'));
    var content = $elem.text().trim();
    content = '<p>' + content + '<br><input id="lzy-editor-rename-to" type="text" /> <button id="lzy-editor-rename-submit" class="lzy-button" title="rename now"><span class="lzy-icon-ok"></span></button></p>';
    $elem.html( content );
    $('#lzy-editor-rename-to').val( file ).select();
    $('#lzy-editor-rename-submit').click(function () {
        var newName = $('#lzy-editor-rename-to').val();
        doRenameFile(file, newName);
    });
    $('#lzy-editor-rename-to').keypress(function(event){
        var keycode = (event.keyCode ? event.keyCode : event.which);
        if(keycode === 13){
            $('#lzy-editor-rename-submit').focus().trigger('click');
        }
    });
}

function doRenameFile(origName, newName)
{
console.log('rename: ' + origName);
    var ticket = $('#lzy-upload-id').val();
    $.ajax({
        url: $('#lzy-fileupload').fileupload('option', 'url'),
        method: 'POST',
        data: { 'lzy-upload': ticket, 'lzy-rename-file': origName, 'to': newName },
        dataType: 'json',
        context: $('#lzy-fileupload')[0]
    }).done(function () {
        lzyReload();
    });
}



function initFileManager()
{
    'use strict';

    var serverUrl = appRoot+'_lizzy/_upload_server.php';

    $('#lzy-fileupload').fileupload({
        url: serverUrl
    });

    $('#lzy-fileupload').addClass('fileupload-processing');

    var url = $('#lzy-fileupload').fileupload('option', 'url');
    var ticket = $('#lzy-upload-id').val();

    $.ajax({
        url: $('#lzy-fileupload').fileupload('option', 'url'),
        // method: 'POST',
        data: { 'lzy-upload': ticket},
        dataType: 'json',
        context: $('#lzy-fileupload')[0]
    })
        .always(function(result) {
            $(this).removeClass('fileupload-processing');
        })
        .done(function(result) {
            $(this)
                .fileupload('option', 'done')
                .call(this, $.Event('done'), { result: result });
            $('.lzy-editor-rename-file').click(function (e) {
                renameFile( this );
            });
            $('.lzy-editor-delete-file').click(function (e) {
                $( this ).next().prop( 'checked', true );
                deleteFiles();
            });
        });

    $('.lzy-editor-new-file').click(function (e) {
        // e.preventDefault();
        $('#lzy-editor-new-file-name-box').show();
    });
    $('#lzy-editor-new-file-input-button').click(function (e) {
        e.preventDefault();
        createFile(ticket, url);
    });
    $('#lzy-editor-new-file-input').keypress(function(event){
        var keycode = (event.keyCode ? event.keyCode : event.which);
        if(keycode === 13){
            event.preventDefault();
            createFile(ticket, url);
        }
    });
    $('.lzy-editor-delete-files').click(function (e) {
        deleteFiles();
    });
} // initFileManager




$(function () {
    'use strict';

    $('.lzy-fileadmin-button').click(function () {
        initFileManager();

        $('.lzy-file-uploader').toggleClass('lzy-open');
        setTimeout(function () {
            $([document.documentElement, document.body]).animate({
                scrollTop: $(".lzy-file-uploader-wrapper").offset().top
            }, 400);
        }, 400);
    });
});

