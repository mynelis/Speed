<?php

namespace Speed;

/*interface DatabaseAdapterInterface {
	public static function getInstance();
	public function connect();
	public function disconnect();
}*/

class Database //implements \Speed\DatabaseAdapterInterface
{
	// protected static $instance;

	public static $queries = [];

	// The database connection is stored in the $instance property ot the 
	// driver user. So to avoid having to make $dbh global whenever we need
	// it in a separate class method, this handy method would just be called
	// to directly return the connection instance.
	public static function getInstance ($key = null)
	{
		global $database_instances;

		$dbh = false;

		if (!$key && is_string(session('app.dbh_key'))) $dbh = $database_instances->{session('app.dbh_key')};
		if ($key && isset($database_instances->$key)) $dbh = $database_instances->$key;
		if (null == $key) $dbh = array_values((array) $database_instances)[0];

		if ($dbh && !isset($dbh->handle)) {
			$dbh->connect();
		}

		return $dbh;
	}
}