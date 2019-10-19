<?php

namespace Speed\Templater;

class XMLDocument
{
	const XML_PRESERVE_WHITE_SPACE = true;
	const XML_DOC_FORMAT_OUTPUT = true;

	private static $loaded_docs = []; 

	public $dom;

	public function __construct ($file)
	{
		if (!isset(self::$loaded_docs[$file])) {
			self::$loaded_docs[$file] = simplexml_load_file(realpath($file));
		}
		
		$this->dom = self::$loaded_docs[$file];
	}

	/**
	 * Convert an HTML file to XML.
	 * This is meant to prepare any HTML file that is not
	 * XML compliant, by reproducing an XML version.
	 * 
	 * @param  string  $dir_path    Base HTML directory 
	 * @param  boolean $overwrite   Overwrite already converted files
	 * @param  mized $delete_html 	Delete processed HTML file if TRUE, or
	 *                              Return processed HTML file to a callback function
	 * @return void              
	 */
	public static function convert_from_html ($dir_path = '', $overwrite = true, $delete_html = false)
	{
		if (!$dir_path) $dir_path = config('app.html_tpl_dir', true);

		$dir_path = ROOT.$dir_path;		
		if (!is_dir($dir_path)) return;

		$files = glob($dir_path.'/*');

		if ($files) {
			foreach ($files as $file) {
				if (is_dir($file)) {
					self::convert_from_html($file, $overwrite, $delete_html);
				}
				elseif (is_file($file) && (preg_match('/.*\.html$/i', $file)) || $overwrite) {
					$html = file_get_contents($file);

					$dom = new \DomDocument('1.0', 'UTF-8');
					$dom->formatOutput = true;
					$dom->preserveWhiteSpace = true;

					$dom->loadHTML($html);
					$xml = str_replace('&#13;', '', $dom->saveXml());
					$save_file = str_replace('.html', '.xml', $file);

					$fp = fopen($save_file, 'w+');
					fwrite($fp, $xml);
					fclose($fp);

					if (preg_match('/.*\.html$/i', $file)) {
						if (true === $delete_html) {
							@unlink($file);
						}
						elseif (is_callable($delete_html)) {
							$delete_html($file);
						}
					}
				}
			}
		}
	}

	public function parse_text ($key, $value, $content, $regex, $marker = '_KEY_')
	{
		$regex = str_replace($marker, $key, $regex);

		return preg_replace_callback($regex, function ($matches) use ($content, $key, $value) {
			$parts = preg_split('/\s/', $matches[2], null, PREG_SPLIT_NO_EMPTY);
			$func = $parts[0];
			$args = array_slice($parts, 1);

			$value = value_of($value, $key);
			array_unshift($args, $value);

			if (function_exists($func)) $value = call_user_func_array($func, $args);

			return $value;
		}, $content);
	}

	public function parse_single_snippet ($row, $text)
	{
		if (!$row) return $text;

		foreach ($row as $key => $value) {
			$value = str_replace('&', '&amp;', $value);
			$text = $this->parse_text($key, $value, $text, Repeater::SNIPPET_REGEX_MATCHER);
		}

		return $text;
	}

	public function get_html ($xpath, $context = null)
	{
		if (!$context) $context = $this->dom;

		$nodes = $context->xpath($xpath.'/*');
        $html = '';

        if ($nodes) {
           	foreach ($nodes as $node) {
               	$domnode = dom_import_simplexml($node);
               	$dom = new \DomDocument('1.0', 'UTF-8');
               	$imported = $dom->importNode($domnode, true);
               	$dom->appendChild($imported);
               	$html .= $dom->saveHtml();
           	}
       	}

        return $html;
	}

	public function parse_node ($xpath, $vars = [], $context = null)
	{
		if (!$context) $context = $this->dom;
		$html = $this->get_html($xpath, $context);
		return $this->parse_single_snippet($vars, $html);
	}

	public function format ($xdoc, $cleanup = false)
	{
		$dom = dom_import_simplexml($xdoc->dom);
		$output = new \DOMDocument('1.0');
		$output->formatOutput = self::XML_DOC_FORMAT_OUTPUT;
		$output->preserveWhiteSpace = self::XML_PRESERVE_WHITE_SPACE;

		$dom = $output->importNode($dom, true);
		$dom = $output->appendChild($dom);
		$content = $output->saveXML($output, LIBXML_NOEMPTYTAG);

		if (true == $cleanup) {
			$regex = '/\$\{(.[^\{]*?)\}/mis';

			return preg_replace_callback($regex, function ($matches) {
				return '';
			}, $content);
		}

		return $content;
	}

	public function create_element (String $tag, Array $attributes = null, $content = null, $closed = true)
	{
		$tag = $closed ? '<'.$tag.'></'.$tag.'>' : '<'.$tag.'/>';
		$xml = new \SimpleXMLElement($tag);

		if ($attributes) {
			foreach ($attributes as $name => $value) {
				$xml->addAttribute($name, $value);
			}
		}

		if ($content) {
			$newsIntro = $xml->addChild($content);
		}

		return $xml;
	}

	public function get_attribute ($node, $name)
	{
		$attribute = $node->xpath('@'.$name);
		return $attribute ? trim($attribute[0]) : null;
	}

	public function remove_attribute ($node, $name)
	{
		if (is_array($name)) {
			array_walk($name, function ($n) use ($node) {
				return $this->remove_attribute($node, $n);
			});
		}

		$attribute = $node->xpath('@'.$name);
		if ($attribute) {
			unset($attribute[0][0]);
			return true;
		}
		return false;
	}

	public function remove_node ($node)
	{	
		if (is_array($node)) {
			array_walk($node, function ($n) {
				$this->remove_node($n);
			});
		}

		$dom = dom_import_simplexml($node);
		if ($dom->parentNode) {
			return $dom->parentNode->removeChild($dom);
		}
	}

	public function replace_node ($old, $new)
	{
		$inserted = $this->insert_after($new, $old);
		$this->remove_node($old);

		return $inserted;
	}

	public function insert_after (\SimpleXMLElement $insert, \SimpleXMLElement $target)
	{
	    $target_dom = dom_import_simplexml($target);
		$insert_dom = dom_import_simplexml($insert);

	    $imported = $target_dom->ownerDocument->importNode($insert_dom, true);

	    if ($target_dom->nextSibling) {
	        return $target_dom->parentNode->insertBefore($imported, $target_dom->nextSibling);
	    } else {
	        return $target_dom->parentNode->appendChild($imported);
	    }
	}

	public function insert_nodes (Array $insert, \SimpleXMLElement $where, \XMLDocument $xml = null)
	{
		if (!$xml) $xml = $this;

		$p = $where;
		foreach ($insert as $n) {
			$p = simplexml_import_dom($xml->insert_after($n, $p));
		}

		return $p;
	}
}