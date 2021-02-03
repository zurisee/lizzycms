// htmltable.js

"use strict";


$('.lzy-table-edit-btn').click(function() {
	HTMLtable.openFormPopup( this );
});


var HTMLtable = new Object({
	lzyTableNewRec: false,
	formHash: false,
	formInx: false,
	recKey: false,

	init: function() {
		this.setupEventHandlers();
	}, // init



	updateEditForm: function( recKey, formId, json )
	{
		try {
			var data = JSON.parse(json);
		} catch (e) {
			console.log('Error condition detected');
			console.log(json);
			return false;
		}
		var i, val;
		var sel;
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
	}, // updateEditForm



	updateUI: function( json )
	{
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
	}, // updateUI


	unlockRecord: function() {
		const req = '?unlock-rec&ds=' + this.formHash + ':form' + this.formInx + '&recKey=' + this.recKey;
		execAjax(false, req, function(json) {
			mylog( json );
		});

	}, // unlockRecord



	openFormPopup: function( that ) {
		const thisObj = this;
		var $table = null;
		var $this = null;
		var fldPreset = '⌛';
		var newRec = false;
		thisObj.recKey = 'new-rec';
		if (typeof that[0] === 'undefined') {
			$this = $(that);
			$table = $this.closest('[data-form-id]');
			this.recKey = $this.closest('[data-reckey]').data('reckey');
		} else {
			$table = that;
			fldPreset = '';
			newRec = true;
		}
		const formId = $table.attr('data-form-id');
		const $form = $( formId );
		this.formInx = formId.replace(/\D/g, '');
		const $popup = $( formId ).closest('.lzy-edit-rec-form');
		// this.recKey = $this.closest('[data-reckey]').data('reckey');
		this.formHash = $table.data('tableHash');
		// this.formHash = $this.closest('[data-table-hash]').data('tableHash');
		$form.data('recKey', this.recKey);
		$('input[name=_rec-key]',$form).val( this.recKey );

		if (this.recKey === 'new-rec') {
			const formTitle = $('#lzy-edit-form-new-rec').html();
			fldPreset = '';
			$('.lzy-form-header').html( formTitle );
			$form.addClass('lzy-new-data');
			lzyPopup({
				contentRef: $popup,
				closeButton: true,
				closeOnBgClick: false,
			});
			// $('#lzy-edit-rec-delete-checkbox').hide();
			this.lzyTableNewRec = true;
			// return;

		} else {
			$form.removeClass('lzy-new-data');
			$('.lzy-form-wrapper [name=_rec-key]').val( this.recKey );
			$('#lzy-edit-rec-delete-checkbox').show();
			this.lzyTableNewRec = false;
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

		if (newRec) {
			return;
		}

		// get data by ajax:
		const req = '?get-rec&ds=' + this.formHash + ':form' + this.formInx + '&lock&recKey=' + this.recKey;
		$('[type=submit]', $form).val( $('#lzy-edit-form-submit').text() );
		$('#lzy-edit-rec-delete-checkbox input[type=checkbox]').prop('checked', false);

		const formTitle = $('#lzy-edit-form-rec').html();
		$('.lzy-form-header').html( formTitle );
		lzyPopup({
			contentRef: $popup,
			closeButton: true,
			closeOnBgClick: false,
		});
		execAjax(false, req, function(json){
			thisObj.updateEditForm( thisObj.recKey, thisObj.formId, json);
		});
	}, // openFormPopup



	setupEventHandlers: function() {
		const thisObj = this;
		$('.lzy-table-editable')
			// cancel button in form:
			.on('click', '.lzy-edit-data-form input[type=reset]', function(e) {
				e.preventDefault();
				e.stopPropagation();
				thisObj.unlockRecord();
				lzyPopupClose();
			})

			// submit button in form:
			.on('click', '.lzy-edit-data-form input[type=submit]', function() {
				if ( thisObj.lzyTableNewRec ) {
					$('#lzy-chckb__delete_1').prop('checked', false);
				}
			})

			// delete checkbox in form:
			.on('change', '.lzy-edit-rec-delete-checkbox', function(e) {
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
	}, // setupEventHandlers
}); // HTMLtable

HTMLtable.init();

