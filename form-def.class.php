<?php

class FormDescriptor
{
	public $formId = '';
	public $formName = '';
	public $formElements = [];	// fields contained in this form
	public $formData = [];		// form entries previosuly submitted by user
	
	public $mailto = '';		// data entered by users will be sent to this address
	public $process = '';	
	public $method = '';	
	public $action = '';
	public $class = '';
	public $options = '';
	public $ticketHash = '';
	public $preventMultipleSubmit = false;
	public $validate = false;
	public $antiSpam = true;
	public $showData = true;
	public $replaceQuotes = true;

	public $labels = [];		// list of labels of form-elems, special case checkbox -> array
	public $names = [];			// list of names of form-elems
}

class FormElement
{
	public $type = '';		// init, radio, checkbox, date, month, number, range, text, email, password, textarea, button

	public $label = '';		// some meaningful label used for the form element
	
	public $labelInOutput = '';// some meaningful short-form of label used in e-mail and .cvs data-file
	
	public $name = '';
	
	public $required = '';	// enforces user input

	public $placeholder = '';// text displayed in empty field, disappears when user enters input field

	public $min = '';
	public $max = '';		// for numerical entries -> defines lower and upper boundry

	public $value = '';		// defines a preset value

	public $class = '';		// class identifier that is added to the surrounding div
	
	public $inpAttr = '';

	public function getAsAttribute($key)
	{
		if (isset($this->$key)) {
			$str = " $key='{$this->$key}'";
		} else {
			$str = '';
		}
		return $str;
	} // getAsAttribute

} // class FormElement

