// editor.js

"use strict";

var lzyEditor = null;
var lzyEditorInx = 1;

function LzyEditor() {

    this.init = function ( args ) {
        this.lzyEditorInx = lzyEditorInx++;

        this.args = args;

        // get text:
        if (typeof args.srcRef === 'undefined') {
            mylog('LzyEditor Error: srcRef missing');
            return;
        }
        if (typeof args.dataRef === 'undefined') {
            mylog('LzyEditor Error: dataRef missing');
            return;
        }
        this.srcRef = args.srcRef;
        this.dataRef = args.dataRef;

        const parent = this;
        var url = appRoot + '_lizzy/_ajax_server.php?get-elem';
        $.ajax({
            method: 'POST',
            url: url,
            data: { ds: args.srcRef, dataRef: args.dataRef, }
        })
            .done(function ( json ) {
            const data = JSON.parse( json );
            const result = data.res;
            if (!result.match(/^ok/i)) {
                mylog( result );
            }
            if (typeof data.data !== 'undefined') {
                parent.origText = data.data;
                parent.startEditing( data.data );
            }
        });
    }; // init



    this.startEditing = function ( text ) {
        mylog('startEditing: ' + text);
        const parent = this;
        const id = 'lzy-editor-wrapper-' + this.lzyEditorInx;

        if (!$('#lzy-editor-' + this.lzyEditorInx).length) {
            const html = '<div id="' + id + '"><textarea id="lzy-editor-' + this.lzyEditorInx +
                '" class="lzy-editor">' + text + '</textarea></div>';
            $('body').append(html);
        }

        // open popup:
        lzyPopup({
            contentFrom: '#' + id,
            closeOnBgClick: false,
            closeButton: false,
            wrapperClass: 'lzy-editor',
            buttons: 'Cancel,Save',
            callbacks: 'onCancel,onSave',
            deleteAfter: true,
        });

        // initialize SimpleMDE:
        //  see https://codemirror.net/doc/manual.html#config
        this.simplemde = new SimpleMDE({
            element: $('#lzy-editor-' + this.lzyEditorInx)[0],
            autofocus: true,
            spellChecker: false,
            autocorrect: false,
            tabSize: 4,
            indentWithTabs: false,
            toolbar: [
                'bold',
                'italic',
                'strikethrough',
                'heading-1',
                'heading-2',
                'heading-3',
                'unordered-list',
                'ordered-list',
                'horizontal-rule',
                'preview',
                'side-by-side',
                'fullscreen',
                'guide',
                {
                    name: 'gap',
                    className: 'lzy-editor-buttons-gap',
                    title: '|',
                },
                {
                    name: 'Exit',
                    action: function customFunction(){
                        const text = parent.simplemde.value();
                        parent.saveEditorData( text );
                        lzyPopupClose();
                    },
                    className: 'fa fa-check',
                    title: '{{ Exit }}',
                },
                {
                    name: 'Save',
                    action: function customFunction(){
                        const text = parent.simplemde.value();
                        parent.saveEditorData( text );
                    },
                    className: 'fa fa-save',
                    title: '{{ Save }}',
                },
                {
                    name: 'Close',
                    action: function customFunction(){
                        lzyPopupClose();
                    },
                    className: 'fa fa-window-close',
                    title: '{{ Close }}',
                },
            ],
        });
        this.simplemde.codemirror.setSelection({line:9999,ch:0}, {line:9999,ch:0});
    }; // startEditing



    this.saveEditorData = function ( text ) {
        const parent = this;
        var url = appRoot + '_lizzy/_ajax_server.php?save-elem';
        $.ajax({
            method: 'POST',
            url: url,
            data: {
                ds: parent.srcRef,
                dataRef: parent.dataRef,
                text: text,
            }
        })
            .done(function ( json ) {
            const data = JSON.parse( json );
            const result = data.res;
            if (!result.match(/^ok/i)) {
                mylog( result );
            }
            if (typeof data.data !== 'undefined') {
                if (typeof parent.args.postSaveCallback !== 'undefined') {
                    if (typeof window[parent.args.postSaveCallback] !== false) {
                        window[parent.args.postSaveCallback](data.data);
                    }
                }
            }
        });
    }; // saveEditorData



    this.cancelEditing = function () {
        if (typeof this.args.postCancelCallback !== 'undefined') {
            if (typeof window[this.args.postCancelCallback] !== false) {
                window[this.args.postCancelCallback]();
            }
        }
    }; // cancelEditing
} // LzyEditor




function initLzyEditor( args ) {
    lzyEditor = new LzyEditor();
    lzyEditor.init( args );
} // initLzyEditor



function editorCallback( that ) {
    const text = lzyEditor.simplemde.value();
    const origText = lzyEditor.origText;
    if (origText !== text) {
        lzyConfirm('{{ Save changes? }}').then(function() {
            lzyEditor.saveEditorData( text );
        }, function() {
            lzyEditor.cancelEditing();
            }
        );
    } else {
        mylog('lzyEditor: no change, noting to save', false);
    }
    return false; // not abort
} // editorCallback



function onSave( that ) {
    const text = lzyEditor.simplemde.value();
    lzyEditor.saveEditorData( text );
    lzyPopupClose();
} // onSave



function onCancel() {
    lzyEditor.cancelEditing();
    lzyPopupClose();
} // onCancel




