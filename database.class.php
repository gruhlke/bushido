<?php
// ADODB class extension and object factories
require_once("adodb.inc.php");

if (!defined("DATABASE_TYPE")) {
	define("DATABASE_TYPE", "mysqli");
}

switch (DATABASE_TYPE) {
	
	case "mssql":
		require_once("drivers/adodb-mssql.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_mssql {
			public $column_wrapper_left = "[";
			public $column_wrapper_right = "]";

			// Thin wrapper for adodb_mssql
			public function create_select_query($parameters = array()) {
				if (isset($parameters["from"])) {
					$select = isset($parameters["select"]) ? $parameters["select"] : "*";
					$from = $parameters["from"];
					$where = isset($parameters["where"]) ? $parameters["where"] : "";
					$order_by = isset($parameters["order_by"]) ? $parameters["order_by"] : "";
					$limit = isset($parameters["limit"]) ? $parameters["limit"] : "";
					if (is_array($limit)) {
						if (ctype_digit($limit["number"]) && ctype_digit($limit["offset"])) {
							$start =  $limit["offset"] - $limit["number"];
							$sql = "
								SELECT *
								FROM (
									SELECT TOP " . $limit["offset"] . " " . $select . ", ROW_NUMBER() OVER(" . $order_by . ") as RowNum
									FROM " . $from . "
									" . $where . "
								) AS page
								WHERE RowNum > " . $start;
						}
					} else {
						if (ctype_digit($limit)) {
							$sql = "
								SELECT TOP " . $limit . " " . $select . "
								FROM " . $from . "
								" . $where . "
								" . $order_by;
						} else {
							$sql = "
								SELECT " . $limit . " " . $select . "
								FROM " . $from . "
								" . $where . "
								" . $order_by;
						}
					}
					return $sql;
				}
				return null;
			}
		}

		break;
		
	case "mssqlnative":
		require_once("drivers/adodb-mssqlnative.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_mssqlnative {
			public $column_wrapper_left = "[";
			public $column_wrapper_right = "]";

			// Thin wrapper for adodb_mssql
			public function create_select_query($parameters = array()) {
				if (isset($parameters["from"])) {
					$select = isset($parameters["select"]) ? $parameters["select"] : "*";
					$from = $parameters["from"];
					$where = isset($parameters["where"]) ? $parameters["where"] : "";
					$order_by = isset($parameters["order_by"]) ? $parameters["order_by"] : "";
					$limit = isset($parameters["limit"]) ? $parameters["limit"] : "";
					if (is_array($limit)) {
						if (ctype_digit($limit["number"]) && ctype_digit($limit["offset"])) {
							$start =  $limit["offset"] - $limit["number"];
							$sql = "
								SELECT *
								FROM (
									SELECT TOP " . $limit["offset"] . " " . $select . ", ROW_NUMBER() OVER(" . $order_by . ") as RowNum
									FROM " . $from . "
									" . $where . "
								) AS page
								WHERE RowNum > " . $start;
						}
					} else {
						if (ctype_digit($limit)) {
							$sql = "
								SELECT TOP " . $limit . " " . $select . "
								FROM " . $from . "
								" . $where . "
								" . $order_by;
						} else {
							$sql = "
								SELECT " . $limit . " " . $select . "
								FROM " . $from . "
								" . $where . "
								" . $order_by;
						}
					}
					return $sql;
				}
				return null;
			}
		
		}
		break;

	case "mysql":
		require_once("drivers/adodb-mysql.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_mysql {
			public $column_wrapper_left = "`";
			public $column_wrapper_right = "`";

			// Thin wrapper for adodb_mysql
			public function create_select_query($parameters = array()) {
				if (isset($parameters["from"])) {
					$parameters = array_merge(array(
						"select" => "*",
						"where" => "",
						"order_by" => "",
						"limit" => ""), $parameters);
					return "
						SELECT " . $parameters["select"] . "
						FROM " . $parameters["from"] . "
						" . $parameters["where"] . "
						" . $parameters["order_by"] . "
						" . $parameters["limit"];
				}
				return null;
			}
		}

		break;

	case "mysqli":
		require_once("drivers/adodb-mysqli.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_mysqli {
			public $column_wrapper_left = "`";
			public $column_wrapper_right = "`";
		
			// Thin wrapper for adodb_mysql
			public function create_select_query($parameters = array()) {
				if (isset($parameters["from"])) {
					$parameters = array_merge(array(
							"select" => "*",
							"where" => "",
							"order_by" => "",
							"limit" => ""), $parameters);
					return "
						SELECT " . $parameters["select"] . "
						FROM " . $parameters["from"] . "
						" . $parameters["where"] . "
						" . $parameters["order_by"] . "
						" . $parameters["limit"];
				}
				return null;
			}
		}
		break;
		
	case "odbc":
		require_once("drivers/adodb-odbc.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_odbc { }
		break;
	
	case "oci8":
	case "oracle":
		require_once("drivers/adodb-oci8.inc.php");
		// -----------------------------------------------------------------------------
		class database_specific extends ADODB_oci8 {
			public $column_wrapper_left = "\"";
			public $column_wrapper_right = "\"";
			var $hasInsertID = true;
			
			/* code for creating an autoindexing field in oracle
				CREATE SEQUENCE TABLE_NAME_SEQ START WITH 1 INCREMENT BY 1;

				CREATE OR REPLACE TRIGGER TABLE_NAME_TRIG BEFORE INSERT ON TABLE_NAME
				for each row
				begin
				select TABLE_NAME_SEQ.nextval
				into :new.ID_FIELD_FROM_TABLE_NAME
				from dual;
				end;
			*/
			
			// relies on naming convention of TABLE_NAME_SEQ for the sequence
			function _insertid($table, $column = "") {
				return ADOConnection::GetOne("SELECT " . strtoupper($table) . "_SEQ.CURRVAL FROM dual");
			}
		}
		break;

	case "couchbase":
		require_once "database/couchbase/ADOConnection_CouchBase.class.php";
		
		class database_specific extends ADOConnection_couchbase {
			public $column_wrapper_left = "`";
			public $column_wrapper_right = "`";
			
		}
		
		break;
}

class Database extends database_specific {
	
	protected $fetch_mode_stack = array();

	// add database property

	public function results_check($results, $fail_empty=false) {
		// Check ADODB results set, if false trigger error

		if ($results === false) {
			// Get file backtrace
			$backtrace = debug_backtrace();
			$bt_print = "";
			$files = array();
			foreach ($backtrace as $trace) {
				if (isset($trace["file"]) and isset($trace["line"])) {
					$files[] = $trace["file"] ." line ". $trace["line"];
				}
			}
			$error = "Unable to execute SQL query. ".
				$this->ErrorMsg() ." ".
				"<strong>traceback:</strong> ". implode(" -> ", $files);
			trigger_error($error, E_USER_WARNING);
			return false;

		} elseif ($fail_empty and is_object($results) and $results->EOF) {
			return false;

		} elseif ($fail_empty and is_array($results) and count($results) == 0) {
			return false;

		} else {
			return true;
		}
	}

	public function push_fetch_mode($set_fetch_mode_to = null) {
		// push the current value
		array_push($this->fetch_mode_stack, $this->fetchMode);
		// if desired, set to something else or false to use the set global
		$this->SetFetchMode(($set_fetch_mode_to !== null ? $set_fetch_mode_to : false));
	}

	public function pop_fetch_mode() {
		// will be false of pushed when not set
		$this->SetFetchMode(array_pop($this->fetch_mode_stack));
	}
}

// -----------------------------------------------------------------------------
//	Override ADODB
// -----------------------------------------------------------------------------

$ADODB_NEWCONNECTION = "db_factory";

function db_factory($driver) {
	// DB object factory that overrides ADODB's default
	
	$db = new Database();
	return $db;
}

// -----------------------------------------------------------------------------
//	Connection object factory
// -----------------------------------------------------------------------------

function db_connection_factory($host, $user, $pass, $database) {
	// Create a connection object
	$db = ADONewConnection(DATABASE_TYPE);
	
	$db->NConnect($host, $user, $pass, $database);
	$db->SetFetchMode(ADODB_FETCH_ASSOC);
	return $db;
}

?>
