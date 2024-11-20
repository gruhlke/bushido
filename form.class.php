<?php
require_once(dirname(__FILE__) .'/base.class.php');
require_once(dirname(__FILE__) .'/validate.class.php');


// -----------------------------------------------------------------------------
//	Field Group
// -----------------------------------------------------------------------------
class FieldGroup extends Base {
	// Related form fields
	
	public $name;							// group name
	public $description;					// group description
	protected $fields = array();			// single fields
	protected $groups = array();			// child groups
	protected $validation = array();		// validator objects
	protected $fields_with_error = array(); // keyed array list of fields that have at least one error.  value is an array of the actual errors.
	
	public function __construct($name, $fields, $validation=false, $description=false) {
		parent::__construct(false);
		$this->name = $name;
		$this->add_fields($fields);
		
		if (!$description) {
			$description = $name;
		}
		$this->description = $description;
		
		if ($validation) {
			$this->add_validation($validation);
		}
	}
	
	public function add_fields($fields) {
		// Add fields or field groups
		
		if (!is_array($fields)) {
			$fields = array($fields);
		}
		foreach ($fields as $field) {
			if (is_object($field) == false) {
				continue;
			}
			if (isset($field->fields)) {
				// Field group
				$this->groups[] = $field;
			} else {
				// Single field
				$this->fields[] = $field;
			}
		}
	}
	
	public function add_validation($validation) {
		// Add validation objects to fields
		
		if (!is_array($validation)) {
			$validation = array($validation);
		}
		
		$group_validations = array();
		$field_validations = array();
		
		// Seperate group validations specific to this group
		foreach ($validation as $validate_obj) {
			if ($validate_obj instanceof ValidGroup) {
				$validate_obj->setup($this->name, $this->description, $this->get_all_fields());
				$group_validations[] = $validate_obj;
			} else {
				$field_validations[] = $validate_obj;
			}
		}

		// This groups specific validations
		$this->validation = array_merge($this->validation, $group_validations);

		// Field groups
		foreach ($this->groups as $group) {
			$group->add_validation($field_validations);
		}
		// Single fields
		foreach ($this->fields as $field) {
			$field->add_validation($field_validations, true);
		}
	}

	public function validate($values) {
		// Run field and group level validation
		// Return true or false
		
		$valid = true;
		
		// Check any validations on this group
		foreach ($this->validation as $validation) {
			// check the validation
			if ($validation->validate($values) !== true) {
				$valid = false;
			}						
		}
				
		// Field groups
		foreach ($this->groups as $group) {
			if ($group->validate($values) !== true) {
				$valid = false;
			}
		}
		
		// Single fields
		foreach ($this->fields as $field) {
			// Field level validation
			if ($field->validate($values) !== true) {
				$valid = false;
			}
		}

		return $valid;
	}
	
	public function merge_values($arguments) {
		// Merge an array of multiple arguments (from func_get_args)
		// into a single array of values
		
		$values = array();
		foreach ($arguments as $arg) {
			if (is_array($arg)) {
				// Look for default values if more than one argument was passed
				// This corrects check box behavior
				if (count($arguments) > 1) {
					foreach($this->get_all_fields() as $field) {
						$field_name = $field->name;
						if (!isset($arg[$field_name]) and isset($arg[$field_name .'_default'])) {
							$arg[$field_name] = $arg[$field_name .'_default'];
						}
					}
				}
				$values = array_merge($values, $arg);
			}
		}
		return $values;
	}

	public function get_errors() {
		// populate with errors from all children
	
		// clear the errored fields l ist
		$this->fields_with_error = array();
			
		// Get group validation errors
		foreach ($this->validation as $validation) {
			$errors = $validation->get_errors();
			$this->error($errors);
			
			if (count($errors) > 0) {
				$this->add_field_with_error($this->name, $errors);
			}
		}
		
		// Field groups
		foreach ($this->groups as $group) {
			$errors = $group->get_errors();
			$this->error($errors);

			if (count($errors) > 0) {
				$this->add_field_with_error($group->name, $errors);
			}
		}

		// Single fields
		foreach ($this->fields as $field) {
			$errors = $field->get_errors(); 
			$this->error($errors);

			if (count($errors) > 0) {
				$this->add_field_with_error($field->name, $errors);
			}
		}
		
		// make error messages unique
		foreach ($this->fields_with_error as $field_name => $errors) {
			$this->fields_with_error[$field_name] = array_unique($errors);
		}
		
		return parent::get_errors();
	}
	
	public function add_field_with_error($field_name, $errors) {
		$errors = (!is_array($errors) ? array($errors) : $errors);
		$this->fields_with_error[$field_name] = $errors;
	}
	
	public function get_fields_with_error($include_errors_array = false) {
		// returns an array of fields that errored.  optionally can include the actual errors.
		return (!$include_errors_array && count($this->fields_with_error) > 0 ? array_keys($this->fields_with_error) : $this->fields_with_error);
	}
	
	public function output_fields($fields_to_output, $values = null) {
		// returns filtered output, returning only the named fields requested
		// arguement should be an array of field names to include in the output
		// any errors that would normally be on $this->name_error are now the fields passed in concatenated with an underscore with _error on the end.  so if 
		// 	array("myfield1", "myfield2") were passed, you need to echo myfield1_myfield2_error to see the errors list.
		
		$fields_to_output = (!is_array($fields_to_output) ? array($fields_to_output) : $fields_to_output);
		// some fields have special variables created that end with the below suffixes.  if we want the field, the special fields will be included as well
		$special_suffixes = array("_error", "_label", "_options", "_value", "_default");

		$base_output = $this->output($values);
		$output_errors = array();
		$output = array();

		foreach ($base_output as $output_key => $output_value) {
			// if this is in the array of values to get
			if (in_array($output_key, $fields_to_output)) {
				$output[$output_key] = $output_value;
				// add in any special fields found for the output key
				foreach ($special_suffixes as $suffix) {
					if (isset($base_output[$output_key . $suffix])) {
						$output[$output_key . $suffix] = $base_output[$output_key . $suffix];
					}
				}
			}
			
			// catch any errors
			if (array_key_exists($output_key, $this->fields_with_error)) {
				foreach ($this->fields_with_error[$output_key] as $field_error) {
					$output_errors[] = $field_error;
				}
			}
		}

		// generate just the errors for the requested fields
		$restore_errors = $this->error_msg;
		$this->error_msg = $output_errors;
		$output[implode("_", $fields_to_output) .'_error'] = $this->error_tpl();		
		$this->error_msg = $restore_errors;
				
		return $output;
	}
	
	public function output() {
		// Return associative array of attributes, values, and errors
		// Any number of value arrays may be passed as arguments
		// Each consecutive argument has a higher precedence than the last
		
		$arguments = func_get_args();
		$values = $this->merge_values($arguments);
		$form_vars = array();
		
		// populate the errors array
		$this->get_errors();

		// populate the form vars
		
		// Field groups
		foreach ($this->groups as $group) {
			$output = $group->output($values);
			$form_vars = array_merge($form_vars, $output);
		}
		// Single fields
		foreach ($this->fields as $field) {
			$value = isset($values[$field->name]) ? $values[$field->name] : null;
			$output = $field->output($value);
			if (is_array($output)) {
				$form_vars = array_merge($form_vars, $output);
			}
		}
		// Generate error html for this group
		$form_vars[$this->name .'_error'] = $this->error_tpl();
		// Group label
		$form_vars[$this->name .'_label'] = $this->label_html();
		
		return $form_vars;	
	}
	
	public function label_html() {
		// Return label parameters
		
		$html = "id=\"". htmlentities($this->name, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."\"";
		
		return $html;
	}
	
	public function get_all_fields() {
		// Return an array of all fields and all fields of contained groups
		
		$fields = $this->fields;

		foreach ($this->groups as $group) {
			$fields = array_merge($fields, $group->get_all_fields());
		}
		
		return $fields;
	}
	
	public function get_field_names() {
		// Return an array of all field names
		
		$names = array();
		$fields = $this->get_all_fields();
		foreach ($fields as $field) {
			$names[] = $field->name;
		}
		
		return $names;
	}
	
	public function get_field($field_name) {
		// Return first field object with $field_name
		// Return false if no matches were found
		
		$fields = $this->get_all_fields();
		foreach ($fields as $field) {
			if ($field->name == $field_name) {
				return $field;
			}
		}
		return false;
	}
}

class Field_Group extends FieldGroup {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Field
// -----------------------------------------------------------------------------
abstract class Field extends Base {
	// Form field data
	
	public $name;
	public $description;
	protected $validation = array();
	protected $valid_attributes = array("id", "name", "value", "disabled");
	protected $attributes = array();
	
	public function __construct($name, $description, $validation = false, $attributes = null) {
		parent::__construct(false);
		$this->name = $name;
		$this->description = $description;
		$this->attributes = array("id" => $name, "name" => $name, "value" => null);
		if ($validation) {
			$this->add_validation($validation);
		}
		
		if ($attributes) {
			$this->add_attributes($attributes);
		}
	}

	public function add_attributes($attributes) {
		// Setup field with new attributes
		
		if ($attributes && is_array($attributes)) {
			foreach ($attributes as $key => $value) {
				// add to the valid list so it passes
				$this->valid_attributes[] = $key;
				$this->attributes[$key] = $value;
			}
		}
	}
	
	public function add_validation($validation, $clone=false) {
		// Setup field with validation, and any additional parameters
		
		if (!is_array($validation)) {
			$validation = array($validation);
		}
		foreach ($validation as $object) {
			if ($clone == true) {
				// Used by Field Groups when assigning validators to fields
				$object = clone $object;
			}
			// Assign attributes
			if ($object instanceof ValidGroup) {
				$attributes = $object->setup($this->name, $this->description, $this);
			} else {
				$attributes = $object->setup($this->name, $this->description);
			}
			if ($attributes and is_array($attributes)) {
				foreach ($attributes as $key => $value) {
					if (array_search($key, $this->valid_attributes) !== false) {
						$this->attributes[$key] = $value;
					}
				}
			}
			$this->validation[] = $object;
		}
	}

	public function validate($values) {
		// Validate field

		// Set $value to item in array, or null
		if (isset($values[$this->name])) {
			$value = $values[$this->name];
		} elseif (isset($values[$this->name .'_default'])) {
			$value = $values[$this->name .'_default'];
		} else {
			$value = null;
		}
		
		$valid = true;
		if (is_array($value)) {
			// Handle multi select fields
			foreach ($value as $item) {
				$values[$this->name] = $item;
				if ($this->validate($values) !== true) {
					$valid = false;
				}
			}
		} else {
			// Handle single value fields
			foreach ($this->validation as $object) {
				if ($object instanceof ValidGroup) {
					// Send all values to group validators
					$valid_result = $object->validate($values);
				} else {
					// Or just one value for regular validators
					$valid_result = $object->validate($value);
				}
				if ($valid_result !== true) {
					$valid = false;
					$this->error($object->get_errors());
				}
			}
		}
		
		return $valid;
	}
	
	public function attributes_html() {
		// Return valid parameters for input tag
		$html = array();
		foreach ($this->attributes as $key => $value) {
			if (array_search($key, $this->valid_attributes) !== false) {
				$html[] = htmlentities($key, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."=\"". htmlentities($value, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."\"";
			}
		}
		if ($this->name === "fname") {
			//var_dump($html);die;
		}
		if (count($html)) {
			return " ". implode(' ', $html);
		} else {
			return null;
		}
	}
	
	public function label_html() {
		// Return label parameters
		
		$html = "for=\"". htmlentities($this->attributes['id'], HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."\"";
		
		return $html;
	}
}


// -----------------------------------------------------------------------------
//	Hidden Field
// -----------------------------------------------------------------------------
class HiddenField extends Field {
	// Hidden field output
	
	protected $valid_attributes = array("id", "name", "value");
	
	public function output($value=null) {
		// Return associative array used for template
		
		$this->attributes['value'] = $value;
		$output = array();
		$output[$this->name] = $this->attributes_html();		// input tag attributes
		
		return $output;
	}
}

class Hidden_Field extends HiddenField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Text Field
// -----------------------------------------------------------------------------
class TextField extends Field {
	// Text field output
	
	protected $valid_attributes = array("id", "name", "value", "disabled", "readonly", "maxlength");
	
	public function output($value=null) {
		// Return associative array used for template
		
		$this->attributes['value'] = $value;
		$output = array();
		$output[$this->name] = $this->attributes_html();		// input tag attributes
		$output[$this->name .'_label'] = $this->label_html();	// label tag attribute
		
		return $output;
	}
}

class Text_Field extends TextField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	File Field
// -----------------------------------------------------------------------------
class FileField extends Text_Field {
	// File field output
	
	protected $valid_attributes = array("id", "name", "disabled");
}

class File_Field extends FileField {
	// Backwards compatibility for old naming convention
}
	

// -----------------------------------------------------------------------------
//	Password Field
// -----------------------------------------------------------------------------
class PasswordField extends Field {
	// Password field output
	
	protected $valid_attributes = array("id", "name", "disabled", "maxlength");
	
	public function output($value=null) {
		// Return associative array used for template
		// Does not output value

		$output = array();
		$output[$this->name] = $this->attributes_html();		// input tag attributes
		$output[$this->name .'_label'] = $this->label_html();	// label tag attribute
		
		return $output;
	}
}

class Password_Field extends PasswordField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Check Field
// -----------------------------------------------------------------------------
class CheckField extends Field {
	// Checkbox field output
	
	public function __construct($name, $description, $validation=false, $check_value=null, $default=null) {
		// Extend constructor with $check_value
		
		parent::__construct($name, $description, $validation);
		$this->attributes['value'] = $check_value;
		$this->default_value = $default;
	}
	
	public function output($value=null) {
		// Return associative array used for template
		// $check_value refers to the value of the checkbox if checked

		$output[$this->name] = $this->attributes_html();		// input tag attributes
		if ($value == $this->attributes['value']) {
			$output[$this->name] .= ' checked="checked"';
		}
		// Add hidden field with same name and no value
		// This means the field will always show in $_POST
		$output[$this->name] .= ">\n".
			"<input type=\"hidden\" name=\"". $this->name ."_default\" value=\"". $this->default_value ."\"";
		
		$output[$this->name .'_label'] = $this->label_html();	// label tag attribute
		
		return $output;
	}
}

class Check_Field extends CheckField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Check Multi Field
// -----------------------------------------------------------------------------
class CheckMultiField extends Field {
	// Checkbox field output for multiple checkboxes
	// Creates an array of inputs
	
	private $check_inputs = array();	// label => value
	
	public function __construct($name, $description, $validation=false, $check_inputs=array()) {
		parent::__construct($name, $description, $validation);
		
		$this->check_inputs = $check_inputs;
		$this->attributes['name'] .= "[]";
	}
	
	public function output($value=null) {
		// Return associative array used for template
		// $check_value refers to the value of the checkbox if checked
		
		// Value must be an array
		if (!is_array($value)) {
			$value = array($value);
		}
		
		// Output will be an array of inputs
		$output[$this->name] = array();
		
		// Loop through each input and add to array
		foreach ($this->check_inputs as $label => $check_value) {
			// Set value and id
			$this->attributes['id'] = $this->name ."_". $check_value;
			$this->attributes['value'] = $check_value;
			// Add input html
			$output[$this->name][$label]['input_html'] = $this->attributes_html();
			if (in_array($check_value, $value)) {
				$output[$this->name][$label]['input_html'] .= ' checked="checked"';
			}
			// Add label html
			$output[$this->name][$label]['label_html'] = $this->label_html();
		}
		
		return $output;
	}
}


// -----------------------------------------------------------------------------
//	Radio Field
// -----------------------------------------------------------------------------
class RadioField extends Field {
	// Radio field output
	
	// id and value need to be set per option, so they are excluded at this level
	protected $valid_attributes = array("name", "disabled");	
	
	public function __construct($name, $description, $validation=false, $options=array()) {
		// Extend constructor with $options array
		
		parent::__construct($name, $description, $validation);
		if (!is_array($options)) {
			$options = array($options);
		}
		$this->options = $options;
	}
	
	public function output($value=null) {
		// Return associative array used for template
		
		$output = array();
		foreach ($this->options as $radio_value) {
			$id = htmlentities($this->name ."_". str_replace(" ", "_", $radio_value), HTMLENTITY_FLAG, BUSHIDO_CHARSET);
			$radio_value = htmlentities($radio_value, HTMLENTITY_FLAG, BUSHIDO_CHARSET);
			
			$output[$id] = $this->attributes_html() ." id=\"$id\" value=\"$radio_value\"";
			if ($radio_value == $value) {
				$output[$id] .= ' checked="checked"';
			}
			$output[$id .'_label'] = "for=\"$id\"";
		}
		
		return $output;
	}
}

class Radio_Field extends RadioField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Select Field
// -----------------------------------------------------------------------------
class SelectField extends Field {
	// Select / option output
	
	protected $valid_attributes = array("id", "name", "disabled", "multiple");
	
	public function __construct($name, $description, $validation=false, $options=array(), $multiple=false) {
		// Extend constructor with $options array
		
		parent::__construct($name, $description, $validation);
		if (!is_array($options)) {
			$options = array($options);
		}
		$this->options = $options;
		if ($multiple == true) {
			$this->attributes['name'] .= "[]";
			$this->attributes['multiple'] = "";
		}
	}
	
	public function output($value=null) {
		// Return associative array used for template
		
		$output = array();
		$output[$this->name] = $this->attributes_html();		// input tag attributes
		$output[$this->name .'_label'] = $this->label_html();	// label tag attribute
		$option_html = array();
		foreach ($this->options as $key => $opt_value) {
			$option = "<option value=\"". htmlentities($opt_value, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."\"";
			if (is_array($value) and array_search($opt_value, $value) !== false) {
				$option .= " selected";
			} elseif ($opt_value == $value) {
				$option .= " selected";
			}
			$option .= ">". htmlentities($key, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."</option>";
			$option_html[] = $option;
		}
		$output[$this->name .'_options'] = implode("\n", $option_html);
		
		return $output;
	}
}

class Select_Field extends SelectField {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Select Multi Field
// -----------------------------------------------------------------------------
class SelectMultiField extends SelectField {
	// Create field with multiple option = true
	
	public function __construct($name, $description, $validation=false, $options=array()) {
		parent::__construct($name, $description, $validation, $options, true);
	}
}


// -----------------------------------------------------------------------------
//	Textarea Field
// -----------------------------------------------------------------------------
class TextareaField extends Field {
	// Textarea field output
	
	protected $valid_attributes = array("id", "name", "disabled");
	
	public function output($value=null) {
		// Return associative array used for template

		$output = array();
		$output[$this->name] = $this->attributes_html();		// input tag attributes
		$output[$this->name .'_label'] = $this->label_html();	// label tag attribute
		$output[$this->name .'_value'] = htmlentities($value, HTMLENTITY_FLAG, BUSHIDO_CHARSET);	// textarea value
		
		return $output;
	}
}

class Textarea_Field extends TextareaField {
	// Backwards compatibility for old naming convention
}

?>
