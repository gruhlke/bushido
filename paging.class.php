<?php
require_once(dirname(__FILE__) .'/form.class.php');


// -----------------------------------------------------------------------------
//	Paging
// -----------------------------------------------------------------------------
class Paging {
	// Handle splitting lists of data into pages
	
	public $total;			// Total records
	public $total_pages;	// Total pages
	public $page;			// Current page
	public $increment;		// Current set increment
	public $start;			// Starting record (0 based)
	public $end;			// Last record on page
	protected $form_data;	// GET or POST data, NOT VALIDATED :O
	protected $form;		// Page / Increment validation
	protected $fields;		// Array of fields
	
	public function __construct($form_data, $total, $incs=array("20", "50", "100")) {
		// $form_data should include the page, increment and previous increment
		
		$increment_list = array();
		foreach ($incs as $value) {
			$increment_list[$value] = $value;
		}
		
		// Setup form
		$increment_field = new SelectField('increment', 'Results Per Page', 
			array(valid_require(), valid_white_list($increment_list)), 
			$increment_list);
		$prev_inc_field = new HiddenField('prev_inc', 'Previous Increment', 
			valid_white_list($increment_list));
		$page_field = new HiddenField('page', 'Page', array(valid_digit()));
		$page_select = new SelectField('page_select', 'Page', array(valid_digit()), array());
		$page_fields = new FieldGroup('page_fields', array($page_field, $page_select), valid_require_count(1));
		
		$this->fields = array($increment_field, $prev_inc_field, $page_fields);
		$this->form = new FieldGroup('paging_form', $this->fields);
		
		// GET or POST data
		$this->form_data = $form_data;
		
		// Total results
		$this->total = $total;
		
		// Page, Increment, Total Pages
		if ($this->form->validate($this->form_data)) {
			if (isset($this->form_data['page_select']) and $this->form_data['page_select']) {
				$this->page = $this->form_data['page_select'];
			} else {
				$this->page = $this->form_data['page'];
			}
			$this->increment = $this->form_data['increment'];
			$this->total_pages = $this->calc_total_pages();
			// Adjust page if needed
			if (isset($this->form_data['prev_inc']) and $this->form_data['prev_inc'] != $this->increment) {
				$start = $this->calc_start($this->page, $this->form_data['prev_inc']);
				$this->page = $this->calc_page($start);
			} elseif ($this->page > $this->total_pages) {
				$this->page = $this->total_pages;
			}
		} else {
			$this->page = 1;
			$this->increment = $incs[0];
			$this->total_pages = $this->calc_total_pages();
		}
		
		// Update page_select with vaid range of pages
		$page_options = array();
		for ($i = 1; $i <= $this->total_pages; $i++) {
			$page_options[$i] = $i;
		}
		$page_select->options = $page_options;
		
		// Start and End records for page
		$this->start = $this->calc_start();
		$this->end = $this->calc_end();
	}
	
	public function limit_sql() {
		// Return string for limit SQL
		
		return "LIMIT ". $this->start .", ". $this->increment;
	}
	
	public function output() {
		// Output paging form / data
		
		$form_data = array("increment" => $this->increment, 
			"prev_inc" => $this->increment,
			"page" => $this->page,
			"page_select" => $this->page);
		$data = $this->form->output($form_data);
		$data['current_page'] = $this->page;
		$data['total_pages'] = $this->total_pages;
		$data['page_links'] = $this->page_links();
		$data['start'] = $this->start + 1;
		$data['end'] = $this->end;
		$data['total'] = $this->total;
		
		return $data;
	}
	
	public function page_links() {
		// Return array of page => $_GET string
		
		$base_get = $this->base_get_string();
		$links = array();
		for ($i = 1; $i <= $this->total_pages; $i++) {
			$links[$i] = $base_get ."page=". $i .
				"&amp;increment=". $this->increment .
				"&amp;prev_inc=". $this->increment;
		}
		
		return $links;
	}
	
	protected function base_get_string() {
		// Return string of $_GET variables except page, increment, and page_select
		
		// Filter form data into base URL
		$variables = array();
		foreach ($this->form_data as $key => $value) {
			if ($key != "page" and $key != "increment" and $key != "prev_inc" and $key != "page_select") {
				// if the form contains indexed form names the resulting array value needs to be handled
				if (is_array($value)) {
					foreach ($value as $value_key => $value_value) {
						$variables[] = htmlentities($key, HTMLENTITY_FLAG, BUSHIDO_CHARSET) . "[" . htmlentities($value_key, HTMLENTITY_FLAG, BUSHIDO_CHARSET) . "]=" . htmlentities($value_value, HTMLENTITY_FLAG, BUSHIDO_CHARSET);
					}
				} else {
					$variables[] = htmlentities($key, HTMLENTITY_FLAG, BUSHIDO_CHARSET) ."=". htmlentities($value, HTMLENTITY_FLAG, BUSHIDO_CHARSET);
				}
			}
		}
		
		if (count($variables)) {
			return "?". implode('&amp;', $variables) ."&amp;";
		} else {
			return "?";
		}
	}
	
	protected function calc_total_pages($increment=false) {
		// Return total number of pages for increment
		
		if (!$increment) {
			$increment = $this->increment;
		}
		
		return ceil($this->total / $increment);
	}
	
	protected function calc_start($page=false, $increment=false) {
		// Calculate the starting record to show
		
		if ($page and $increment) {
			$total_pages = $this->calc_total_pages($increment);
		} else {
			$page = $this->page;
			$increment = $this->increment;
			$total_pages = $this->total_pages;
		}
		
		if ($page > 0 and $increment > 0 and $this->total > 0) {
			if ($page > $total_pages) {
				$page = $total_pages;
			}
			return ($page - 1) * $increment;
		} else {
			return 0;
		}
	}
	
	protected function calc_end($start=false, $increment=false) {
		// Calculate the ending record to show
		
		if ($start === false or !$increment) {
			$start = $this->start;
			$increment = $this->increment;
		}
		
		$end = $start + $increment;
		if ($end > $this->total) {
			$end = $this->total;
		}
		
		return $end;
	}
	
	protected function calc_page($start, $increment=false) {
		// Calculate current page number
		
		if (!$increment) {
			$increment = $this->increment;
		}
		
		if ($start > 0 and $increment > 0) {
			return ceil(($start + 1) / $increment);
		} else {
			return 1;
		}
	}
}


// -----------------------------------------------------------------------------
//	Record Set Paging
// -----------------------------------------------------------------------------
class PagingRecordSet extends Paging {
	// Split an already existing recordset into pages
	
	protected $record_set;	// ADOdb record set
	
	function __construct($form_data, $record_set, $incs=array("20", "50", "100")) {
		
		$this->record_set = $record_set;
		// Total records
		if ($this->record_set) {
			$total = $this->record_set->RecordCount();
		} else {
			$total = 0;
		}
		parent::__construct($form_data, $total, $incs);
	}
	
	public function page_results($page=null, $increment=null) {
		// Return 2 dimensional array of results for page
		
		if ($page and $increment) {
			$start = $this->calc_start($page, $increment);
			$end = $this->calc_end($start, $increment);
		} else {
			$start = $this->start;
			$end = $this->end;
		}
		
		$results = array();
		if ($this->total > 0) {
			$this->record_set->Move($start);
			while ($this->record_set->CurrentRow() < $end) {
				$results[] = $this->record_set->FetchRow();
			}
		}
		
		return $results;
	}
}
		
?>