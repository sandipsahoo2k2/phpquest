<?php

//ini_set('display_errors', 'On');
//error_reporting(E_ALL);

require_once("../SQLRest.inc.php");
session_start();

class VEHICLE_SERVICE extends SQLREST {

	public function __construct(){
		parent::__construct();          // Init parent contructor
	}

	function getDBServerArray(){
		return array(
			"server" => "127.0.0.1",
			"user" => "root",
			"password" => "",
			"table" => "aimbee_vehicles",
			);
	}

	function getDBColumnDefaults(){
		return array(
			"_id" => "int",
			"vehicle_no" => "charnull",
			"vehicle_type" => "charnull",
			"status" => "char",
			"location" => "char",
			"driver_id" => "intnull",
			"route_id" => "intnull",
			);
	}

	/*
	 * Dynmically call the method based on the query string
	 */
	public function processApi(){
		$func = strtolower(trim(str_replace("/","",$_REQUEST['x'])));
		if((int)method_exists($this,$func) > 0)
			$this->$func();
		else
			$this->response('',404); // If the method not exist with in this class "Page not found".
	}

	private function vehicles(){
		$this->get();
	}
	private function vehicle(){
		$this->get();
	}
}

$service = new VEHICLE_SERVICE;
$service->processApi();
?>
