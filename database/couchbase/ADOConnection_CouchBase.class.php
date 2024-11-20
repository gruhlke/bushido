<?php
// NOTE: server needs couchbase php library installed (php-pecl-couchbase4 php74-php-pecl-couchbase4 php82-php-pecl-couchbase4)
// NOTE: requires version 4.1.5+ of the pecl extension else all queries must use the full path to a table
// NOTE: composer requires couchbase/couchbase and adodb/adodb-php

use Couchbase\Bucket;
use Couchbase\Cluster;
use Couchbase\ClusterOptions;
use Couchbase\Collection;
use Couchbase\Exception\CasMismatchException;
use Couchbase\Exception\CouchbaseException;
use Couchbase\Exception\DocumentExistsException;
use Couchbase\Exception\DocumentNotFoundException;
use Couchbase\Exception\InvalidArgumentException;
use Couchbase\Exception\TimeoutException;
use Couchbase\MutationResult;
use Couchbase\QueryOptions;
use Couchbase\QueryScanConsistency;
use Couchbase\QueryResult;
use Couchbase\Scope;

require_once "transcoder.class.php";
require_once "ADORecordSet_CouchBase.class.php";
require_once "ADORecordSet_CouchBase_Empty.class.php";

class ADOConnection_couchbase extends \ADOConnection {
	protected const LEFT_WRAPPER = "`";
	protected const RIGHT_WRAPPER = "`";
	
	public $databaseType = "couchbase";
		
	protected static Cluster $cluster;
	protected static Bucket $bucket;
	protected static ?Scope $scope;
	
	protected QueryResult $query_result;
	protected bool $last_statement_was_insert = false;
	
	public bool $perform_sql_translations = true;
	
	var $fmtTimeStamp = "'Y-m-d H:i:s'";
	var $fmtDate="'Y-m-d'";
	
	var $hasTransactions = false;

	/**
	 * @throws InvalidArgumentException
	 */
	public static function new_connection(string $connection_string, string $username, string $password, string $bucket_name, ?string $scope_name = null): ADOConnection_CouchBase {
		$options = new ClusterOptions();
		$options->credentials($username, $password);
		
		$scope_name = (defined("DATABASE_SCOPE") ? DATABASE_SCOPE : $scope_name);
				
		self::$cluster = new Cluster($connection_string, $options);
		self::$bucket = self::$cluster->bucket($bucket_name);
		
		if (!is_null($scope_name)) {
			self::$scope = self::$bucket->scope($scope_name);
		} else {
			self::$scope = null;
		}

		return new self();
	}
	
	public function NConnect($connection_string = '', $username = '', $password = '', $bucket_name = '', ?string $scope_name = null) {
		ADOConnection_CouchBase::new_connection($connection_string, $username, $password, $bucket_name, $scope_name);
	}

	/**
	 * @throws CouchbaseException
	 * @throws TimeoutException
	 * @throws InvalidArgumentException
	 */	
	public function Execute($stmt, $data = false) {

		$this->last_statement_was_insert = (stripos($stmt, "INSERT INTO") !== false || stripos($stmt, "couchbase_prepared_insert") !== false);
		if ($this->last_statement_was_insert) $this->lastInsID = false;

		$options = new QueryOptions();
		$options->transcoder(new transcoder());
		$options->scanConsistency(QueryScanConsistency::REQUEST_PLUS);
		
		// check to see if this is a prepared statement
		if (stripos($stmt, "couchbase_prepared_") !== false) {
			$stmt = "EXECUTE " . $stmt;
		} else {
			if ($this->perform_sql_translations && is_string($stmt)) {
				$stmt = $this->translate_sql($stmt);
			}
		}

		if (!empty($data)) {
			// if the array is an associative array
			if (array_keys($data) !== range(0, count($data) - 1)) {
				$options->namedParameters($data);	
			} else {
				$options->positionalParameters(array_values($data));
			}
		}
				
		if (!is_null(self::$scope)) {
			$this->query_result = self::$scope->query($stmt, $options);
		} else {
			$this->query_result = self::$cluster->query($stmt, $options);
		}
		
		if (!empty($this->query_result->rows())) {
			if ($this->last_statement_was_insert && isset($this->query_result->rows()[0]["id"])) {
				$this->lastInsID = $this->query_result->rows()[0]["id"];
			}
			
			$rs_class = $this->rsPrefix . $this->databaseType;
			$rs_class = new $rs_class($this->query_result);
		} else {
			$rs_class = $this->rsPrefix . $this->databaseType . "_empty";
			$rs_class = new $rs_class();
		}
		
		$rs_class->connection = $this;
		$rs_class->Init();
		
		return $rs_class;
	}

	public function Insert_ID($foo = "", $bar = "") {
		return $this->lastInsID;
	}

	function Prepare($sql) {
		
		$statement_id = null;
		
		// build the prepared statement id and include insert if its an insert
		$prepared_statement_id = uniqid("couchbase_prepared_" . (stripos($sql, "INSERT INTO") !== false ? "insert_" : ""));

		if ($this->perform_sql_translations && is_string($sql)) {
			$sql = $this->translate_sql($sql);
		}
		
		$sql = "PREPARE " . $prepared_statement_id . " AS " . $sql;
		
		if (!is_null(self::$scope)) {
			$query_result = self::$scope->query($sql);
		} else {
			$query_result = self::$cluster->query($sql);
		}

		if (!empty($query_result->rows())) {
			$statement_id = $query_result->rows()[0]["name"];
		}
				
		return $statement_id;
	}

	// perform some translations from MySQL that can be handled easily
	protected function translate_sql(string $sql) : string {
		
		// convert any NOW() references
		$sql = str_ireplace("NOW()", "NOW_TZ('America/Chicago', 'YYYY-MM-DD hh:mm:ss')", $sql);
		
		// convert any CAST references
		$sql = preg_replace('/CAST\((.*?)\s+AS\s+[A-Za-z_]+\)/i', '$1', $sql);

		// convert UNIX_TIMESTAMP to MILLIS
		$sql = preg_replace('/UNIX_TIMESTAMP\(([^)]+)\)/i', '(MILLIS($1) / 1000)', $sql);

		// perform INSERT translation
		if (stripos($sql, "INSERT INTO") !== false) {

			// make sure the insert isnt already in the right format
			if (stripos($sql, "RETURNING META().id") === false) {
			
				if (stripos($sql, " SET ") !== false) {
					$sql = str_ireplace(" SET ", " (KEY, VALUE) VALUES (UUID(), {", $sql);
					$sql = str_ireplace("=", " : ", $sql);
					$sql .= "}) RETURNING META().id";

					// wrap names of json values in quotes as required by the insert
					$sql = preg_replace('/(?<=\{|,\s)([A-Za-z_][A-Za-z0-9_]*)(?=\s*:)/', '"$1"', $sql);
				} elseif (stripos($sql, " VALUES ") !== false) {
					// split SQL based on parens
					if (preg_match_all("/\((.*?)\) VALUES \((.*?)\)/i", $sql, $matches)) {

						$sql = substr($sql, 0, strpos($sql, "(")) . " (KEY, VALUE) VALUES (UUID(),";										
						$fields = str_getcsv($matches[1][0], ",");

						// trim spaces and single quotes from the field names
						$fields = array_map(function($value) {
							return trim($value, " \t\n\r\0\x0B'\"`");
						}, $fields);

						$values = preg_split('/,\s*(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $matches[2][0]);

						// trim spaces and single quotes from the values
						$values = array_map(function($value) {
							return trim($value, " \t\n\r\0\x0B'\"");
						}, $values);

						$json_insert_data = json_encode(array_combine($fields, $values), JSON_NUMERIC_CHECK);
						
						// remove quotes from around any value that starts with a $ (ie prepared statement placeholders)
						$json_insert_data = preg_replace('/"\$([^"]+)"/', '$$1', $json_insert_data);
												
						$sql = $sql . $json_insert_data . ") RETURNING META().id";					
					}
				}
			}
		}

		return $sql;
	}
}