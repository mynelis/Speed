<?php

namespace Speed\DataLibrary;

use app\cache\SchemaData;
use \Speed\Database;

class QueryBuilder extends \Speed\DataLibrary\Data
{
	private $name;
	private $col_ns;

	private static $_cols;

	protected $raw_table;
	
	protected static $rel_depth;
	protected static $query_paths = [];
	protected static $context;
	protected static $primary_instance;
	protected static $cols = [];
	// protected static $distinct_rows = [];
	protected static $criteria = [];
	protected static $row_order = [];
	protected static $join = [];
	protected static $row_limit;
	protected static $group_by;

	const QUERY_FILTER_COLUMN_REGEX = '/(\w+)_(is$|not$|includes$|excludes$|between$|greater_than$|less_than$|contains$|matches$|sounds_like$|begins_with$|ends_with$)/i';
	# const QUERY_JOIN_TABLE_REGEX = '/join_(\w[^_]+).?(left$|right$|cross$|inner$|left_outer$|right_outer$)?/i';
	const QUERY_JOIN_TABLE_REGEX = '/^(\w*)join_(\w+)$/i';

	public function __construct ($table = null, $column = null, $pk_value = null)
	{
		$this->raw_table = $table;
		parent::__construct($pk_value, $table);

		self::$context = $this;

		if ($column) {
			$this->name = $column;
			$this->col_ns = $this->alias.'.'.$column;
		}

		if (!self::$primary_instance) self::$primary_instance = $this;

		$this->add_default_columns($this->columns);
		$this->set_audit_criteria();

		if (null !== $this->pk_value) {
			// dump($this->pk_name.' >> '.$this->pk_value);
			$this->set_criteria($this->pk_value, $this->pk_name);
		}
	}

	protected function set_criteria ($value, $key = null)
	{
		if (is_array($value)) {
			foreach ($value as $k => $v) {
				$this->set_criteria($v, $k);
			}

			return $this;
		}

		if (false !== array_search($value, self::$criteria)) return $this;
		if (is_string($key)) {
			self::$criteria[$key] = $value;
		}
		else {
			self::$criteria[] = $value;
		}

		return $this;
	}

	private function set_audit_criteria ()
	{
		if (true === $this->audit) {
			$this->set_criteria([
				$this->alias.'.deleted = 0',
				$this->alias.'.deleted_by = 0',
				$this->alias.'.deleted_at is null'
			]);
		}
	}

	private function add_default_columns ($cols)
	{
		if (!self::$cols) {
			self::$cols = [];
			self::$_cols = [];
		}

		foreach ($cols as $field => $column) {
			$key = $this->alias.'.'.$field;
			if (!in_array($field, self::$_cols)) {
				self::$cols[] = $this->alias.'.'.$field;
				self::$_cols[] = $field;
			}
		}
	}

	private function alias_prefixed ($col)
	{
		return -1 < strpos($col, $this->alias.'.');
	}

	private function normalize_column ($col)
	{
		return $this->alias.'.'.str_replace($this->alias.'.', '', trim($col));
	}

	private function reset_column_selection ()
	{
		foreach (self::$cols as $key => $value) {
			if ($this->alias_prefixed($key) || $this->alias_prefixed($value)) unset(self::$cols[$key]);
		}
	}

	public function select_none ()
	{
		$last = last(QueryBuilder::$query_paths);
		if ($last) {
			$last = array_values($last)[0];
			return QueryBuilder::$context;
		}

		$this->reset_column_selection();
		return $this;
	}

	private function normalize_select ($cols)
	{
		$cols = preg_replace('/\s,|,\s/', ',', $cols);

		$fk_columns = $this->foreign_key_columns();

		if ($fk_columns) {
			$cols = preg_split('/,/', $cols, null, PREG_SPLIT_NO_EMPTY);
			$cols = array_merge($cols, array_values($fk_columns));
			$cols = array_unique($cols);
			$cols = implode(',', $cols);
		}

		return preg_replace('/,/', ', ', $cols);
	}

	public function select ($cols, $retrieve = false, $free = false)
	{
		if (!$cols) return;

		$cols = $this->normalize_select($cols);

		$last = last(QueryBuilder::$query_paths);
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->select = $cols;
			return QueryBuilder::$context;
		}

		$this->reset_column_selection();
		$cols = explode(',', $cols);

		foreach ($cols as $key => $field) {

			// $field = $retrieve ? $field : $this->normalize_column($field);
			$normalized = $free ? $field : $this->normalize_column($field);
			$field = $retrieve ? $field : $normalized;

			$parts = explode(' ', trim($field), 2);

			if (isset($parts[1])) {
				self::$cols[$parts[0]] = $parts[1];
			}
			else {
				self::$cols[] = $parts[0];
			}
		}

		return $this;
	}

	public function custom_select ($cols, $retrieve = false)
	{
		return $this->select($cols, $retrieve, true);
	}

	public function count ($column, $alias, $retrieve = false)
	{
		$last = last(QueryBuilder::$query_paths);
		
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->count[$alias] = $column;
			return QueryBuilder::$context;
		}

		self::$cols[] = 'count('.$column.') as '.$alias;
		return $this;
	}

	/*public function distinct ($column, $retrieve = false)
	{
		$last = last(QueryBuilder::$query_paths);
		
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->count[] = $column;
			return QueryBuilder::$context;
		}

		self::$distinct_rows[] = $column;
		return $this;
	}*/

	public function group ($columns, $retrieve = false)
	{
		$last = last(QueryBuilder::$query_paths);
		
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->group = $columns;
			return QueryBuilder::$context;
		}

		self::$group_by = $columns;
		return $this;
	}

	public function where ()
	{
		$last = last(QueryBuilder::$query_paths);
		if ($last && !func_get_arg(3)) {
			$last = array_values($last)[0];
			$last->where[] = func_get_args();
			return QueryBuilder::$context;
		}

		$args = func_get_args();
		$key = $args[0];
		$value = isset($args[1]) ? $args[1] : null;
		$extra = isset($args[2]) ? $args[2] : null;

		if (is_array($key)) {
			foreach ($key as $k => $v) {
				if (is_string($key)) {
					$this->set_criteria($v, $k);
				}
				else {
					$this->set_criteria($v);
				}
			}
			return $this;
		}
		
		if (is_string($key) && null == $value && null == $extra) {
			return $this->set_criteria($key);
		}

		if (null != $value && null == $extra) {
			if (is_string($value)) $value = quote($value);
			return $this->set_criteria($value, $key);
		}

		if (null != $value && null != $extra) {
			if ('like' == $value) $extra = "'".$extra."'";
			return $this->set_criteria($key.' '.$value.' '.$extra);
		}

		return $this;
	}

	public function filter_is ($value, $quote = true)
	{
		if ($quote) $value = quote($value);
		return $this->set_criteria($value, $this->col_ns);
	}

	public function filter_includes ($value, $quote = true)
	{
		if (!is_array($value)) $value = [$value];
		$in = [];
		foreach ($value as $val) {
			$in[] = $quote ? quote($val) : $val;
		}

		return $this->set_criteria($this->col_ns.' in ('.implode(',', $in).')');
	}

	public function filter_not ($value, $quote = true)
	{
		$value = $quote ? quote($value) : $value;
		if (null === $value) return $this->set_criteria($this->col_ns.' is not null');
		if ('' === $value) return $this->set_criteria($this->col_ns." <> ''");
		return $this->set_criteria($this->col_ns.' <> '.$value);
	}

	public function filter_excludes ($value, $quote = true)
	{
		if (!is_array($value)) $value = [$value];
		$in = [];
		foreach ($value as $val) {
			$in[] = $quote ? quote($val) : $val;
		}

		return $this->set_criteria($this->col_ns.' not in ('.implode(',', $in).')');
	}

	public function filter_between ($left, $right, $quote = true)
	{
		if ($quote) {
			$left = quote($left);
			$right = quote($right);
		}
		return $this->set_criteria($this->col_ns.' between '.$left.' and '.$right);
	}

	public function filter_greater_than ($value)
	{
		return $this->set_criteria($this->col_ns.' > '.$value);
	}

	public function filter_less_than ($value)
	{
		return $this->set_criteria($this->col_ns.' < '.$value);
	}

	public function filter_begins_with ($value)
	{
		return $this->set_criteria($this->col_ns.' like '.quote($value.'%'));
	}

	public function filter_contains ($value)
	{
		return $this->set_criteria($this->col_ns.' like '.quote('%'.$value.'%'));
	}

	public function filter_ends_with ($value)
	{
		return $this->set_criteria($this->col_ns.' like '.quote('%'.$value));
	}

	public function filter_matches ($value)
	{
		return $this->set_criteria($this->col_ns.' REGEXP '.quote($value));
	}

	public function filter_sounds_like ($value)
	{
		return $this->set_criteria($this->col_ns.' sounds like '.quote($value));
	}



	public function order ($key, $direction = 'asc', $retrieve = false)
	{
		$last = last(QueryBuilder::$query_paths);
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->order[] = (object) ['key' => $key, 'direction' => $direction];
			return QueryBuilder::$context;
		}

		$order = [];

		if (is_array($key)) {
			foreach ($key as $field => $dir) {
				$order[] = is_numeric($field) ? $dir.' '.$direction : $this->alias.'.'.$field.' '.$dir;
			}
			$order = implode(', ', $order);
		}
		else {
			$order = $this->alias.'.'.$key.' '.$direction;
		}

		self::$row_order[] = $order;
		return $this;
	}



	public function limit ($limit, $offset = null, $retrieve = false) 
	{
		$last = last(QueryBuilder::$query_paths);
		if ($last && !$retrieve) {
			$last = array_values($last)[0];
			$last->limit = [$limit, $offset];
			return QueryBuilder::$context;
		}

		if (is_array($limit)) {
			$offset = isset($limit[1]) ? $limit[1] : null;
			$limit = $limit[0];
		}

		$offset = (null !== $offset) ? $offset : 0;//$limit - 1;
		self::$row_limit = $limit.' offset '.$offset;

		return $this;
	}



	protected function __join ()
	{
		$join = [];

		$args = func_get_args();

		$type = array_pop($args);
		$count = sizeof($args);

		switch ($count) {
			case 1:
				$from = $args[0];
				$to = null;
				break;
		
			case 2:				
				if (is_string($args[0]) && is_string($args[1])) {
					$from = $args[0];
					$to = $args[1];
				}
				break;

			default:
				$from = null;
				$to = null;
				break;
		}

		if (is_array($from)) {
			$type = $to;
			foreach ($from as $key => $value) {
				$join[] = $from = $this->alias.'.'.$key.' = '.$value;
			}
		}
		else {
			$join[] = $from = $this->alias.'.'.$from.' = '.$to;
		}


		$alias = ($this->table === $this->alias) ? $this->alias : $this->table.' '.$this->alias;
		$_join = $type.' '.$alias.' on ('.implode(' and ', $join).')';

		self::$join[] = $_join;

		return $this;
	}

	private function build_query ()
	{
		$cols = [];
		$condition = [];
		$order = [];
		$joins = [];
		$limit = '';

		foreach (self::$cols as $key => $value) {
			$cols[] = is_int($key) ? $value : $key.' as '.$value;
		}
		// if (self::$distinct_rows) $cols[] = 'distinct '.implode(', ', self::$distinct_rows);

		if ($this->pk_name && $this->pk_value) {
			// dump($this->normalize_column($this->pk_name).' >> '.$this->pk_value);
			$this->set_criteria($this->pk_value, $this->normalize_column($this->pk_name));
		}

		foreach (self::$criteria as $key => $value) {
			$condition[] = is_int($key) ? $value : $key.' = '.$value;
		}

		$sql = 'select ';
		if ($cols) $sql .= implode(', ', $cols).' from '.self::$primary_instance->table.' '.self::$primary_instance->alias;
		if (self::$join) $sql .= ' '.implode(' ', self::$join);
		if ($condition) $sql .= ' where '.implode(' and ', $condition);
		if (self::$group_by) $sql .= ' group by '.self::$group_by;
		if (self::$row_order) $sql .= ' order by '.implode(', ', self::$row_order);
		if (self::$row_limit) $sql .= ' limit '.self::$row_limit;

		// dump($sql);

		return $sql;
	}

	public function get ($fkey_recursive = false/*, $single_row = false*/)
	{
		$rows = Database::getInstance()->fetch($this->build_query());

		$constraints = self::$primary_instance->constraints;

		$this->reset_query();
		$this->parse_foreign_tables($rows, $constraints, $fkey_recursive);

		/*if ($this->pk_name && $this->pk_value && $rows && $single_row) {
			return $rows[0];
		}*/

		return $rows;
	}

	public function get_row ($key = null, $fkey_recursive = false)
	{
		if ($rows = $this->get($fkey_recursive)) {
			return ($key && isset($rows[0]->$key)) ? $rows[0]->$key : $rows[0];
		}
	}

	protected function parse_foreign_tables ($rows, $constraints, $fkey_recursive)
	{
		if (!$constraints || !$rows) return false;

		foreach ($rows as $each) {
			foreach ($constraints as $set) {

				$t_table = $set->target->table;

				$s_column = $set->source->column;
				$t_column = $set->target->column;

				$fkey = $set->fkey;
				$foreign_keys = SchemaData::foreign_keys()->$fkey;

				$link_source = array_keys($foreign_keys->link)[0];
				$link_target = array_values($foreign_keys->link)[0];

				if (false === $fkey_recursive && !isset(QueryBuilder::$query_paths[$foreign_keys->path])) continue;

				$filter_set = self::$query_paths[$foreign_keys->path];
				$fmodel = Model::create($t_table, null, $filter_set->column);

				if (isset($filter_set->join)) {
					foreach ($filter_set->join as $join) {
						$builder = Model::create($join->table);
						call_user_func_array([$builder, '__join'], $join->args);
					}
				}

				if (isset($filter_set->select_none)) {
					$fmodel->select_none();
				}

				if (isset($filter_set->select)) {
					$fmodel->select($filter_set->select, true);
				}

				if (isset($filter_set->filters)) {
					foreach ($filter_set->filters as $filter) {
						$builder = Model::create($fmodel->raw_table, null, $filter->column);
						call_user_func_array([$builder, $filter->func], $filter->args);
					}
				}

				if (isset($filter_set->where)) {
					foreach ($filter_set->where as $where) {
						$where = array_pad($where, 4, null);
						$where[3] = true;
						call_user_func_array([$fmodel, 'where'], $where);
					}
				}

				if (isset($filter_set->limit)) {
					$fmodel->limit($filter_set->limit[0], $filter_set->limit[1], true);
				}

				if (isset($filter_set->order)) {
					foreach ($filter_set->order as $order) {
						$fmodel->order($order->key, $order->direction, true);
					}
				}

				if (isset($filter_set->count)) {
					foreach ($filter_set->count as $alias => $count) {
						$fmodel->count($count, $alias, true);
					}
				}

				if (isset($filter_set->distinct)) {
					foreach ($filter_set->distinct as $distinct) {
						$fmodel->distinct($distinct, true);
					}
				}

				$s_col_value = $each->$s_column;

				if ($s_col_value) {
					$fmodel->where($link_target, $s_col_value, null, true);
				}

				$each->$t_table = $fmodel->get($fkey_recursive);
			}
		}
	}
}