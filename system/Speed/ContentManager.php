<?php

namespace Speed;

use \Speed\Security\InputValidator;
use \Speed\Templater\XMLDocument;
use \Speed\DataLibrary\Binding;
use \Speed\Security\Sanitizer;
use \Speed\DataLibrary\Model;
use \Speed\Templater\Layout;
use \Speed\Util\Uploader;
use \Speed\Database;

class ContentManager
{
	private $snp;
	private $cml;
	private $dbh;
	private $pagesize = 10;
	private $baseurl;
	private $user;
	private $session;
	private static $instance;

	public $defaultlang = "en";
	public $version = "1.0.0";
	public $meta = "";
	//public $defaultaccess;
	public $useraccess;

	const GROUPS_XPATH_LOCATOR = '//binding/group[@id="{gid}"]';
	const GROUP_XPATH_LOCATOR = '/binding/group[@id="{gid}"]';
	const ENTRY_XPATH_LOCATOR = '/binding/group/entry[@id="{__BIND__}"]';

	public function __construct ($session) 
	{
		$this->dbh = Database::getInstance();
		$this->session = session('cms');

		// dump($this->session->get());

		self::$instance = $this;

		$this->snp = new XMLDocument(ROOT.'system/f7/xml/snippets.xml');
		if (!is_file(ROOT.'app/f7.xml')) {
			trigger_error('binding.xml is required but not found');
		}
		$this->cml = new XMLDocument(ROOT.'app/f7.xml');

		if ($this->session->get('user')) {
			$this->_init();
		}
		else {
			$this->_check_token();
		}

		/*$time = new \Speed\Time;
		if ((time() - $this->session->get('session_started')) > $time->parse($this->session->timeout)) {
			$this->Logout();
		}*/

		$this->user = $this->session->get('user');

		if (!$this->user) {
			setcookie('utk', null, null, '/');
		}
	}

	public static function getInstance ()
	{
		return self::$instance;
	}

	public function getXmlContext ()
	{
		return $this->cml;
	}

	private function snippet_node ($xpath, $vars = [])
	{
		$snippet = $this->snp->parse_node($xpath, $vars);
		return str_replace('&amp;', '&', $snippet);
	}

	private function cml_xpath ($xpath)
	{
		return $this->cml->dom->xpath($xpath);
	}

	//private function get_default_access ()












	private function get_xpath_locator ($path, $params, $target = '')
	{
		foreach ($params as $key => $value) {
			$path = str_replace('{'.$key.'}', $value, $path);
		}

		return $path.$target;
	}

	private function get_binding_entry ($bind, $return_node = true)
	{		
		$xpath = $this->get_xpath_locator(self::ENTRY_XPATH_LOCATOR, ['__BIND__' => $bind]);
		return $return_node ? $this->cml_xpath($xpath) : $xpath;
	}

	private function sanitize ($str, $type = '')
	{
		if (!is_string($str) or !is_string($type)) return '';

		$str = strip_tags($str);
		if ('word' == $type) {
			$str = preg_replace('/[^a-zA-Z0-9\.\?\s\/#=-]/', '', $str);
			$type = '';
		}
		return Sanitizer::Sanitize($str, $type);
	}

	private function _parsebinding ($node, $bind) 
	{
		$parsed = (object)array("bind" => $bind);
		$label = $node->xpath("@label");
		$parsed->label = $label ? trim($label[0]) : '';
		$child_table = $node->xpath("@table");
		$addnew = $node->xpath("@addnew");
		$readonly = $node->xpath("@readonly");
		$nodelete = $node->xpath("@nodelete");
		$view = $node->xpath("@viewtable");
		$searchfields = $node->xpath("field[@search='true']/@name");
		//$groupid = $node->xpath("@group");
		$groupid = $node->xpath("../@id");
		$standalone = $node->xpath("@standalone");
		$listview = $node->xpath("@listview");
		$toolbar = $node->xpath("@toolbar");

		$parsed->keyfield = null;
		$parsed->unique = (object)null;
		$parsed->group = null;
		$parsed->readonly = false;
		$parsed->nodelete = false;
		$parsed->addnew = true;

		$parsed->table = trim($child_table[0]);
		$fields = $node->xpath("field");

		if ($groupid) {
			$gid = trim($groupid[0]);
			//$glb = $node->xpath('//binding/group[@id="'.$gid.'"]/@label');
			//$glb = $node->xpath($this->get_xpath_locator(self::GROUPS_XPATH_LOCATOR, ['gid' => $gid], '/@label'));
			$glb = $node->xpath('../@label');
			$parsed->group = (object)array(
				'id' => $gid,
				'label' => trim($glb[0])
			);
		}

		if ($view) $parsed->viewtable = trim($view[0]);
		if ($readonly and "true"==trim($readonly[0])) $parsed->readonly = true;
		if ($nodelete and "true"==trim($nodelete[0])) $parsed->nodelete = true;
		if ($addnew and "false"==trim($addnew[0])) $parsed->addnew = false;
		if ($listview) $parsed->listview = trim($listview[0]);
		if ($toolbar) $parsed->toolbar = explode(',', trim($toolbar[0]));

		$parsed->order = $this->_parse_order($node);
		$parsed->limit = $this->_parse_limit($node);
		$parsed->pagesize = isset($node->pagesize) ? trim($node->pagesize) : $this->pagesize;

		if (isset($node->filter)) {
			$parsed->filter = $this->_parse_filter ($node, $bind);
		}
		else {
			$parsed->filter = array('bind' => $bind);
		}

		if ($searchfields) $parsed->searchfields = $searchfields;

		if ($fields) {
			$parsed->fields = isset($parsed->fields) ? $parsed->fields : (object)null;
			foreach ($fields as $each) {
				$name = $each->xpath("@name");
				$name = trim($name[0]);
				$parsed->fields->$name = $each;
				$unique = $each->xpath("@unique");
				$label = $each->xpath("@label");

				if (!$parsed->keyfield) $parsed->keyfield = $name;

				if ($unique) {
					$is = trim($unique[0]);
					if ("true" == $is) {
						$parsed->unique->$name = trim($label[0]);
					}
					else {
						unset($parsed->unique->$name);
					}
				}
			}
		}
		if (!isset($parsed->fields)) {
			trigger_error("Fields not defined for '".$bind."'");
		}

		return $parsed;
	}

	private function _getdata ($parsed, $get=array()) 
	{
		$order = isset($parsed->order) ? $parsed->order : null;
		$limit = isset($parsed->limit) ? $parsed->limit : null;
		$filter = isset($parsed->filter) ? $parsed->filter : array();

		if (!empty($get)) {
			foreach ($get as $key => $value) {
				$filter[$key] = $value;
			}
		}
		if (isset($parsed->id)) {
			$filter["id"] = $parsed->id;
		}
		if (isset($parsed->query)) {
			$_filter = " where 1 ";
			if ($filter) {
				$_filter = $this->dbh->make_values($filter);
				$_filter = " where ".implode(" and ", $_filter);
			}
			$parsed->query = str_replace("{filter}", $_filter, $parsed->query);
			return $this->dbh->fetch($parsed->query);
		}

		$sf = $this->getSearchFilter($parsed);
		if ($sf) $filter[] = $sf;

		if (isset($parsed->table)) {
			$table = isset($parsed->viewtable) ? $parsed->viewtable : $parsed->table;
			/*$count = $this->dbh->find($table, $filter, null, null, array(
				'*',
				'count(`'.$parsed->keyfield.'`)' => 'total'
			), false);*/

			$flt = $filter ? ' where '.implode(' and ', $this->dbh->make_values($filter)) : '';
			$sql = "
				select count(`".$parsed->keyfield."`) as total
				from ".$table.$flt."
			";
			$count = $this->dbh->fetch($sql);

			$parsed->total = $count[0]->total;
			return $this->dbh->find($table, $filter, $order, $limit);
		}
		return array();
	}

	private function getSearchFilter ($parsed)
	{
		if (!isset($parsed->searchfields) or !isset($parsed->keywords)) return '';

		$searchfields = $parsed->searchfields;
		$keywords = preg_split('/\s/', trim($parsed->keywords));

		$filter = array();
		foreach ($searchfields as $each) {
			$sf = array();
			foreach ($keywords as $kw) {
				$sf[] = "`".trim($each)."` like '%".$kw."%'";
			}
			$filter[] = '('.implode(' and ', $sf).')';
		}
		return '('.implode(' or ', $filter).')';
	}

	public function Published ($bind, $filter=array(), $offset=0, $limit=0, $order="") {
		$node = $this->get_binding_entry($bind);
		if (!$node) return array();

		$parsed = $this->_parsebinding($node[0], $bind);
		if (isset($parsed->filter)) {
			$parsed->filter["published"] = 1;
		}
		else {
			$parsed->filter = array("published" => 1);
		}
		if ($filter) {
			foreach ($filter as $key => $value) {
				$parsed->filter[$key] = $value;
			}
		}
		if ($limit or $offset) {
			$parsed->limit = $limit." offset ".$offset;
		}
		if ($order) {
			$parsed->order = $order;
		}
		return $this->_getdata($parsed);
	}

	public function Fetch ($bind, $filter=array(), $offset=0, $limit=0) {
		$node = $this->get_binding_entry($bind);
		if (!$node) return array();

		$parsed = $this->_parsebinding($node[0], $bind);
		if ($filter) {
			foreach ($filter as $key => $value) {
				$parsed->filter[$key] = $value;
			}
		}

		if ($limit or $offset) {
			$parsed->limit = $limit." offset ".$offset;
		}
		return $this->_getdata($parsed);
	}

	public final function GetBox ($get) {
		$bind = trim($get['bind']);
		$page = (int)$get['page'];

		$node = $this->get_binding_entry($bind);

		if (!$node) {
			return "Undefined node for '".$bind."'";
		}

		$parsed = $this->_parsebinding($node[0], $bind);
		if (!$parsed->limit) {
			$parsed->limit = $parsed->pagesize." offset ".($parsed->pagesize*$page);
		}
		else {
			$parsed->pagesize = 0;
		}

		unset($get['bind'], $get['page'], $get['call'], $get['type']);

		if (!empty($get)) {
			foreach ($get as $key => $value) $parsed->$key = $value;
		}

		$_get = $get;
		if (isset($get['keywords'])) {
			unset($get['keywords']);
			$page = 0;
			$parsed->limit = null;
		}

		$data = $this->_getdata($parsed, $get);

		return $this->_build_list_screen($parsed, $data, $page, $node[0]->xpath("limit/@rows"), $_get);
	}

	private function _paging_links ($parsed, $page, $datalen, $total) {
		$parsed->limit = $parsed->pagesize." offset ".($parsed->pagesize*$page);

		$links = "";
		$pagesize = $parsed->pagesize;
		$next = ($pagesize < $total and $parsed->pagesize <= $datalen) ?
			$this->snippet_node("/snippets/general/paging_next", array(
				"page" => $page+1,
				"bind" => $parsed->bind
			)) : "";

		$previous = (0 < $page) ?
			$this->snippet_node("/snippets/general/paging_previous", array(
				"page" => $page-1,
				"bind" => $parsed->bind
			)) : "";

		if ($next or $previous) {
			$links = $this->snippet_node("/snippets/general/paging", array(
				"next" => $next,
				"previous" => $previous
			));
		}

		return $links;
	}

	private function getDeleteLink ($node, $each, $get)
	{
		if ($node->nodelete) return '';
		return $this->snippet_node("/snippets/general/delete_link", array(
			"id" => $each->id,
			"bind" => $node->bind,
			'vars' => json_encode($get)
		));
	}

	private function _build_list_screen ($node, $data, $page, $limitrows = null, $get = null) 
	{
		$rows = "";
		$headers = "";

		if ($data) {
			foreach ($data as $each) {

				$deletelink = $node->readonly ? '' : $this->getDeleteLink($node, $each, $get);
				// $access = json_decode($this->useraccess)->{$node->bind};
				// if (0 === $access->d) $deletelink = '';
				if (!$this->has_access($node->bind, 'd') || true == $node->readonly) $deletelink = '';

				$rows .= $this->snippet_node("/snippets/general/list_screen_row", array(
					"fields" => $this->_build_list_fields($node, $node->fields, $each, $get),
					"id" => $each->id,
					"bind" => $node->bind,
					'deletelink' => $deletelink,
					'vars' => json_encode($get)
				));
			}
			$headers = $this->_build_list_headers($node);
		}

		$paging = '';
		if ($node->pagesize and $node->total > $node->pagesize) {
			$paging = $this->_paging_links($node, $page, sizeof($data), $node->total);
		}

		$snippet = 'buttonfoot';

		if ($limitrows or !$node->addnew) {
	        if (isset($limitrows[0]) and sizeof($data) >= (int)$limitrows[0]) $snippet = 'pagingonly';
		}

		if ($node->readonly || !$this->has_access($node->bind, 'c')) {
		    $snippet = 'pagingonly';
		}

		$searchform = '';
		//if (isset($node->searchfields) and $node->total > $node->pagesize) {
		if (isset($node->searchfields)) {// and $node->total > $node->pagesize) {
			$searchform = $this->snippet_node('/snippets/general/searchform', array(
				'bind' => $node->bind,
				'vars' => json_encode($get),
				'page' => $page,
				'keywords' => isset($get['keywords']) ? $get['keywords'] : ''
			));
		}

		//print_r($get);

		$foot = $this->snippet_node('/snippets/general/'.$snippet, array(
			'paging' => $paging,
			'bind' => $node->bind,
			'vars' => json_encode($get)
		));

		$label = $node->label;
		//if ($node->group) $label = $node->group->label.' &raquo; '.$label;
		$group = ($node->group) ? $node->group->label : '--';

		$label = $this->snippet_node('/snippets/general/breadcrumb', array(
			'group' => $group,
			'label' => $label,
			'bind' => $node->bind
		));

		$listview = 'listview';
		if (isset($node->listview)) $listview = 'custom_listviews/'.$node->listview;
		if (isset($node->toolbar)) $listview = 'custom_listviews/toolbar-view';

		return $this->snippet_node("/snippets/general/".$listview, array(
			'bind' => $node->bind,
			'toolbar' => $this->_build_toolbars($node),
			'searchform' => $searchform,
			'body' => $rows,
			'head' => $headers,
			'foot' => $foot,
			'label' => $label
		));
	}

	private function _build_toolbars ($node) {
		if (!isset($node->toolbar) || !$node->toolbar) return '';

		$toolbar = [];
		foreach ($node->toolbar as $tool) {
			$toolbar[] = $this->snippet_node('/snippets/general/toolbar/tool', [
				'bind' => $node->bind,
				'name' => $tool
			]);
		}

		return $this->snippet_node('/snippets/general/toolbar/toolbox', [
			'toolbar' => implode('', $toolbar)
		]);

		return implode('', $toolbar);
	}

	private function _build_list_headers ($node) {
		$html = '';
		$bind = $node->bind;
		$fields = $node->fields;

		foreach ($fields as $key => $xml) {
			$list = $xml->xpath("@list");
			if ($list and "false" == trim($list[0])) continue;

			$name = $xml->xpath("@name");
			$label = $xml->xpath("@label");
			$label = isset($label[0]) ? trim($label[0]) : '';
			$sort = $xml->xpath("@sort");

			if (!$label && $name) $label = ucfirst(trim($name[0]));

			$snippet = "field_header_sort";
			if ($sort and "true" != trim($sort[0])) {
				$snippet = "field_header_nosort";
			}

			$html .= $this->snippet_node("/snippets/general/".$snippet, array(
				"name" => trim($name[0]),
				"label" => $label
			));
		}

		// $access = json_decode($this->useraccess)->$bind;
		// if (0 === $access->d) $deletelink = '';
		if (false == $node->nodelete && $this->has_access($bind, 'd') && true != $node->readonly) {
			$html .= $this->snippet_node("/snippets/general/field_header_nosort", [
				'label' => 'DEL'
			]);
		}

		return $html;
	}

	private function _build_list_fields ($node, $fields, $each, $get = null) 
	{
		$html = "";
		$bind = $node->bind;

		foreach ($fields as $key => $xml) {
			$list = $xml->xpath("@list");
			//if ($list and "true" == trim($list[0])) {
			if ($list and "false" == trim($list[0])) continue;
			//{
				$name = $xml->xpath("@name");
				$name = trim($name[0]);
				$link = $xml->xpath("@link");
				$value = $each->$name;
				$value = ($value or "0"==$value) ? (string)$value : "&nbsp;";
				$ref = $this->_parse_reftable($xml, $each, $name);
				if ($ref) {
					$lb = $xml->xpath("ref/select/@show");
					$lb = trim($lb[0]);
					$value = $ref[0]->$lb;
				}
				$islist = $xml->xpath("list");
				if ($islist) {
				   $show = $xml->xpath("list/@show");
				   if (isset($show[0]) and "name" == trim($show[0])) {
				       $v = $xml->xpath("list/value[@name='".$each->$name."']/@name");
				       $value = trim($v[0]);
				   }
				   else {
				       $v = $xml->xpath("list/value[@name='".$each->$name."']");
				       $value = isset($v[0]) ? trim($v[0]) : "";
				   }
				}

				$id = $each->id;
				$lnk = $link ? trim($link[0]) : '';
				// $access = json_decode($this->useraccess)->{$node->bind};
				$can_read = $this->has_access($node->bind, 'r');

				$snippet = ($link && $can_read) ? 'field_link' : 'field_normal';

				if ($lnk and 'true' !== $lnk) {
					$bind = $lnk;
					$id = $each->$name;
					if (!$id) $snippet = 'field_normal';
				}
				$html .= $this->snippet_node("/snippets/general/".$snippet, array(
					"id" => $id,
					'name' => $name,
					"bind" => $bind,
					"value" => stripslashes($value),
					'vars' => json_encode($get)
				));
			//}
		}
		return $html;
	}

	private function has_access ($bind, $mode)
	{
		// return true;
		$access = $this->useraccess->$bind;
		return 1 === $access->$mode;
	}

	public function Delete ($get) {
		$bind = trim($get['bind']);
		$id = (int)($get['id']);
		unset($get['bind'], $get['id'], $get['call'], $get['type']);

		$node = $this->get_binding_entry($bind);

		if (!$node) {
			return "Undefined node for '".$bind."'";
		}

		$parsed = $this->_parsebinding($node[0], $bind);

		$this->_delete_ref($parsed, $id);
		$this->fireTrigger('before delete', $parsed->table, $id);
		if ($this->dbh->delete($parsed->table, array("id" => $id))) {
			$this->fireTrigger('after delete', $parsed->table);
			return true;
		}
		return "Unknown error";
	}

	private function _delete_ref ($node) {
        $data = $this->_getdata($node);

        //$linked = array_merge($linked, $linked2);
        $linked = $this->cml_xpath("//ref[@table='".$node->table."']");
        $query = null;

        if ($linked) {
            foreach ($linked as $each) {
                $link = $each->xpath("@link");
                $link = trim($link[0]);
                $entry_table = $each->xpath("../../../@table");
                if (!$entry_table) $entry_table = $each->xpath("../../../@name");
                $entry_table = trim($entry_table[0]);

                $entry_field = $each->xpath("../@name");
                $entry_field = trim($entry_field[0]);
                $value = $data[0]->$link;

                $query[$entry_table."-".$entry_field] = "update $entry_table set `$entry_field`=default where `$entry_field`='$value'";
            }
            if ($query) {
                foreach ($query as $each) {
                    $this->dbh->execute($each);
                }
            }
        }
	}

	private function _unique_values ($get, $parsed, $id) {
		if (!isset($parsed->unique) or !isset($parsed->table)) return false;

		$un = array();
		foreach ($parsed->unique as $key => $value) {
			if (isset($get[$key]) and $get[$key]) {
				$un[$key] = " `".$key."`='".addslashes($get[$key])."' ";
			}
		}
		if ($un) {
			$un = implode(" or ", $un);
			$sql = "select id from `".$parsed->table."` where id <> '".$id."' and bind='".$parsed->bind."' and (".$un.")";
			$data = $this->dbh->fetch($sql);

			if ($data) {
				return implode(", ", array_values((array)$parsed->unique));
			}
		}
		return false;
	}

	private function encodeHTMLCharacters ($request)
	{
		foreach ($request as $key => $value) {
			$request[$key] = htmlentities($value);
		}
		return $request;
	}

	private function decodeHTMLCharacters ($request)
	{
		foreach ($request as $key => $value) {
			$request[$key] = html_entity_decode($value);
		}
		return $request;
	}

	private function prepareHtmlFieldValues ($bind, $table, $request)
	{
		foreach ($request as $key => $value) {
			$node = $this->cml_xpath("/binding/group/entry[@id='".$bind."']/field[@name='".$key."'][@type='html' or @type='expandable']");
			if ($node) {
				$value = html_entity_decode($value);
				$request[$key] = Sanitizer::Unscript($value);
			}
		}
		return $request;
	}

	//public function SaveContent ($get)
	public function SaveContent ()
	{
		$get = (array) post();//$this->encodeHTMLCharacters($get);

		$bind = trim($get['bind']);
		$id = (int)($get['id']);
		unset($get['btn_submit'], $get['id'], $get['call'], $get['type']);

		$node = $this->get_binding_entry($bind);

		if (!$node) {
			return "Undefined node for '".$bind."'";
		}

		$parsed = $this->_parsebinding($node[0], $bind);
		$get = $this->prepareHtmlFieldValues($bind, $parsed->table, $get);

		$duplication = $this->_unique_values($get, $parsed, $id);

		// dump(post());
		// dump($id);

		//$user = $this->session->get('user');
		//dump($user);

		//$cryptor = new \Speed\Security\Cryptor($user->id.$user->username);
		//post('cms_access', Binding::encrypt_column($bind, 'cms_access', post('cms_access')));
		//dump(post());
		Binding::encrypt_columns($bind, (object) $get, function ($k, $e) {
			post($k, $e);
		});

		// dump($id);
		// dump($get);
		// dump(post(), true);

		if ($duplication) {
			return "Field duplication error: '".$duplication."'";
		}
		elseif (0 == $id) {
			$this->fireTrigger('before insert', $parsed->table);

			$new = Model::create($parsed->table)->from_post();
			if ($new->validation_errors) {
				return $new->validation_errors;
			}
			else if ($new->save()) {
				$this->fireTrigger('after insert', $parsed->table);
			}

			/*if ($this->dbh->insert($parsed->table, $get)) {
				$this->fireTrigger('after insert', $parsed->table);
				return true;
			}*/
		}
		elseif (0 < $id) {
			$this->fireTrigger('before update', $parsed->table);
			
			$update = Model::create($parsed->table, $id)->from_post();
			if ($update->validation_errors) {
				return $update->validation_errors;
			}
			else if ($update->save()) {
				$this->fireTrigger('after update', $parsed->table);
			}

			/*$this->fireTrigger('before update', $parsed->table, $id);
			if ($this->dbh->update($parsed->table, $get, array("id" => $id))) {
				$this->fireTrigger('after update', $parsed->table, $id);
				return true;
			}*/
		}

		return true;
	}

	//public final function GetContent ($get)
	public final function GetContent ()
	{
		$get = (array) get();//$this->encodeHTMLCharacters($get);
		$bind = trim($get['bind']);
		$id = (int)($get['id']);

		$node = $this->get_binding_entry($bind);

		if (!$node) {
			return "Undefined node for '".$bind."'";
		}

		$parsed = $this->_parsebinding($node[0], $bind);
		$parsed->id = $id;

		$data = $this->_getdata($parsed);

		unset($get['call'], $get['type'], $get['bind'], $get['id']);
		return $this->_build_edit_screen($parsed, $data, $get);
	}

	private function _build_edit_screen ($node, $data, $get=null) {
		$rows = "";
		$data = $data ? $data[0] : (object)array('id' => 0);
		$_hidden = $get;
		$_get = $get;

		foreach ($node->fields as $each) {
			$edit = $each->xpath('@edit');
			if ($edit and 'false' == trim($edit[0])) continue;
			//{
			$name = $each->xpath('@name');
			$name = trim($name[0]);
			$label = $each->xpath('@label');
			$label = isset($label[0]) ? trim($label[0]) : '';
			$default = $each->xpath('@default');
			$type = $each->xpath('@type');
			$type = isset($type[0]) ? trim($type[0]) : 'text';

			if ($type and 'reflink' == trim($type[0])) unset($data->$name);

			$value = isset($data->$name) ? $data->$name : '';

			if (!$value and $default) {
				$default = trim($default[0]);
				if ('$' == substr($default,0,1)) {
					$var = substr($default, 1);
					if (isset($this->$var)) $default = $this->$var;
				}
				if ('$user' == substr($default,0,5)) {
					$var = substr($default, 6);
					if (isset($this->user->$var)) $default = $this->user->$var;
				}
				$data->$name = $default;
			}

			if ($type and 'hidden' == trim($type[0])) {
				$_hidden[$name] = $data->$name;
				continue;
			}

			if (!$label && $name) $label = ucfirst($name);

			$rows .= $this->snippet_node('/snippets/general/edit_screen_row', array(
				'label' => $label,
				'field' => $this->_build_edit_field($node->bind, $each, $data)
			));

			if ($type and 'html' == trim($type[0])) {
				$tp = trim($type[0]);
				if (isset($_get[$tp])) unset($_get[$tp]);
			}
			//}
		}

		$hidden = '';
		if ($_hidden) {
			foreach ($_hidden as $key => $value) {
				$hidden .= $this->snippet_node('/snippets/forms/hidden', array(
					'name' => $key,
					'value' => $value
				));
			}
		}

		$label = $node->label;
		//if ($node->group) $label = $node->group->label.' > '.$label;
		$group = ($node->group) ? $node->group->label : '--';

		$label = $this->snippet_node('/snippets/general/breadcrumb', array(
			'group' => $group,
			'label' => $label,
			'bind' => $node->bind
		));

		$submitbutton = ($node->readonly || !$this->has_access($node->bind, 'u')) ? '' : $this->snippet_node('/snippets/forms/submitbutton');

		return $this->snippet_node('/snippets/forms/edit', array(
			'body' => $rows,
			'label' => $label,
			'bind' => $node->bind,
			'id' => $data->id,
			'vars' => json_encode($get),
			'hiddenfields' => $hidden,
			'submitbutton' => $submitbutton,
			'backbutton' => $submitbutton ? 'Cancel' : 'Back',
			'buttonclass' => $node->readonly ? '' : 'yellow',
			'disabled' => !$this->has_access($node->bind, 'u') ? 'true' : 'false'
		));
	}

	private function _build_edit_field ($bind, $node, $data) {
		$type = $node->xpath('@type');
		$list_type = $node->xpath('list');
		$ref_type = $node->xpath('ref');
		if (!$type and !$list_type and !$ref_type) $type = ['text']; // We intentionally wrapped this in an array so that trim($type[0]) does not throw offset error 

		$name = $node->xpath('@name');
		$label = $node->xpath('@label');
		$format = $node->xpath('@format');
		$default = $node->xpath('@default');

		$label = isset($label[0]) ? trim($label[0]) : '';
		$format = isset($format[0]) ? trim($format[0]) : "";

		if ($type) {
			$type = trim($type[0]);
		// 	if (-1 < strpos($type, '/')) {
		// 		$type = $node->xpath($type);
		// print_r($type);
		// 		$type = $type ? trim($type[0]) : 'text';
		// 	}
		}


		if ($list_type) $type = 'list';
		if (!$type and $ref_type) $type = 'ref';

		$name = trim($name[0]);


		$data->$name = Binding::decrypt_column($bind, $name, $data->$name);
		// dump($name.' >> '.$data->$name);


		$value = isset($data->$name) ? $data->$name : '';
		if (!$value and $default) {
			$value = trim($default[0]);
		}

		if (!$label && $name) $label = ucfirst($name);

		$vars = null;
		$method = '_editvars_'.$type;
		if ($node->xpath("ref")) $vars = $this->_parse_reftable($node, $data);
		if ($list_type) $vars = $this->_parse_list_type($list_type[0], $data);
		if (method_exists($this, $method)) $vars = $this->$method();

		$refbind = '';
		$refbind_node = $node->xpath("ref/filter[@key='bind']/@value");
		if ($refbind_node) {
			$refbind = trim($refbind_node[0]);
		}

		/*$reflink = '';
		$reflink_node = $node->xpath("ref/filter[@key='bind']/@value");
		if ($refbind_node) {
			$reflink = trim($reflink_node[0]);
		}*/

		/*$_data = (object)array();
		foreach ($data as $key => $val) {
			if ('html' != $key) {
				$_data->$key = $val;
				//if (isset($each->$type)) unset($_data[$key]->$type);
			}
		}*/
		$_data = $data;
		if ('html' == $type) {
			unset($_data->$name);
		}

		if ('cms_access' == $type) {
			$binding = new Binding($bind);
			return $binding->cms_access_table($bind, $node, $data);
		}

		if ('app_access' == $type) {
			$binding = new Binding($bind);
			return $binding->app_access_table($bind, $node, $data);
		}

		return $this->snippet_node('/snippets/types/'.$type, array(
			'name' => $name,
			'label' => $label,
			'value' => stripslashes($value),
			'default' => $default,
			'format' => $format,
			'vars' => json_encode($vars),
			'baseurl' => BASEURL,
			'data' => json_encode($_data),
			'refbind' => $refbind
		));
	}

	private function _parse_list_type ($node, $data) {
		$return = array();
		$values = $node->xpath("value");
		if ($data) {
			foreach ($values as $each) {
				$name = $each->xpath("@name");
				$type = $each->xpath("@type");
				if (!$name) continue;
				$text = $each;
				$name = trim($name[0]);
				$return[] = (object)array("name" => $name, "text" => trim($each));
			}
		}
		return $return;
	}

	private function _parse_order ($node) {
		$order = $node->xpath("order");
		if ($order) {
			$ord = array();
			foreach ($order as $each) {
				$key = $each->xpath("@key");
				$dir = $each->xpath("@direction");
				if ($key) {
				   $ord[] = trim($key[0])." ".trim($dir[0]);
				}
				else {
				   $ord[] = trim($dir[0]);
				}
			}
			return implode(",", $ord);
		}
		return null;
	}

	private function _parse_limit ($node) {
		$limit = $node->xpath("limit");
		if ($limit) {
			$rows = $limit[0]->xpath("@rows");
			$offset = $limit[0]->xpath("@offset");
			$rows = $rows ? (int)($rows[0]) : 0;
			$offset = $offset ? (int)($offset[0]) : 0;
			return $rows." offset ".$offset;
		}
		return null;
	}

	private function _parse_filter ($node, $bind="") {
		$filter = $node->xpath("filter");
		$_filter = $bind ? array("bind" => $bind) : array();
		foreach ($filter as $each) {
			$key = $each->xpath("@key");
			$key = $key ? trim($key[0]) : null;
			$value = $each->xpath("@value");
			$value = $value ? trim($value[0]) : null;

			if ('$' == substr($value,0,1)) {
				$var = substr($value, 1);
				if (isset($this->$var)) {
					$value = $this->$var;
				}
			}
			if ('$user' == substr($value,0,5)) {
				$var = substr($value, 6);
				if (isset($this->user->$var)) {
					$value = $this->user->$var;
				}
			}
			if ($key and $value) {
				$_filter[$key] = trim($value);
			}
			elseif (!$key and $value) {
				$_filter[] = $value;
			}
			if ("bind" == $key and !$value) {
				unset($_filter["bind"]);
			}
		}
		return $_filter;
	}

	private function _parse_reftable ($node, $data, $name=null) {
		$ref = $node->xpath("ref");
		if (!$ref) return null;

		$table = $ref[0]->xpath("@table");
		$table = trim($table[0]);
		$link = $ref[0]->xpath("@link");
		$link = trim($link[0]);

		$fil = $this->_parse_filter($ref[0]);
		$ord = $this->_parse_order($ref[0]);
		$lmt = $this->_parse_limit($ref[0]);
		$fields = $ref[0]->xpath("select/field");

		$fld = array();
		$quote = true;
		foreach ($fields as $v) {
            $n = $v->xpath("@name");
            $n = trim($n[0]);
            if (preg_match('/\s/', $n)) {
            	$quote = false;
            }
            $alias = $v->xpath("@alias");
            if ($alias) {
                $fld[$n] = trim($alias[0]);
            }
            else {
			    $fld[] = $n;
			}
		}
		if ($name) $fil[$link] = $data->$name;
		return $this->dbh->find($table, $fil, $ord, $lmt, $fld, $quote);
	}

	private function GetPhotos () {
		return $this->dbh->find("photo", array(), "id desc", 20);
	}
	private function GetFiles () {
		return $this->dbh->find("file", array(), "id desc", 20);
	}

	public final function GetCMSUser ($get=array())
	{
		$user = $this->session->get('user');
		if ($user) return $user;
		return null;
	}

	public final function Login ($get)
	{
		if ($get['username'] and $get['password']) {

			$username = trim($get['username']);
			$password = md5($get['password']);

			$user = Model::create('sys_access sac')
				->select_none()

				// ->left_join_sys_users('usr', 'sac.user_id', 'usr.id')
				->left_join_sys_users('usr', 'id', 'sac.user_id')
					->select('id, username, fullname, email')
					->username_is($username)
					->password_is($password)
					->active_is(1)

				// ->left_join_sys_groups('sgr', 'sac.group_id', 'sgr.id')
				->left_join_sys_groups('sgr', 'id', 'sac.group_id')
					->select('bind, cms_access, app_access')

				->get();

			if ($user) {
				$access = (object)[
					'cms_access' => (object)[],
					'app_access' => (object)[]
				];

				foreach ($user as $each) {
					$binding = $each->bind;
					$u = Binding::decrypt_columns($binding, $each);

					$dec_cms = json_decode($u->cms_access);
					foreach ($dec_cms as $b => $crud) {
						if (!isset($access->cms_access->$b)) $access->cms_access->$b = $crud;
						foreach ($crud as $k => $v) {
							if (0 == $access->cms_access->$b->$k) {
								$access->cms_access->$b->$k = $v;
							}
						}
					}

					$dec_app = json_decode($u->app_access);
					foreach ($dec_app as $b => $crud) {
						if (!isset($access->app_access->$b)) $access->app_access->$b = $crud;
						foreach ($crud as $k => $v) {
							if (0 == $access->app_access->$b->$k) {
								$access->app_access->$b->$k = $v;
							}
						}
					}
				}

				$user = $user[0];
				$user->cms_access = $access->cms_access;
				$user->app_access = $access->app_access;

				// $this->session->set('user', $user);
				// $this->session->set('session_started', time());

				$this->session->set('user', $user);
				$this->session->set('start_time', time());

				return true;
			}
		}

		return 'Login failed';
	}

	public function ChangePassword ($get)
	{
		if (!$get['cpass'] or !$get['pass1'] or !isset($get['pass2'])) {
			return "Please complete the form";
		}
		$cpass = trim($get['cpass']);
		$pass = trim($get['pass1']);

		$user = $this->session->get('user');

		if (md5($cpass) !== $user->password) {
			return "That is not your current password";
		}
		if ($pass !== trim($get['pass2'])) {
			return "The new passwords do not match";
		}
		if ($this->dbh->update("users", array(
				"password" => md5($pass)
			), array(
				"username" => $user->username,
				"password" => md5($cpass)
			))) {
			return true;
		}
		return "Unnknown error";
	}

	public final function Logout ()
	{
		// $this->session->delete('user');
		$this->session->end();
		setcookie('utk', null, null, '/');
		return true;
	}

	public final function GetForm ($get) {
		return $this->snippet_node("/snippets/forms/".trim($get['form']));
	}

	private final function _check_token ()
	{
		if (!isset($_GET['cms_login']) or !isset($_GET['t'])) {
			return false;
		}

		$this->session->delete('user');
		$token = md5("cms_session_".date("YmidHim"));

		if (isset($_GET['token']) and is_string($_GET['t'])) {
			$login_token = trim($_GET['t']);
			if ($token !== $login_token) {
				echo "Your login token has expired";
				exit;
			}
		}
	}

	private final function _init ()
	{
		$user = $this->session->get('user');
		$this->useraccess = $user->cms_access;

		$utk = md5($user->id.$user->username.$this->useraccess);
		setcookie('utk', $utk, null, '/');
	}

	private final function GetAccess ($get)
	{
		$user = $this->session->get('user');
		$utk = $get['utk'];

		if (!$user or $utk !== md5($user->id.$user->username.$this->useraccess)) {
			return null;
		}

		return [
			//'defaultaccess' => $this->defaultaccess,
			'useraccess' => $this->useraccess,
			'baseurl' => BASEURL
		];
	}

	public function Upload ($source, $type, $post) {

		$_types = array('.jpg', '.jpeg', '.gif', '.png', '.bmp', '.xbm', '.webp');
		if ('file' == $type) {
			$_types = array('.jpg', '.jpeg', '.gif', '.png', '.bmp', '.xbm', '.webp', '.doc', '.docx', '.pdf');
		}
		$_sizelimit = 5000 * 1000; // 2Mb
		$size = $_FILES[$source]['size'];
		$ext = strtolower(strrchr($_FILES[$source]['name'], '.'));

		if (!in_array($ext, $_types)) {
			return '<script type="text/javascript">top.CMS.Uploaded("'.$source.'","Files with '.$ext.' extension are not allowed");</script>';
		}

		if ($size > $_sizelimit) {
			return '<script type="text/javascript">top.CMS.Uploaded("'.$source.'","Image cannot be larger than '.($_sizelimit/1000).'kb");</script>';
		}

		$oldimage = trim($post[$source."_original"]);
		$dir = ("image"==$type) ? "assets/images/" : "assets/files/";

		$uploader = new Uploader();

		if ("image" == $type and isset($post[$source."_image_width"])) {
			$width = (int)$post[$source."_image_width"];
			$height = (int)$post[$source."_image_height"];
			$adjust = trim($post[$source."_image_adjust"]);
			$compression = (int)$post[$source."_image_compression"];
			if ($width or $height) {
				$uploader->crop = true;
				$uploader->auto_adjust = $adjust;
				$uploader->compression = $compression;
			}
			if ($width) $uploader->width = $width;
			if ($height) $uploader->height = $height;
		}

		$uploader->savedir = ROOT.$dir;
		$uploader->source = $source;
		$uploader->upload();

		$file = $uploader->filename;
		if ($uploader->uploaded) {
			if (is_file($uploader->savedir.$oldimage) and $oldimage != $file) {
				unlink($uploader->savedir.$oldimage);
			}
			return '<script type="text/javascript">top.CMS.Uploaded("'.$source.'","'.$file.'","'.$type.'");</script>';
		}
	}

	public function InitCall () 
	{

		$user = $this->session->get('user');

		$call = isset($_GET['call']) ? trim($_GET['call']) : false;
		$call = isset($_POST['call']) ? trim($_POST['call']) : $call;

		$type = isset($_GET['type']) ? trim($_GET['type']) : false;
		$type = isset($_POST['type']) ? trim($_POST['type']) : $type;

		$getLoginForm = ('GetForm' === $call and isset($_GET['form']) and 'login' === $_GET['form']);

		$request = isset($_GET['call']) ? $_GET : false;
		$request = isset($_POST['call']) ? $_POST : $request;

		if ($getLoginForm) {
			header("Content-Type: text/html");
			echo $this->GetForm($request);
			//$this->dbh->disconnect();
			exit;
		}

		if (!$user and 'Login' === $call) {
			$this->sendHttpRespnse('Login', $_POST, $type);
		}

		if ($user and 'ChangePassword' === $call) {
			$this->sendHttpRespnse('ChangePassword', $_POST, $type);
		}

		if ($user) {
			if (isset($_POST['imagekey'])) {
				$imagekey = trim($_POST['imagekey']);
				echo $this->Upload($imagekey, "image", $_POST);
				exit;
			}
			if (isset($_POST['filekey'])) {
				$filekey = trim($_POST['filekey']);
				echo $this->Upload($filekey, "file", $_POST);
				exit;
			}

			if ($call) {
				$this->sendHttpRespnse($call, $request, $type);
			}
		}
		elseif (!$getLoginForm) {
			if (!get('t')) {
				$token = md5("cms_session_".date("YmidHim"));
				//dump($token);
				header("location:?cms_login&t=".$token);
				exit;
			}
		}
	}

	private function sendHttpRespnse ($method, $args, $type)
	{
		if (method_exists($this, $method)) {
			$data = $this->$method($args);

			if ("json"==$type) {
				header("Content-Type: application/json");
				echo json_encode($data);
			}
			elseif ("xml"==$type) {
				header("Content-Type: text/xml");
				echo $data;
			}
			else {
				echo $data;
			}
		}
		$this->dbh->disconnect();
		exit;
	}

	/*private function getMenuGroups ($node)
	{
		$groups = $node->xpath('/binding/group');
	}*/

	/*private function getGroupEntries ($node, $groupid)
	{
		$entries = $node->xpath('/binding/entry[@group="'.$groupid.'"]');
	}*/

	private function fireTrigger ($et, $table, $id = 0)
	{
		$t = explode(' ', $et);
		$timing = $t[0];
		$event = $t[1];

		$this->runCallback($et, $table, $id);

		$xpath = '/binding/group/entry[@table="'.$table.'"]/trigger[@event="'.$event.'"][@timing="'.$timing.'"]/text()';
		$trigger = $this->cml_xpath($xpath);

		if ($trigger) {
			$vars = (object)array(
				'date' => date('Y-m-d'),
				'datetime' => date('Y-m-d H:i:s'),
				'timestamp' => time()
			);
			$sql = $this->parseTriggerVariables(trim($trigger[0]), '$', $vars);

			if (!$id) {
				$idrs = $this->dbh->find($table, array(), 'lid desc', '1 offset 0', array('last_insert_id()' => 'lid'), false);
				if ($idrs) $id = $idrs[0]->lid;
			}

			if ($id) {
				$rs = $this->dbh->find($table, array('id' => $id));
				if ($rs) {
					$sql = $this->parseTriggerVariables($sql, '@', $rs[0]);
					$this->dbh->handle->query($sql);
				}
			}
		}
	}

	private function runCallback ($et, $table, $id = 0)
	{
		$t = explode(' ', $et);
		$timing = $t[0];
		$event = $t[1];

		$xpath = '/binding/group/entry[@table="'.$table.'"]/callback[@event="'.$event.'"][@timing="'.$timing.'"]';
		$callback = $this->cml_xpath($xpath);

		if ($callback) {
			$callback = $callback[0];

			$vars = (object)array(
				'date' => date('Y-m-d'),
				'datetime' => date('Y-m-d H:i:s'),
				'timestamp' => time()
			);
			$call = $callback->xpath('@call');
			$call = explode('.', trim($call[0]));

			$class = trim($call[0]);
			$method = trim($call[1]);

			if (!$id) {
				$idrs = $this->dbh->find($table, array(), 'lid desc', '1 offset 0', array('last_insert_id()' => 'lid'), false);
				if ($idrs) $id = $idrs[0]->lid;
			}

			if ($id) {
				$rs = $this->dbh->find($table, array('id' => $id));
				if ($rs and class_exists($class) and method_exists($class, $method)) {
					call_user_func_array(array(new $class, $method), $rs);
				}
			}
		}
	}

	private function parseTriggerVariables ($sql, $symbol, $vars)
	{
		foreach ($vars as $key => $value) {
			$sql = str_replace($symbol.$key, $value, $sql);
		}
		return $sql;
	}
}

?>