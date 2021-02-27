// file_editor.js

"use strict";

var fileEditor = null;

function FileEditor() {

    this.init = function ( srcRef ) {
        // get file from _ajax_server.php?getfile
        // execAjax({srcRef: srcRef}, 'getfile', function ( json ) {
        //     mylog( json );
        // });
        const parent = this;
        var url = appRoot + '_lizzy/_ajax_server.php?getfile';
        $.ajax({
            method: 'POST',
            url: url,
            data: { srcRef: srcRef }
        }).done(function ( json ) {
            const data = JSON.parse( json );
            if (typeof data.data !== 'undefined') {
                parent.startEditing( data.data );
            }
        });


        // inject editing HTML
        // open popup
        // setup handlers
    }; // init



    this.startEditing = function ( text ) {
        mylog('startEditing: ' + text);
    }; // startEditing


} // FileEditor



function initFileEditor( srcRef ) {

    fileEditor = new FileEditor();
    fileEditor.init( srcRef );

} // initFileEditor