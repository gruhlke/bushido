<?php
use Couchbase\QueryResult;

class ADORecordSet_couchbase extends \ADORecordSet_array {
	protected QueryResult $query_result;
	
	public function __construct($query_result) {
		$this->query_result = $query_result;
		$queryID = $this->query_result->metaData()->requestId();
		
		parent::__construct($queryID);
		
		$this->init_from_result();
	}
	
	protected function init_from_result(): void {
		$rows = $this->query_result->rows();
		
		$first_record = $rows[0];
		
		$col_names = array_keys($first_record);
		$col_types = array_fill(0, count($col_names), 'C');
		
		$this->InitArray($rows, $col_types, $col_names);
	}
}