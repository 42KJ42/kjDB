<?php
/* Author: Kevin Johnson / Quantum Slice Corporation
 * Released under GNU GPL v3
 * https://www.QuantumSlice.com
 * 
 * Description:  A wrapper class that helps us deal with replicated
 *		mysql databases.  i.e. we can only WRITE to one database but read from many
 *
 * Provided as is without warranty. Use at your own risk.
 * Requires PHP >= 7.1
 *
 * Should use with kjQry class as a front end!
 *
 * We dont connect right away upon instantiation since we might not need both
 * read and write connections. instead, when we call the query functions we
 * check to see if we have the proper connection in place
 *
 * use by including kjQry first and using kjQry as a front end
 * 
 * 121207 - this has been updated to use mysqli connections so we
 * can properly use stored procedures on the servers
 * 
 * 130807 - modified connect to use same connection for read if write already open
 *	and changed destructor to only try to close once
 * 
 * 130901 - if a connection to a db fails, the connect function will automatically try 
 *	one of the other servers if any are available.
 * 
 * 130901 - if opening a read db and we use a write db definition, automatically set the 
 *	write db as opened as well so we dont make another connection for writes
 * 
 * 130901 - when opening a WRITE db, we dont do randomly, we always use array[0] first
 *	as the primary write db, then if it errs out it will pick another. this lets us have
 *	more control as we would prefer all writes to go to our one powerful server. read 
 *	servers are picked randomly
 * 
 * 200318 - updated to fix missing { } for compatibility (version 2.1)
 * 230801 - updates for vscode intelliphense comaptibility and updated docs (version 2.1.1)
 */
namespace kjDB;

class kjSafeDBi {
	public static $version = '2.1.1';

	private ?\mysqli $hdW=null;	// mysql handles for writing
	private ?\mysqli $hdR=null; 	// mysql handles for reading
	public  string $error='';	// last err message
	private array $serverList;	// array of servers
	private int $numR=0, $numW=0; // number of servers in serverList of each type

	/**
	 * construct: Note that this does NOT open DB connections yet! Will only open
	 * connections as needed.
	 * @param array $DB_serverList array of writeable "w" mysql servers and
	 *	  readable "r" servers. random one chosen of each
	 *	  if "r"=>0 then we use the same db for read and write
	 *    array(
	 *		"w"=>array(array("host"=>"","user"=>"","pass"=>"","db"=>""),array(),...)
	 *		"r"=>array(array("host"=>"","user"=>"","pass"=>"","db"=>""),array(),...)
	 *	  )
	 */
	function __construct(&$DB_serverList) {
		$this->serverList=$DB_serverList;

		// get the number of possible read and write mysql servers
		$this->numW=sizeof($DB_serverList["w"]);
		if ($DB_serverList['r']!=0) {
			$this->numR=sizeof($DB_serverList["r"]);
		} else {
			$this->numR=0;
		}
	}


	/**
	 * close connections at destruct
	 * @return void
	 */
	function __destruct() {
		if ($this->hdR===$this->hdW) {
			// if read and write connection is same, only close one
			if ($this->hdR) { mysqli_close($this->hdR); }
		} else {
			if ($this->hdR) { mysqli_close($this->hdR); }
			if ($this->hdW) { mysqli_close($this->hdW); }
		}
	}

	/**
	 * get the current open db handle... open one if not yet open
	 * @param string $rw 'w' || 'r'
	 * @return \mysqli - returns mysql handle or 0 if cannot open one
	 */
	function get_hd($rw) {
		if (!$this->connect($rw)) { 
			return 0;
		}

		if ($rw=='r') {
			return $this->hdR;
		} else if ($rw=='w') {
			return $this->hdW;
		} else {
			$this->error='inval rw in get_hd: '.$rw;
			return 0;
		}
	}

	/**
	 * acutally do a mysql query.  this function takes care of deciding whether
	 * or not a connection is needed and to which db
	 *
	 * NOTE!!! very important that you set the correct param for $rw to make sure
	 *			that writes go to the correct database!!!!
	 *
	 * @param string $rw 'r' || 'w'
	 * @param string $qry the mysql query string to run
	 * @param \mysqlResultSet &$result the return var to put the return into - this allow us to
	 *					still do mysql stuff easily without calling class fns
	 * @return int 0 if error or 1 if query seems to have run
	 */
	public function mysqli_query($rw, $qry, &$result) {
		if (!$this->connect($rw)) {
			return 0;
		}

		if ($rw=='r') {
			$rc=mysqli_multi_query($this->hdR, $qry);
			$result=mysqli_store_result($this->hdR); // save the result set
			while(mysqli_more_results($this->hdR)) {  
				// changed 200318 - used the more_results test to see if more so doesnt pop warning!
				mysqli_next_result($this->hdR);
			} // need this to clear out any remaining result sets to avoid concurrent stored proc calls
		} else if ($rw=='w') {
			$rc=mysqli_multi_query($this->hdW, $qry);
			$result=mysqli_store_result($this->hdW);
			while(mysqli_more_results($this->hdW)) {  
				// changed 200318 - used the more_results test to see if more so doesnt pop warning!
				mysqli_next_result($this->hdW);
			} // need this to clear out any remaining result sets to avoid concurrent stored proc calls
		} else {
			$this->error='inval rw in mysqli_query: '.$rw;
			return 0;
		}

		if ($rc) {
			return 1;
		}
		
		$this->error='Bad Result set. qry='.$qry;
		return 0;
	}

	/**
	 * connect to a database server - ensures that either $hdW or $hdR or BOTH
	 * are correctly set up with live connections to appropriate databases
	 *
	 * @param string $rw 'r' || 'w'
	 * @return int 1 if all OK, 0 if error, this->$error set to error string
	 */
	public function connect($rw) {
		if ($rw=='r') {
			if ($this->hdR) {
				return 1; // already connected
			}
			
			if (!$this->numR) { // no read dbs specified
				// if write connection already open, just use it instead of opening new one
				if ($this->hdW) { // new 130807
					$this->hdR=$this->hdW;
					return 1;
				}
				// we need to connect, but use the same db as the write fns
				$rc = $this->do_mysqli_connect('w');
				if ($rc) {
					// since we successfully opened a write db
					// we can now save the open db handle into the read db handle too
					$this->hdR=$this->hdW;
				}
				return $rc;
			} else { // we do have specific read dbs
				return $this->do_mysqli_connect('r');
			}
		} else if ($rw=='w') {
			if ($this->hdW) {
				return 1; // already connected
			}
			return $this->do_mysqli_connect('w');
		} else {
			$this->error='Inval rw: '.$rw.' in connect';
			return 0;
		}
	}

	/**
	 * actually do a mysql connection and return 1 if ok or 0 if err
	 * @param char $rw 'r' || 'w'
	 * @return int 0 if err, 1 if all ok
	 */
	private function do_mysqli_connect($rw) {
		$numServers=($rw=='w') ? $this->numW : $this->numR;
		
		if ($rw=='r') {
			$rand=mt_rand(0, $numServers-1);
		} else {
			$rand=0; // for write servers, always use the primary one first (array[0])
		}
		$server=$this->serverList[$rw][$rand];
		
		$hd = mysqli_connect($server['host'], $server['user'], $server['pass']);
		if (!$hd) {
			// try one more db connection before giving up, if there are any
			$rand=$this->get_new_server_num($numServers-1, $rand);
			if ($rand!=-1) {
				$server=$this->serverList[$rw][$rand];
				$hd = mysqli_connect($server['host'], $server['user'], $server['pass']);
				if (!$hd) {
					$this->error='Unable to connect to host '.$server['host'];
					return 0;
				}
			} else {
				// if we could not get another READ server to try, try a WRITE server
				if ($rw=='r') {
					$rand=mt_rand(0, $this->numW-1);
					$server=$this->serverList['w'][$rand];
					$hd = mysqli_connect($server['host'], $server['user'], $server['pass']);
					if (!$hd) {
						$this->error='Unable to connect to host '.$server['host'];
						return 0;	
					}
				} else {
					$this->error='Unable to connect to host '.$server['host'];
					return 0;
				}
			}
		}
		if (!mysqli_select_db($hd,$server['db'])) {
			$this->error='Unable to select DB';
			return 0;
		}
		// save the connection to our class vars
		if ($rw=='r') {
			$this->hdR=$hd;
		} else {
			$this->hdW=$hd;
		}
		return 1;
	}
	
	/**
	 * find a new random server to try after one fails
	 * @param int $maxServers get a random server between 0 and this
	 * @param int $dontWant the server num we already tried
	 * @return int -1 means none avail, otherwise return server num to try
	 */
	private function get_new_server_num($maxServers, $dontWant) {
		if ($maxServers==0) {
			return -1; // there was only one server
		}
		$count=0;
		while ($count<25) { //just try up to 25 times
			++$count;
			$rand=mt_rand(0, $maxServers);
			if ($rand!=$dontWant) {
				return $rand;
			}
		}
		return -1; // cant find one
	}

}
