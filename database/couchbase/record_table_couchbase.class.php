<?php
require_once('meta_table.class.php');

abstract class Record_Table_Couchbase extends Record_Table {
	
    function __construct($table_name, $key_field, $root_group_name = null, $alt_db = null) {    	
    	parent::__construct($table_name, $key_field, $root_group_name, $alt_db);
    	$this->init_couchbase();
    }
	
	protected function init_couchbase() {
		// if the value is a numeric value, then dont wrap it in quotes
		$this->quotestring_numbers = false;

		// check to see if the id field is in the metatable and if so, add the necessary pieces
		if (isset($this->meta_table[$this->key_field])) {
			$this->set_meta_table_value($this->key_field, "field_as", "META().id");
			$this->set_meta_table_value($this->key_field, "db_readonly", true);
		}
	}
	
	// override for custom load and save where clause
	public function where_clause($id = null) {
		$id = (is_null($id) ? $this->get_id() : $id);
		return 	($this->auto_key_field ? "META({$this->table_name}).id" : $this->key_field) . " = " . ($this->quotestring_numbers || is_string($id) ? $this->db->qstr($id) : $id);
	}
	
	public function save($fields = null) {
		$is_saved = parent::save($fields);		
		return ($is_saved && !is_bool($is_saved) ? true : $is_saved);
	}
	
    public function is_new($id = null) {
		$id = (!$id ? $this->get_id() : $id);

		if ($this->auto_key_field) {
			return (!is_string($id) || (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id) !== 1));
		} else { // manually check
			$check_sql = "SELECT " .($this->auto_key_field ? "META({$this->table_name}).id AS " . $this->key_field : $this->key_field) . 
			" FROM " . $this->table_name . 
			" WHERE " . $this->where_clause($id);		
			$new_check = $this->db->Execute($check_sql);
			return (!$new_check || $new_check->EOF);
		}
    }	
}