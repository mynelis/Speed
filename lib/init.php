<?php

use \Speed\Security\SessionManager;
use \Speed\Templater\Layout;
use \Speed\ContentManager;
use \Speed\CacheControl;
use \Speed\AppControl;
use \Speed\Util\Ajax;

if (!defined('ROOT')) exit;

$_str = preg_split('/\n/', file_get_contents(ROOT.'config/config.json'));
$str = '';
foreach ($_str as $line) {
	$line = trim($line);
	if (preg_match('%^//.*%', $line)) continue;
	$line = preg_replace('%(\s//|^#).*%', '', $line);
	$str .= $line;
}
$config = json_decode($str);

if (!isset($config->site)) $config->site = (object) null;
$config->site->localdir = basename(dirname(__DIR__));

if (!$config) {
	exit ('Configuration error');
}

require ROOT.'lib/helpers.php';
require ROOT.'lib/loader.php';

// XML files are blocked from direct rendering (via .htaccess) 
// so we need to read it out using PHP, for the cms.js script to use.
// 
// TODO:
// - Encrypt the string passed to cms.js, then decrypt it there
//   before use. This must be a simple but strong custom encryption.
//   
if ('f7/binding.xml' === request('meta.uri')) {
	header('Content-Type: application/xml');
	$xml = file_get_contents(ROOT.'app/f7.xml');
	readfile(ROOT.'app/f7.xml');
	exit;
}

CacheControl::Initialize();
Ajax::Initialize();

new ContentManager(SessionManager::getSession('cms'));

if ('cms' == request('module')) call_request(true);
