<?php

class mySQL extends PDO{

	private $connection;
	public $queries = array(
	);


	public function __construct($dbHost,$dbName,$dbUser,$dbPass){
		$this->connection = new PDO('mysql:host='.$dbHost.';dbname='.$dbName.';charset=utf8',$dbUser,$dbPass);
		$this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, TRUE);
		$this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
		$this->connection->exec("SET time_zone = '+00:00'");
	}

	public function query($query){
		// Check If We Referred To A Pre-Existing Query; If Not Assume It Is A Raw Query
		if(isset($this->queries[$query])){
			return $this->connection->query($this->queries[$query]);
		}else{
			return $this->connection->query($query);
		}
	}

	public function prepare($query,$options = NULL){
		// Check If We Referred To A Pre-Existing Query; If Not Assume It Is A Raw Query
		if(isset($this->queries[$query])){
			return $this->connection->prepare($this->queries[$query]);
		}else{
			return $this->connection->prepare($query);
		}
	}

	public function fetchRow($query,$vars = array()){
		$statement = $this->prepare($query);
		$result = $statement->execute($vars);
		$row = $statement->fetch(PDO::FETCH_ASSOC);
		return $row;
	}

	public function lastInsertId($seqName = NULL){
		$result = $this->fetchRow("SELECT LAST_INSERT_ID() AS id");
		return $result['id'];
	}

	public function formatForDateTime($time = NULL){
		$time = ($time == NULL) ? time() : $time;
		return date("Y-m-d H:i:s",$time);
	}
}