<?php

require_once(dirname(__FILE__) .'/write_file.class.php');


// -----------------------------------------------------------------------------
//	Tab Seperated
// -----------------------------------------------------------------------------
class TabSeperated extends WriteFile {
	// Write a tab seperated file
	
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
		
		if (is_array($array) == false) {
			$array = array($array);
		}
		$output = implode("\t", $array) ."\n";
		
		return $this->write($output);
	}
}

?>
