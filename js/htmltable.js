// htmltable.js

"use strict";

var lzyActiveTables = [];

$('.lzy-active-table').each(function() {
	var tableInx = $( this ).data('inx');
	lzyActiveTables[tableInx] = new HTMLtable( this );
});

$('.lzy-active-table').on('click', '.lzy-table-view-btn', function() {
	var tableInx = $( this ).closest('.lzy-active-table').data('inx');
	lzyActiveTables[tableInx].openViewRecPopup( this );
});

$('.lzy-active-table').on('click', '.lzy-table-edit-btn', function() {
	var tableInx = $( this ).closest('.lzy-active-table').data('inx');
	lzyActiveTables[tableInx].openFormPopup( this );
});




function HTMLtable( tableObj ) {
	this.$table = tableObj;
	if (typeof this.$table[0] === 'undefined') {
		this.$table = $( tableObj );
	}
	this.tableInx = this.$table.data('inx');
	this.formHash = this.$table.data('tableHash');
	this.formHtml = null;
	this.lzyTableNewRec = false;
	this.formInx = false;
	this.$form = null;
	this.recKey = false;
	this.$recviewPopup = null;
	this.recViewPopupId = null;
	this.recEditPopupId = null;
	this.$recEditForm = null;
	var tmpHash = Math.random().toString().substr(2,10);
	this.recViewHash = 'V' + tmpHash;
	this.recEditHash = 'E' + tmpHash;
	this.waitSymbol = '⌛';




	this.init = function () {
		var formId = this.$table.attr('data-form-id');
		this.formInx = formId.replace(/\D/g, '');
		this.$form = $( formId ).closest('.lzy-edit-rec-form');
		this.recViewPopupId = 'lzy-recview-popup-' + this.formInx;
		this.recEditPopupId = 'lzy-recedit-popup-' + this.formInx;

		if (this.$form.length) {
			this.formHtml = this.$form.html();
			this.$form.remove();
			this.initViewRecPopup();
			this.initEditFormPopup();
			this.setupEventHandlers();
		}
	}; // init




	this.initViewRecPopup = function () {
		if (!this.$table.hasClass('lzy-rec-preview')) {
			return;
		}

		// modify all IDs to avoid id-clashes:
		var formHtml = this.formHtml;

		// remove all ids from form (which serves no longer as a form, but rather as container):
		formHtml = formHtml.replace(/(id=['"].*?['"])/g, '');
		const cls = 'lzy-popup-bg lzy-popup-' + this.formInx + ' lzy-close-on-bg-click lzy-popup-with-header lzy-recview-popup';

		// inject popup code at end of body:
		const popupHtml = '<div id="' + this.recViewPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="lzy-popup-wrapper lzy-popup-wrapper-ref" data-popup-inx="' + this.recViewHash + '">\n' +
			'<div class="lzy-popup-header lzy-draggable">' +
				'<div></div>' +
				'<button class="lzy-popup-close-button">×</button>' +
			'</div>' +
			'<div class="lzy-popup-container lzy-scroll-hints">\n' +
			'<div id="inner-recview">\n' +
				formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );


		// change form fields into read-only fields:
		this.$recviewPopup = $('#' + this.recViewPopupId);
		this.makeReadonly( this.$recviewPopup );
	}; // initViewRecPopup




	this.initEditFormPopup = function () {
		const cls = 'lzy-popup-bg lzy-popup-' + this.formInx + ' lzy-popup-with-header';
		const formHtml = this.formHtml;
		const popupHtml = '<div id="' + this.recEditPopupId + '" class="' + cls + '" style="display: none;">\n' +
			'<div class="lzy-popup-wrapper lzy-popup-wrapper-ref" data-popup-inx="' + this.recEditHash + '">\n' +
			'<div class="lzy-popup-header lzy-draggable">' +
			'<div></div>' +
			'</div>' +
			'<div class="lzy-popup-container lzy-scroll-hints">\n' +
			'<div id="inner-rec-edit-form">\n' +
			formHtml +
			'</div></div></div></div>\n';
		$( 'body' ).append( popupHtml );
		this.$recEditForm = $( '#' + this.recEditPopupId + ' form');

	}; // initEditFormPopup




	this.openViewRecPopup = function ( $triggerSrc ) {
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
		}
		lzyPopup({
			id: 'lzy-recview-popup-' + this.formInx,
			contentRef: this.$recviewPopup,
			header: true,
			draggable: true,
		}, this.recViewHash);

		// copy table cell values into preview popup form:
		const $row = $triggerSrc.closest('tr');
		$('td', $row).each(function () {
			const $td = $(this);
			var val = $td.text();
			val = val? val: ' ';
			const dataRef = $td.attr('data-ref');
			if (typeof dataRef === 'undefined') {
				return;
			}
			const elemInx = parseInt( dataRef.replace(/^.*,/, '') ) + 1;
			const $formEl = $('[data-elem-inx=' + elemInx + ']', this.$recViewPopup);
			if ( $formEl.length ) {
				$('.lzy-form-field-placeholder', $formEl ).text( val );
			}
		});

		var header = $('#lzy-recview-header').text();
		if (!header) {
			header = '&nbsp;';
		}
		$('.lzy-popup-header > div').html( header );

	}; // openViewRecPopup




	this.openFormPopup = function ( $triggerSrc ) {
		const parent = this;
		var $table = null;
		var fldPreset = this.waitSymbol;
		var newRec = false;
		parent.recKey = 'new-rec';
		if (typeof $triggerSrc[0] === 'undefined') {
			$triggerSrc = $( $triggerSrc );
			$table = $triggerSrc.closest('[data-form-id]');
			this.recKey = $triggerSrc.closest('[data-reckey]').data('reckey');
		} else {
			$table = $triggerSrc;
			fldPreset = '';
			newRec = true;
		}

		var formTitle = '';

		this.formHash = $table.data('tableHash');
		const popupId = '#lzy-recedit-popup-' + this.formInx;
		const $popup = $( popupId );

		if (this.recKey === 'new-rec') {
			formTitle = $('#lzy-edit-form-new-rec').html();
			fldPreset = '';
			this.lzyTableNewRec = true;

		} else { // existing rec:
			formTitle = $('#lzy-edit-form-rec').html();
			this.lzyTableNewRec = false;
		}

		lzyPopup({
			contentRef: $popup,
			closeButton: false,
			closeOnBgClick: false,
			header: formTitle,
		});

		const $form = this.$recEditForm;
		$form.data('recKey', this.recKey);
		$('input[name=_rec-key]',$form).val( this.recKey );

		if (this.recKey === 'new-rec') {
			$form.addClass('lzy-new-data');
			$('#lzy-edit-rec-delete-checkbox').hide();
		} else  {
			$form.removeClass('lzy-new-data');
			$('.lzy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('#lzy-edit-rec-delete-checkbox').show();
		}

		// reset input fields, insert hourglass where possible:
		$('.lzy-form-field-wrapper input', $form).each(function() {
			const type = $(this).attr('type');
			const scalarTypes = ',string,text,password,email,textarea,' +
				',url,date,time,datetime,month,number,range,tel,';
			if (scalarTypes.match(','+type+',')) {
				$(this).val( fldPreset );

			} else if ((type === 'radio') || (type === 'checkbox')) {
				$(this).prop('checked', false);
			}
		});
		$('textarea', $form).val( fldPreset );
		$('option', $form).each(function() {
			$(this).prop('selected', false);
		});

		if ( this.lzyTableNewRec ) {
			return;
		}

		// get data by ajax:
		const req = '?get-rec&ds=' + this.formHash + ':form' + this.formInx + '&lock&recKey=' + this.recKey;
		$('[type=submit]', $form).val( $('#lzy-edit-form-submit').text() );
		$('#lzy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		execAjax(false, req, function(json){
			parent.updateEditForm( parent.recKey, parent.formId, json);
		});
	}; // openFormPopup




	this.setupEventHandlers = function () {
		const parent = this;
		$('body')
			// cancel button in form:
			.on('click', '.lzy-edit-data-form input[type=reset]', function(e) {
				e.preventDefault();
				e.stopPropagation();
				e.stopImmediatePropagation();

				// only in case of existing rec we need to unlock the record:
				if (!$(this).closest('.lzy-new-data').length) {
					parent.unlockRecord();
				}
				lzyPopupClose();
			})

			// submit button in form:
			.on('click', '.lzy-edit-data-form input[type=submit]', function() {
				if ( parent.lzyTableNewRec ) {
					$('#lzy-chckb__delete_1').prop('checked', false);
				}
			})

			// delete checkbox in form:
			.on('change', 'input.lzy-edit-rec-delete-checkbox', function(e) {
				e.preventDefault();
				e.stopPropagation();
				const $form = $(this).closest('.lzy-edit-data-form');
				var text;
				var val;
				if ($(this).prop('checked')) {
					text = $('#lzy-edit-rec-delete-btn').text();
					val = true;
					mylog('delete');
				} else {
					text = $('#lzy-edit-form-submit').text();
					val = false;
					mylog('don\'t delete');
				}
				$('.lzy-form-button-submit', $form).val(text);
				$form.data('delete', val);
			});
	}; // setupEventHandlers




	this.makeReadonly = function ( $srcElem ) {
		$('.lzy-form-field-wrapper', $srcElem).each(function () {
			const $formEl = $( this );
			var type = $('input', $formEl).attr('type');
			if (typeof type === 'undefined') {
				type = 'undefined';
			}
			var name = $('input', $formEl).attr('name');
			if ((typeof name !== 'undefined') && (name.charAt(0) === '_')) {
				return;
			}
			if ('hidden,button,submit,reset'.match(type)) {
				return;
			}
			if ('radio,checkbox'.match(type)) {
				$('.lzy-fieldset-body', $formEl).remove();
				$('fieldset',$formEl).append('<div class="lzy-form-field-placeholder">&nbsp;</div>');

			} else if ($formEl.hasClass('lzy-form-field-type-dropdown')) {
				$('select', $formEl).remove();
				$formEl.append('<div class="lzy-form-field-placeholder">&nbsp;</div>');
			} else if ($('.lzy-textarea-autogrow', $formEl).length) {
				$('.lzy-textarea-autogrow', $formEl).remove();
				$formEl.append('<div class="lzy-form-field-placeholder">&nbsp;</div>');
			} else if ($('textarea', $formEl).length) {
				$('textarea', $formEl).remove();
				$formEl.append('<div class="lzy-form-field-placeholder">&nbsp;</div>');
			} else {
				$('input', $formEl).remove();
				$('.lzy-form-pw-toggle', $formEl).remove();
				$formEl.append('<div class="lzy-form-field-placeholder">&nbsp;</div>');
			}
		});
	};




	this.updateEditForm = function ( recKey, formId, json ) {
		try {
			var data = JSON.parse(json);
		} catch (e) {
			console.log('Error condition detected');
			console.log(json);
			lzyPopupClose();
			lzyPopup('{{ lzy-table-record-locked }}');
			return false;
		}
		var i, val;
		var sel;
		if ( data.res !== 'Ok') {
			this.handleException( data );
		}
		for (i in data.data) {
			val = data.data[ i ];
			if (i.match(/input:/)) {
				$( i ).prop('checked', true);
			} else if (i.match(/option/)) {
				$( i ).prop('selected', true);
			} else {
				sel ='[name=' + i + ']';
				$( sel, formId ).val( val );
			}
		}
	}; // updateEditForm



	this.handleException = function ( data ) {
		lzyPopupClose();
		var lockedRec = null, i = null;
		if (typeof data.lockedRecs === 'object') {
			for (i in data.lockedRecs) {
				lockedRec = data.lockedRecs[i];
				$('[data-reckey="' + lockedRec + '"]').addClass('lzy-record-locked');
			}
			lzyPopup('{{ lzy-table-record-locked }}');
		}
	};



	this.updateUI = function ( json ) {
		try {
			var data = JSON.parse(json);
		} catch (e) {
			console.log('Error condition detected');
			console.log(json);
			return false;
		}
		var data1 = data.data;
		var targ = '';
		var val = '';
		var r = data1.recInx;
		for (var c in data1.rec) {
			val = data1.rec[ c ];
			targ = '[data-ref="' + r + ',' + c + '"]';
			$( targ ).text( val );
		}
	}; // updateUI




	this.unlockRecord = function () {
		const req = '?unlock-rec&ds=' + this.formHash + ':form' + this.formInx + '&recKey=' + this.recKey;
		execAjax(false, req, function(json) {
			mylog( json );
		});
	}; // unlockRecord




	this.init();

} // HTMLtable
