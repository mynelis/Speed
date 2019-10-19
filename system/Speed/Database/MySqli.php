<?php

namespace Speed\Database;

/***
* MySQLi adaptor
*/
class MySqli extends \Speed\Database
{
	/***
	* Property for storing connection handle
	* @access protected
	*/
	public $handle;

	public $key;
	public $mode;

	/***
	* Host
	* @access private
	*/
	private $host;

	/***
	* Name of database
	* @access public
	*/
	private $name;

	/***
	* Connection username
	* @access private
	*/
	private $username;

	/***
	* Connection password
	* @access private
	*/
	private $password;

	private $persist;

	public $timeout;

	public final function __construct ()
	{
		// There should be no open connections when a new instance is needed
		if ($this->handle) {
			$this->disconnect();
		}
	}

	public function batchQuery ($sql)
	{
		return $this->execute(implode(';', $sql), true);
	}

	public function setProperty ($property, $value)
	{
		if (property_exists($this, $property)) {
		//if (in_array($property, $this->fields) and $value) {
			$this->$property = $value;
		}
	}

	/***
	* Destrucor
	*/
	public  final function __destruct () {
	}

	/***
	* Connect to the database
	* @access public
	* @param string $host
	* @param string $db
	* @param string $user
	* @param string $password
	*/
	public final function connect ()
	{
		// Establish a new connection
		$conn = $this->persist
			? new \mysqli("p:".$this->host, $this->username, $this->password, $this->name)
			: new \mysqli($this->host, $this->username, $this->password, $this->name);

	  	if ($this->timeout) {
	  		$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->timeout);
	  	}

	  	// Check for successful connection, select the database
	  	if ($conn and is_object($conn)) {
			$this->handle = $conn;
			// self::$instance = $this;
		}

		// if we cannot establic a connection, throw an error and nutralize the handle
		else {
	    	trigger_error(mysqli_connect_error());
	    	unset($this->handle);
		}

		return $this;
	}

	/***
	* Disconnect from the database and refresh the handle
	* @access public
	*/
	public final function disconnect () {
		if ($this->handle) {
			$this->handle->close();
			unset($this->handle);
		}
	}

	/*public function transact (Callable $operation, Callable $callback = null)
	{
		$this->execute('start transaction');
		$op = $operation();
		if (!$op) return $this->execute('rollback');
		$this->execute('commit');

		return is_callable($callback) ? $callback($op) : $this;
	}*/

	/***
	* Running select queries on the connection handle
	* @access public
	* @param string $sql
	* @return array on success, false on failure
	*/
	public final function fetch ($sql) {

		// There is no connection handle, throw error
		if (!$this->handle) {
			trigger_error('Not Connected');
			return false;
		}

		// Now we're clear. Run the query and check for errors
		$run = $this->handle->query($sql);

        if (!$run) {
			trigger_error('Query failed: '.$sql);
			return false;
		}
		if (!is_object($run)) {
			trigger_error('The query did not return a valid object');
			return false;
		}

		// We need an empty array to store all the rows found
		$results = array();
		do {

		   	// Fetch each row and push into the results array
		   	$row = $run->fetch_object();
			if ($row) {
					$results[] = $row;
			}
		} while ($row);

		// Free the results handle and return the records
		$run->close();
		if ($this->handle->more_results()) {
            $this->handle->next_result();
        }

        self::$queries[] = $sql;

		return $results;
	}

	/***
	* Execute a non-selecting query
	* @access public
	* @param string $sql
	* @return int
	*/
	public final function execute ($sql, $multi = false) {

		// There is no connection handle, throw error
		if (!$this->handle) {
			trigger_error('Not Connected');
			return false;
		}

		if (!$sql) return false;

		// Now we're clear. Run the query and check for errors
		$run = $multi ? $this->handle->multi_query($sql) : $this->handle->query($sql);
        if (!$run) {
			trigger_error('Query failed: '.$sql.' >> '.$this->handle->error);
			//return $this->handle->error;
			return false;
		}

        self::$queries[] = $sql;
		//echo $this->handle->insert_id;
		return $this->handle;//->affected_rows;
		//return $run;//->affected_rows;
	}

	/***
	* Call stored procedures
	* @access public
	* @param string $proc
	* @param [optional] arguments
	* @return mixed
	*/
	public final function proc ($proc, $args = null) {

		// There is no connection handle, throw error
		if (!$this->handle) {
			trigger_error('Not Connected');
			return false;
		}

		if ('array' !== gettype($args)) {
			$args = func_get_args();
			array_shift($args);
		}

		$sql = "call ".$proc."(".implode(",", $args).");";
		return $this->fetch($sql);
	}

	/***
	* Call function
	* @access public
	* @param string $func
	* @param [optional] arguments
	* @return mixed
	*/
	public final function func ($func) {

		// There is no connection handle, throw error
		if (!$this->handle) {
			trigger_error('Not Connected');
			return false;
		}

		if (3 > func_num_args()) {
            trigger_error("This function accepts 3 or more arguments");
            return false;
		}
		$args = func_get_args();
		$alias = $args[count($args)-1];
		array_shift($args);
		array_pop($args);

		$sql = "select ".$func."(".implode(",", $args).") as ".$alias.";";
		return $this->fetch($sql);
	}

	public function make_values ($values) {
		$sql = array();
		foreach ($values as $key => $val) {
			if (!is_string($key)) {
				$sql[] = " $val ";
			}
			else {
				//$sql[] = "`".$key."` = '".$val."' ";
				//$sql[] = "`".$key."` = '".addslashes($val)."' ";
				$key = (strchr($key, ".")) ? $key : "`".$key."`";
				$sql[] = $key." = '".addslashes($val)."' ";
			}
		}
		return $sql;
	}

	public function find ($table, $values=array(), $order="", $limit="", $fields=array(), $quote = true) {
		$sql = $this->make_values($values);
		$where = $sql ? " where ".implode(" and ", $sql) : "";
		$order = $order ? " order by $order " : "";
		$limit = $limit ? " limit $limit " : "";

		$fld = array();
		if ($fields) {
			foreach ($fields as $k => $v) {
				if (is_string($k)) {
				   $fld[] = (strchr($k, ".") || $quote) ? "`".$k."` as ".$v : $k." as ".$v;
				}
				else {
				   $fld[] = (strchr($v, ".") || $quote) ? "`".$v."`" : $v;
				}
			}
		}

		$fields = $fld ? implode(",", $fld) : "*";
		$sql = "select ".$fields." from $table ".$where.$order.$limit;

		return $this->fetch($sql);
	}

	public function insert ($table, $values, &$batch = null) {
		$sql = array();
		foreach ($values as $key => $val) {
			$sql[] = "`".$key."` = '".addslashes($val)."' ";
		}
		$sql = "insert into $table set ".implode(", ", $sql);

		if (is_array($batch)) {
			$batch[] = $sql;
			return $batch;
		}

		return $this->execute($sql);
	}

	public function replace ($table, $values)
	{
		$sql = array();
		foreach ($values as $key => $val) {
			$sql[] = "`".$key."` = '".addslashes($val)."' ";
		}
		$sql = "replace into $table set ".implode(", ", $sql);
		return $this->execute($sql);
	}

	public function update ($table, $values, $conditions=array())
	{
		$where = $this->make_values($conditions);
		$sql = $this->make_values($values);
		$where = $where ? " where ".implode(" and ", $where) : "";
		$sql = "update $table set ".implode(", ", $sql).$where;
		return $this->execute($sql);
	}

	public function delete ($table, $values)
	{
		$sql = $this->make_values($values);
		$sql = "delete from $table where ".implode(" and ", $sql);
		return $this->execute($sql);
	}

	public function getLastInsertId ($table)
	{
		$id = $this->find($table, array(), 'lid desc', '1 offset 0', array('last_insert_id()' => 'lid'), false);
		return $id ? $id[0]->lid : null;
	}

	public function getLastInsertRow ($table, $idkey = 'id')
	{
		$id = $this->getLastInsertId($table);
		$row = $this->find($table, array(
			$idkey => $id
		));

		return $row ? $row[0] : null;
	}

	public function getPrimaryKey ($table)
	{
		$sql = "
			select
				`column_name` as pkey
			from
				`information_schema`.`columns`
			where
				(`table_schema` = '".$this->name."')
				and (`table_name` = '".$table."')
				and (`column_key` = 'pri')
		";

		$rs = $this->fetch($sql);

		return $rs ? $rs[0]->pkey : null;
	}

	private function getConstraints ($srctable, $reftbl = '')
	{
		$condition = '';
		if ($srctable) $condition .= 'and table_name = "'.$srctable.'"';
		if ($reftbl) $condition .= 'and referenced_table_name = "'.$reftbl.'"';

		$sql = "
			select
			    table_name as tbl, column_name as col, constraint_name as fkey,
			    referenced_table_name as reftbl, referenced_column_name as refcol
			from
			    information_schema.key_column_usage
			where
			    referenced_table_schema = '".$this->name."'
			    and table_name is not null
			    and referenced_table_name is not null
			    ".$condition."
			order by
			    column_name
		";

		return $this->fetch($sql);
	}

	public function getConstraintDefinitions ($table, $reftbl = '')
	{
		$rs = $this->getConstraints($table, $reftbl);

		if ($rs) {
			$keys = [];
			foreach ($rs as $each) {
				$keys[] = (object) [
					'fkey' => $each->fkey,
					'source' => (object) ['table' => $each->tbl, 'column' => $each->col],
					'target' => (object) ['table' => $each->reftbl, 'column' => $each->refcol]
				];
			}
			return $keys;
		}
		return null;
	}

	public function getConstraintReferences ($table, $reftbl = '')
	{
		return $this->getConstraintDefinitions($reftbl, $table);

		/*$rs = $this->getConstraints($reftbl, $table);

		if ($rs) {
			$fkeys = (object) null;
			foreach ($rs as $each) {
				$keys[] = (object) [
					'source' => (object) ['table' => $each->tbl, 'column' => $each->col],
					'target' => (object) ['table' => $each->reftbl, 'column' => $each->refcol]
				];
			}
			dump($keys);
			return $fkeys;
		}
		return null;*/
	}

	public function getTables ()
	{
		$sql = '
			select table_type, table_name, table_comment 
		    from information_schema.tables 
		    where table_schema = "'.$this->name.'"
		    /*and table_type != \'VIEW\'*/
		    order by table_name asc;
		';
		//$rows = $this->fetch('show tables');
		$rows = $this->fetch($sql);

		//$key = 'Tables_in_'.$this->name;
		$tables = [];

		if ($rows) {
			foreach ($rows as $each) {
				// $tables[] = $each->table_name;
				$type = 'table';
				if ('VIEW' === $each->table_type) $type = 'view';

				$tables[] = (object) [
					'type' => $type,
					'name' => $each->table_name,
					'comment' => $each->table_comment,
					'audit' => (-1 < strpos($each->table_comment, 'ENABLE_AUDIT'))
				];
			}
		}

		return $tables;
	}

	/*public function getBaseTables ($tables)
	{
		$base_tables = [];

		foreach ($tables as $tbl) {
			if ('BASE TABLE' == $tbl->type) $base_tables[] = $tbl->name;
		};

		return $base_tables;
	}

	public function getViewTables ($tables)
	{
		$view_tables = [];

		foreach ($tables as $tbl) {
			if ('VIEW' == $tbl->type) $view_tables[] = $tbl->name;
		};

		return $view_tables;
	}*/

	public function is_base_table ($table)
	{
		return 'table' === $table->type;
	}

	public function is_view_table ($table)
	{
		return 'view' === $table->type;
	}

	private function extract_column_properties ($col)
	{
		$props = (object) [
			'pkey' => false, 
			'auto_inc' => false, 
			'type' => null, 
			'length' => null, 
			'nullable' => false, 
			'default' => null, 
			'fract' => false,
			'required' => false
		];

		$props->nullable = ('YES' == ucfirst($col->Null)); 
		$props->default = $col->Default;
		$props->pkey = ('PRI' == $col->Key);
		$props->auto_inc = ('auto_increment' == strtolower($col->Extra));

		if (preg_match('/^(\w+)$|^(\w+)\s?\((\d+)\)$|^(\w+)\s?\((\d+),(\d+)\)$/', $col->Type, $matches)) {
			// dump($matches);
			switch (sizeof($matches)) {
				case 2:
					$props->type = $matches[1];
					break;

				case 4:
					$props->type = $matches[2];
					$props->length = (int) $matches[3];
					break;

				case 7:
					$props->type = $matches[4];
					$props->length = (int) $matches[5];
					$props->fract = (int) $matches[6];
					break;
			}
		}

		$props->required = (false == $props->nullable && null == $props->default);

		return $props;
	}

	public function getTableColumns ($table)
	{
		$sql = 'show columns from '.$table;
		$cols = $this->fetch($sql);
		$_cols = (object) null;

		/*if ('staff' == $table) {
			// dump($cols);
		}*/

		foreach ($cols as $each) {
			// if ('staff' == $table) {
				// dump($each->Field);
				$_cols->{$each->Field} = $this->extract_column_properties($each);
				// dump($this->extract_column_properties($each));
			// }
			/*$type = [];
			// preg_match('/(.+).*\((\d+)\)/', $each->Type, $type);
			preg_match(['/^(\w+)$|^(\w+)\s?\((\d+)\)$|^(\w+)\s?\((\d+),(\d+)\)$/', $each->Type, $type);
			
			$_cols->{$each->Field} = (object) [
				'pkey' => ('PRI' == $each->Key),
				'type' => isset($type[2]) ? $type[2] : null,//preg_replace('/\(.+/', '', $each->Type),
				'length' => isset($type[3]) ? $type[3] : null,//preg_replace('/\((\d+)\)/', '$3', $each->Type),
				'fract' => isset($type[4]) ? $type[4] : false,
				// 'isint' => isset($type[3]) && preg_match('/^\d+$/', $type[3]) ? true : false,
				// 'isfract' => isset($type[2]) && preg_match('/^\d+\.\d+$/', $type[2]) ? true : false,
				'nullable' => ('NO' ==  ucwords($each->Null)) ? false : true,
				'default' => $each->Default,
				'auto_inc' => 'auto_increment' == strtolower($each->Extra) ? true : false
			];

			if (preg_match('/\format=/', $each->Extra)) {
				$_cols->{$each->Field}->format = str_replace('format=', '', $each->Extra);
			}*/
		}

		/*if ('staff' == $table) {
			// dump($_cols, true);
			exit;
		}*/

		return $_cols;
	}
}
