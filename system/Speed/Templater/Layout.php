<?php

namespace Speed\Templater;

use \Speed\AppControl;
use \Speed\DataLibrary\Model;

class Layout 
{
	private $xml;

	private static $map;
	private static $layout_vars;
	private static $partial_map;
	private static $call_stack;
	private static $instance;
	private static $view;

	public static $scripts = [];
	public static $hooks = [];

	const TEXT_HTML = 'text/html';
	const TEXT_PLAIN = 'text/plain';
	const TEXT_XML = 'application/xml';
	const TEXT_JSON = 'application/json';

	public function __construct ()
	{
		if (self::$instance) return self::$instance;

		$file = find_view();

		self::$map = (object) [
			'meta' => [
				(object) null
			]
		];

		if (true === config('app.enable_access_control')) {
			AppControl::check_access();
		}

		/*if (AppControl::$notice) {
			Layout::register('system_notice', function () {
				return AppControl::$notice;	
			});

			if (AppControl::$view) $file = AppControl::$view;
		}*/

		if (!is_readable($file)) return;

		self::$call_stack = (object) null;
		self::$partial_map = [];

		// dump(request(), true);
		// dump(routing(), true);

		call_request(true);

		if (AppControl::$notice) {
			Layout::register('system_notice', function () {
				return AppControl::$notice;	
			});

			if (AppControl::$view) $file = AppControl::$view;

			// dump(\Speed\AppControl::$notice);
		}


		if (self::$hooks) {
			foreach (self::$hooks as $hook) {
				if (is_callable($hook)) {
					$hook(self::$map);
				}
			}
		}

		$file = self::$view ? self::$view : find_view();

		$this->xml = new XMLDocument($file);
		$this->parse_page();

		self::$instance = $this;
	}

	public static function set_view ($view)
	{
		self::$view = find_view($view);
	}

	public static function add_hook ($hook)
	{
		if (is_callable($hook)) self::$hooks[] = $hook;
	}

	public static function get_hooks ()
	{
		return self::$hooks;
	}

	public static function set_meta ($key, $value = null)
	{
		if (isset(self::$map->meta[0])) {
			if (is_array($key)) {
				foreach ($key as $k => $v) {
					self::$map->meta[0]->$k = $v;
				}
			}
			else {
				self::$map->meta[0]->$key = $value;
			}
		}
	}

	// Layout::add_scripts([
	//	'custom', 'rypp'
	// ]);
	public static function add_scripts ($scripts)
	{
		if (is_string($scripts)) {
			$scripts = preg_split('/\s/', $scripts, null, PREG_SPLIT_NO_EMPTY);
		}

		foreach ($scripts as $p) {
			$path = ROOT.'assets/js/'.$p.'.js';

			if (!in_array($p, self::$scripts) && is_readable($path)) {
				self::$scripts[] = $path;
			}
		}
	}

	private function insert_scripts ($xml)
	{
		if (self::$scripts) {
			$scripts = $xml->dom->xpath('//script[@src]');
			$last = last($scripts)[0];

			foreach (self::$scripts as $script) {
				$node = $xml->create_element('script', [
					'src' => $script,
					'type' => 'text/javascript',
					'charset' => 'utf-8'
				]);

				$inserted = $xml->insert_after($node, simplexml_import_dom($last));
				$last = $inserted;
			}
		}

		return $xml;
	}

	public static function getInstance ()
	{
		return self::$instance;
	}

	// 1. Layout::register('title', 'Application built with F7 Speed'); 
	// 2. Layout::register('poll_options', function () {
	//		return Model::create('poll_option')->get();
	//	});
	public static function register ($identity, $data = [])
	{
		if (is_string($identity) && is_scalar($data)) {
			// self::$map->$identity = [[$identity => $data]];
			self::$map->$identity = [(object) [$identity => $data]];
			// self::$map->$identity = $data;

			// dump($identity);
			// dump(self::$map->$identity);
			// dump([$data]);
		}

		elseif (is_string($identity)) {
			self::$map->$identity = $data;
		}

		elseif (is_array($identity)) {
			foreach ($identity as $key => $value) {
				self::register($key, $value);
			}
		}
	}

	public function render ($type = self::TEXT_HTML)
	{
		header('Content-Type: '.$type);
		//header('Chache-Control: no-cache');
		$page = $this->xml->format($this->xml, true);
		$page = str_replace('&amp;', '&', $page);
		// echo $page;

		/*$tidy = \tidy_parse_string($page);
		$tidy->cleanRepair();
		echo $tidy;*/

		if (extension_loaded('tidy')) {
			// Specify configuration
			$config = array(
	           'indent' => true,
	           'indent-spaces' => 4,
	           'output-xhtml' => true,
	           'wrap' => 200
	       );

			// Tidy
			$tidy = new \Tidy();
			$tidy->parseString($page, $config, 'utf8');
			$tidy->cleanRepair();

			$page = $tidy;
		}

		echo $page;
	} 

	private function mapping (String $identity, String $key = '')
	{
		$data = null;

		if (isset(self::$map->$identity)) {
			$data = self::$map->$identity;

			/*if (is_scalar(self::$map->$identity)) {
				$data = [(object) [$identity => $data]];
			}*/
		}

		if (is_callable($data)) {
			if (isset(self::$call_stack->$identity)) {
				$data = self::$call_stack->$identity;
			}
			else {
				$data = $data($this);
				self::$call_stack->$identity = $data;
			}
		}

		// dump($identity);/
		// dump($data);
		return value_of($data, $key);
	}

	private function xpath ($path, $context = null)
	{
		if (!$context) $context = $this->xml->dom;
		return $context ? $context->xpath($path) : null;
	}

	private function find_repeaters ()
	{
		$repeaters = [];
		$layout = $this;

		if (self::$map) {
			$nodes = $this->xpath(Repeater::XPATH_REPEATER_PATH);

			if ($nodes) {
				foreach ($nodes as $node) {

					$repeater = new Repeater($node, function ($idk, $select = '') use ($layout) {
						if ($idk) return $layout->mapping($idk, $select);
					});

					if ($repeater->data) {
						$repeaters[] = $repeater;
					}
					else {
						$this->xml->remove_node($repeater->node);
					}
				}
			}
		}

		usort($repeaters, function ($a, $b) {
			return $a->depth < $b->depth;
		});

		return $repeaters;
	}

	private function find_dynamic_view ($path)
	{
		return preg_replace_callback('/\$(\w[^\/]+)/', function ($matches) {
			$np = $this->mapping($matches[1]);
			if ($np) return $np[0]->{$matches[1]};
		}, $path);
	}

	private function import_partials ($xdoc)
	{
		$partials = $this->find_partials($xdoc);

		if ($partials) {
			foreach ($partials as $part) {
				$node_path = trim($part->attributes()->select);

				$node_path = $this->find_dynamic_view($node_path);

				// dump($node_path);
				
				$file_path = explode('/', $node_path);
				$file_path = array_slice($file_path, 1);
				$file = find_view($file_path[0], '../partials/');

				// dump($file_path);

				if ($file) {
					$partial_xdoc = new XMLDocument($file);
					$nodes = $partial_xdoc->dom->xpath($node_path.'/*');

					if ($nodes) {
						$xdoc->insert_nodes($nodes, $part);
					}

					self::$partial_map[] = $node_path;
				}

				$xdoc->remove_node($part);
			}

			return $xdoc;
		}

		return false;
	}

	private function parse_page ()
	{
		// TODO:
		// Check if requested pare exists in app/cache/xml..
		// if found, just straight to parse it..
		// otherwise do all the stuff below and cache the file, 
		// then parse it

		$mod = request('module', true);
		$cls = request('class', true);

		$cache_dir = ROOT.config('app.cache_dir').'view/';
	    $cache_file = $cache_dir.strtolower($mod.'_'.$cls).'.xml';

	    $cache = config('app.cache', true);
	    // dump($cache);

		// $file = find_view('', ROOT.'app/cache/xml/');
		//$file = find_view($cache_file, $cache_dir);
		// dump(realpath($cache_file), true);

		if (true == config('app.cache', true) && is_readable($cache_file)) {

			$this->xml = new XMLDocument($cache_file);
			// dump('cached');
		}
		else {

			$file = find_view('page', '../');
			// dump($file, true);
			$xml = new XMLDocument($file);

			$node = $xml->dom->xpath('//view');
			$view_xml = $this->xml;

			if ($node) {

				// In case the select attribute is set for the "view" element, find the 
				// specified view referenced by "select" and use it, rather than the 
				// default or current module's view. The value for "select" is always 
				// in the format /$module/$view
				$select = $node[0]->attributes()->select;
				if ($select) {

					$select = $this->find_dynamic_view(trim($select));

					$path = preg_split('/\//', $select, null, PREG_SPLIT_NO_EMPTY);
					$v = $path[0];
					$m = '';

					if (isset($path[1])) {
						$m = $path[0]; // module part
						$v = $path[1]; // view file 
					}

					$file = find_view($v, '', $m);
					// $xml = new XMLDocument($file);
					$view_xml = new XMLDocument($file);
				}
			}

			// Replace the "view" element in page.xml with the specified view and remove the
			// original "view" element
			$view = $view_xml->dom->xpath('/view/*');
			if ($view && $node) {
				$xml->insert_nodes($view, $node[0]);
			}
			$xml->remove_node($node[0]);

			// Append defined scripts
			$xml = $this->insert_scripts($xml);

			$this->xml = $xml;

			while (false != $xml) {
				$xml = $this->import_partials($xml);
			}

			// Cache the prepared view file
			$this->xml->dom->saveXml($cache_file);
		}

		$this->init_auto_parse();

		//$this->xml = $xml;
		$this->parse_template();
	}

	private function init_auto_parse ()
	{
		$auto_parse = $this->xml->dom->xpath(Repeater::XPATH_AUTO_PARSE_IDENTIKEY_PATH);
		if ($auto_parse) {
			foreach ($auto_parse as $each) {
				$identity = trim($each->identity);

				Layout::register($identity, function () use ($identity) {
					return Model::create($identity)->get();
				});
			}
		}
	}

	private function find_partials ($xdoc)
	{
		if ($xdoc->dom) return $xdoc->dom->xpath(Repeater::XPATH_PARTIALS_PATH);
	}

	private function parse_template ()
	{
		$this->parse_repeaters();
	}

	private function parse_repeaters ()
	{
		$repeaters = $this->find_repeaters();

		foreach ($repeaters as $rpt) {	
			$this->parse_snippets($rpt, $rpt->data);
			$this->reset_node($rpt);
		}
	}

	private function reset_node (Repeater $rpt)
	{
		if ($rpt->parent) {
			$this->xml->remove_attribute($rpt->parent, 'identity');
		}

		if ($rpt->node) $this->xml->remove_node($rpt->node);
	}

	private function parse_snippets (Repeater $rpt, Array $data)
	{
		$this->xml->remove_attribute($rpt->node, ['repeat', 'identity']);

		$content = $rpt->node->saveXml();
		$node = $rpt->node;

		foreach ($data as $each) {
			$snip = $this->xml->parse_single_snippet($each, $content);
			$new = simplexml_load_string($snip);

			if ($node) {
				$inserted = $this->xml->insert_after($new, $node);
				$inserted = simplexml_import_dom($inserted);

				$this->xml->remove_attribute($inserted, 'select');

				$node = $inserted;
			}
		}

		return $this;
	}
}