<?php
// Global ADODB object '$db' must be instantiated
//


// BUSHIDO_CHARSET is used in all htmlentities() calls and some multibyte functions - default value is ISO-8859-1 for older 
// versions of PHP (<5.4.0). Site OnCall uses this value for UTF8 support (UTF8_SUPPORT changes default charset).
if (!defined("BUSHIDO_CHARSET")) {
	if (defined("UTF8_SUPPORT") && UTF8_SUPPORT === true) {
		define("BUSHIDO_CHARSET", "UTF-8");
	} else {
		define("BUSHIDO_CHARSET", "ISO-8859-1");
	}
}

// HTMLENTITY_FLAG is used in all htmlentities() calls - default value is ENT_COMPAT (PHP default as well).
if (!defined("HTMLENTITY_FLAG")) {
	define("HTMLENTITY_FLAG", ENT_COMPAT);
}

// Set PHP's multibyte function internal encoding (see php.net/manual/en/function.mb-internal-encoding.php)
mb_internal_encoding(BUSHIDO_CHARSET);


// -----------------------------------------------------------------------------
//	Base
// -----------------------------------------------------------------------------
abstract class Base {
	// Error handling and database reference
	
	protected $db;						// ADODB object ref
	protected $error_msg = array();		// friendly error messages
	
	function __construct($alt_db=false) {
		// Setup reference to ADODB object
		global $db;
		
		if (is_object($alt_db)) {
			$this->db = $alt_db;
		} elseif (isset($db) && is_object($db)) {
			$this->db = $db;
		} else {
			trigger_error("ADODB object was not found", E_USER_ERROR);
		}
	}
	
	public function error($error_msg) {
		// Add user friendly error messages to $error_msg array
		
		if (is_array($error_msg)) {
			$this->error_msg = array_merge($this->error_msg, $error_msg);
		} else {
			$this->error_msg[] = $error_msg;
		}
		
		// Deduplicate errors
		$this->error_msg = array_unique($this->error_msg);
	}
	
	public function get_errors() {
		// Return $error_msg array
		
		return $this->error_msg;
	}
	
	public function error_tpl() {
		// Format error_msg array into template format
		// Template class is required
		
		if (count($this->error_msg)) {
			require_once(dirname(__FILE__) .'/template.class.php');
			
			$error = new Template('tpl/error.tpl.php');
			$error->set('errors', $this->error_msg);
			
			return $error;
		}
	}
	
	public function get_db() {
		// return the internal DB connection
		return $this->db;
	}
}

?>
