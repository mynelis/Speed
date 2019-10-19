<?php

namespace Speed\DataLibrary;

use \app\cache\SchemaData;

use \Speed\Database;
use \Speed\DataLibrary\Binding;
use \Speed\DataLibrary\QueryBuilder;

class Model extends \Speed\DataLibrary\QueryBuilder
{
	// Constructor method for creating new model instances.
	// 
	// @param scalar $pk_value
	// @param string $table
	// @param string $pk_name
	// 
	public function __construct ($pk_value = null, $table = null, $column = null)
	{
		parent::__construct($table, $column, $pk_value);
	}

	// To create new models without creating a new file, call this 
	// static method. This would be more helpful for smaller tables
	// that would not need custom functions.
	// 
	// @param string $table
	// @param scalar $pk_value
	// @param string $pk_name
	// 
	public static function create ($table, $pk_value = null, $column = null)
	{
		if (preg_match('/\./', $table)) {
			return new Binding($table);
		}

		return new Model($pk_value, $table, $column);
	}

	// Sometimes you want to validate a collection of fields against a single
	// regular expression. You can conveniently group them together, separated
	// by commas. This method parses the rules into individual sets of REGEX
	// matching rules for each field.
	protected function parse_validation ()
	{
		if (!$this->validation) return;

		$rules = (object)null;
		foreach ($this->validation as $fields => $regex) {
			$parts = preg_split('/,/', $fields, null, PREG_SPLIT_NO_EMPTY);
			foreach ($parts as $f) $rules->$f = $regex;
		}
		return $rules;
	}

	// Call this method to validate all key, value pairs in the $vars map.
	// To validate a single pair, you can pass the key and value as separate
	// arguments, or pass them as a single array.
	// 
	// @param mixed $vars
	// 
	public function validate ($vars)
	{
		$this->validation = $this->parse_validation();

		$mode = 'multi';
		$key = null;

		$args = func_get_args();
		if (2 == sizeof($args)) {
			$key = $args[0];
			$vars = [$key => $args[1]];
			$mode = 'single';
		}

		$status = true;
		$messages = (object) null;

		foreach ($vars as $key => $value) {
			if (isset($this->validation->$key)) {
				$col_check = $this->check_column ($key, $value, $this->validation->$key);
				$messages->$key = $col_check;
				// $messages->$key = [$key, $value, $this->validation->$key];

				if (true !== $col_check) $status = $col_check;
			}
			else {
				$messages->$key = true;
			}
		}

		if ('single' == $mode) return $messages->$key;

		return (true === $status) ? true : $messages;
	}

	// Check for required fields and trigger a custom error if they are empty.
	// To suppress errors, check app.error_handler in config for debug settings
	// and types of errors to report.
	protected function check_required_fields ($values)
	{
		// return $this->columns;

		// Check validation columns
		foreach ($this->validation as $key => $value) {
			if (!isset($values->$key) || !$values->$key) {
				$this->validation_errors[$key] = $key.' is required.. #validation';
				return false;
			}
		}

		// Check table columns
		foreach ($this->columns as $name => $props) {

			// In update mode, ignore columns not contained in posted keys
			if ($this->pk_value && !isset($values->$name)) continue;

			if ($name != $this->pk_name && true == $props->required && (!isset($values->$name) || !$values->$name)) {
				$this->validation_errors[$name] = $name.' is required.. #schema';
				return false;
			}
		}

		return true;
	}

	// Prepare post, get or custom arrays for use in updates or inserts.
	// The fields are validated first, then a strict type checking is done
	// before setting up the keys and values on the object.
	// 
	// @param mixed $vars
	// 
	protected function prepare_vars ($vars)
	{
		// $valids = new \stdClass;
		if (!$this->check_required_fields($vars)) return $this;

		if ($vars) {
			foreach ($vars as $key => $value) {
				$valid = $this->validate($key, $value);

				if (true === $valid && isset($this->columns->$key)) {
					$this->$key = $this->enforce_data_type($value, $this->columns->$key->type);
					// $valids->$key = $valid;//$this->$key;
				}

				elseif (true !== $valid) {
					$this->validation_errors[$key] = $value.' is not a valid value for '.$key;
					// return false;
				}
			}
		}

		// return $valids;

		return $this;
	}

	// Grab all post variables and prepare them
	public function from_post ($validation = null)
	{
		if ($validation) $this->validation = $validation;
		return $this->prepare_vars(post());
	}

	// Grab all get variables and prepare them
	public function from_get ($validation = null)
	{
		if ($validation) $this->validation = $validation;
		return $this->prepare_vars(get());
	}

	// Prepare custom variables
	public function from_custom ($vars, $validation = null)
	{
		if ($validation) $this->validation = $validation;
		return $this->prepare_vars($vars);
	}

	private function init_linked_builder ($method, $args)
	{
		if ($split = preg_split('/[_]?with_/', $method, null, PREG_SPLIT_NO_EMPTY)) {
			$key = preg_replace('/^with_/', '', $method);

			$tables = [];
			$_tables = [QueryBuilder::$primary_instance->table];
			$paths = [];

			foreach ($split as $each) {
				$parts = preg_split('/_as_/', $each, null, PREG_SPLIT_NO_EMPTY);

				$table = isset($parts[1]) ? $parts[0].' '.$parts[1] : $parts[0];
				$tables[] = $table;
				$_tables[] = $parts[0];
			}

			foreach ($_tables as $k => $v) {
				$p = array_slice($_tables, $k, 2);
				if (2 == sizeof($p)) {
					$path = implode('.', array_slice($_tables, $k, 2));

					if (isset(QueryBuilder::$query_paths[$path])) continue;

					$fkey = SchemaData::foreign_key_from_path($path);
					QueryBuilder::$query_paths[$path] =  (object) [
						'path' => $path,
						'fkey' => $fkey,
						'select' => null,
						'select_none' => null,
						'where' => [],
						'count' => [],
						'group' => null,
						'filters' => [], 
						'order' => [], 
						'limit' => null, 
						'join' => []
					];
				}
			}

			return $this;
		}

		return false;
	}


	protected function get_aliased_table_from_args ($args)
	{
		$count = sizeof($args);

		if (2 === $count && is_string($args[0]) && is_array($args[1])) return $args[0];
		if (3 === $count && is_string($args[0]) && is_string($args[1]) && is_string($args[2])) return $args[0];

		return $table;
	}

	public function __call ($method, $args)
	{
		// Joining additional tables to the query.
		// Parse the method and call the appropriate join type, passing the given
		// arguments. 
		if (preg_match(QueryBuilder::QUERY_JOIN_TABLE_REGEX, $method, $matches)) {

			$table = $matches[2];
			$alias = $this->get_aliased_table_from_args($args);
			if ($alias) array_shift($args);

			// $join = isset($matches[2]) ? $matches[2].' join' : 'join';
			$join = trim(preg_replace('/_/', ' ', $matches[1].'join'));

			array_push($args, $join);

			$last = last(QueryBuilder::$query_paths);
			if ($last) {
				$last = array_values($last)[0];
				$last->join[] = (object) ['table' => $table.' '.$alias, 'args' => $args];
				return QueryBuilder::$context;
			}			
			
			$builder = self::create($table.' '.$alias);
			// dump($args);

			return call_user_func_array([$builder, '__join'], $args);
		}

		// Filtering mode.. Order, Limit and WWhere are directly tied to the QueryBuilder
		// class so these are not catered for here. Only dynamic filtering is considered
		// and are called using the parsed method and associated arguments. 
		elseif (preg_match(QueryBuilder::QUERY_FILTER_COLUMN_REGEX, $method, $matches)) {

			$func = 'filter_'.$matches[2];

			$last = last(QueryBuilder::$query_paths);
			if ($last) {
				$last = array_values($last)[0];
				$last->filters[] = (object) ['func' => $func, 'column' => $matches[1], 'args' => $args];
				return QueryBuilder::$context;
			}

			$builder = self::create(QueryBuilder::$context->raw_table, null, $matches[1]);

			if (method_exists($builder, $func)) {
				return call_user_func_array([$builder, $func], $args);
			}
		}

		// Linked / foreign key tables. These are nested records to the current rows, and 
		// are not called or parsed upon call. Delayed calls are made after the context
		// row is retrieved. The QueryBuilder instance created is therefore stored with
		// the associated foreign key or table name as determined by the extracted paths,
		// for later use.
		else {
			$this->init_linked_builder($method, $args);
			return $this;
		}

		return $this;
	}
}