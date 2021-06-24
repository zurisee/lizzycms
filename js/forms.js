// forms.js

"use strict";

var lzyForms = new LzyForms();

function LzyForms() {
    this.liveUpdateActive = true;
    this.$form = null;
    this.waitSymbol = '⌛';
    this.lockRecWhileFormOpen = false;



    this.init = function ( lockRecWhileFormOpen ) {
        if ((typeof lockRecWhileFormOpen !== 'undefined') && lockRecWhileFormOpen) {
            this.lockRecWhileFormOpen = true;
        }
        this.setupOnChangeHandler();
        this.onOpen();
        mylog('lzyForms initialized');
    }; // init



    this.clearForm = function( $form, fldPreset ) {
        if (typeof $form === 'undefined') {
            $form = $('lzy-form');
        }
        if (typeof fldPreset === 'undefined') {
            fldPreset = '';
        }

        $('.lzy-form-field-wrapper input', $form).each(function() {
            let $this = $( this );
            if ($this.hasClass('lzy-readonly')) {
                return;
            }
            const type = $(this).attr('type');
            if ((type === 'string') || (type === 'text') || (type === 'textarea')) {
                $this.val( fldPreset );

            } else if ((type === 'radio') || (type === 'checkbox')) {
                $this.prop('checked', false);
            } else {
                $this.val( '' );
            }
        });
        $('option', $form).each(function() {
            $(this).prop('selected', false);
        });

        // reset deactivates liveValues:
        $('[data-live-value-inactive]', $form).each(function() {
            let $this = $( this );
            let v = $this.data('live-value-inactive');
            $this.attr('data-live-value', v);
            $this.removeAttr('data-live-value-inactive');
        });
        $('.lzy-elem-revealed', $form).removeClass('lzy-elem-revealed');
        $('.lzy-reveal-container > div', $form).css('margin-top', '-10000px');
    }; // clearForm



    this.fetchValuesFromHost = function( $form, recKey ) {
        let formHash = $('[name=_lizzy-form]', $form).val();
        let url = null;
        if (this.lockRecWhileFormOpen) {
            url = appRoot + '_lizzy/_ajax_server.php?get-rec&ds=' + formHash  + '&lock&recKey=' + recKey; // includes '&lock'
        } else {
            url = appRoot + '_lizzy/_ajax_server.php?get-rec&ds=' + formHash + '&recKey=' + recKey;
        }

        return new Promise(function(resolve) {
            if ((typeof recKey === 'undefined') || !recKey) {
                resolve( false );
                return;
            }

            $.ajax({
                url: url,
            })
                .done(function ( json ) {
                    if (json.charAt(0) === '<') {
                        mylog( json.replace(/(<([^>]+)>)/gi, '') );
                        json = false;
                    }
                    resolve( json );
                });
        });
    }; // fetchValuesFromHost



    this.updateForm = function ( $form, recKey, json ) {
        if ( json === false ) {
            return;
        }

        let formId  = '#' + $form.attr('id');
        let $recKey = $('[name=_rec-key]', $form);
        $recKey.val( recKey );

        var data = null;
        try {
            data = JSON.parse(json);
        } catch (e) {
            console.log('Error condition detected');
            let str = json.replace(/(<([^>]+)>)/gi, '');
            console.log(str);
            let msg = '{{ lzy-table-record-locked }}';
            if (msg.match(/^{{/)) {
                msg = 'Table is locked or not available';
            }
            lzyPopupClose();
            lzyPopup(msg);
            return false;
        }

        if ( data.res !== 'Ok') {
            this.handleException( data );
        }
        for (let key in data.data) {
            if ((typeof key === 'string') && (key.charAt(0) === '_')) {
                continue;
            }
            let val = data.data[ key ];
            let sel ='[name=' + key + ']';
            let $el = $( sel, formId );
            let type = $el.attr('type');

            if (',radio,checkbox,'.includes(type)) {
                if (typeof val[0] !== 'undefined') {
                    let tmp = val[0];
                    val = tmp;
                }
                $el.each(function () {
                    let v = $(this).val();
                    $(this).prop('checked', val.includes(v) );
                });
            } else {
                $el.val( val );
            }
            mylog('updateFromHost ' + sel + ' => ' + val, false);
        }
    }; // updateForm



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
    }; // handleException



    this.presetDefaultValues = function ( $form, placehoderInEmptyFields ) {
        let parent = this;
        // finds all 'data-default-value' instances in form and copies value to value attrib
        // default values may be defined as literals '' or as compound definition '=( x..y )', which may contain
        // references to other input fields defined as '$varName'. These will be string-replaced.
        // Example: data-default-value="=( Mr. $lastname )" => " Mr. Bond "
        if (typeof placehoderInEmptyFields === 'undefined') {
            placehoderInEmptyFields = this.waitSymbol;
        }
        if (typeof $form === 'undefined') {
            $form = $('lzy-form');
        }
        this.$form = $form;
        this.liveUpdateActive = false;

        // copy default-values to value attributs:
        $('[data-default-value]', $form).each(function () {
            let $this = $(this);

            // only preset fields with default if field is empty:
            let origVal = $this.val();
            if (origVal && (origVal !== placehoderInEmptyFields)) {
                return;
            }

            let value = $this.data('default-value');
            if (typeof value === 'string') {
                value = value.replace(/´/g, "'");
                // check whether there are compound definitions "=(...)":
                let compound = value.match(/=\(\((.*)\)\)/); // =((
                if (compound !== null) {
                    value = compound[1];
                    // check whether there is a reference to some other input field (defined as '$varName'):
                    value = parent.resolveInputVars(value);
                }

                // handle expressions to eval:
                compound = value.match(/^=eval\(\((.*?)\)\)/); // =eval((
                if (compound !== null) {
                    let expr = parent.resolveInputVars(compound[1]);
                    let code = '"use strict";return (' + expr + ')';
                    mylog(code, false);
                    value = Function(code)(); // requires 'site_ContentSecurityPolicy: false'
                }
            }

            if (!$this.hasClass('lzy-fieldset-body')) {
                $this.val(value);
            } else  {
                // $('input[value='+ value+']', $this).val(value);
                $('input[value=normal]', $this).prop('checked', true);
            }
            $this.attr('value', value);
            let lbl = $this.attr('name');
            if (typeof lbl === 'undefined') {
                lbl = $('[name]', $this).attr('name');
            }
            mylog('presetDefaultValues: ' + lbl + ' => ' + value, false);
        });


        // live-values may have been deactivated by prepending '#' to value, so re-activate all:
        $('[data-live-value]', $form).each(function () {
            let $this = $(this);
            let a = $this.attr('data-live-value');
            if (a.charAt(0) === '#') {
                $this.attr('data-live-value', a.substr(1));
            }
        });

        this.liveUpdateActive = true;
    }; // presetDefaultValues



    this.updateLiveValues = function ( $form ) {
        // on changed value within form:
        // finds all 'data-live-value' instances in form and copies value to value attrib
        // values may be of form "=( $name )" or "=eval( 'js code...' )"

        if (!this.liveUpdateActive) {
            return;
        }

        if (typeof $form === 'undefined') {
            $form = $('lzy-form');
        }
        $('[data-live-value]', $form).each(function () {
            let $this = $(this);
            let value = $this.data('live-value');
            value = value.replace(/´/g, "'");
            let compound = value.match(/^=\(\((.*)\)\)\s*$/);
            if (compound !== null) {
                value = compound[1];
                let vars = value.match(/\$(\w+)/g);
                if (vars) {
                    for (let i in vars) {
                        let v = vars[i].substr(1);
                        let sel = '[name=' + vars[i].substr(1) + ']';
                        let $src = $(sel, $form);
                        let val = '';
                        if ($src.prop('tagName') === 'SELECT') {
                            val = $('option:selected', $src).text();
                        } else {
                            val = $src.val();
                        }
                        value = value.replace(vars[i], val);
                    }
                }
            }

            compound = value.match(/^=eval\(\((.*)\)\)\s*$/);
            if (compound !== null) {
                let expr = compound[1];
                let vars = expr.match(/\$(\w+)/g);
                if (vars) {
                    for (let i in vars) {
                        let v = vars[i].substr(1);
                        let sel = '[name=' + vars[i].substr(1) + ']';
                        let $src = $(sel, $form);
                        let val = $src.val();
                        expr = expr.replace(vars[i], val);
                    }
                }
                let code = '"use strict";return (' + expr + ')';
                mylog( code, false );
                value = Function( code )(); // requires 'site_ContentSecurityPolicy: false'
            }
            $this.val( value );
            let lbl = $this.attr('name');
            if (typeof lbl === 'undefined') { lbl = ''; }
            mylog('updateLiveValues: ' + lbl + ' => ' + value, false);
        });
    }; // updateLiveValues



    this.setupOnChangeHandler = function() {
        // update liveElements when any input element is changed:
        $('.lzy-form-input-elem, .lzy-form select').change(function() {
            let $this = $( this );
            let $form = $this.closest('.lzy-form');
            if ($form.length) {
                // disable liveData for currently modified element:
                if ($this.attr('data-live-value')) {
                    $this.attr('data-live-value-inactive', $this.attr('data-live-value'));
                    $this.removeAttr('data-live-value')
                }
                // update all (remaining) liveElements:
                let $liveElems = $('[data-live-value]', $form);
                if ($liveElems.length) {
                    lzyForms.updateLiveValues($form);
                }
            }
        });
    }; // setupOnChangeHandler



    this.onOpen = function ( recKey, $form ) {
        let parent = this;
        if (typeof $form !== 'undefined') {
            this.clearForm( $form );
            parent.fetchValuesFromHost($form, recKey).then(function (json) {
                parent.updateForm( $form, recKey, json);
                parent.presetDefaultValues( $form );
                parent.updateLiveValues( $form );
            });

        } else {
            $('.lzy-form').each(function () {
                let $form = $(this);
                parent.clearForm( $form );
                parent.fetchValuesFromHost($form, recKey).then(function (json) {
                    parent.updateForm( $form, recKey, json);
                    parent.presetDefaultValues( $form );
                    parent.updateLiveValues( $form );
                });
            });
        }
    }; // openForm



    this.resolveInputVars = function (value) {
        let vars = value.match(/\$(\w+)/g);
        if (vars) {
            for (let i in vars) {
                // fetch values from input fields defined as '$varName':
                let v = vars[i]; // string of form '$varName'
                let sel = '[name=' + v.substr(1) + ']';
                let $src = $(sel, this.$form);
                let val = $src.val();
                value = value.replace(v, val);
            }
        }
        return value;
    }; // resolveInputVars


} // LzyForms

