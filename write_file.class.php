<?php

require_once(dirname(__FILE__) .'/base.class.php');


// -----------------------------------------------------------------------------
//	Write File
// -----------------------------------------------------------------------------
class WriteFile extends Base {
	// Write to a file, replacing old file if it exists

	public $name;		// path and file name
	protected $handle;	// file handle
	
    public function __construct($file_name) {
    	parent::__construct(false);
    	$this->name = $file_name;
    	$this->handle = fopen($this->name, 'w');
    	if ($this->handle == false) {
    		$this->error('Unable to create or open file');
    	}
    }
    
    public function get_handle() {
    	// Return file handle
    	
    	return $this->handle;
    }
    
    public function write($string) {
    	// Add text to file
    	
    	if ($this->handle == false) {
    		return false;
    	}
    	$written = fwrite($this->handle, $string);
    	if ($written == false) {
    		$this->error('Unable to write to file');
    		return false;
    	} else {
    		return true;
    	}
    }
    
    public function close() {
    	// Close the file
    	
    	if ($this->handle == false) {
    		return false;
    	}
    	$closed = fclose($this->handle);
    	if ($closed == false) {
    		$this->error('Unable to close file');
    		return false;
    	} else {
    		return true;
    	}
    }
}


// -----------------------------------------------------------------------------
//	Write Comma Seperated Values
// -----------------------------------------------------------------------------
class WriteCsv extends WriteFile {
	// Write a csv file
	
	public function add_table($two_d_array) {
		// Add multiple rows of data using a two dimensional array
		
		if (is_array($two_d_array) == false) {
			$two_d_array = array($two_d_array);
		}
		foreach ($two_d_array as $array) {
			$added = $this->add_row($array);
			if ($added === false) {
				return false;
			}
		}
		return true;
	}
	
	public function add_row($array) {
		// Add a new row of data
		
		if ($this->get_handle() == false) {
    		return false;
    	}
		if (is_array($array) == false) {
			$array = array($array);
		}		
		return fputcsv($this->get_handle(), $array);
	}
}
?>
