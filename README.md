# kjDB
Simple easy to use MySQL / MySQLi / MariaDB PHP class that allows for **single** AND **mutliple replicated** db access.  Allows you to have different READ and WRITE instances of replicated databases.

- Does not try and rewrite all the mysql functionality: I assume you can write proper MySQL statements without making you use a class member like insert(array("date"=>...)) for each type of mysql transaction.  If you are looking for a class that takes away the need to know MySQL code this is not for you.  You will need to write full SQL statements
    - This is a pet peeve of mine with other PHP MySQLi classes - I can write my own SQL statements (and prefer to use stored procedures anyway - so should you!)
- Allows you to use stored procs on the server (highly suggested)
- Allows you to define multiple read and write instances of a replicated database to move read stress off master server and onto read only replicated instances if you choose
- Can use one single instance for read/write on local or remote machine
- Does not sanitize any data - just deals with the DB in and out
- Instantiates without immediately connecting read/write instances - only connects as needed
- Once instantiation can connect to both a read AND a write server instance depending on queries that run (defined by you in your calls).

## Sample Use

### Include This Code
```
require_once __DIR__.'/includes/kjQry.php'
```

### Define your server list and instantiate
You must declare a variable with your array of servers (even if you have only one!).  For this, you have a set of '**w**'riteable servers and a set of '**r**'eadonly servers.  This is especially useful if you are replicating a DB across many readonly slaves for performance.

```
// In this sample we only have one server that reads and writes all go to:
$dbServerList = array(
    "w"=>array(array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')),
    "r"=>0
);
```

If you only have one server then read and write are from the same server so set "r"=>0 (like in the above example) else include an array of read database servers like this:
```
$dbServerList = array(
    "w"=>array(array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')),
    "r"=>array(
        array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
        array('host'=>'host2','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
        array('host'=>'host3','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')
    )
);
```
If you have multiple writeable servers (masters) you can include the same way but note that it will always try to connect to the FIRST writeable server in the array (super-master) and if that fails will alternatively try a different writeable server.

```
// Example with multiple Masters and multiple Slaves:
$dbServerList = array(
    "w"=>array(
        array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
        array('host'=>'host2','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')
    ),
    "r"=>array(
        array('host'=>'localhost','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
        array('host'=>'host2','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname'),
        array('host'=>'host3','user'=>'dbuser','pass'=>'dbpass','db'=>'dbname')
    )
);
```

After declaring your server list you should instantiate the classs - no connections will be made until queries are issued but this lets you define and set up all your database info once in a global include and use the resulting object anywhere queries are needed:

```
// define the kjSafeDBi access class object
$db=new \kjDB\kjSafeDBi($dbServerList);	
```

### Instantiate And Use
```
// any valid MySQL statement
$qry = "call my_stored_proc('a@example.com')"; 

// run the sql on a 'w'rite db instance - use 'r' to select a read instance
$result=new \kjDB\kjQry($db, 'w', $qry);  // note using the global $db declared previously

// test for a valid return and use the data
if ($result->rv!=0 && mysqli_num_rows($result->result)) {
    $row=mysqli_fetch_assoc($result->result); // get one row - could loop through result set here
    echo $row['id']; // do something with the data
} else {
    // some err to deal with!!!! 
}
```

### Notes:
- If running a MySQL statement and you dont care or dont return a row of data you can just use **if ($result->rv!=0)** and dont bother checking if rows are returned.
- Be sure for every new query you properly select 'w' or 'r'.  Only really important if the SQL code actually alters any data - then must use 'w' to select the writeable instance


#### Disclaimer
THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS “AS IS” AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
