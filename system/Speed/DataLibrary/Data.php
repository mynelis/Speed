<?php

namespace Speed\DataLibrary;

use \app\cache\SchemaData;

use \Speed\Security\InputValidator;
use \app\model\SchemaDataCache;
use \Speed\DataLibrary\QueryBuilder;
use \Speed\DataLibrary\Binding;
use \Speed\Database;

class Data
{
	protected $instance_id = 0;
	protected static $instances = [];
	private static $instances_count = 0;

	protected $limit;
	protected $condition = [];
	protected $order;

	protected $table;
	protected $alias;
	protected $constraints;
	protected $references;
	protected $columns;

	protected $pk_name;
	protected $pk_value;
	protected $keys;
	protected $fkey_columns = [];
	protected $delete_mode;

	protected $data;
	protected $updates;

	// Set the $audit flag to true to create and automatically populate
	// audit details for every update or insert on the table.
	protected $audit;

	protected static $fk_where;
	protected static $fk_order;
	protected static $fk_limit;

	// public $view;
	public $validation;
	public $validation_errors = [];

	const ROW_ORDER_ASC = 'asc';
	const ROW_ORDER_DESC = 'desc';

	// These columns will be created automatically on first object instance
	// if the $audit property is set to true. For this to work, you need to
	// grand "ALTER" permission to the database user's privileges. You should
	// revoke thi grant in production environments.
	//const AUDIT_COLUMNS = 'created_by updated_by deleted_by created_at updated_at deleted_at deleted';
	const AUDIT_COLUMNS = [
		'insert' => ['created_by', 'created_at'],
		'update' => ['updated_by', 'updated_at'],
		'delete' => ['deleted_by', 'deleted_at', 'deleted']
	];

	public function __construct ($pk_value = null, $table = null)
	{
		$dbh = Database::getInstance();

		// Initializing default properties that require values
		$this->updates = (object) null;
		$this->data = (object) null;

		// We need to figure out which class invoked the construct method.
		// In other words, what is the name of the instance that is asking 
		// to be initialized? 
		$class = get_called_class();
		$class_name = strtolower(substr(strrchr($class, '\\'), 1));

		// In case a table name is not given, we assume the the name of the
		// class instance being initialized is the same as tthe table.
		if (!$table) $table = $class_name;
		$this->table = strtolower(trim($table));

		// Using pseudo names for tables.. this is important when querying multiple
		// tables in a join select. Te alias name will only be used in joins.
		// Aliases are specified by adding it after the table, separated by a single space.
		// To add an alias from a model file, the parent model constructior has to be
		// instantiated fully, so the table name could be suffic=xed with the alias. 
		$this->alias = $this->table;
		if (preg_match('/^(\w+)\s(\w+)$/', $table, $match)) {
			$this->table = $match[1];
			$this->alias = $match[2];
		}

		// Inheritance identification. Both a numeric index value and string name
		// are assigned for easy future reference.
		$this->instance_id = $this->table.'.'.$this->alias.'.'.self::$instances_count++;

		// And we assign the table name as the view if a view is not given.
		// The view is needed for doing all selection queries.
		// if (!$this->view) $this->view = $this->alias;

		// These properties are required for some core automations like 
		// discovering the column names of the table, the constraints or
		// foreign key relationships to it, and references of this table
		// to other tables in the database.
		// We would need these later for automatically pulling data
		// associated with certain rows, based on relationships between
		// the tables involved. No need for manual relationship deinitions.
		$meta = new SchemaData($this->table);
		$this->constraints = $meta->constraints;
		$this->references = $meta->references;
		$this->columns = $meta->columns;
		$this->audit = $meta->audit;
		$this->pk_name = $meta->primary_key;		
		$this->pk_value = $pk_value;

		if ($pk_value) $this->condition[$this->pk_name] = $pk_value;

		self::$instances[$this->instance_id] = $this;
	}

	// This method is called to reset limit, order and condition after each query
	protected function reset_query ()
	{
		$this->condition = null;
		$this->limit = null;
		$this->order = null;

		QueryBuilder::$primary_instance  = null;
		QueryBuilder::$cols = [];
		QueryBuilder::$criteria = [];
		// QueryBuilder::$distinct_rows = [];
		QueryBuilder::$row_order = [];
		QueryBuilder::$row_limit = null;
		QueryBuilder::$group_by = null;
		QueryBuilder::$join = [];
		QueryBuilder::$rel_depth = null;
	}

	// Get actual columns for audit.
	// Since table may already have sudit columns, we need to find the missing columns
	// and create only those. This method provides the difference in columns.
	private static function audit_columns ($columns)
	{
		$cols = [];
		foreach (self::AUDIT_COLUMNS as $group) {
			$cols = array_merge($cols, $group);
		}
		return array_diff($cols, array_keys((array)$columns));
	}

	// Initializing the audit requirements.
	// This method is called in the construct to setup the audit fields on
	// the table. It first performs a diff with the pre-loaded columns of the
	// table, so only the fields not already created on the table will be created. 
	private static function init_audit ($columns, $table)
	{
		$cols = self::audit_columns($columns);//$this->audit_columns();
		
		if ($cols) {
			$query = [];

			foreach ($cols as $col) {
				$type = 'tinyint(1)';
				$default = 0;

				if ('_by' == substr($col, -3)) {
					$type = 'int(5)';
				}
				if ('_at' == substr($col, -3)) {
					$type = 'datetime';
					$default = false;
				}

				$sql = ' add `'.$col.'` '.$type;
				if (false !== $default) $sql .= ' not null default '.$default;

				$query[] = $sql;
			}

			if ($query) {
				$sql = 'alter table `'.$table.'`'.implode(',', $query);
				$dbh = Database::getInstance();
				$query = $dbh->execute($sql);

				$cols = $dbh->getTableColumns($table);
			}
		}

		return $cols;
	}

	private function audit_values ($mode, $map = null)
	{
		if (true !== $this->audit) return false;

		$cols = self::AUDIT_COLUMNS[$mode];
		$user_id = session('cms.user.id');

		settype($map, 'object');

		foreach ($cols as $key) {
			if ('_by' == substr($key, -3)) $map->$key = $user_id;
			if ('_at' == substr($key, -3)) $map->$key = date('Y-m-d H:i:s');
			if ('deleted' === $key) $map->$key = 1;
		}

		if (true === $this->delete_mode) {
			unset($map->updated_by, $map->updated_at);
		}

		return $map;
	}

	// This method ensures that values are presented in the appropriate
	// formarts for updates or inserts, based on the colum definition
	// of the table column.
	//  
	// @param scalar $value
	// @param string $type
	protected function enforce_data_type ($value, $type)
	{
		switch ($type)
		{
			case 'tinyint':
			case 'int':
			case 'bigint':
				return (int) $value;
				break;

			case 'decimal':
			case 'float':
				return (float) $value;
				break;

			case 'bool':
			case 'boolean':
				return (boolean) $value;
				break;

			case 'date':
				return date('Y-m-d', strtotime($value));
				break;

			case 'datetime':
			case 'time':
			case 'timestamp':
				return date('Y-m-d H:i:s', strtotime($value));
				break;

			default:
				return trim($value);
				break;
		}
	}

	// Check validity of column value per the defined REGEX format, 
	// and also check other meta properties like restrictions on
	// the length of the colum value.
	protected function check_column ($key, $value, $regex)
	{
		$col = $this->columns->$key;

		// Check input value validity
		if (!InputValidator::ValidateUsingRegex($regex, $value)) return 'Invalid input value for '.$key;

		// Check length of input against maximum permitted length defined on table
		if (strlen($value) > $col->length) return 'Maximum character length of '.$col->length.' exceeded';

		return true;
	}

	// When an undefined property is being set on the object, we should
	// assume its a table column and its value, therefore push it to the 
	// $data property which holds values assigned on-the-fly.
	// 
	// @param string $property
	// @param scalar $value
	public function __set ($property, $value)
	{
		if (isset($this->columns->$property) && $property !== $this->pk_name) {
			$validity = $this->validate($property, $value);

			if (true !== $validity) {
				trigger_error($validity);
				$value = null;
			}

			$this->data->$property = $this->enforce_data_type($value, $this->columns->$property->type);
		}
	}

	// Make it possible to access data values directly from the object by
	// just pointing to it by name, directly from the object;
	public function __get ($property)
	{
		if (isset($this->data->$property)) {
			return $this->data->$property;
		}

		return null;
	}

	// This method just returns the $data property, only additionally
	// validating and preparing its values in the required format/datatype.
	// 
	// @param scalar $pk_value
	// 
	protected function get_values ($pk_value)
	{
		$data = (object) null;
		foreach ($this->data as $key => $value) {
			if ($key === $this->pk_name) continue;
			
			if (true === $this->validate($key, $value)) {
				$data->$key = $this->enforce_data_type($value, $this->columns->$key->type);
			}
		}

		$this->audit_values($pk_value ? 'update' : 'insert', $data);

		return $data;
	}

	// Save updates or inserts in database.
	// You can either call this method after setting all the required values
	// or passing custom data to it, together with predefined methods such as
	// from_post, from_get, and from_custom, or the values to commit.
	// 
	// EXAMPLES: 
	// 1.	->save(null, 'post') 
	// 			=> saves data from post 
	// 			=> by calling from_post()->save()
	// 			
	// 2.	->save(null, 'custom', ['name' => 'Nelis']) 
	// 			=> saves data from custom data provided
	// 			=> by calling from_custom(['name' => Nelis])->save())
	// 			
	// 	@param array $condition
	// 	@param string $method suffix
	// 	@param array $vars
	// 	
	public function save ($condition = [], $method = null, $vars = null, $force_update = false)
	{
		if (!$this->pk_name) return;

		if ($method) {
			$method = 'from_'.$method;
			if (method_exists($this, $method)) {
				$method($vars); 
			}
		}

		$dbh = Database::getInstance();

		$row = null;
		$values = $this->get_values($this->pk_value);

		if ($this->validation_errors) return false;

		$status = false;

		if (QueryBuilder::$criteria) {
			$condition = array_merge($condition, (array)QueryBuilder::$criteria);
		}

		if ($this->pk_value || $force_update) {
			if ($this->pk_value) $condition[$this->pk_name] = $this->pk_value;
			$status = $dbh->update($this->table, $values, $condition);
			$row = $this->data;
		}
		else {
			$status = $dbh->insert($this->table, $values);
			$row = $dbh->getLastInsertRow($this->table, $this->pk_name);
		}

		if (false !== $status && 1 == sizeof($row)) {
			$this->remap_row($row);
			return $row;
		}

		$this->reset_query();

		return $status;
	}

	// Update all data values en-block. 
	// This is mostly useful when you need to update the object with 
	// current values fetched from database. The primary key valus is
	// assigned separately as usual. 
	protected function remap_row ($row)
	{
		if ($row) {
			foreach ($row as $key => $value) {
				if ($key === $this->pk_name) {
					$this->pk_value = $value;
				}
				else $this->data->$key = $value;
			}
		}

		return $this;
	}

	// Delete a row, using the primary key value of the current
	// object instance. The $data property and primary key values
	// are reset after the row is deleted.
	public function delete_row ()
	{
		if (!$this->pk_name) return;

		$dbh = Database::getInstance();

		if ($this->pk_value) {

			if (true === $this->audit) {
				$this->delete_mode = true;
				$condition = $this->audit_values('delete', [$this->pk_name => $this->pk_value]);
				$delete = $this->from_custom($condition)->save();
			}
			else {
				$delete = $dbh->delete($this->table, [$this->pk_name => $this->pk_value]);
			}

			if ($delete) {
				$this->data = (object)null;
				$this->pk_value = 0;
			}
		}

		return $this;
	}

	public function exists ()
	{
		return $this->pk_value ? true : false;
	}

	public function delete ()
	{
		return $this->delete_row();
	}

	protected function foreign_key_columns ()
	{
		if (!isset($this->constraints)) return null;
		if ($this->fkey_columns) return $this->fkey_columns;

		$constraints = $this->constraints;

		foreach ($constraints as $each) {
			$this->fkey_columns[$each->source->column] = $this->alias.'.'.$each->source->column;
		}

		return $this->fkey_columns;
	}

	public static function WriteSchemaData ()
	{
		$dbh = Database::getInstance();
		$tables = $dbh->getTables();

		// dump($tables, true);

		$tpl = '<?php

namespace app\cache;

class SchemaData
{
	public function __construct (String $table)
	{
		if (method_exists($this, $table)) {
			$props = unserialize(base64_decode($this->$table()));
			foreach ($props as $key => $value) $this->$key = $value;
		}
	}

	public function __get ($property)
	{
		if (isset($this->$property)) return false;
	}

	public static function foreign_key_from_path ($path)
	{
		$fkeys = self::foreign_keys();

		if ($keys) {
			foreach ($fkeys as $k => $fk) {
				if ($fk->path == $path) return $k;
			}
		}
	}

	public static function foreign_keys ()
	{
		return unserialize(base64_decode(\'__FOREIGN_KEYS__\'));
	}

__METHODS__
}';

		if ($tables) {
			$class = [];

			$foreign_keys = (object) [];

			foreach ($tables as $tbl) {
				$is_table = $dbh->is_base_table($tbl);
				$is_view = $dbh->is_view_table($tbl);
				$type = $tbl->type;
				$audit = $tbl->audit;
				$tbl = $tbl->name;

				$columns = $dbh->getTableColumns($tbl);

				if ($is_table) {
					if ($audit) self::init_audit($columns, $tbl);
					$columns = $dbh->getTableColumns($tbl);

					$constraints = $dbh->getConstraintDefinitions($tbl);
					$references = $dbh->getConstraintReferences($tbl);

					foreach ($constraints as $set) {
						$foreign_keys->{$set->fkey} = (object) [
							'path' => $set->source->table.'.'.$set->target->table,
							'link' => [$set->source->table.'.'.$set->source->column => $set->target->table.'.'.$set->target->column]
						];
					}

					$metadef = (object) [
						'type' => $type,
						'constraints' => $constraints,
						'references' => $references,
						'columns' => $columns,
						'primary_key' => $dbh->getPrimaryKey($tbl),
						'audit' => $audit
					];
				}
				else {
					$metadef = (object) [
						'columns' => $columns
					];
				}

				$encrypted = base64_encode(serialize($metadef));

				$class[$tbl] = '	public final function '.$tbl.' () 
	{
		return \''.$encrypted.'\';
	}'."\r\n";
			}

			$enc = base64_encode(serialize($foreign_keys));

			$tpl = str_replace('__FOREIGN_KEYS__', $enc, $tpl);
			$tpl = str_replace('__METHODS__', implode("\r\n", $class), $tpl);
		}

		$file = ROOT.'app/cache/SchemaData.php';
		$fp = fopen($file, 'w+');
		fwrite($fp, $tpl);
		fclose($fp);
	}
}