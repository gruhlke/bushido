<?php
require_once('auth.class.php');

class login_couchbase extends Login {

	public function sql_hash($password) {
		// Create SQL string for hashing a password
		
		$password = trim($password);
		return $this->db->qstr(sha1($password . BUSHIDO_SALT_STRING));
	}	
	
	public function sql_encrypt($username, $password) {
		throw new Exception("sql_encrypt is not supported.");
	}
	
	public function sql_decrypt() {
		throw new Exception("sql_decrypt is not supported.");
	}
}