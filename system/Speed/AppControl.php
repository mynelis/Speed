<?php

namespace Speed;

use \Speed\Templater\Layout;

class AppControl
{
	protected $access;
	protected $skip_session_check = [];

	public static $notice;
	public static $view;

	const NOTICE_ERROR = 'error';
	const NOTICE_WARNING = 'error';
	const NOTICE_ALERT = 'alert';
	const NOTICE_ACCESS_DENIED = 'access_denied';
	const NOTICE_SESSION_EXPIRED = 'session_expired';
	const NOTICE_SUCCESS = 'success';

	public function __construct ()
	{
	}

    public static function logged_in ()
    {
        return (false != cookie('u'));
    }

    public static function valid_session ()
    {
        return hash('sha256', md5(session('app.user.id'))) === server('x-u');
    }

    public function __call ($method, $args)
    {
    	// In case a remote_call_prefix is given, we need to check if that method
    	// is allowed to be called even when session has expired or is not set.
    	// This is essential in protecting certain functions like User login,
    	// registration, and other checks that do not require valid sessions.
    	// 
    	// The AJAX module uses the remote_call_prefix when calling object methods.
    	// It calls only methods with this prefix, thereby protecting all other
    	// public methods from external calls (outside the controller scope).
    	// 
    	// If the remote_call_prefix is set to an empty string, all public class
    	// methods maybe be successfully called, directly, from AJAX.
        $m = str_replace(config('app.remote_call_prefix', true), '', $method);
        if (!in_array($m, $this->skip_session_check) && session_expired()) {
        	return fail_rc('Session expired');
        }
        
        return call_user_func_array([$this, $m], $args);
    }

	public static function check_access ()
	{
		$user = session('app.user');

		if (!$user) {
			self::set_notice(self::NOTICE_SESSION_EXPIRED, '', false);
		}

		$access = json_decode($user->app_access);
		$path = strtolower(request('module').'.'.request('class').'.'.request('method'));

		$privs = isset($access->$path) ? $access->$path : null;
		
		if ($user && !$privs) {
			self::set_notice(self::NOTICE_ACCESS_DENIED, $path);
		}
	}

	public static function set_notice ($type, $message = '', $redirect = true)
	{
		self::$notice = [(object) [
			'type' => $type,
			'message' => $message
		]];
		
		if ($redirect) self::$view = find_view($type, '', 'system_notice');
	}

	protected function notify ($message, $type = 'none')
	{
		self::set_notice($type, $message, false);
	}

	protected function set_title (string $title)
	{
		Layout::set_meta('title', $title);
		return $this;
	}

	protected function set_data ($identity, $data)
	{
		Layout::register($identity, $data);
		return $this;
	}

	public static function WriteClassPaths ()
	{
		$parsed = self::get_files(ROOT.'app/control', [], GLOB_ONLYDIR);
		$mapping = self::create_mapping($parsed);

		$file = ROOT.'app/cache/ControllerMapping.php';
		$write = '<?php

namespace app\cache;

class ControllerMapping
{
	const APP_ACCESS_MAPPING = \''.base64_encode(serialize($mapping)).'\';
}';

		if (!is_readable($file)) {
			$fp = fopen($file, 'w+');
			fwrite($fp, $write);
			fclose($fp);
		}
	}

	public static function get_access_mapping ()
	{
		return unserialize(base64_decode(\app\cache\ControllerMapping::APP_ACCESS_MAPPING));
	}

	private static function create_mapping ($parsed)
	{
		$paths = [];

		foreach ($parsed as $each) {
			foreach ($each as $k => $v) {
				if (!isset($paths[$k])) $paths[$k] = [];

				$values = array_merge($paths[$k], array_values($v)[0]);
				ksort($values);

				$paths[$k] = $values;
			}
		}

		ksort($paths);

		return (object) $paths;
	}

	private static function get_files ($path, $files = [], $flag = 0)
	{
		$glob = glob($path.'/*', $flag);

		if ($glob) {
			foreach ($glob as $each) {
				if (is_dir($each)) {
					$files = self::get_files($each, $files);
				}
				elseif (is_readable($each) && stat($each) && 0 < stat($each)['size']) {
					$parsed = self::parse_file($each);
					if ($parsed) $files[] = $parsed;
				}
			}
		}

		return $files;
	}

	private static function parse_file ($file)
	{
		$content = file_get_contents($file);
		$parts = [];

		$parts = explode('/', $file);

		array_pop($parts);
		$namespace = implode('/', $parts);
		$module = str_replace('app/control/', '', $namespace);

		$class_match = [];
		preg_match('/class\s(\w+).*/', $content, $class_match);

		if (!isset($class_match[1])) return;
		if (preg_match('/.+__$|^__.+|^rc__.+/', $class_match[1])) return;

		$class = $class_match[1];

		$matches = [];
		$paths = [];

		preg_match_all('/public function (\w+)/ism', $content, $matches, PREG_SET_ORDER);

		foreach ($matches as $set) {
			if (!isset($paths[$module])) $paths[$module] = [];
			if (!isset($paths[$module][$class])) $paths[$module][$class] = [];

			$rc_prefix = config('app.remote_call_prefix', true);

			if (!preg_match('/.+__$|^__.+|^'.$rc_prefix.'.+/', $set[1])) {
				$key = strtolower($class.'.'.$set[1]);
				$val = ucfirst($class).' > '.ucwords(preg_replace('/_/', ' ', $set[1]));

				$paths[$module][$class][$key] = $val;
			}
		}

		return $paths;
	}
}