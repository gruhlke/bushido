<?php
// Template Class
//
require_once(dirname(__FILE__) .'/base.class.php');

// define USE_STREAMING_TEMPLATES and set to true in order to globally use streaming templates

// -----------------------------------------------------------------------------
//	Template
// -----------------------------------------------------------------------------
class Template extends Base {
	// Lightweight template system

	protected $vars = array();	// Holds all the template variables
	protected $streamed_templates = array();
	public $streamed = false;
	
	public function __construct($file=null) {
		// No DB access is required, do not call parent::__construct
		$this->file = $file;
	}
	
	public function __toString() {
		try {
			return $this->fetch_template();
		} catch (Exception $e) {
			if (DEV_ENVIRONMENT) {
				var_dump($e);
			}
		}
	}

	private function fetch_streamed_template($name) {
		echo str_repeat(" ", 8000); // fill up the buffer for the flush
		ob_flush();
		foreach ($this->streamed_templates[$name] as $template) {
			echo str_repeat(" ", 8000); // fill up the buffer for the flush
			$template->fetch_template();
		}
	}

	public function set($name, $value, $streamed = null) {
		// Set a template variable
		// Inner templates can be appended to one variable
		
		$streamed = (is_null($streamed) && defined('USE_STREAMING_TEMPLATES') && USE_STREAMING_TEMPLATES ? true : $streamed);
		
		if ($name != '') { // basic check to make sure we passed a variable name
			if (is_object($value) && method_exists($value, 'fetch_template')) {
				if (!$streamed) {
					if (!isset($this->vars[$name])) 
						$this->vars[$name] = "";
					$this->vars[$name] .= $value->fetch_template();
				} else {
					if (!isset($this->vars[$name])) {
						$this->vars[$name] = '$this->fetch_streamed_template("' . $name . '");';
					}
					$value->streamed = true;
					$this->streamed_templates[$name][] = $value;
				}
			} else {
				$this->vars[$name] = $value;
			}
		}
	}
	
	public function set_multi($array) {
		// Set several variables at once
		// $array must be in the format ('var name' => 'value')

		if (is_array($array)) { // basic check to make sure an array was passed
			foreach ($array as $name => $value) {
				$this->set($name, $value);
			}
		}
	}

	public function fetch_template($template_file=null) {
		// Open, parse, and return the template file

		if (!$template_file) {
			$template_file = $this->file;
		}
		
		extract($this->vars);			// Extract the vars to local namespace
		
		// if REMOVE_REQUEST is defined, we remove these from being available in the template.  
		if (defined('REMOVE_REQUEST_VARS') && REMOVE_REQUEST_VARS == true) {
			$_restore_get = (isset($_GET) ? $_GET : null);
			$_restore_post = (isset($_POST) ? $_POST : null);
			$_restore_request = (isset($_REQUEST) ? $_REQUEST : null);
			$_restore_cookie = (isset($_COOKIE) ? $_COOKIE : null);
			
			unset($_GET);
			unset($_POST);
			unset($_REQUEST);
			unset($_COOKIE);
		}

		// if there are any streaming templates, replace the regular output variable with the streamed eval() version
		if (count($this->streamed_templates) > 0) {
			$template_text = file_get_contents($template_file, true);
			foreach ($this->streamed_templates as $name => $template) {
				$template_text = str_replace('$' . $name . ";", 'eval($' . $name . ');', $template_text);
				$template_text = str_replace('if_var($' . $name . ");", 'eval($' . $name . ');', $template_text);
			}
				
			$temp_template = tempnam("/tmp", "template_");
			file_put_contents($temp_template, $template_text);
			$template_file = $temp_template;
		} 
		
		$contents = "";
		
		if (!$this->streamed) {
			ob_start();						// Start output buffering
			include($template_file);		// Include the file
			$contents = ob_get_contents();	// Get the contents of the buffer
			ob_end_clean();					// End buffering and discard
		} else {
			include($template_file);		// Include the file
			ob_flush();
		}
		
		if (isset($temp_template) && is_file($temp_template)) {
			unlink($temp_template);
		}
		
		// if REMOVE_REQUEST is defined, restore the variables
		if (defined('REMOVE_REQUEST_VARS') && REMOVE_REQUEST_VARS == true) {
			$_GET = $_restore_get;
			$_POST = $_restore_post;
			$_REQUEST = $_restore_request;
			$_COOKIE = $_restore_cookie;
		}
		
		return $contents;				// Return the contents
	}
}

// -----------------------------------------------------------------------------
//	Helper functions
// -----------------------------------------------------------------------------

function if_var(&$variable, $default=null) {
	// Return variable if set, otherwise return default

	return isset($variable) ? $variable : $default;
}

?>
