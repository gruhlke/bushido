<?php
// -----------------------------------------------------------------------------
// Error Handling
// -----------------------------------------------------------------------------

function error_handle($error_no, $error_msg, $error_file, $error_line) {
	// Add additional handling for errors raised
	
	if ($error_no == E_USER_NOTICE) {
		// Do not perform default error handling
		return true;
	}
		
	// Use built in PHP error handling
	return false;
}

set_error_handler('error_handle');
?>
