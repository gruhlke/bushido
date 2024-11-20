<?php
require_once(dirname(__FILE__) .'/form.class.php');

// if defined and true, require the html purifier library.  library root dir must be in app path.
if (defined('USE_HTML_PURIFIER') && USE_HTML_PURIFIER) {
	$html_purifier_path = (defined('HTML_PURIFIER_PATH') ? HTML_PURIFIER_PATH : "");
	require_once($html_purifier_path . 'library/HTMLPurifier.auto.php');
}

/*
	defined meta key types:
		- field_type (string): used to create a validation control/group based on type.  anything without a type will be ignored in the validation creation.
		- class (string): defines class to use instead of the default to create a validation representation of the meta key.
		- children (string array): specific to TYPE_GROUP table entries, string array of children (ie array("name", "phone"))
		- descript (string, method, function): description for the meta table key
		- validation (instance or array of Validator instances or method/function): instances of type Validator to use for validation of this meta table entry
		- field_options: any extra options needed by the field type.  can be method, function, array, string.
		- field_obj: stores reference to the field object
		- set_value: function or method only to be called to filter the data.  must have 2 params, field and value.
		- get_value: function or method only to be called to filter the data.  must field param.
*/

abstract class Meta_Table extends Base
{
	protected $meta_table = Array(""=>"");
	protected $field_types = array(
			"TYPE_GROUP" 			=> "FieldGroup", 
			"TYPE_TEXT" 			=> "TextField", 
			"TYPE_SELECT" 			=> "SelectField",
			"TYPE_MULTI_SELECT" 	=> "SelectMultiField",
			"TYPE_RADIO" 			=> "RadioField",
			"TYPE_CHECKBOX"			=> "CheckField",
			"TYPE_MULTI_CHECKBOX"	=> "CheckMultiField",
			"TYPE_HIDDEN" 			=> "HiddenField",
			"TYPE_TEXTAREA"			=> "TextareaField"
		);
	protected $root_group = null;
	protected $root_group_name = null;
	protected $data = null;
	protected $manual_create_fields = false; // true to no call create_fields on construction.  it must be called manually later in order to have the field objects.
	protected $create_fields_on_set = false; // set to true to run create_fields on call to set().  this will alleviate dynamic validations based on data from record.
	protected $validate_called = false;
	protected $word_character_replace_array = array();

	// for use with the HTMLPurifier library.  set to true to enable purification.
	protected $purify_field_types = array("TYPE_TEXT", "TYPE_TEXTAREA");
	private $purifier_obj = null;
	
	// can provide a huge boost in performance but if user functions are defined after the very first instance of a meta_table class has been created (rare) it can cause problems.
	// to turn on, define USE_DEFINED_FUNCTION_CACHE and set to true.
	static private $defined_functions_cache = array();
	
	// constructor		
    function __construct($root_group_name = null, $alt_db = null)
    {    	
    	parent::__construct($alt_db);
    	
    	$this->root_group_name = ($root_group_name ? $root_group_name : "root_group") ;

		$this->initialize_object(true);
		
		$this->init_word_character_replace_array();
    }

	// initializes the meta table object with a blank record.
	public function initialize_object($call_set_before = false) {	

    	if (!$call_set_before) { // initialize a blank dataset to defaults			
			$this->set();			
		}
	
    	$this->init_meta_table();
		
		if (!$this->manual_create_fields) {
			$this->create_fields();
		} else { // create a default root group
			$this->root_group = new $this->field_types["TYPE_GROUP"]($this->root_group_name, null);
		}
		
    	if ($call_set_before) { // initialize a blank dataset to defaults			
			$this->set();			
		}
	}
		
    // initializes meta info for the fields
    // it is important to note that groups must be sequenced properly (ie small to big) in order for builing to work
    // correctly.
    abstract protected function init_meta_table();

	public function get_meta_table() {
		return $this->meta_table;
	}
	
	public function set($data = null) {
		if (is_array($data)) {
			foreach ($this->meta_table as $meta_table_key => $key_info) {
				$data_key = $meta_table_key;
				// if this is a checkbox, update the data key to the default if needed
				if ($this->get_meta_table_value($meta_table_key, "field_type") == "TYPE_CHECKBOX" && !isset($data[$meta_table_key]) && isset($data[$meta_table_key . "_default"])) {
					$data_key = $meta_table_key . "_default";
				}
				if (isset($data[$data_key])) {
					$this->set_value($meta_table_key, $data[$data_key]);
				}
			}
		} else { // run the defaults code
			foreach ($this->meta_table as $meta_table_key => $key_info) {
				$this->set_value($meta_table_key, null);
			}
		}
		
		if ($this->create_fields_on_set) {
			$this->create_fields();
		}
	}

	protected function purify_string($string) {		
		$this->init_html_purifier();
		return $this->purifier_obj->purify($string);
	}
	
	protected function init_html_purifier() {
		if (!$this->purifier_obj) {
			$config = $this->get_html_purifiy_config();
			$this->purifier_obj = new HTMLPurifier($config);			
		}
	}
	
	protected function get_html_purifiy_config() {
		$config = HTMLPurifier_Config::createDefault();

		$cache_path = sys_get_temp_dir() . '/HTMLPurifier/Serializer';
		if (!is_dir($cache_path)) mkdir($cache_path, 0755, true);
		$config->set('Cache.SerializerPath', $cache_path);	

		$config->set('Core.Encoding', (defined('HTML_PURIFIER_ENCODING') ? HTML_PURIFIER_ENCODING : 'ISO-8859-1')); 
		$config->set('HTML.Doctype', (defined('HTML_PURIFIER_DOCTYPE') ? HTML_PURIFIER_DOCTYPE : 'HTML 4.01 Transitional')); 
		
		return $config;
	}
	
	protected function convert_word_characters($string) {
		foreach ($this->word_character_replace_array as $word_char => $ascii_char) {
			$string = str_replace($word_char, $ascii_char, $string);
		}
		return $string;
	}		

	protected function init_word_character_replace_array() {
	
		$this->word_character_replace_array = array("�" => "'", "�" => "'", "�" => '"', "�" => '"', "�" => "-", "�" => "_");
		$this->word_character_replace_array[chr(133)] = "_"; // "�"
		$this->word_character_replace_array[chr(145)] = "'"; // "�"
		$this->word_character_replace_array[chr(146)] = "'"; // "�" 
		$this->word_character_replace_array[chr(147)] = '"'; // "�"
		$this->word_character_replace_array[chr(148)] = '"'; // "�"
		$this->word_character_replace_array[chr(150)] = "-"; // "�"
		
		return $this->word_character_replace_array;
	}
	
	// sets the underlying value of a meta table key
	// NOTE: if method/function must return value to set
	public function set_value($meta_table_key, $value) {
		
		// update any word chars only if string
		if (is_string($value) === true) {
			// get special override to allow word characters
			$allow_word = $this->get_meta_table_value($meta_table_key, "allow_word_characters");
			
			// if not specifically set to allow word characters, remove them
			if (!$allow_word) {
				$value = $this->convert_word_characters($value);
			}
		}

		// if to use html purifier
		if (is_string($value) && defined('USE_HTML_PURIFIER') && USE_HTML_PURIFIER) {		
			// make sure this is a type that we want to purify
			if (in_array($this->get_meta_table_value($meta_table_key, "field_type"), $this->purify_field_types)) {
				$skip_purify = $this->get_meta_table_value($meta_table_key, "skip_purify_html");
				if (!$skip_purify) {
					$value = $this->purify_string($value);
				}
			}
		}
		
		$set = $this->get_meta_table_value($meta_table_key, "set_value");
		if ($this->is_method($set)) {
			$this->data[$meta_table_key] = $this->$set($meta_table_key, $value);
		} elseif ($this->is_user_function($set)) {
			$this->data[$meta_table_key] = $set($meta_table_key, $value);
		} else {
			// default the value if can
			if ($this->can_default_value($value)) {
				$value = $this->get_default($meta_table_key);
			}
			$this->data[$meta_table_key] = $value;
		}
	}
	
	// returns the internal representation of the data.  use only if needed (usually inside of a getter).  use get() or get_value() instead.
	public function get_raw_data($field = null) {
		return (!$field ? $this->data : $this->data[$field]);
	}
	
	// returns an array representing all the data
	public function get($fields = null) {
		$fields = (is_null($fields) ? array_keys($this->meta_table) : $fields);
		$fields = (!is_array($fields) ? array($fields) : $fields);
		
		$data = null;
				
		foreach ($fields as $meta_table_key) {
			$data[$meta_table_key] = $this->get_value($meta_table_key);
		}
		
		return $data;
	}

	// returns the underlying data value for the passed meta table key or default if the value didnt exist
	public function get_value($meta_table_key, $default = null) {
				
		// if no default passed, call the default method/function if there is one
		if ($this->can_default_value($default)) $default = $this->get_default($meta_table_key);

		$value = $this->get_meta_table_value($meta_table_key, "get_value", $default);
		
		if ($this->is_method($value)) {
			return $this->$value($meta_table_key);
		} elseif ($this->is_user_function($value)) {
			return $value($meta_table_key);
		} else {
			return (!isset($this->data[$meta_table_key]) || $this->can_default_value($this->data[$meta_table_key]) ? $value : $this->data[$meta_table_key]);
		}
	}

	public function get_value_callback($parameters = array()) {
		$meta_table_key = isset($parameters["callback_argument"]) ? $parameters["callback_argument"] : null;
		$value = isset($parameters["value"]) ? $parameters["value"] : null;
		return (!isset($this->data[$meta_table_key]) || $this->can_default_value($this->data[$meta_table_key]) ? $value : $this->data[$meta_table_key]);
	}

	// validates a subset of the full field list
	// CAUTION:  this swaps out the root_group node for a custom instance of the object.
	public function validate_fields($fields_to_validate, $values = null, $bDontSet = false) { 

		if ($values && !$bDontSet) {
			$this->Set($values);
			$values = $this->get();
		} else {
			$values = (!$values ? $this->get() : $values);
		}

		$this->create_fields($fields_to_validate);
		$this->validate_called = true;
				
		if (!$this->root_group) {
			trigger_error("Root Group Not Found", E_USER_ERROR);
		}
		
		return $this->root_group->validate($values);
	}
	
	public function validate($values = null, $bDontSet = false) {		
		if ($values && !$bDontSet) {
			$this->Set($values);
			$values = $this->get();
		} else {
			$values = (!$values ? $this->get() : $values);
		}
		
		// rebuild the fields in case anything has changed
    	if (!$this->manual_create_fields) {
			$this->create_fields();	
			$this->validate_called = true;
		}

		if (!$this->root_group) {
			trigger_error("Root Group Not Found", E_USER_ERROR);
		}

		return $this->root_group->validate($values);
	}

	// returns specific fields instead of all fields
	public function output_fields($fields_to_output, $values = null) {
		$values = (!$values ? $this->get() : $values);

		return $this->root_group->output_fields($fields_to_output, $values);
	}

	public function output($values = null) {		
		
		$values = (!$values ? $this->get() : $values);
		
		// if we are to create the fields and validate has not been called this instance, of output then create the fields
    	if (!$this->manual_create_fields && !$this->validate_called) $this->create_fields();

		if (!$this->root_group) {
			trigger_error("Root Group Not Found", E_USER_ERROR);
		}

		return $this->root_group->output($values);
	}

	public function get_root() {
		return $this->root_group;
	}

	public function error($error_msg) {
		$this->root_group->error($error_msg);
	}

	public function get_errors() {
		return $this->root_group->get_errors();
	}
		
	public function get_fields_with_error($force_get_errors = false, $include_errors_array = false) {
		if ($force_get_errors) $this->get_errors();
		return $this->root_group->get_fields_with_error($include_errors_array);
	}
	
	// returns the description for a meta_table_key
	// NOTE: can be overridden by using a function or method as the meta value.  function must take meta_key as param even if not used.
	public function get_descript($meta_table_key) {
		return $this->process_meta_table_value($meta_table_key, array("info_key" => "descript"));
	}

	public function process_meta_table_value($meta_table_key, $parameters = array()) {
		if (!isset($parameters["default"])) {
			$parameters["default"] = null;
		}
		if (!isset($parameters["value"]) && isset($parameters["info_key"])) {
			$parameters["value"] = $this->get_meta_table_value($meta_table_key, $parameters["info_key"], $parameters["default"]);
		}
		return $this->process_callback($meta_table_key, $parameters);
	}

	public function display_debug_backtrace() {
		foreach (debug_backtrace() as $level) {
			var_dump($level["file"] . " " . $level["line"] . " " . $level["function"] . " " . $level["class"] . "  " . $level["type"]);
			foreach ($level["args"] as $arg) {
				if (is_object($arg)) {
					var_dump("object");
				} else {
					var_dump($arg);
				}
			}
		}
	}

	public function process_callback($callback_argument, $parameters = array()) {
		if (is_array($parameters)) {
			$value = isset($parameters["value"]) ? $parameters["value"] : null;
		} else {
			$value = $parameters;
		}
		
		if ($this->is_method($value)) {
			return $this->$value($callback_argument);
		} elseif ($this->is_user_function($value)) {
			return $value($callback_argument);
		} else {
			if (is_array($parameters) && array_key_exists("callback", $parameters)) {
				return $this->process_callback(array(
					"callback_argument" => $callback_argument,
					"value" =>  $value), array(
					"value" => $parameters["callback"]));
			} else {
				return $value;
			}
		}
	}
    // returns a value from the meta table or passed $default if it doesnt exist
	public function get_meta_table_value($meta_table_key, $info_key, $default = null)
	{
		if (!isset($this->meta_table[$meta_table_key]) || !isset($this->meta_table[$meta_table_key][$info_key])) {
			return $default;
		} else {
			return $this->meta_table[$meta_table_key][$info_key];
		}
	}

	// sets a value in the meta table
	public function set_meta_table_value($meta_table_key, $info_key, $key_value)
	{
		$this->meta_table[$meta_table_key][$info_key] = $key_value;
	}
	
	// removes a meta table key or info key from the meta table
	public function remove_meta_table_info($meta_table_key, $info_key = null) {
		if (!$info_key) {
			unset($this->meta_table[$meta_table_key]);
		} elseif (isset($this->meta_table[$meta_table_key][$info_key])) {
			unset($this->meta_table[$meta_table_key][$info_key]);
		}
	}
	
	// returns an array of all meta keys with a certain meta information set to a certain value
	// if value is not passed, the meta info key must simply exist to be included
	public function get_meta_table_keys_with_meta_info_key($info_key, $value = null, $return_meta_info_array = false, $return_not = false) {
		$retval = null;

		foreach ($this->meta_table as $meta_table_key => $meta_info) {
			$include_in_results = (isset($meta_info[$info_key]) && ($value === null || $meta_info[$info_key] == $value));
			if ((!$return_not && $include_in_results) || ($return_not && !$include_in_results)) {
				if ($return_meta_info_array) {
					$retval[$meta_table_key] = $meta_info;
				} else {
					$retval[] = $meta_table_key;
				}
			}
		}

		return $retval;
	}

	// swaps the value of a meta info
	// if toggle relations is not passed, then the meta info value is logically inverted
	public function toggle_meta_table_info_for_all_keys($info_key, $toggle_relations = null) {

		foreach ($this->meta_table as $meta_table_key => $meta_info) {
			$meta_info_value = $meta_info[$info_key];
			if (isset($meta_info_value)) {
				if ($toggle_relations === null) {
					$this->meta_table[$meta_table_key][$info_key] = !$meta_info_value;
				} elseif (isset($toggle_relations) && is_array($toggle_relations) && count($toggle_relations) > 0) {
					$meta_info_swap_value = $toggle_relations[$meta_info_value];
					if (isset($meta_info_swap_value)) {
						$this->meta_table[$meta_table_key][$info_key] = $meta_info_swap_value;
					} else {
						$meta_info_swap_value = array_search($meta_info_value, $toggle_relations);
						if ($meta_info_swap_value === false) {
							$this->meta_table[$meta_table_key][$info_key] = $meta_info_swap_value;
						}
					}
				}
			}
		}
	}
		
	// on_load callback should return true if successful.  returning false will cancel the on_load routines.
	// on_load should use get_value to determine what is to be loaded (ie a key is not passed into the on_load function)
	public function load($meta_table_keys = null) {
		// process the on_load routines		
		return $this->process_meta_table_callback("on_load", $meta_table_keys);		
	}

	// on_save callback should return true if successful.  returning false will cancel the on_save routines.
	public function save($meta_table_keys = null) {
		// process the on_save routines
		return $this->process_meta_table_callback("on_save", $meta_table_keys);
	}

// -----------------------------------------------------------------------------
// protected / private
// -----------------------------------------------------------------------------

	// calls the function passed for each key that has it defined.  true is returned if all were successful.  callback functions should return true or false.
	protected function process_meta_table_callback($meta_table_callback, $meta_table_keys = null) {
		// grab all entries with an callbacl
		$on_callback_meta_keys = $this->get_meta_table_keys_with_meta_info_key($meta_table_callback);
		$on_callback_success = true;
		
		if (is_array($on_callback_meta_keys)) {
			foreach ($on_callback_meta_keys as $on_callback_meta_key) {
				$on_callback_success &= $this->process_callback($on_callback_meta_key, $this->get_meta_table_value($on_callback_meta_key, $meta_table_callback));
			}
		}
		return $on_callback_success;
	}

	// returns a default value for a meta_key from the meta info
	// NOTE: can be overridden by using a function or method as the meta value.  function must take meta_key as param even if not used.
	protected function get_default($meta_table_key, $default = null) {
	
		$default = $this->get_meta_table_value($meta_table_key, "default", $default);
		
		if ($this->is_method($default)) {
			return $this->$default($meta_table_key);
		} elseif ($this->is_user_function($default)) {
			return $default($meta_table_key);
		} else {
			return $default;
		}
	}

	// returns true if the value passed in can be defaulted
	protected function can_default_value($value) {
		return ($value === null || $value === '0000-00-00 00:00:00');
	}

	// returns a field_options value for a meta_key from the meta info
	// NOTE: can be overridden by using a function or method as the meta value.  function must take meta_key as param even if not used.
	protected function get_field_options($meta_table_key, $default = null) {
		return $this->process_meta_table_value($meta_table_key, array("info_key" => "field_options", "default" => $default));
	}

	protected function get_validation($meta_table_key, $default = null) {
		return $this->process_meta_table_value($meta_table_key, array("info_key" => "validation", "default" => $default));
	}
	
	// returns true if the passed value is a function
	protected function is_function($to_check) {
		return (!empty($to_check) && !is_object($to_check) && !is_array($to_check) && function_exists($to_check));
	}

	public function is_user_function($to_check) {
		$is_user_function = false;
		if (!empty($to_check) && is_string($to_check)) {
			$defined_user_functions = $this->get_user_functions();		
			$is_user_function = (in_array($to_check, $defined_user_functions));
		}
		return $is_user_function;
	}

	protected function get_user_functions() {
		if (defined('USE_DEFINED_FUNCTION_CACHE') && USE_DEFINED_FUNCTION_CACHE) {
			meta_table::$defined_functions_cache = (!meta_table::$defined_functions_cache ? get_defined_functions() : meta_table::$defined_functions_cache);
			$defined_functions = meta_table::$defined_functions_cache['user'];
		} else {
			$defined_functions = get_defined_functions();
			$defined_functions = $defined_functions['user'];
		}
		return $defined_functions;
	}
	
	// returns true if the passed value is a method of this class
	protected function is_method($to_check) {
		return (!empty($to_check) && !is_object($to_check) && !is_array($to_check) && method_exists($this, $to_check));
	}

	// returns true if manual create fields is set
	public function is_manual_create_fields() {
		return $this->manual_create_fields;
	}
	
	// creates all the defined field objects and their validations
	protected function create_fields($field_list = null) {
		$fields = $this->get_meta_table_keys_with_meta_info_key("field_type");
		$groups = null;
		$field_list = ($field_list && !is_array($field_list) ? array($field_list) : $field_list);
		
		// keep list of all constructed fields
		$constructed_fields = null;
		
		if (is_array($fields)) {
			foreach ($fields as $meta_table_key) {
				if (!isset($field_list) || !$field_list || (is_array($field_list) && in_array($meta_table_key, $field_list))) {
					$field_type = $this->get_meta_table_value($meta_table_key, "field_type");
				
					// store the groups for later use
					if ($field_type == "TYPE_GROUP") {
						$groups[] = $meta_table_key;
					} else {
						$class = $this->get_meta_table_value($meta_table_key, "class", $this->field_types[$field_type]);
						$options = $this->get_field_options($meta_table_key);
						
						// create the field
						$field_obj = new $class(
								$meta_table_key, 
								$this->get_descript($meta_table_key), 
								$this->get_validation($meta_table_key),
								$options,
								($field_type == "TYPE_CHECKBOX" ? $this->get_default($meta_table_key) : null)
							);
						
						$field_obj->add_attributes($this->get_meta_table_value($meta_table_key, "additional_attributes"));
						
						$this->set_meta_table_value($meta_table_key, "field_obj", $field_obj);
						$constructed_fields[] = $field_obj;
					}
				}
			}
		}

		// keep list of the children that are members of groups
		$children_members_of_groups = array();
		// keep list of all constructed groups
		$constructed_groups = array();
			
		// groups need to be last
		if (is_array($groups)) {
			
			// for each group, get the children and then construct the group
			foreach ($groups as $group) {

				// get the class to use for the group
				$class = $this->get_meta_table_value($group, "class", $this->field_types['TYPE_GROUP']);
				// children belonging to this group
				$children = $this->get_meta_table_value($group, "children");
				
				$child_fields = null;

				// build an array of children
				if ($children && is_array($children)) {
					
					foreach ($children as $child) {
						$field_obj = $this->get_meta_table_value($child, "field_obj");
						if ($field_obj) {
							// pass on the group data attributes to the child
							if (is_subclass_of($field_obj, 'Field')) {
								$field_obj->add_attributes($this->get_meta_table_value($group, "additional_attributes"));
							}

							$child_fields[] = $field_obj;							
						}
					}
										
					// if we have children, create the group
					if (is_array($child_fields)) {																		
						$group_obj = new $class(
								$group, 
								$child_fields, 
								$this->get_validation($group),
								$this->get_descript($group)
							);
														
						$this->set_meta_table_value($group, "field_obj", $group_obj);
							
						$children_members_of_groups = ($children_members_of_groups ? array_merge($children_members_of_groups, $child_fields) : $child_fields);
						$constructed_groups[] = $group_obj;
					}
				}				
			}
		}

		// create the root groups and fields
		$for_root = $constructed_groups;
		
		if (is_array($constructed_fields)) {
			foreach ($constructed_fields as $field_obj) {
				if (!in_array($field_obj, $children_members_of_groups)) {
					$for_root[] = $field_obj;
				}
			}
		}
		
		$this->root_group = new $this->field_types['TYPE_GROUP'](
								$this->root_group_name, 
								$for_root
							);
	}
}

/*
	meta keys:
		- db_field
		- db_readonly 
*/
abstract class Record_Table extends Meta_Table
{
	protected $table_name;
	protected $key_field;
	protected $auto_key_field = true; // set to false if key field value is not an autogenerated id
	protected $fetch_mode = 0; // ADODB_FETCH_DEFAULT (not using constant to avoid issues where definition of constant may not be included at the time this class is included)
	protected $old_data = array();
	protected $retain_dirty = false;
	protected $getrowassoc_case = 0; // default to lower
	protected $quotestring_numbers = true; // true to quotestring number values when saving
	
	// stores record action being performed
	protected $current_record_action = "";
	
	const RECORD_ACTION_SAVE = "save";
	const RECORD_ACTION_LOAD = "load";
	const RECORD_ACTION_DELETE = "delete";
	
	// constructor		
    function __construct($table_name, $key_field, $root_group_name = null, $alt_db = null)
    {    	
    	$this->table_name = $table_name;
    	$this->key_field = $key_field;    	
    	parent::__construct($root_group_name, $alt_db);
    	
    }

	public function get_table_name() {
		return $this->table_name;
	}
    
	public function get_key_field() {
		return $this->key_field;
	}
	
	public function set($data = null, $is_from_load = false) {
		parent::set($data);
		
		// clear out old data if the set is clearing data
		if (is_null($data)) { 
			$this->old_data = [];
		}
	}
    
    public function get_id() {
		return $this->get_value($this->key_field);
    }
        
    public function is_new($id = null) {
		$id = (!$id ? $this->get_id() : $id);

		if ($this->auto_key_field) {
			return !(is_numeric($id) && $id >= 1);
		} else { // manually check
			$check_sql = "SELECT " . $this->key_field . 
			" FROM " . $this->table_name . 
			" WHERE " . $this->where_clause($id);		
			$new_check = $this->db->Execute($check_sql);
			return (!$new_check || $new_check->EOF);
		}
    }

	public function load_readonly($id = null) {
		return $this->load($id, $this->get_meta_table_keys_with_meta_info_key("db_readonly", true));
	}

	public function load($id = null, $fields = null) {
		$this->current_record_action = Record_Table::RECORD_ACTION_LOAD;
	
		// getrowassoc call may not be compatible
		$this->db->push_fetch_mode($this->fetch_mode);
		$is_loaded = false;
		$id = (!$id ? $this->get_id() : $id);
		
		if ($id) {
			$field_sql = '';
			$cnt = 0;
			
			$fields = (!$fields ? $this->get_meta_table_keys_with_meta_info_key("db_field", true) : (!is_array($fields) ? array($fields) : $fields));

			if (is_array($fields)) {

				foreach ($fields as $meta_table_key) {
					$field_as = $this->get_meta_table_value($meta_table_key, "field_as");
				
					$field_sql .= ($cnt > 0 ? ", " : "") . (!empty($field_as) ? $field_as . ' AS ' : '') .  $this->db->column_wrapper_left . $meta_table_key . $this->db->column_wrapper_right;
					$cnt++;
				}
				
				$sql = 
					"SELECT " . $field_sql . 
					" FROM " . $this->table_name . 
					" WHERE " . $this->where_clause($id);

				$results = $this->db->execute($sql);	

				if ($this->db->results_check($results, true)) {
					$this->set($results->GetRowAssoc($this->getrowassoc_case), true);
					
					$is_loaded = true;
				}
			} else {
				$this->error("No Database Fields Found");
			}
		} else {
			$this->error("Unable to determine ID");
		}

		$this->db->pop_fetch_mode();

		if ($is_loaded && $this->retain_dirty) { // setup old data
			$this->old_data = $this->data;
		}
		
		$is_loaded &= parent::load($fields);

		$this->current_record_action = null;
		
		return $is_loaded;
	}

	public function create_save_sql($parameters = array()) {
		$parameters = array_merge(array(
			"fields" => null,
			"is_new" => $this->is_new(),
			"include_readonly_fields" => false), $parameters);
		$fields = $parameters["fields"];
		if (isset($fields)) {
			$db_fields = is_array($fields) ? $fields : array($fields);
		} else {
			$db_fields = $this->get_meta_table_keys_with_meta_info_key("db_field", true);
		}
		if ($this->auto_key_field) { // skip the UID field if autogen
			$key_field_index = array_search($this->key_field, $db_fields);
			if ($key_field_index !== false) {
				unset($db_fields[$key_field_index]);
			}
		}
		if (is_array($db_fields)) {
			$readonly_fields = $parameters["include_readonly_fields"] ? null : $this->get_meta_table_keys_with_meta_info_key("db_readonly", true);
			$fields = is_array($readonly_fields) ? array_diff($db_fields, $readonly_fields) : $db_fields;
			$value_list = "";
			$is_new = $parameters["is_new"];
			$cnt = 0;
			
			$insert_field_list = array();
			
			// build the insert/update statement
			foreach ($fields as $meta_table_key) {
				$value = $this->get_value($meta_table_key);
				$db_null = $this->get_meta_table_value($meta_table_key, "db_null");
				// get the field_as value if it exists, if not use the meta_key
				$field_as = $this->get_meta_table_value($meta_table_key, "field_as", $meta_table_key);
				$insert_field_list[] = $field_as;
				
				$value_list .= ($cnt++ > 0 ? ", " : "") . (!$is_new ? $this->db->column_wrapper_left . $field_as . $this->db->column_wrapper_right . " = " : "") . ($db_null && is_null($value) ? "NULL" : ($this->quotestring_numbers || is_string($value) || is_null($value) ? $this->db->qstr((is_null($value) ? "" : $value)) : $value));
			}
			if ($is_new) {
				return "INSERT INTO " . $this->table_name . "(" . implode(", ", $insert_field_list) . ") VALUES (" . $value_list . ")";
			} else { 
				return "UPDATE " . $this->table_name . " SET " . $value_list . " WHERE " . $this->where_clause();
			}
		}
		return null;
	}

	public function save($fields = null) {

		$this->current_record_action = Record_Table::RECORD_ACTION_SAVE;

		$is_saved = false;
		$is_new = $this->is_new();
		$updated_data = array();
		
		$sql = ($is_new ? "INSERT INTO" : "UPDATE") . " " . $this->table_name;
		$where = (!$is_new ? "WHERE " . $this->where_clause() : "");
		$cnt = 0;

		$fields = (!$fields ? $this->get_meta_table_keys_with_meta_info_key("db_field", true) : (!is_array($fields) ? array($fields) : $fields));

		if (is_array($fields)) {
			$field_list = "";
			$value_list = "";
		
			// build the insert/update statement
			foreach ($fields as $meta_table_key) {
				$value = $this->get_value($meta_table_key);
				$db_null = $this->get_meta_table_value($meta_table_key, "db_null");
				
				if ($meta_table_key != $this->key_field || !$this->auto_key_field) { // skip the UID field if autogen
					if (!$this->get_meta_table_value($meta_table_key, "db_readonly") && (!$this->retain_dirty || $this->is_dirty($meta_table_key, $value))) { // if not readonly field and is dirty
						// get the field_as value if it exists, if not use the meta_key
						$field_as = $this->get_meta_table_value($meta_table_key, "field_as", $meta_table_key);
						
						$updated_data[$meta_table_key] = $value;
						$field_list .= ($is_new ? ($cnt > 0 ? ", " : "") . $this->db->column_wrapper_left . $field_as . $this->db->column_wrapper_right : "");
						$value_list .= ($cnt > 0 ? ", " : "") . (!$is_new ? $this->db->column_wrapper_left . $field_as . $this->db->column_wrapper_right . " = " : "") . ($db_null && is_null($value) ? "NULL" : ($this->quotestring_numbers || is_string($value) || is_null($value) ? $this->db->qstr((is_null($value) ? "" : $value)) : $value));
						$cnt++;
					}
				}
			}
			
			if ($is_new) {
				$sql .= " (" . $field_list . ") VALUES (" . $value_list . ")";
			} else { 
				$sql .= " SET " . $value_list;
			}
			
			$sql .= " " . $where;

			$is_saved = ($cnt > 0 ? $this->db->execute($sql) : true);
			if(isset($_SESSION['debug'])) {
				echo $sql.'<br>';
				var_dump($is_saved);
				echo '<br><br>';
				unset($_SESSION['debug']);
			}
			
			if (!$is_saved) {
				$this->error("Unable to save to " . $this->table_name);
			} else {
				if ($is_new  && $this->auto_key_field) {
					$this->set_value($this->key_field, $this->db->Insert_ID($this->table_name, $this->key_field));
				}
			}
		} else {
			$this->error("No Database Fields Found");
		}
		$is_saved = (parent::save($fields) ? $is_saved : false);

		if ($is_saved && $this->retain_dirty) { // setup old data
			$this->old_data = array_merge($this->data, $updated_data);
		}

		$this->current_record_action = null;
		
		return $is_saved;
	}

	public function delete($id = null) {
		$this->current_record_action = Record_Table::RECORD_ACTION_DELETE;

		$id = (!$id ? $this->get_id() : $id);

		$sql = "DELETE FROM " . $this->table_name . " WHERE " . $this->where_clause($id);

		$is_deleted = $this->db->execute($sql);
		if (!$is_deleted) {
			$this->error("Unable to delete from " . $this->table_name);
		}
		
		$this->current_record_action = null;

		return $is_deleted;		
	}
	
	// override for custom load and save where clause
	public function where_clause($id = null) {
		$id = (is_null($id) ? $this->get_id() : $id);
		return $this->key_field . " = " . ($this->quotestring_numbers || is_string($id) ? $this->db->qstr($id) : $id);
	}

	public function update_field($field, $value, $force_update = false, $id = null) {
		return $this->update_fields(array($field => $value), $force_update, $id);
	}
	
	public function update_fields($fields, $force_update = false, $id = null) {
		$is_updated = false;
		$id = (!$id ? $this->get_id() : $id);
		if ($id) {
			$sql = "UPDATE " . $this->table_name . " SET ";
			$add_comma = false;
			foreach ($fields as $field => $value) {
				if ($this->get_meta_table_value($field, "db_field") && ($force_update || !$this->get_meta_table_value($field, "db_readonly"))) { // if not readonly field
					// get the field_as value if it exists, if not use the meta_key
					$field_as = $this->get_meta_table_value($field, "field_as", $field);
					
					$sql .= ($add_comma ? ", " : "") . $this->db->column_wrapper_left . $field_as . $this->db->column_wrapper_right . " = " . ($this->quotestring_numbers || is_string($value) || is_null($value) ? $this->db->qstr((is_null($value) ? "" : $value)) : $value) . " ";
					$add_comma = true;
				}
			}
			$sql .= "WHERE " . $this->where_clause($id);
			
			// only run if we have had at least one field updated
			if ($add_comma) {
				$is_updated = $this->db->results_check($this->db->execute($sql));
			}
			
			if (!$is_updated) {
				$this->error("Unable to update fields.");
			} else {
				if ($id == $this->get_id()) {
					// if successful update local representation of field
					foreach ($fields as $field => $value) {
						if ($force_update || !$this->get_meta_table_value($field, "db_readonly")) { // if not readonly field
							$this->set_value($field, $value);
						}
					}
				}
			}
		} else {
			$this->error("Unable to determine ID");
		}
		
		return $is_updated;		
	}	

	public function load_fields($fields, $set = false, $id = null) {
		// getrowassoc is not compatible with some settings
		$this->db->push_fetch_mode($this->fetch_mode);

		$values = null;
		$single_passed = null;		
		$id = (!$id ? $this->get_id() : $id);
		
		if (!is_array($fields)) {
			$single_passed = $fields;
			$fields = array($fields);
		}
		
		if ($id) {			
			$field_sql = '';
			
			foreach ($fields as $field) {
				$field_as = $this->get_meta_table_value($field, "field_as");
				$field_sql .= ($field_sql ? ", " : "") . (!empty($field_as) ? $field_as . ' AS ' : '') .  $this->db->column_wrapper_left . $field . $this->db->column_wrapper_right;
			}
						
			$sql = "SELECT " . $field_sql . " FROM " . $this->table_name . " ";
			$sql .= "WHERE " . $this->where_clause($id);

			$rs = $this->db->execute($sql);
			if (!$rs || $rs->RecordCount() != 1) {
				$this->error("Unable to load field " . $field);
			} else {

				$values = $rs->GetRowAssoc($this->getrowassoc_case);
				
				if ($set) { // use these values for the current fields
					foreach ($values as $field => $value) {
						$this->set_value($field, $value);
						// reset the old data
						if ($this->retain_dirty) {
							$this->old_data[$field] = $this->get_value($field);
						}
					}
				}			
			}			
		} else {
			$this->error("Unable to determine ID");
		}

		$this->db->pop_fetch_mode();

		return (($single_passed  && $values && $single_passed != '*') ? $values[$single_passed] : $values);
	}	

	public function create_meta_table_select_clause($fields = null) {
		if ($fields === null || !is_array($fields)) {
			if ($fields === "*" || $fields === " TOP 1 *") {
				return $fields;
			}
			$fields = $fields === null ? $this->get_meta_table_keys_with_meta_info_key("db_field", true) : array($fields);
		}
		return $this->get_table_name() . '.' . $this->db->column_wrapper_left .
			implode($this->db->column_wrapper_right . ', ' . $this->get_table_name() . '.' . $this->db->column_wrapper_left, $fields) . $this->db->column_wrapper_right;
	}
	
	// returns the enums for a field/meta_table_key as an array
	public function get_enum_options($meta_key) {
		static $enum_options;

		if (empty($enum_options[$this->table_name][$meta_key])) {
			$meta_columns = $this->db->MetaColumns($this->table_name);

			foreach ($meta_columns as $column_key => $column_info) {
				if (preg_match('~^(set)\((.*)\)$~i', $column_info->type, $set_values)) {
					$column_info->type = 'set';
					$column_info->enums = explode(',', $set_values[2]);
				}
				
				// there is a bug in the mysqli driver that for enum types it does not create the enums property on the column object.  this is a fix for that issue.
				if ($column_info->type == "enum" && (!isset($column_info->enums) || !is_array($column_info->enums))) {
					// retrieve the meta info for this specific column
					$meta_column_info_sql = sprintf($this->db->metaColumnsSQL, $this->table_name) . " WHERE field = '" . $column_info->name . "'";
					$enum_values_rs = $this->db->execute($meta_column_info_sql);
					
					if ($enum_values_rs && !$enum_values_rs->EOF) {
						// get the enum values from the type field
						$enum_values = $enum_values_rs->fields('Type');
						if (preg_match("/^(enum)\((.*)\)$/i", $enum_values, $set_values)) {
							// store the values from the enum
							$column_info->enums = explode(',', $set_values[2]);
						}						
					}
				}
			}
			
			$temp_enums = $meta_columns[strtoupper($meta_key)]->enums;
			$fixed_enums = array();

			$current_temp = '';

			foreach ($temp_enums as $option) {
				$current_temp .= ($option[0] == ' ' ? ',' : '') . $option;

				if (substr($option, -1) === "'") {
					$fixed_enums[] = $current_temp;
					$current_temp = '';
				}
			}

			$fixed_enums = array_map(array($this, 'clean_enum_options'), $fixed_enums);

			$enum_options[$this->table_name][$meta_key] = array_combine($fixed_enums, $fixed_enums);
		}

		return $enum_options[$this->table_name][$meta_key];
	}

	protected function clean_enum_options($option) {
		return str_replace("''", "'", trim($option, "'"));
	}
	
	// returns true if a field value is not the same as when it was loaded
	public function is_dirty($field, $value = null) {
		$value = (is_null($value) && is_array($this->data) && isset($this->data[$field]) ? $this->data[$field] : $value);
		return ($this->get_old_data($field) !== $value);
	}
	
	// returns a value from the old data or all the old data.  if retain_dirty is off it will query for the data.
	public function get_old_data($field = null) {
		$old_data = null;
		if ($this->retain_dirty) {
			$old_data = (!$field ? $this->old_data : (is_array($this->old_data) && isset($this->old_data[$field]) ? $this->old_data[$field] : null));
		} else { // TODO: load it
		}		
		return $old_data;
	}	
}

// from: http://www.php.net/manual/en/function.sys-get-temp-dir.php
if ( !function_exists('sys_get_temp_dir')) {
  function sys_get_temp_dir() {
      if( $temp=getenv('TMP') )        return $temp;
      if( $temp=getenv('TEMP') )        return $temp;
      if( $temp=getenv('TMPDIR') )    return $temp;
      $temp=tempnam(__FILE__,'');
	  if (file_exists($temp)) {
          unlink($temp);
          return dirname($temp);
      }
      return null;
  }
}
?>