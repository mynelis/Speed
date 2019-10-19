<?php

if (!defined('ROOT')) define('ROOT', '');

$debug = config('app.debug', true);
if (null == $debug) $debug = false;
define('DEBUG', $debug);

// GLOBAL ROUTES CONTAINER, DON'T DELETE
$__global_routing = (object) null;
//=================================================================================================================//

// Setup autoload function
spl_autoload_register (function ($class) {
	$base = ('app\\' === substr($class, 0, 4)) ? ROOT : ROOT.'lib/';

	// Classes from the base Speed namespace should be
	// loaded from /system
	if (-1 < strpos($class, 'Speed\\')) {
		$base = ROOT.'system/';
	}

	// Missing classes with no namespace should be assumed to
	// reside in /lib/App
	elseif (!preg_match('/\\\/', $class)) {
		//$base = ROOT.'lib/App/';
		$base = ROOT.'lib/';
	}

	$name = str_replace('\\', '/', $class);
	$namespace = preg_replace('#/(.[^/]+)$#', '', $name);

	// Load any file with same name as the namespace. Maybe it contains
	// some initializer functions of constants.
	$namespaceFile = $base.$namespace.'.php';
	if (is_readable($namespaceFile)) {
		require_once $namespaceFile;
	}

	$classFile = $base.$name.'.php';

	if (is_readable($classFile)) {
		require_once $classFile;
	}
});
//=================================================================================================================//

$errors = [];
set_error_handler(function ($code, $message, $file, $line, $context) {
	global $errors;

	$params = config('app.error_handler.params');
	$report = config('app.error_handler.report');

	$errors[] = (object) [
		'file' => $file,
		'line' => $line,
		'code' => $code,
		'type' => isset($report->$code) ? $report->$code : 'Unknown',
		'message' => $message,
		'context' => $context
	];	

	if (true === DEBUG) {
		$label = null;

		/*if (E_USER_ERROR === $code) $label = 'User Error';
		if (E_USER_WARNING === $code) $label = 'Warning';
		if (E_USER_NOTICE === $code) $label = 'Notice';
		if (E_DEPRECATED === $code) $label = 'Deprecated';*/
		if (!isset($report->$code)) return;

		$label = $report->$code;

		$_error = [];

		if ($params) {
			foreach ($params as $key) {
				if (isset($$key)) $_error[$key] = $$key;
			}
		}
		dump($_error, false, 'echo', $label);
	}
});
//=================================================================================================================//

define('HOST', strtolower(server('server.name')));

//define('BASEDIR', isset($config->site) && isset($config->site->localdir) ? $config->site->localdir.'/' : '');
$basedir = is_scalar(config('site.localdir')) ? config('site.localdir').'/' : '';

$localhost_ips = ['127.0.0.1', '::1'];
$remote_ip = server('remote.addr');
if (is_scalar($remote_ip) && !in_array($remote_ip, $localhost_ips)) {
	$basedir = '';
}
define('BASEDIR', $basedir);

// dump($config, true);
// dump(BASEDIR, true);

define('PROTO', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https' : 'http');

if ('https' === PROTO && 'localhost' !== HOST) {
	// Uses a secure connection (HTTPS) if possible
	ini_set('session.cookie_secure', 1);
}

define('BASEURL', PROTO.'://'.HOST.'/'.BASEDIR);
//=================================================================================================================//

// Set timezone as defined in the config file
if (is_scalar(config('timezone'))) {
	date_default_timezone_set(config('timezone'));
}
//=================================================================================================================//

// Configure database driver and establish connection.
// Use MySql as default database driver if none is specified.
$database_instances = (object) null;

if (config('databases')) {
	$databases = config('databases');

	foreach ($databases as $key => $settings) {
		$k = 'databases.'.$key.'.';

		if (!config($k.'driver', true)) $config->databases->$key->driver = 'MySqli';
		if (!config($k.'host', true)) $config->databases->$key->host = 'localhost';
		if (!config($k.'mode', true)) $config->databases->$key->mode = 'manual';

		$class = '\\Speed\\Database\\'.config($k.'driver', true);
		$dbh = new $class;

		foreach ($config->databases->$key as $p => $value) {
			$dbh->setProperty($p, $value);
		}
		$dbh->key = $key;

		$database_instances->$key = $dbh;

		if ('auto' == $config->databases->$key->mode) {
			$database_instances->$key->connect();
		}
	}
}
//=================================================================================================================//

register_shutdown_function(function () use ($dbh) {
	$dbh->disconnect();
});
//=================================================================================================================//

// Create defined session instances
if (isset($config->sessions)) {
	\Speed\Security\SessionManager::Init();
}
\Speed\Security\SessionSecurity::Initialize();
//=================================================================================================================//