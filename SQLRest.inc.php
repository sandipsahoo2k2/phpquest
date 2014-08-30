<?php

require_once '../../includes/config.php'; // The mysql database connection script

abstract class SQLREST {

	public $_content_type = "application/json";
	public $_request = array();

	private $_method = "";		
	private $_code = 200;
	private $applyOverRide = false;

	protected $COLUMN_DEFAULTS = array();
	protected $COLUMN_OVERRIDES = array();
	protected $DB_SERVER_DETAILS = array();
	protected $mysqli = NULL;

	public function __construct(){
		$serverArray = $this->getDBServerArray();
		if (is_array($serverArray))
		{
			$this->DB_SERVER_DETAILS = $serverArray;
		}
		$columnsArray = $this->getDBColumnDefaults();
		if (is_array($columnsArray))
		{
			$this->COLUMN_DEFAULTS = $columnsArray;
		}
		$this->inputs();
		$this->dbConnect();
	}

	abstract protected function getDBServerArray() ;
	abstract protected function getDBColumnDefaults() ;
	
	/*
	 *  Connect to Database
	 */
	private function dbConnect(){
		global $mysqli;
		$this->mysqli = $mysqli;
		//$this->mysqli = new mysqli($this->DB_SERVER_DETAILS['server'], $this->DB_SERVER_DETAILS['user'], $this->DB_SERVER_DETAILS['password'], $this->DB_SERVER_DETAILS['table']);
	}

	public function getConnection()
	{
		return $this->mysqli;
	}

	public function get_referer(){
		return $_SERVER['HTTP_REFERER'];
	}

	public function response($data,$status){
		$this->_code = ($status)?$status:200;
		$this->set_headers();
		echo $data;
		exit;
	}
	// For a list of http codes checkout http://en.wikipedia.org/wiki/List_of_HTTP_status_codes
	private function get_status_message(){
		$status = array(
				200 => 'OK',
				201 => 'Created',  
				204 => 'No Content',  
				404 => 'Not Found',  
				406 => 'Not Acceptable',
				409 => 'Already Exist');
		return ($status[$this->_code])?$status[$this->_code]:$status[500];
	}

	public function get_request_method(){
		return $_SERVER['REQUEST_METHOD'];
	}

	private function inputs(){
		switch($this->get_request_method()){
			case "POST":
			case "PUT":
				$this->_request = $this->cleanInputs($_POST);
				break;
			case "GET":
			case "DELETE":
				$this->_request = $this->cleanInputs($_GET);
				break;
			default:
				$this->response('',406);
				break;
		}
	}		

	private function cleanInputs($data){
		$clean_input = array();
		if(is_array($data)){
			foreach($data as $k => $v){
				$clean_input[$k] = $this->cleanInputs($v);
			}
		}else{
			if(get_magic_quotes_gpc()){
				$data = trim(stripslashes($data));
			}
			$data = strip_tags($data);
			$clean_input = trim($data);
		}
		return $clean_input;
	}		

	private function set_headers(){
		header("HTTP/1.1 ".$this->_code." ".$this->get_status_message());
		header("Content-Type:".$this->_content_type);
	}

	protected function get(){	
		if($this->get_request_method() != "GET"){
			$this->response('',406);
		}
		$query= $this->getQueryFromPayload($this->COLUMN_DEFAULTS, "SELECT");
		$request_id = '';
		if(in_array('_id', array_keys($this->_request))) 
		{
			$request_id = $this->_request['_id']; //if request_id = 0 dont use empty()
			$id = (int)$request_id;
			$query .= " WHERE _id=$id";
		}
		$r = $this->mysqli->query($query) or die($this->mysqli->error.__LINE_);
		if($r->num_rows > 0) {
			if(empty($request_id))
			{
				$result = array();
				while($row = $r->fetch_assoc()){
					$result[] = $row;
				}
				$this->response($this->json($result), 200); // send user details
			}
			else
			{
				$result = $r->fetch_assoc();	
				$this->response($this->json($result), 200); // send user details
			}
		}
		$this->response('',204);	// If no records "No Content" status
	}

	protected function add(){
		if($this->get_request_method() != "POST"){
			$this->response('',406);
		}

		$inputRecord = json_decode(file_get_contents("php://input"),true);
		if(!empty($inputRecord)){
			$query = $this->getQueryFromPayload($inputRecord, "INSERT");
			$r = $this->mysqli->query($query) or die($this->mysqli->error.__LINE__.$query);
			$success = array('status' => "Success", "msg" => "Record Created Successfully.", "data" => $inputRecord);
			$this->response($this->json($success),200);
		}else
			$this->response('',204);	//"No Content" status
	}


	protected function update(){
		if($this->get_request_method() != "PUT"){
			$this->response('',406);
		}
		$inputRecord = json_decode(file_get_contents("php://input"),true);
		if(!empty($inputRecord)){
			$query = $this->getQueryFromPayload($inputRecord, "UPDATE");
			$r = $this->mysqli->query($query) or die($this->mysqli->error.__LINE__.$query);
			$success = array('status' => "Success", "msg" => "Successfully updated record with _id=" . $inputRecord['_id'] , "data" => $inputRecord);
			$this->response($this->json($success),200);
		}else
			$this->response('',204);	// "No Content" status
	}

	protected function delete(){
		if($this->get_request_method() != "DELETE"){
			$this->response('',406);
		}
		$id = (int)$this->_request['_id'];
		if($id > 0){				
			$query= $this->getQueryFromPayload($this->COLUMN_DEFAULTS, "DELETE");
			$query .= " WHERE _id = $id";
			$r = $this->mysqli->query($query) or die($this->mysqli->error.__LINE__);
			$success = array('status' => "Success", "msg" => "Successfully deleted record with _id=" . $id);
			$this->response($this->json($success),200);
		}else
			$this->response('',204);	// If no records "No Content" status
	}

	private	function getQueryFromPayload($theInputArray, $TYPE)
	{
		$query = '';
		$incoming_keys = array_keys($theInputArray);
		$setParameters = '';
		$insertColumns = '';
		$insertValues = '';
		if($TYPE == "DELETE")
		{
			$query = "DELETE FROM " . $this->DB_SERVER_DETAILS['table'] ;
		}
		else
		{
			foreach($this->COLUMN_DEFAULTS as $key => $value)
			{ 
				if($TYPE == "SELECT")
				{
					$insertColumns .= $key . ',';	
				}
				else
				{
					// Check the record received from socket request. If key does not exist, insert default into the array.
					$desired_value = '';
					if(!in_array($key, $incoming_keys)) 
					{
						switch ($value) 
						{
							case "char":
								$desired_value = "''"; //empty string
								break;
							case "charnull":
							case "intnull":
								$desired_value = 'NULL';
								break;
							case "int":
								$desired_value = 'NULL';
								break;
							default:
								$desired_value = $value;
						}
					}
					else
					{
						$temp = $theInputArray[$key];
						/*if(empty($temp))
						{
							print '<pre>' . print_r("empty:". $temp, true) . '</pre>';
						}*/
						if(is_null($temp))
						{
							//print '<pre>' . print_r("is_null:". $temp, true) . '</pre>';
							$desired_value = 'NULL';
						}
						else
						{
							$desired_value = "'" . $theInputArray[$key] . "'";
						}
					}
					if ($TYPE == "UPDATE")
					{
						$setParameters .= $key . "=" . $desired_value . ",";
					}
					else if($TYPE == "INSERT")
					{
						$insertColumns .= $key . ',';
						$insertValues  .= $desired_value . ","; //make sure $desired_value comes with '' for strings
					}
				}
			}
			if ($TYPE == "UPDATE")
			{
				$id = (int)$theInputArray['_id'];
				$query = "UPDATE " . $this->DB_SERVER_DETAILS['table'] . " SET " . chop($setParameters, ",") . " WHERE _id=$id";
			}
			else if ($TYPE == "INSERT")
			{
				$query = "INSERT INTO " . $this->DB_SERVER_DETAILS['table'] . "(" . chop($insertColumns, ",") . ") VALUES (" . chop($insertValues, ",") . ")";
			}
			else
			{
				$query = "SELECT " . chop($insertColumns, ",") . " FROM " . $this->DB_SERVER_DETAILS['table'];
			}
		}
		return $query;
	}

	/*
	 *	Encode array into JSON
	 */
	protected function json($data){
		if(is_array($data)){
			return json_encode($data);
		}
	}
}
?>
