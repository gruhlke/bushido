<?php
require_once(dirname(__FILE__) . '/meta_table.class.php');

class email_log extends record_table
{

	public function __construct() {
		parent::__construct("email_log", "id");
	}

	protected function init_meta_table() {
		$this->meta_table = array(
			"id" => array("db_field" => true),
			"recipient" => array("db_field" => true),
			"sender" => array("db_field" => true),
			"subject" => array("db_field" => true),
			"body" => array("db_field" => true),
			"category" => array("db_field" => true),
			"send_date" => array("db_field" => true, "get_value" => "get_send_date")
		);
	}

	public function get_send_date($field = null) {
		return date("Y-m-d H:i:s");
	}

	public function set_data_from_email_class($data) {
		$this->set(array(
			"recipient" => $data["to"],
			"sender" => $data["from"],
			"subject" => $data["subject"],
			"body" => $data["body"],
			"category" => $data["category"]));
	}
}

?>