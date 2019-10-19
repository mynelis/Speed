<?php

namespace Speed\Templater;

class Repeater
{
	public $identity;
	public $repeat;
	public $select;
	public $data;
	public $parent;

	const XPATH_PARTIALS_PATH = '//partial[@select]';
	const XPATH_REPEATER_PATH = '//*[@repeat]';
	const XPATH_SELF_IDENTIKEY_PATH = '@identity';
	const XPATH_AUTO_PARSE_IDENTIKEY_PATH = '//*[@parse="auto"]/@identity';
	const XPATH_PARENT_PATH = 'ancestor::*[@identity][position() = 1]';
	const SNIPPET_REGEX_MATCHER = '/\$\{(_KEY_)[\s]?([^\{]*?)\}/mis';

	public function __construct (\SimpleXMLElement $node, Callable $data_provider)
	{
		$this->node = $node;
		$this->repeat = (int) $node->attributes()->repeat;
		$this->select = trim($node->attributes()->select);
		$this->parent = $this->get_parent();
		$this->identity = $this->get_identity($node);
		$this->depth = $this->node_depth($node);

		$data = $data_provider($this->identity, $this->select);
		if ($data) {
			$this->data = $this->data_chunck($this->repeat, $data);
		}
	}

	public function get_parent ()
	{
		$parent = $this->node->xpath($this->path.self::XPATH_PARENT_PATH);
		return $parent ? $parent[0] : null;
	}

	private function node_depth ($node)
	{
		return sizeof($node->xpath('ancestor::*'));
	}

	private function get_identity ($node)
	{
		$paths = [
			self::XPATH_SELF_IDENTIKEY_PATH, 
			self::XPATH_PARENT_PATH.'/@identity'
		];

		foreach ($paths as $path) {
			$n = $node->xpath($path);
			if ($n) return trim($n[0]->identity);
		}
	}

	private function data_chunck ($repeat, Array $rows)
	{
		if (false === $repeat) return [];
		return (0 < $repeat) ? array_slice($rows, 0, $repeat) : $rows;
	}
}