<?php

date_default_timezone_set("UTC");
include_once('credentials.php');

define('API_HOST', 'https://api.telegram.org/');
define('API_URL', API_HOST.'bot'.BOT_TOKEN.'/');
define('API_FILE_URL', API_HOST.'file/bot'.BOT_TOKEN.'/');
define('HIGH_SIERRA', 'http://www.amazon.com/High-Sierra-Rolling-Upright-32-Inch/dp/B00COBKWS8/');

class database
{
	public $db;

	public function __construct()
	{
		$this->_u = DB_USER;
		$this->_pw = DB_PASS;
		$this->_schema = DB_SCHEMA;
		$this->_url = DB_HOST;
	}

	protected function conn()
	{
		return new mysqli($this->_url, $this->_u, $this->_pw, $this->_schema);
	}

	public function connect()
	{
		$connect = $this->conn();
		if (!$connect->errno) {
			$this->db = $connect;
			return $this->db;
		} else {
			throw new exception($connect->error);
		}
	}

}
