<?php
require_once(dirname(__FILE__) .'/base.class.php');


// -----------------------------------------------------------------------------
//	Validator
// -----------------------------------------------------------------------------
abstract class Validator extends Base {

	protected $name;				// field or group name
	protected $desc;				// field or group description
	protected $alt_error;			// alternate error message

	public function __construct($alt_error=false) {
		// Extend error reporting to handle generic and custom error messaging

		parent::__construct(false);
		$this->set_alt_error($alt_error);
	}

	public function setup($name, $desc) {
		// Set name, description, and default error message
		// Return any field attributes

		$this->name = $name;
		$this->desc = $desc;

		// add in descript to alt_error  if alt_error starts with a space
		if ($this->alt_error) {
			$this->alt_error = ($this->alt_error && $this->alt_error[0] == ' ' ? $this->desc . $this->alt_error : $this->alt_error);
		}

		// Attributes to send back to field object, or false
		return false;
	}

	public function set_alt_error($alt_error = false) {
		$this->alt_error = $alt_error;
	}

	public function validate($value) {
		// Validate the value passed and return true or false

		return in_array($value, array(null, ""));
	}

	public function valid_error($default_error) {
		// Call error method with chosen message
		// Setting an alternate error will override the default

		if ($this->alt_error) {
			parent::error($this->alt_error);
		} else {
			parent::error($default_error);
		}
	}
}


// -----------------------------------------------------------------------------
//	Valid Group
// -----------------------------------------------------------------------------
abstract class ValidGroup extends Validator {

	public $fields;

	public function setup($name, $desc, $fields=false) {
		parent::setup($name, $desc);

		if (!is_array($fields)) {
			// use a clone to prevent cyclic references
			if (is_object($fields)) {
				$fields = clone $fields;
			}
			$fields = array($fields);
		}

		$this->fields = $fields;

		// Attributes to send back to field object, or false
		return false;
	}

	public function validate($values) {
		// ValidGroup gets all field values to use

		return false;
	}

	protected function get_field($field_name) {
		// returns a field object from the fields array with the passed name

		foreach ($this->fields as $field) {
			if ($field->name == $field_name) {
				return $field;
			}
		}

		return null;
	}
}

class Valid_Group extends ValidGroup {
	// Backwards compatibility for old naming convention
}


// -----------------------------------------------------------------------------
//	Valid Require
// -----------------------------------------------------------------------------
class ValidRequire extends Validator {

	protected $error_values = array('', null);

	public function __construct($alt_error=false, $extra_error_values=false) {
		// Extend error reporting to handle generic and custom error messaging

		parent::__construct(false);
		$this->alt_error = $alt_error;
		if ($extra_error_values !== false) {
			if (!is_array($extra_error_values)) {
				$extra_error_values = array($extra_error_values);
			}
			$this->error_values = array_merge($extra_error_values, $this->error_values);
		}
	}

	public function validate($value) {
		// Return true if value is not blank/null

		if (in_array($value, $this->error_values, true)) {
			$this->valid_error($this->desc .' is required.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_require($alt_error=false, $extra_error_values=false) {
	// Validator object factory

	return new ValidRequire($alt_error, $extra_error_values);
}


// -----------------------------------------------------------------------------
//	Valid Disable
// -----------------------------------------------------------------------------
class ValidDisable extends Validator {

	public function setup($name, $desc) {
		// Return disabled attribute

		parent::setup($name, $desc);
		return array("disabled" => "disabled");
	}

	public function validate($value) {
		// No validation done, return true

		return true;
	}
}

function valid_disable($alt_error=false) {
	// Validator object factory

	return new ValidDisable($alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Readonly
// -----------------------------------------------------------------------------
class ValidReadOnly extends Validator {

	public function setup($name, $desc) {
		// Return disabled attribute

		parent::setup($name, $desc);
		return array("readonly" => "");
	}

	public function validate($value) {
		// No validation done, return true

		return true;
	}
}

function valid_readonly($alt_error=false) {
	// Validator object factory

	return new ValidReadOnly($alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Min Length
// -----------------------------------------------------------------------------
class ValidMinLength extends Validator {

	public function __construct($length, $alt_error=false) {
		parent::__construct($alt_error);
		$this->length = $length;
	}

	public function validate($value) {
		// Return true if $value is >= $this->length characters

		// Ignore blank and non strings
		if ($value == null or !is_string($value) or $value == '') {
			return true;
		}

		if (mb_strlen($value, BUSHIDO_CHARSET) >= $this->length) {
			return true;
		} else {
			$this->valid_error($this->desc .' must contain at least '. $this->length .' characters.');
			return false;
		}
	}
}

function valid_min_length($length, $alt_error=false) {
	// Validator object factory

	return new ValidMinLength($length, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Max Length
// -----------------------------------------------------------------------------
class ValidMaxLength extends Validator {

	public function __construct($length, $alt_error=false) {
		parent::__construct($alt_error);
		$this->length = $length;
	}

	public function setup($name, $desc) {
		// Set name, description, and default error message
		// Return maxlength attribute

		parent::setup($name, $desc);
		$attributes = array("maxlength" => $this->length);

		return $attributes;
	}

	public function validate($value) {
		// Return true if $value is <= $this->length characters

		// Ignore blank and non strings
		if ($value == null or !is_string($value) or $value == '') {
			return true;
		}

		if (mb_strlen($value, BUSHIDO_CHARSET) <= $this->length) {
			return true;
		} else {
			$this->valid_error($this->desc .' may only contain up to '. $this->length .' characters.');
			return false;
		}
	}
}

function valid_max_length($length, $alt_error=false) {
	// Validator object factory

	return new ValidMaxLength($length, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Exact Length
// -----------------------------------------------------------------------------
class ValidExactLength extends Validator {

	public function __construct($length, $alt_error=false) {
		parent::__construct($alt_error);
		$this->length = $length;
	}

	public function setup($name, $desc) {
		// Set name, description, and default error message
		// Return maxlength attribute

		parent::setup($name, $desc);
		$attributes = array("maxlength" => $this->length);

		return $attributes;
	}

	public function validate($value) {
		// Return true if $value is <= $this->length characters

		// Ignore blank and non strings
		if ($value == null or !is_string($value) or $value == '') {
			return true;
		}

		if (mb_strlen($value, BUSHIDO_CHARSET) == $this->length) {
			return true;
		} else {
			$this->valid_error($this->desc .' must be '. $this->length .' characters.');
			return false;
		}
	}
}

function valid_exact_length($length, $alt_error=false) {
	// Validator object factory

	return new ValidExactLength($length, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Digit
// -----------------------------------------------------------------------------
class ValidDigit extends Validator {

	public function validate($value) {
		// Return true if value is digits only
		// No decimal points, positive, negative, etc.

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		// Convert to string if necessary
		if (!is_string($value)) {
			$value = (string)$value;
		}

		if (ctype_digit($value) == false) {
			$this->valid_error($this->desc .' must be numbers only.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_digit($alt_error=false) {
	// Validator object factory

	return new ValidDigit($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Numeric
// -----------------------------------------------------------------------------
class ValidNumeric extends Validator {

	public function validate($value) {
		// Return true if value is numeric, allowing +- and decimal places

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		if (is_numeric($value)) {
			return true;
		} else {
			$this->valid_error($this->desc .' must be numbers only.');
			return false;
		}
	}
}

function valid_numeric($alt_error=false) {
	// Validator object factory

	return new ValidNumeric($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Currency
// -----------------------------------------------------------------------------
class ValidCurrency extends ValidNumeric {

	public function validate($value) {
		// Return true if value is a proper currency (without $)

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		// Check if value is a number
		if (!parent::validate($value)) {
			return false;
		}

		// Make sure number is positive (don't worry be happy)
		if ($value >= 0) {
			return true;
		} else {
			return false;
		}
	}
}

function valid_currency($alt_error=false) {
	// Validator object factory

	return new ValidCurrency($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Name
// -----------------------------------------------------------------------------
class ValidName extends Validator {

	public function validate($value) {
		// Return true if string contains letters, punctuation, and/or spaces
		//ie: St. Louis, Kung-Pao Chicken!

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		if (preg_match("/^[[:alpha:][:punct:][:space:]]*$/", $value) == false) {
			$this->valid_error($this->desc .' can only contain letters, punctuation, and spaces.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_Name($alt_error=false) {
	// Validator object factory

	return new ValidName($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Alpha Numeric
// -----------------------------------------------------------------------------

class ValidAlphaNum extends Validator {

	public function validate($value) {
		// Return true if every character in value is a letter or number

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		// Convert to string if necessary
		if (!is_string($value)) {
			$value = (string)$value;
		}

		if (ctype_alnum($value) == false) {
			$this->valid_error($this->desc .' must only contain letters and numbers.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_alpha_num($alt_error=false) {
	// Validator object factory

	return new ValidAlphaNum($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Increment
// -----------------------------------------------------------------------------
class ValidIncrement extends Validator {

	public function __construct($increments, $alt_error=false) {
		parent::__construct($alt_error);
		if (!is_array($increments)) {
			$increments = array($increments);
		}
		$this->increments = $increments;
	}

	public function validate($value) {
		// Return true if value is evenly divisible by one of the increments passed

		// Ignore null, blank, or non numeric values
		if ($value == null or $value == '' or is_numeric($value) == false) {
			return true;
		}

		// Check modulus of each increment
		$valid = false;
		foreach ($this->increments as $increment) {
			if (floor($value / $increment) == $value / $increment) {
				$valid = true;
			}
		}
		if ($valid == false) {
			if (count($this->increments) == 1) {
				$increment_txt = $this->increments[0];
			} elseif (count($this->increments) == 2) {
				$increment_txt = implode(' or ', $this->increments);
			} else {
				$this->increments[-1] = "or ". $this->increments[-1];
				$increment_txt = implode(', ', $this->increments);
			}
			$error = $this->desc ." must be in increments of ". $increment_txt;
			$this->valid_error($error);
		}

		return $valid;
	}
}

function valid_increment($increments, $alt_error=false) {
	// Validator object factory

	return new ValidIncrement($increments, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Max Value
// -----------------------------------------------------------------------------
class ValidMaxValue extends Validator {

	public function __construct($max_value, $alt_error=false) {
		parent::__construct($alt_error);
		$this->max_value = $max_value;
	}

	public function validate($value) {
		// Return true if value <= $this->max_value
		// Ignore max_value 0, null, blank, or non numeric values
		if ($this->max_value == 0 or $value == null or $value == '' or is_numeric($value) == false) {
			return true;
		}

		if ($value > $this->max_value) {
			$this->valid_error($this->desc .' may be no greater than '. $this->max_value);
			return false;
		} else {
			return true;
		}
	}
}

function valid_max_value($max_value, $alt_error=false) {
	// Validator object factory

	return new ValidMaxValue($max_value, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Min Value
// -----------------------------------------------------------------------------
class ValidMinValue extends Validator {

	public function __construct($min_value, $alt_error=false) {
		parent::__construct($alt_error);
		$this->min_value = $min_value;
	}

	public function validate($value) {
		// Return true if value >= $this->min_value

		// Ignore null, blank, or non numeric values
		if ($value == null or $value == '' or is_numeric($value) == false) {
			return true;
		}

		if ($value < $this->min_value) {
			$this->valid_error($this->desc .' must be at least '. $this->min_value);
			return false;
		} else {
			return true;
		}
	}
}

function valid_min_value($min_value, $alt_error=false) {
	// Validator object factory

	return new ValidMinValue($min_value, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Preg Match
// -----------------------------------------------------------------------------
class ValidPregMatch extends Validator {

	public function __construct($regex, $alt_error=false) {
		parent::__construct($alt_error);
		$this->regex = $regex;
	}

	public function validate($value) {

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		if (preg_match($this->regex, $value) == false) {
			$this->valid_error($this->desc .' is not valid.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_preg_match($regex, $alt_error=false) {
	// Validator object factory

	return new ValidPregMatch($regex, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid US Zip Code
// -----------------------------------------------------------------------------
class ValidZipCode extends ValidPregMatch {

	public function __construct($alt_error=false) {
		parent::__construct("/^([0-9]{5})(-[0-9]{4})?$/i", $alt_error);
	}
}

function valid_zip_code($alt_error=false) {
	// Validator object factory

	return new ValidZipCode($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid URL
// -----------------------------------------------------------------------------
class ValidURL extends ValidPregMatch {

	public function __construct($alt_error=false) {
		parent::__construct('/^(http|https):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i', $alt_error);
	}
}

function valid_url($alt_error=false) {
	// Validator object factory

	return new ValidURL($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid US phone number
// -----------------------------------------------------------------------------
class ValidPhoneNumber extends ValidPregMatch {

	public function __construct($delimiters = null, $parenthesis = false, $delimiter_optional = true, $parenthesis_optional = true, $alt_error = false) {
		if ($parenthesis) {
			if ($parenthesis_optional) {
				$left_paren = '\(?';
				$right_paren = '\)?';
			} else {
				$left_paren = '\(';
				$right_paren = '\)';
			}
		} else {
			$left_paren = '';
			$right_paren = '';
		}
		if ($delimiters !== null) {
			if ($delimiter_optional) {
				$optional_regex = '?';
			} else {
				$optional_regex = '';
			}
			if (is_array($delimiters) && count($delimiters) > 0) {
				$delimiters_regex = '[';
				foreach ($delimiters as $delimiter) {
					$delimiters_regex .= $delimiter;
				}
				$delimiters_regex .= ']';
				parent::__construct('/' . $left_paren . '\d{3}' . $right_paren . $delimiters_regex . $optional_regex . '\d{3}' . $delimiters_regex . $optional_regex . '\d{4}/', $alt_error);
			} elseif ($delimiters != '') {
				parent::__construct('/\d{3}' . $delimiters . $optional_regex . '\d{3}' . $delimiters . $optional_regex . '\d{4}/', $alt_error);
			} elseif ($parenthesis) {
				parent::__construct('/\\(d{3}\)d{7}/', $alt_error);
			} else {
				parent::__construct('/\d{10}/', $alt_error);
			}
		} else {
			if ($parenthesis) {
				parent::__construct('/\\(d{3}\)d{7}/', $alt_error);
			} else {
				parent::__construct('/\d{10}/', $alt_error);
			}
		}
	}
}

function valid_phone_number($delimiters = null, $parenthesis = false, $delimiter_optional = true, $parenthesis_optional = true, $alt_error = false) {
	// Validator object factory

	return new ValidPhoneNumber($delimiters, $parenthesis, $delimiter_optional, $parenthesis_optional, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Date
// -----------------------------------------------------------------------------
class ValidDate extends Validator {

	protected $valid_date_format;
	protected $display_date_format;
	protected $leading_zero_to_no_leading_zero = array('d' => 'j', 'm' => 'n', 'g' => 'h', 'G' => 'H');
	protected $year_characters = array("Y", "y");
	protected $month_characters = array("m", "F", "M", "n");
	protected $day_characters = array("d", "j", "z");
	protected $check_other_zero_formats;
	protected $date_TS;

	public function __construct($valid_date_format=null, $display_date_format=null, $check_other_zero_formats=true, $alt_error=false) {
		parent::__construct($alt_error);
		// valid_date_format must be in a format that the date function works with.
		// fully supported characters are: Y, y, m, F, M, n, d, j, z
		// Other characters may be supported in certain sequences
		$this->valid_date_format = $valid_date_format ? $valid_date_format : "Y-m-d";
		$this->display_date_format = $display_date_format ? $display_date_format : $this->convert_date_format_to_display_format($this->valid_date_format);
		$this->check_other_zero_formats = $check_other_zero_formats;
	}

	public function validate($value) {
		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}
		$timestamp = $this->get_timestamp($value, $this->valid_date_format);
		$this->date_TS = $timestamp;
		if (is_numeric($timestamp)) {
			$formatted_date = date($this->valid_date_format, $timestamp);
			if ($formatted_date == $value) {
				return true;
			} elseif (preg_replace('/\d/', '', $value) == preg_replace('/\d/', '', $formatted_date) && (!$this->check_other_zero_formats ||
				($this->validate_other_zero_formats($this->valid_date_format, $value, $timestamp, true) ||
					$this->validate_other_zero_formats($this->valid_date_format, $value, $timestamp, false)))) {
				return true;
			}
		}
		$this->valid_error($this->desc . ' must be in the proper date format (' . $this->display_date_format . ').');
		return false;
	}

	public static function get_timestamp($value, $format = null) {
		if (function_exists("strptime")) {
			if (isset($format) && $format == str_replace(array("a", "A", "g", "h", "H", "i", "s", "o"), array("","","","","","",""), $format)) {
				$strptime_array = strptime($value, self::convert_date_format_to_strftime_format($format));
				if (is_array($strptime_array)) {
					if ($strptime_array["tm_year"] < 200) {
						$strptime_array["tm_year"] += 1900;
					} else {
						$strptime_array["tm_year"] = 1970;
					}
					if ($strptime_array["tm_mon"] > 11 || $strptime_array["tm_mon"] < 0) {
						$strptime_array["tm_mon"] = 1;
					} else {
						$strptime_array["tm_mon"] += 1;
					}
					if ($strptime_array["tm_mday"] > 31 || $strptime_array["tm_mday"] < 1) {
						$strptime_array["tm_mday"] = 1;
					}
				}
				if (strpos($format, "z") !== false) {
					return strtotime($strptime_array["tm_year"] . "-01-02 + " . $strptime_array["tm_yday"] . " days");
				}
				return strtotime($strptime_array["tm_year"] . "-" . $strptime_array["tm_mon"] . "-" . $strptime_array["tm_mday"]);
			}
		} else {
			if (isset($format) && $format == str_replace(array("a", "A", "g", "h", "H", "i", "s", "o", "F", "M", "n", "j", "z"), array("","","","","","","","","","","",""), $format)) {
				$timestamp = self::convert_formatted_date_to_timestamp($value, self::convert_date_format_to_strftime_format($format));
				if ($timestamp !== false) {
					return $timestamp;
				}
			}
		}
		return strtotime(str_replace(' ', '', $value));
	}

	public static function convert_formatted_date_to_timestamp($date, $format) {
		$day = 1;
		$month = 0;
		$year = 0;
		while ($format != "") {
			$current_subformat_index = strpos($format, '%');
			if ($current_subformat_index === false || substr($format, 0, $current_subformat_index) != substr($date,   0, $current_subformat_index)) {
				break;
			}
			$date = substr($date, $current_subformat_index);
			$dateAfter = "";
			switch (substr($format, $current_subformat_index, 2)) {
				case '%d':
					sscanf($date, "%2d%[^\\n]", $day, $dateAfter);
					if (($day < 1) || ($day > 31)) {
						return false;
					}
					break;
				case '%m':
					sscanf($date, "%2d%[^\\n]", $month, $dateAfter);
					if (($month < 1) || ($month > 12)) {
						return false;
					}
					break;
				case '%Y':
					sscanf($date, "%4d%[^\\n]", $year, $dateAfter);
					break;
				case '%y':
					sscanf($date, "%2d%[^\\n]", $year, $dateAfter);
					break;
				default:
					break 2; // Break Switch and while
			}
			$format = substr($format, $current_subformat_index + 2);
			$date = $dateAfter;
		}
		$timestamp = mktime(0, 0, 0, $month, $day, $year);
		if (($timestamp === false) || ($timestamp === -1)) {
			return false;
		}
		return $timestamp;
	}

	protected function validate_other_zero_formats($date_format, $value, $timestamp, $leading_zero) {
		foreach ($this->leading_zero_to_no_leading_zero as $leading_zero => $no_leading_zero) {
			if ($leading_zero) {
				$temporary_date_format = str_replace($leading_zero, $no_leading_zero, $date_format);
			} else {
				$temporary_date_format = str_replace($no_leading_zero, $leading_zero, $date_format);
			}
			if ($temporary_date_format != $date_format && (date($temporary_date_format, $timestamp) == $value ||
				(!$leading_zero && $this->validate_other_zero_formats($temporary_date_format, $value, $timestamp, false)) ||
				($leading_zero && $this->validate_other_zero_formats($temporary_date_format, $value, $timestamp, true)))) {
				return true;
			}
		}
		return false;
	}

	public static function convert_date_format_to_display_format($date_format) {
		$valid_characters = array('D', 'd', 'j', 'M', 'm', 'n', 'o', 'Y', 'y', 'a', 'A', 'g', 'G', 'h', 'H', 'i', 's');
		$display_characters = array ('DDD', 'DD', 'D', 'MMM', 'MM', 'M', 'YYYY', 'YYYY', 'YY', 'am/pm', 'AM/PM', 'h', '24h', 'hh', '24hh', 'mm', 'ss');
		return str_replace($valid_characters, $display_characters, $date_format);
	}


	public static function convert_date_format_to_strftime_format($date_format) {
		$caracs = array(
			// Day - no strf eq : S
			'd' => '%d', 'D' => '%a', 'j' => '%e', 'z' => '%j',
			// Month - no strf eq : t
			'F' => '%B', 'm' => '%m', 'M' => '%b', 'n' => '%m',
			// Year - no strf eq : L; no date eq : %C, %g
			'Y' => '%Y', 'y' => '%y',
		);
		return strtr((string)$date_format, $caracs);
	}
}

function valid_date($valid_date_format=null, $display_date_format=null, $check_other_zero_formats = true, $alt_error=false) {
	// Validator object factory

	return new ValidDate($valid_date_format, $display_date_format, $check_other_zero_formats, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid make sure date is in the future.  date value is expected to be in mm/dd/yyyy format.
// -----------------------------------------------------------------------------
class Valid_Date_Future extends ValidDate {

	protected $today_TS;

	public function __construct($valid_date_format=null, $display_date_format=null, $check_other_zero_formats=true, $alt_error=false) {
		parent::__construct($valid_date_format, $display_date_format, $check_other_zero_formats, $alt_error);
	}

	public function validate($value) {

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		// run through ValidDate
		if (parent::validate($value)) {
			$this->today_TS = $this->get_today_TS();

			if ($this->date_TS < $this->today_TS) {
				$this->valid_error($this->desc . " must be today or later");
				return false;
			}

			return true;
		}
	}

	protected function get_today_TS() {
		return mktime(0,0,0,date("m"),date("d"),date("Y"));
	}
}

function valid_date_future($valid_date_format=null, $display_date_format=null, $check_other_zero_formats=true, $alt_error=false) {
	// Validator object factory
	return new Valid_Date_Future($valid_date_format, $display_date_format, $check_other_zero_formats, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid make sure date is in the future as is between min and max days.  date is expected to be in mm/dd/yyyy format.
// -----------------------------------------------------------------------------
class Valid_Date_Future_Min_Max extends Valid_Date_Future
{
	protected $min_days;
	protected $max_days;

	public function __construct($min_days = 10, $max_days = 30, $valid_date_format=null, $display_date_format=null, $check_other_zero_formats=true, $alt_error=false) {
		parent::__construct($valid_date_format, $display_date_format, $check_other_zero_formats, $alt_error);
		$this->min_days = $min_days;
		$this->max_days = $max_days;
	}

	public function validate($value) {

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		if (parent::validate($value)) {
			$numDays = $this->get_business_days($this->today_TS, $this->date_TS);
			if ($numDays < $this->min_days) {
				$this->valid_error($this->desc . " must be at least " . $this->min_days . " business day" . ($this->min_days > 1 ? "s" : "") . " in the future");
				return false;
			} elseif ($this->date_TS - $this->today_TS > 60*60*24*$this->max_days) {
				$this->valid_error($this->desc . " must be less than " . $this->max_days . " calendar day" . ($this->max_days > 1 ? "s" : "") . " in the future");
				return false;
			} else {
				return true;
			}
		} else {
			return false;
		}
	}

	function get_business_days($startTS, $endTS) {
		$numDayStart = date("w",$startTS);
		$weekStart = date("W",$startTS);

		if ($numDayStart == 0) { // sunday counts as previous week
			$weekStart++;
		}

		$numDayEnd = date("w",$endTS);
		$weekEnd = date("W", $endTS);

		//stupid january
		if ($weekEnd<$weekStart) {
			$weekEnd += 52;
		}

		if ($numDayEnd>=$numDayStart) {
			//number of weeks between dates*5 + difference in dates
			$numDays = (($weekEnd-$weekStart)*5) + ($numDayEnd-$numDayStart);
			if ($numDayEnd == 6) {
				$numDays--;
			}
		} else {
			//number of weeks between dates - 1 (since end before start)*5 + number of bus days in start week + num bus days in end week
			$numDays = (($weekEnd-$weekStart-1)*5) + (5-$numDayStart) + $numDayEnd;
		}
		return $numDays;
	}
}

function valid_date_future_min_max($min_days = 10, $max_days = 30, $valid_date_format=null, $display_date_format=null, $check_other_zero_formats=true, $alt_error=false) {
	// Validator object factory
	return new Valid_Date_Future_Min_Max($min_days, $max_days, $valid_date_format, $display_date_format, $check_other_zero_formats, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Email
// -----------------------------------------------------------------------------
class ValidEmail extends Validator {

	public function validate($value) {
		// Return true if value is a correctly formated email address

		// Ignore null or blank values
		if ($value == null or $value == '') {
			return true;
		}

		if (preg_match('/^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $value) == false) {
			$this->valid_error($this->desc .' must be a valid e-mail address.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_email($alt_error=false) {
	// Validator object factory

	return new ValidEmail($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Datetime
// -----------------------------------------------------------------------------
class ValidDatetime extends Validator {

	public function validate($value) {
		// Return true if value is a properly formatted datetime

		// Ignore null or blank values
		if ($value == null) {
			return true;
		}

		// Look for format YYYY-MM-DD hh:mm:ss
		if (!preg_match('/^((19|20)[\d]{2})-(0[1-9]|1[012])-(0[1-9]|[12][0-9]|3[01]) ([0-1][0-9]|2[0-4]):([0-5][0-9]|60):([0-5][0-9]|60)$/', $value, $matches)) {
			$error = $this->desc ." must be in the format YYYY-MM-DD hh:mm:ss";
			$this->valid_error($error);
			return false;
		} else {
			// Make sure day of the month is correct
			$year = $matches[1];
			$month = $matches[3];
			$day = $matches[4];
			$timestamp = mktime(0, 0, 0, $month, 1, $year);
			if ($day > date('t', $timestamp)) {
				$error = $this->desc ." is invalid, ". date('F', $timestamp) .
					" doesn't have that many days";
				$this->valid_error($error);
				return false;
			}
			return true;
		}
	}
}

function valid_datetime($alt_error=false) {
	// Validator object factory

	return new ValidDatetime($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Character Variance
// -----------------------------------------------------------------------------
class ValidCharacterVariance extends Validator {

	protected $variance = 1;

	public function __construct($variance=1, $alt_error=false) {
		parent::__construct($alt_error);
		$this->variance = $variance;
	}

	public function validate($value) {
		// return true if the value contains more than the $variance # of unique characters

		if (strlen(count_chars($value, 3)) <= $this->variance) {
			$this->valid_error($this->desc .' must contain at least ' . ($this->variance + 1) . " unique characters");
			return false;
		} else {
			return true;
		}
	}
}

function valid_character_variance($variance=1, $alt_error=false) {
	// Validator object factory

	return new ValidCharacterVariance($variance, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Credit Card Check Sum
// -----------------------------------------------------------------------------
class ValidCreditCardCheckSum extends Validator {

	public function validate($value) {
		// Every even positioned digit is doubled and if the resulting value has multiple digits then those digits are summed
		// Every odd positioned digit is summed without doubling
		// Then if the total sum is divisible by 10 the check sum is valid.
		// Example: 6011000000000004 is a discover card number that has a valid check sum
		// 6 is the 16th digit so it is doubled 6 * 2 = 12, 12 gets broken up into 1 + 2 = 3, 3 + 0 + 1 * 2 + 1 + 0 + 0 * 2 + 0 + 0 * 2 + 0 + 0 * 2 + 0 + 0 * 2 + 0 + 0 * 2 + 0 + 4 = 10 which is divisible by 10.
		if (!is_string($value)) {
			$value = (string)$value;
		}
		$sum = 0;
		$i = strlen($value);
		$i_offset = 1 + $i & 1;
		while ($i--) {
			$sub_value = $value[$i] << ($i_offset + $i & 1);
			if ($sub_value > 9) {
				$sum += $sub_value - 9;
			} else {
				$sum += $sub_value;
			}
		}
		if ($sum % 10 === 0) {
			return true;
		}
		$this->valid_error($this->desc . " is invalid.");
		return false;
	}
}

function valid_credit_card_check_sum($alt_error=false) {
	// Validator object factory

	return new ValidCreditCardCheckSum($alt_error);
}


class ExplodingValidator extends Validator {

	public function __construct($validator, $delimiter = ",", $alt_error=false) {
		parent::__construct($alt_error);
		$this->validator = $validator;
		$this->delimiter = $delimiter;
	}

	public function validate($value) {
		if (parent::validate($value)) {
			return true;
		}
		$validator = $this->validator;
		$validator = $validator();
		$invalid_fragments = array();
		foreach (explode($this->delimiter, $value) as $fragment) {
			if (!$validator->validate(trim($fragment))) {
				$invalid_fragments[] = $fragment;
			}
		}
		if ($invalid_fragments === array()) {
			return true;
		}
		$this->valid_error($this->desc . " had the following invalid value" . (count($invalid_fragments) > 1 ? "s" : "") . ": " . implode(", ", $invalid_fragments));
		return false;
	}
}

function exploding_validator($validator, $delimiter = ",", $alt_error=false) {
	return new ExplodingValidator($validator, $delimiter, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Change
// -----------------------------------------------------------------------------
class ValidChange extends ValidGroup {

	public function __construct($existing_values, $alt_error=false) {
		parent::__construct($alt_error);

		$this->existing_values = $existing_values;
	}

	public function validate($values) {
		// Return true if at least one value has changed

		foreach ($this->existing_values as $key => $value) {
			// Ignore blank and non strings
			if ($values[$key] == null or $values[$key] == '') {
				continue;
			}

			if ($value != $values[$key]) {
				return true;
			}
		}

		// Made it through loop, no changes
		$this->valid_error('No changes were made to '. $this->desc);
		return false;
	}
}

function valid_change($existing_values, $alt_error=false) {
	// Validator object factory

	return new ValidChange($existing_values, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Require All Or None
// -----------------------------------------------------------------------------
class ValidRequireAllOrNone extends ValidGroup {

	public function validate($values) {
		// Return true if all or none of the fields have values

		$validate_obj = valid_require();

		$last_field = "";
		$good = true;
		foreach ($this->fields as $field) {
			if (!isset($values[$field->name]) or !$validate_obj->validate($values[$field->name])) {
				if ($last_field == "value") {
					$good = false;
					break;
				}
				$last_field = "null";
			} else {
				if ($last_field == "null") {
					$good = false;
					break;
				}
				$last_field = "value";
			}
		}

		if ($good) {
			return true;
		} else {
			// This error message only works with a good $this->desc
			$this->valid_error($this->desc .' must have all fields filled out.');
			return false;
		}
	}
}

function valid_require_all_or_none($alt_error=false) {
	// Validator object factory

	return new ValidRequireAllOrNone($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Require One Group
// -----------------------------------------------------------------------------
class ValidRequireOneGroup extends ValidGroup {

	// Multi dimensional array containing groups of field names
	protected $grouped_fields;

	public function __construct($grouped_fields, $alt_error=false) {
		parent::__construct($alt_error);
		if (is_array($grouped_fields) == false) {
			$grouped_fields = array($grouped_fields);
		}
		foreach ($grouped_fields as &$value) {
			if (is_array($value) == false) {
				$value = array($value);
			}
		}
		$this->grouped_fields = $grouped_fields;
	}

	public function validate($values) {
		// Return true if one (or more) of the listed groups has values in all fields

		$validate_obj = valid_require();

		foreach ($this->grouped_fields as $group) {
			$good = true;
			foreach ($group as $field) {
				if (!isset($values[$field->name]) or !$validate_obj->validate($values[$field->name])) {
					$good = false;
					break;
				}
			}
			if ($good == true) {
				return true;
			}
		}

		// This error message only works with a good $this->desc
		$this->valid_error($this->desc .' is required.');
		return false;
	}
}

function valid_require_one_group($group_array, $alt_error=false) {
	// Validator object factory

	return new ValidRequireOneGroup($group_array, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group Mask
// -----------------------------------------------------------------------------
class ValidGroupMask extends ValidGroup {

	// Multi dimensional array containing groups of field names
	protected $grouped_fields;
	// Number of groups allowed to have input
	protected $num_allowed;

	public function __construct($grouped_fields, $num_allowed=1, $alt_error=false) {
		parent::__construct($alt_error);
		if (is_array($grouped_fields) == false) {
			$grouped_fields = array($grouped_fields);
		}
		foreach ($grouped_fields as &$value) {
			if (is_array($value) == false) {
				$value = array($value);
			}
		}
		$this->grouped_fields = $grouped_fields;
		$this->num_allowed = $num_allowed;
	}

	public function validate($values) {
		// Return true if only $this->num_allowed groups have fields with data

		$validate_obj = valid_require();

		$num_groups = 0;
		foreach ($this->grouped_fields as $group) {
			foreach ($group as $field) {
				if (isset($values[$field->name]) and $validate_obj->validate($values[$field->name])) {
					$num_groups ++;
					break;
				}
			}
		}
		if ($num_groups > $this->num_allowed) {
			// This error message will likely never work, so replace it!
			$this->valid_error($this->desc .' has too many responses.');
			return false;
		} else {
			return true;
		}
	}
}

function valid_group_mask($group_array, $num_allowed=1, $alt_error=false) {
	// Validator object factory

	return new ValidGroupMask($group_array, $num_allowed, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Require Count
// -----------------------------------------------------------------------------
class ValidRequireCount extends ValidGroup {

	public $number_required = 1;

	public function __construct($number_required=1, $alt_error=false) {
		parent::__construct($alt_error);
		$this->number_required = $number_required;
	}

	public function validate($values) {
		// Return true if $number_required (or more) fields have values
		$valid_count = 0;

		// Use the existing single field require validator to validate
		$validate_obj = valid_require();

		foreach ($this->fields as $field) {
			if (isset($values[$field->name]) && $validate_obj->validate($values[$field->name])) {
				$valid_count++;
			}
		}

		// See if we have enough to be valid
		if ($valid_count < $this->number_required) {
			$this->valid_error("At least ". $this->number_required ." ". $this->desc . ($this->number_required == 1 ? " is " : " are ") ."required");
			return false;
		} else {
			return true;
		}
	}
}

function valid_require_count($number_required=1, $alt_error=false) {
	// Validator object factory

	return new ValidRequireCount($number_required, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Require Count Delimited String
// -----------------------------------------------------------------------------
class ValidRequireCountDelimitedString extends Validator {

	public $number_required = 1;
	public $delimiter = '|';

	public function __construct($number_required=1, $delimiter='|', $alt_error=false) {
		parent::__construct($alt_error);
		$this->number_required = $number_required;
		$this->delimiter = $delimiter;
	}

	public function validate($value) {
		// See if we have enough to be valid
		if ($value === null || $value === '' || count(explode($this->delimiter, $value)) < $this->number_required) {
			$this->valid_error("At least ". $this->number_required ." ". $this->desc . ($this->number_required == 1 ? " is " : " are ") ."required");
			return false;
		} else {
			return true;
		}
	}
}

function valid_require_count_delimited_string($number_required=1, $delimiter='|', $alt_error=false) {
	// Validator object factory

	return new ValidRequireCountDelimitedString($number_required, $delimiter, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group Match
// -----------------------------------------------------------------------------
class ValidGroupMatch extends ValidGroup {

	public function validate($values) {
		// Return true if all fields in field group match

		$last_value = "";
		foreach ($this->fields as $field) {
			// Ignore null or blank values
			if (isset($values[$field->name]) == false or $values[$field->name] == null) {
				continue;
			}
			// Check if first time through loop
			if ($last_value === "") {
				$last_value = $values[$field->name];
			}
			// Compare current value to last value
			if ($values[$field->name] != $last_value) {
				$this->valid_error($this->desc .' do not match.');
				return false;
			}
		}
		return true;
	}
}

function valid_group_match($alt_error=false) {
	// Validator object factory

	return new ValidGroupMatch($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group No Match
// -----------------------------------------------------------------------------
class ValidGroupNoMatch extends ValidGroup {

	public function validate($values) {
		// Return true if no fields in field group match

		$all_values = array();
		foreach ($this->fields as $field) {
			// Ignore null or blank values
			if (isset($values[$field->name]) == false or $values[$field->name] == null) {
				continue;
			}
			// Compare current value to all values
			if (array_search($values[$field->name], $all_values) !== false) {
				$this->valid_error($this->desc .' must be different.');
				return false;
			} else {
				$all_values[] = $values[$field->name];
			}
		}
		return true;
	}
}

function valid_group_no_match($alt_error=false) {
	// Validator object factory

	return new ValidGroupNoMatch($alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Require If Other Field Equals
// -----------------------------------------------------------------------------
class ValidRequireIfFieldEquals extends ValidGroup {

	// key array of field names and values
	protected $if_array;

	public function __construct($if_array, $alt_error=false) {
		parent::__construct($alt_error);
		$this->if_array = $if_array;
	}

	public function validate($values) {

		// use the existing single field require validator to validate
		$validate_obj = valid_require();
		$all_good = true;

		foreach ($this->fields as $field) {
			// return true if passes regular require
			if (!isset($values[$field->name]) or !$validate_obj->validate($values[$field->name])) {
				$all_good = false;
				break;
			}
		}

		// if all of the values already pass, then no error checking is required
		if ($all_good) {
			return true;
		}

		foreach ($this->if_array as $if_field => $if_value) {
			if (isset($values[$if_field])) {
				$value = $values[$if_field];
			} else {
				$value = null;
			}
			if ((is_array($if_value) && in_array($value, $if_value)) || (!is_array($if_value) && $value == $if_value)) {
				$this->valid_error($this->desc .' is required.');
				return false;
			}
		}

		// if fields dont match if values we are good
		return true;
	}
}

function valid_require_if_field_equals($if_array, $alt_error=false) {
	// Validator object factory

	return new ValidRequireIfFieldEquals($if_array, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Require Only If all Other Fields Equal their values
// -----------------------------------------------------------------------------
class ValidRequireIfFieldsEqual extends ValidGroup {

	// key array of field names and values
	protected $if_array;

	public function __construct($if_array, $alt_error=false) {
		parent::__construct($alt_error);
		$this->if_array = $if_array;
	}

	public function validate($values) {

		// use the existing single field require validator to validate
		$validate_obj = valid_require();
		$all_good = true;

		foreach ($this->fields as $field) {
			// return true if passes regular require
			if (!isset($values[$field->name]) or !$validate_obj->validate($values[$field->name])) {
				$all_good = false;
				break;
			}
		}

		// if all of the values already pass, then no error checking is required
		if ($all_good) {
			return true;
		}

		$all_equal = true;

		foreach ($this->if_array as $if_field => $if_value) {
			if (isset($values[$if_field])) {
				$value = $values[$if_field];
			} else {
				$value = null;
			}
			$all_equal &= ((is_array($if_value) && in_array($value, $if_value)) || (!is_array($if_value) && $value == $if_value));
		}

		// if all matched, then we are required
		if ($all_equal) {
			$this->valid_error($this->desc .' is required.');
			return false;
		}

		// if fields dont match if values we are good
		return true;
	}
}

function valid_require_if_fields_equal($if_array, $alt_error=false) {
	// Validator object factory

	return new ValidRequireIfFieldsEqual($if_array, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Require If Other Field Does not Equal
// -----------------------------------------------------------------------------
class ValidRequireIfFieldNotEquals extends ValidGroup {

	// key array of field names and values
	protected $if_array;

	public function __construct($if_array, $alt_error=false) {
		parent::__construct($alt_error);
		$this->if_array = $if_array;
	}

	public function validate($values) {

		// use the existing single field require validator to validate
		$validate_obj = valid_require();
		$all_good = true;

		foreach ($this->fields as $field) {
			// return true if passes regular require
			if (!isset($values[$field->name]) or !$validate_obj->validate($values[$field->name])) {
				$all_good = false;
				break;
			}
		}

		// if all of the values already pass, then no error checking is required
		if ($all_good) {
			return true;
		}

		foreach ($this->if_array as $if_field => $if_value) {
			if (isset($values[$if_field])) {
				$value = $values[$if_field];
			} else {
				$value = null;
			}

			if ((is_array($if_value) && !in_array($value, $if_value)) || (!is_array($if_value) && $value != $if_value)) {
				$this->valid_error($this->desc .' is required.');
				return false;
			}
		}

		// if fields dont match if values we are good
		return true;
	}
}

function valid_require_if_field_not_equals($if_array, $alt_error=false) {
	// Validator object factory

	return new ValidRequireIfFieldNotEquals($if_array, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group Max Value
// -----------------------------------------------------------------------------
class ValidGroupMaxValue extends ValidGroup {

	protected $max_value;

	public function __construct($max_value, $alt_error=false) {
		parent::__construct($alt_error);
		$this->max_value = $max_value;
	}

	public function validate($values) {
		// Return true if sum of values <= $this->max_value
		$field_list = '';
		$total_value = 0;
		$cnt = 0;
		$field_cnt = count($this->fields);

		foreach ($this->fields as $field) {
			$cnt++;
			$value = $values[$field->name];

			// see if we include the current value
			if (!($value == null || $value == '' || is_numeric($value) == false)) {
				$total_value += $value;
			}

			$field_list .= $field->description;

			if (($cnt + 1) == $field_cnt) {
				$field_list .= ($field_cnt > 2 ? ", " : " " ) . "and ";
			} elseif ($cnt > 0 && $cnt < count($this->fields)) {
				$field_list .= ", ";
			}
		}

		// Ignore max_value 0
		if ($this->max_value == 0 || $total_value <= $this->max_value) {
			return true;
		} else {
			$this->valid_error($field_list .' combined may be no greater than '. $this->max_value);
			return false;
		}
	}

}

function valid_group_max_value($max_value, $alt_error=false) {
	// Validator object factory

	return new ValidGroupMaxValue($max_value, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group Min Value
// -----------------------------------------------------------------------------
class ValidGroupMinValue extends ValidGroup {

	protected $min_value;

	public function __construct($min_value, $alt_error=false) {
		parent::__construct($alt_error);
		$this->min_value = $min_value;
	}

	public function validate($values) {
		// Return true if sum of values >= $this->min_value
		$field_list = '';
		$total_value = 0;
		$cnt = 0;
		$field_cnt = count($this->fields);

		foreach ($this->fields as $field) {
			$cnt++;
			$value = $values[$field->name];

			// see if we include the current value
			if (!($value == null || $value == '' || is_numeric($value) == false)) {
				$total_value += $value;
			}

			$field_list .= $field->description;

			if (($cnt + 1) == $field_cnt) {
				$field_list .= ($field_cnt > 2 ? ", " : " " ) . "and ";
			} elseif ($cnt > 0 && $cnt < count($this->fields)) {
				$field_list .= ", ";
			}
		}

		if ($total_value >= $this->min_value) {
			return true;
		} else {
			$this->valid_error($field_list .' combined must at least equal '. $this->min_value);
			return false;
		}
	}
}

function valid_group_min_value($min_value, $alt_error=false) {
	// Validator object factory

	return new ValidGroupMinValue($min_value, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Group Exact Value
// -----------------------------------------------------------------------------
class ValidGroupExactValue extends ValidGroup {

	protected $value;

	public function __construct($value, $alt_error=false) {
		parent::__construct($alt_error);
		$this->value = $value;
	}

	public function validate($values) {
		// Return true if sum of values == $this->value

		$field_list = '';
		$total_value = 0;
		$cnt = 0;
		$field_cnt = count($this->fields);

		foreach ($this->fields as $field) {
			$cnt++;
			$value = $values[$field->name];

			// see if we include the current value
			if (!($value == null || $value == '' || is_numeric($value) == false)) {
				$total_value += $value;
			}

			$field_list .= $field->description;

			if (($cnt + 1) == $field_cnt) {
				$field_list .= ($field_cnt > 2 ? ", " : " " ) . "and ";
			} elseif ($cnt > 0 && $cnt < count($this->fields)) {
				$field_list .= ", ";
			}
		}

		// Ignore max_value 0
		if ($this->value == 0 || $total_value == $this->value) {
			return true;
		} else {
			$this->valid_error($field_list .' combined must equal '. $this->value);
			return false;
		}
	}

}

function valid_group_exact_value($value, $alt_error=false) {
	// Validator object factory

	return new ValidGroupExactValue($value, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Unique
// -----------------------------------------------------------------------------
class ValidUnique extends ValidGroup {

	protected $key_field;
	protected $table_name;

	public function __construct($key_field, $table_name, $alt_error=false) {
		// Extend error reporting to handle generic and custom error messaging

		parent::__construct(false);
		$this->alt_error = $alt_error;
		$this->key_field = $key_field;
		$this->table_name = $table_name;
	}

	public function validate($values) {
		// Return true if unique fields are unique
		$key_field = $this->key_field;
		$table_name = $this->table_name;
		$return_value = true;
		$where = '';
		foreach ($this->fields as $field) {
			if ($where == '') {
				$where .= $field->name . " = " . $this->db->qstr($values[$field->name]);
			} else {
				$where .= " AND " . $field->name . " = " . $this->db->qstr($values[$field->name]);
			}
		}
		if ($where != '') {
			$sql = "SELECT " . $key_field . "
				FROM " . $table_name . "
				WHERE " . $where;
			if ($values[$key_field] !== null) {
				$sql .= "
					AND " . $key_field . " != " . $this->db->qstr($values[$key_field]);
			}
			$result = $this->db->GetOne($sql);
			if ($result !== null && $result !== false) {
				$this->valid_error($values[$field->name] . " has previously been saved as a(n) " . str_replace("_", " ", $this->table_name) . ". Please select a unique " . $this->desc . ".");
				$return_value = false;
			}
		} else {
			$return_value = false;
		}
		return $return_value;
	}
}

function valid_unique($key_field, $table_name, $alt_error=false) {
	// Validator object factory

	return new ValidUnique($key_field, $table_name, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid White List
// -----------------------------------------------------------------------------
class ValidWhiteList extends Validator {

	public function __construct($value_list, $alt_error=false) {
		parent::__construct($alt_error);
		if (!is_array($value_list)) {
			$value_list = array($value_list);
		}
		$this->value_list = $value_list;
	}

	public function validate($value) {
		// Return true if value is in value list

		// Ignore null or blank values
		if ($value == null) {
			return true;
		}

		if (array_search($value, $this->value_list, true) !== false) {
			return true;
		} else {
			$this->valid_error($this->desc .' is not valid.');
			return false;
		}
	}
}

function valid_white_list($value_list, $alt_error=false) {
	// Validator object factory

	return new ValidWhiteList($value_list, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Black List
// -----------------------------------------------------------------------------
class ValidBlackList extends ValidWhiteList {

	public function validate($value) {
		// Return true if value is not in value list

		// Ignore null or blank values
		if ($value == null) {
			return true;
		}

		if (array_search($value, $this->value_list) === false) {
			return true;
		} else {
			$this->valid_error($this->desc .' is not valid.');
			return false;
		}
	}
}

function valid_black_list($value_list, $alt_error=false) {
	// Validator object factory

	return new ValidBlackList($value_list, $alt_error);
}


// -----------------------------------------------------------------------------
//	Valid Session Id
// -----------------------------------------------------------------------------
class ValidSessionId extends Validator {

	public function validate($value) {
		// Return true if value is the MD5 hash of the session id

		// Ignore null or blank values
		if ($value == null) {
			return true;
		}

		if ($value == md5(session_id())) {
			return true;
		} else {
			$this->valid_error($this->desc .' is not valid.');
			return false;
		}
	}
}

function valid_session_id($alt_error=false) {
	// Validator object factory

	return new ValidSessionId($alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Valid Date After Date
// -----------------------------------------------------------------------------
class ValidDateAfterDate extends ValidGroup {

	protected $start_date_field;
	protected $end_date_field;

	function __construct($start_date_field, $end_date_field, $format = "Y-m-d", $alt_error=false) {
		parent::__construct($alt_error);

		$this->start_date_field = $start_date_field;
		$this->end_date_field = $end_date_field;
		$this->format = $format;
	}

	function validate($values) {
		if (isset($values[$this->start_date_field], $values[$this->end_date_field]) && $values[$this->start_date_field] !== "" &&  $values[$this->end_date_field] !== "") {
			$end_timestamp = ValidDate::get_timestamp($values[$this->end_date_field], $this->format);

			// Continue with validation only if end date is not false
			if ($end_timestamp !== false && $end_timestamp < ValidDate::get_timestamp($values[$this->start_date_field], $this->format)) {
				$this->valid_error('End Date must be after Start Date.');
				return false;
			}
		}

		// Default to true
		return true;
	}
}

function valid_date_after_date($start_date_field, $end_date_field, $format = null, $alt_error=false) {
	// Validator object factory

	return new ValidDateAfterDate($start_date_field, $end_date_field, $format, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Function Adapter - adapts a function or method to be compatible with the bushido validation system
// -----------------------------------------------------------------------------
class ValidFunctionAdapter extends ValidGroup {
	// calls a custom function or method with name and values parameters

	protected $custom_function = null;
	protected $obj_business = null;

	public function __construct($custom_valid_function, $obj_business = null, $alt_error=false) {
		parent::__construct($alt_error);

		$this->obj_business = $obj_business;
		$this->custom_function = $custom_valid_function;
	}

	public function validate($values) {
		// Return true if custom function/method returns true
		$custom_valid_function = $this->custom_function;

		$is_valid = false;
		if ($this->obj_business) {
			if (method_exists($this->obj_business, $custom_valid_function)) {
				$is_valid = $this->obj_business->$custom_valid_function($this->name, $values, $this);
			}
		} elseif (function_exists($custom_valid_function)) {
			$is_valid = $custom_valid_function($this->name, $values, $this);
		}

		if (!$is_valid) {
			$this->valid_error($this->desc .' is not valid');
		}

		return $is_valid;
	}
}

function valid_function_adapter($custom_valid_function, $obj_business = null, $alt_error=false) {
	// Validator object factory

	return new ValidFunctionAdapter($custom_valid_function, $obj_business, $alt_error);
}

// -----------------------------------------------------------------------------
//	Valid Special Characters - checks the value to make sure all the characters are in the allowed list
// -----------------------------------------------------------------------------
class ValidSpecialCharacters extends Validator {

	// array of special characters allowed in data input
	public $allowed_special_characters =
		array("®","©","™"," ","!",'"',"#",'$',"%","&","'","(",")","*","+",",","-",".","/",":",";","<","=",">","?","@","[","]","{","}","\\","_","`","|","~","^");

	public function __construct($allow_crlf = false, $additional_allowed_characters = null, $alt_error=false) {
		parent::__construct($alt_error);

		// add in to the allowed list if crlf is to be allowed (ie for text areas)
		if ($allow_crlf) {
			$this->allowed_special_characters = array_merge($this->allowed_special_characters, array("\r","\n"));
		}

		//  add in any extra characters to be allowed
		if ($additional_allowed_characters && is_array($additional_allowed_characters)) {
			$this->allowed_special_characters = array_merge($this->allowed_special_characters, $additional_allowed_characters);
		}
	}

	public function validate($value) {

		// if we dont have a value, bail out now
		if ($value === null || $value === '') return true;

		$value = trim(str_replace($this->allowed_special_characters, "", $value));

		// if we still have characters left and at least one is not alpha-numeric, then we fail
		if ($value != "" && !ctype_alnum($value)) {
			$this->valid_error($this->desc . " contains invalid characters");
			return false;
		}

		return true;
	}
}

function valid_special_characters($allow_crlf = false, $additional_allowed_characters = null, $alt_error=false) {
	// Validator object factory

	return new ValidSpecialCharacters($allow_crlf, $additional_allowed_characters, $alt_error);
}
?>