<?php
/* Author: Kevin Johnson / Quantum Slice Corporation
 * Released under GNU GPL v3
 * https://www.QuantumSlice.com
 * 
 * Description: db access flows through this to return datasets, errs and track queries
 *
 * Provided as is without warranty. Use at your own risk.
 * Requires PHP >= 7.1
 *  
 * Example usage:
 * 
 	require_once __DIR__.'/_kjClasses/kjQry.php';
 	
	// define your db array of servers
	// if only one server then read and write are the same set "r"=>0 else include an array
	//		of read database servers like this:
	//  "r"=>array(
	//		array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
	//		array('host'=>'host2','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
	//		array('host'=>'host3','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')
	//	)
	$dbServerList = array(
		"w"=>array(array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')),
		"r"=>0
	);

	// define the kjSafeDBi access class object
	$db=new \kjDB\kjSafeDBi($dbServerList);	

 	$qry = "call my_stored_proc('a@example.com')";
	$result=new \kjDB\kjQry($db, 'w', $qry);
	if ($result->rv!=0 && mysqli_num_rows($result->result)) { 
		$row=mysqli_fetch_assoc($result->result); // get one row - could loop through result set here
		return $row['id']; // do something with the data
	} else {
		// some err!!!! 
	}
 *	
 */
namespace kjDB;

require_once __DIR__.'/kjSafeDBi.php';

class kjQry {
	public static $version = "2.1";

	/**
	 * Query results are stored here after query is executed.
	 * @var ?\mysqli_result $result
	 */
	public ?\mysqli_result $result=null;

	/**
	 * query string to run
	 * @var string $qry
	 */
	public string $qry='';

	/**
	 * query type that was run - either a 'r'ead instance or 'w'rite instance of database
	 * @var string $type = 'r' || 'w'
	 */
	public string $type='';

	/**
	 * return value from the mysqli_query operation (1 if ran ok, 0 if err)
	 * should check this before dealing with returned data
	 * @var int $rv 
	 */
	public int $rv=0;
	
	/**
	 * run a query and return results in the class variable set
	 * @param kjSafeDBi $db
	 * @param string $type 'r' || 'w' - defaults to 'w' if not recognized for safety
	 * @param string $qry
	 * @return null
	 */
	public function  __construct(kjSafeDBi $db, string $type, string $qry) {
		$this->qry=$qry;
		$this->type=$type;
		
		if (strtolower($type)=='r') {
			$this->rv=$db->mysqli_query('r', $this->qry, $this->result);
		} else {
			// if not r or w choose w to allow read/write access
			$this->rv=$db->mysqli_query('w', $this->qry, $this->result);
		}
	}

}

