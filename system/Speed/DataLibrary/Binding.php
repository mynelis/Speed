<?php

namespace Speed\DataLibrary;

use \app\cache\SchemaData;

use \Speed\Templater\XMLDocument;
use \Speed\ContentManager;
use \Speed\DataLibrary\Model;
use \Speed\Database;

class Binding extends Model
{
	private $name;
	private $cml;
	private $xml;
	private $xdoc;
	private $group;

	public function __construct ($name)
	{
		$this->name = $name;

		$cms = ContentManager::getInstance();
		$context = $cms->getXmlContext();
		
		$xml = $context->dom->xpath('/binding/group/entry[@id="'.$name.'"]');
		if (!$xml) return false;

		$this->xml = $xml[0];
		$this->cml = $context->dom;
		$this->group = $this->get_group();
		$this->xdoc = new XMLDocument(ROOT.'system/f7/xml/snippets.xml');

		$table = $this->get_table();
		if ($table) parent::__construct(null, $table);
	}

	private function get_group ()
	{
		$node = $this->xml->xpath('parent::group[position() = 1]');
		if ($node) return $node[0];
	}

	private function get_table ()
	{
		$node = $this->xml->xpath('@table');
		if ($node) return trim($node[0]);
	}

	public function cms_access_table ($bind, $field, $access_data = null)
	{
		$groups = $this->cml->xpath('/binding/group');

		if ($groups) {
			$_groups = '';

			foreach ($groups as $group) {
				$attributes = $group->attributes();
				$entries = $this->cms_access_items($group, $field, $access_data);

				if (!$entries) continue;

				$_groups .= $this->xdoc->parse_node('/snippets/access/group', [
					'id' => trim($attributes->id),
					'label' => trim($attributes->label)
				]);

				$_groups .= $entries;
			}

			if ($_groups) {				
				$label = $field ? trim($field->attributes()->label) : '';
				$name = $field ? trim($field->attributes()->name) : '';

				return $this->xdoc->parse_node('/snippets/access/list', [
					'items' => $_groups,
					'label' => $label,
					'name' => $name,
					'value' => $access_data->$name,
					'bind' => $bind
				]);
			}
		}

		return '';
	}

	private function cms_access_items ($group, $field, $access_data = null)
	{
		$entries = $group->xpath('entry');
		if ($entries) {
			$items = '';
			$group_id = trim($group->attributes()->id);

			foreach ($entries as $entry) {
				$attributes = $entry->attributes();
				if ('core' == trim($attributes->scope)) continue;

				$field_attributes = $field->attributes();
				//dump($access_data);

				$items .= $this->xdoc->parse_node('/snippets/access/item', [
					'id' => trim($attributes->id),
					'group' => $group_id,
					'label' => trim($attributes->label),
					'name' => trim($field_attributes->name),
					'type' => trim($field_attributes->type)
				]);
			}

			return $items;
		}
	}

	public function app_access_table ($bind, $field, $access_data = null)
	{
		$groups = \Speed\AppControl::get_access_mapping();

		if ($groups) {
			$_groups = '';

			foreach ($groups as $module => $group) {
				$entries = $this->app_access_items($module, $group, $field, $access_data);

				if (!$entries) continue;

				$_groups .= $this->xdoc->parse_node('/snippets/access/group', [
					'id' => $module,
					'label' => strtoupper($module)
				]);

				$_groups .= $entries;
			}

			if ($_groups) {				
				$label = $field ? trim($field->attributes()->label) : '';
				$name = $field ? trim($field->attributes()->name) : '';

				return $this->xdoc->parse_node('/snippets/access/list', [
					'items' => $_groups,
					'label' => $label,
					'name' => $name,
					'value' => $access_data->$name,
					'bind' => $bind
				]);
			}
		}

		return '';
	}

	private function app_access_items ($module, $paths, $field, $access_data = null)
	{
		if ($paths) {
			$items = '';

			foreach ($paths as $key => $val) {
				$field_attributes = $field->attributes();

				$items .= $this->xdoc->parse_node('/snippets/access/item', [
					'id' => $module.'.'.$key,
					'group' => $module,
					'label' => $val,
					'name' => trim($field_attributes->name),
					'type' => trim($field_attributes->type)
				]);
			}

			return $items;
		}
	}

	public static function do_crypt_column ($entry, $field, $value, $method)
	{
		$cml = ContentManager::getInstance()->getXmlContext();
		// $cryptor = $cml->dom->xpath('/binding/group/entry[@id="'.$entry.'"]/field[@name="'.$field.'"][@cryptor]');
		$cryptor = $cml->dom->xpath('/binding/group/entry[@id="'.$entry.'"]/field[@name="'.$field.'"]/@cryptor');

		if ($cryptor) {
			// $cryptor = trim($cryptor[0]->attributes()->cryptor);
			// $cryptor = trim($cryptor[0]);

			$cryptors = explode(':', trim($cryptor[0]), 2);
			$cryptor = $cryptors[0];
			if (isset($cryptors[1]) && 'decrypt' == $method) $cryptor = $cryptors[1];

			// dump($entry.' >> '.$field.' >> '.$method.' >> '.$cryptor);

			$crypt = explode('.', $cryptor);

			foreach ($crypt as $cr) {

				if ('system' == $cr) {
					$value = (new \Speed\Security\Cryptor($field))->$method($value);
				}
				elseif (function_exists($cr)) {
					$value = $cr($value);
				}
			}
		}

		return $value;
	}

	public static function encrypt_column ($entry, $field, $value)
	{
		return self::do_crypt_column($entry, $field, $value, 'encrypt');
	}

	public static function decrypt_column ($entry, $field, $value)
	{
		return self::do_crypt_column($entry, $field, $value, 'decrypt');
	}

	public static function crypt_columns ($entry, $values, $callback = null, $method)
	{
		foreach ($values as $k => $v) {
			$enc = self::$method($entry, $k, $v);

			if (is_callable($callback)) {
				$callback($k, $enc, $v);
			}
			else {
				$values->$k = $enc;
			}
		}

		return $values;
	}

	public static function encrypt_columns (String $entry, \stdClass $values, $callback = null)
	{
		return self::crypt_columns($entry, $values, $callback, 'encrypt_column');
	}

	public static function decrypt_columns (String $entry, \stdClass $values, $callback = null)
	{
		return self::crypt_columns($entry, $values, $callback, 'decrypt_column');
	}
}