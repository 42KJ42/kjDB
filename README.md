# kjDB
Simple easy to use MySQLi PHP wrapper classes that allow for single AND mutliple replicated db access.  Allows you to have different READ and WRITE instances of replicated databases.

- Does not try and rewrite all the mysql functionality: I assume you can write proper MySQL statements without making you use a class member like insert(array("date"=>...)) for each type of mysql transaction.
- Allows you to use stored procs on the server (highly suggested)
- Allows you to define multiple read and write instances of a replicated database to move read stress off master server and onto read only replicated instances if you choose
- Can use one single instance for read/write on local or remote machine
- Instantiates without immediately connecting all read/write instances - only connects as needed

