<?php

function dump($var, $exit = false, $func = 'var_dump', $label = null) {

    if ('json' === $func) {
        echo json_encode($var);
        return;
    }

    $label = $label ? $label : gettype($var).' => ';

    // Store dump in variable
    ob_start();
    //var_dump($var);

    if ('echo' === $func) {
        echo "\r\n".'<p>';
        foreach ($var as $key => $value) {
            echo $key.': <i>'.$value.'</i>'."\r\n";
        }
        echo "</p>";
    }
    else {
        $func($var);
    }

    $output = ob_get_clean();
    
    // Add formatting
    $output = preg_replace("/\]\=\>\n(\s+)/m", "] => ", $output);
    echo '<pre style="
        background: #FFFEEF; 
        color: #000; 
        border-radius: 10px; 
        box-shadow: 0px 0px 20px 10px #ccc; 
        border: 1px solid #aaa; 
        padding: 10px 20px; 
        margin: 20px; 
        text-align: left;"><b>'.strtoupper($label).'</b>'. $output . '</pre>';

    if ($exit) exit;
}

function value_of ($obj, $key, $delim = '.', $scalar = false, $strict_search = false) {
    $keys = explode($delim, $key);
    
    foreach ($keys as $k) {
        $type = gettype($obj);

        if ('object' == $type && isset($obj->$k)) {
            $obj = $obj->$k;
        }
        elseif ('array' == $type && isset($obj[$k])) {
            $obj = $obj[$k];
        }
        elseif ($strict_search) {
            $obj = null;
        }
    }

    if ($scalar && !is_scalar($obj)) return null;

    return $obj;
}

function config ($key = '', $scalar = false) {
    global $config;
    return value_of($config, $key, '.', $scalar);
}

function request ($key = '', $default_all = false)  {

    // The URL we are going to work with is the REQUEST_URI index's value
    // retrieved from the global $_SERVER array.
    // If there is a config value for localdir, we assume this is a local
    // development environment and therefore remove the localdir prefix
    // from the URL string.
    // global $config;
    // $rx = (isset($config->site) && isset($config->site->localdir)) ? '/^\/'.$config->site->localdir.'\//' : '/^\/(\w+)\//'; 
    // $rx = '/^\/(\w+)\//'; 

    // $uri = preg_replace($rx, '',  $_SERVER['REQUEST_URI']);
    $uri = preg_replace('/^\/(\w+)\//', '',  $_SERVER['REQUEST_URI']);
    $parts = explode('.', $uri);
    $path = $parts[0];

    $api = array_slice($parts, 1);

    //header('Content-Type: application/json');

    //dump(server(), true);
    //dump(getallheaders(), true);

    // A skeletal map of object we would return later.
    $map = (object) [
        'module' => 'main',
        'class' => 'Main',
        'method' => 'index',
        'args' => [],
        'meta' => (object) [
            'uri' => $uri,
            'path' => $path,
            'url' => null,
            'dir' => null,
        ],
        'request' => (object) [
            'method' => null,
            'format' => null,
            'get' => get(),
            'post' => post()
        ],
        'server' => server()
    ];

    //if (!$path) return ($key && isset($map->$key)) ? $map->$key : null;//$map;
    if (!$path) {
        if ($key && isset($map->$key)) return $map->$key;
        if (true === $default_all) return $map;
        return null;
        //return ($key && isset($map->$key)) ? $map->$key : null;//$map;
    }

    if (2 == sizeof($api)) {
        $map->request->method = strtoupper($api[0]);
        $map->request->format = strtoupper(explode('?', $api[1], 2)[0]);
    }

    //dump($url);

    $url = parse_url($path);
    $map->meta->url = $url;
    //$path = $url['path'];

    //dump($url);
    //exit;

    $path_arr = preg_split('/\//', $url['path'], null, PREG_SPLIT_NO_EMPTY);
    //dump($path_arr);

    $dir = realpath('app/control/'.$path_arr[0]);
    $map->meta->dir = (object)[
        'control' => 'app/control/'.$path_arr[0],
        'view' => 'app/view/'.$path_arr[0]
    ];

    //dump($dir);

    if (is_dir($dir)) {
        $map->module = $path_arr[0];
        array_shift($path_arr);
    }

    $pattern = ['class', 'method'];
    foreach ($pattern as $each) {
        if ($path_arr) {
            $map->$each = $path_arr[0];
            array_shift($path_arr);
        }
    }

    if ($path_arr) $map->args = $path_arr;
    if (isset($url['query'])) parse_str($url['query'], $map->args[]);

    // All class files and class names must begin with a capital letter.
    $map->class = ucfirst($map->class);

    // When a query string is requested, look for the value inside the
    // only array data in args.
    if ('?' === substr($key, 0, 1)) {
        $key = substr($key, 1);

        // Filter out the array data in args, which is actually the
        // parsed query string.
        $args = array_filter($map->args, function ($item) {
            return is_array($item);
        });

        // The result of our filter may return an array with unpredictable
        // integer index, so we sort to reset the index to 0.
        sort($args);

        // Now we can return the corresponding value of the key passed,
        // if this key is actually present in the query string and has
        // a value. 
        // An empty string is returned if no value is found in the url
        // query string for the key provided.
        return isset($args[0][$key]) ? $args[0][$key] : $args[0];
    }




    

    $module = isset($map->module) ? $map->module : 'main';
    $class_name = isset($map->class) ? $map->class : 'Main';
    $method = isset($map->method) ? $prefix.$map->method : 'index';
    $args = isset($map->args) ? $map->args : [];

    $namespace = '\\app\\control\\'.$module;
    $class = $namespace.'\\'.$class_name;

    if (!class_exists($class)) {
        $class = $namespace.'\\Main';
        $method = strtolower($class_name);
    }

    if (!method_exists($class, $method)) {
        $method = 'index';
    }

    /*dump('MODULE: '.$module);
    dump('CLASS: '.$class);
    dump('METHOD: '.$method);
    dump($args, true);*/

    $map->class = $class_name;
    $map->class_ns = $class;
    $map->method = $method;





    if ($key) {
        return value_of($map, $key);
    }
    
    return $map;
}

function routing ($key = null) {
    $route = (object) [
        'view' => find_view(),
        'module' => request('module'),
        'class' => request('class_ns'),
        'method' => request('method'),
        'args' => request('args')
    ];

    $route->route = $route->module.':'.$route->class.':'.$route->method;

    return $key ? value_of($route, $key) : $route;
}

/*function session ($key = '', $value = null) {
    if (!$key) return (object)$_SESSION;
    if (is_array($key)) {
        foreach ($key as $k => $v) $_SESSION[$k] = $v;
        return (object)$_SESSION;
    }
    if ($key && $value) {
        $_SESSION[$key] = $value;
        return (object)$_SESSION;
    }
    return isset($_SESSION[$key]) ? $_SESSION[$key] : value_of($_SESSION, $key);
}*/

function session ($key, $value = null) {
    // $session = \Speed\Security\SessionManager;

    if (is_array($key)) {
        foreach ($key as $k => $v) $session->set($k, $v);
        return $session->get();
    }

    $keys = explode('.', $key);

    if (1 == sizeof($keys)) return \Speed\Security\SessionManager::getSession($keys[0]);

    $session = \Speed\Security\SessionManager::getSession($keys[0]);
    $key = implode('.', array_slice($keys, 1));

    // if (!$key) return (object)$_SESSION;
    if ($key && $value) {
        if ($session->get($key)) {
            $session->update($key, $value);
        }
        else {
            $session->set($key, $value);
        }
        return $session->get();
    }

    // return $key;
    // $sess = $session->get();
    // return $sess->$key;

    // return isset($_SESSION[$key]) ? $_SESSION[$key] : value_of($_SESSION, $key);
    // return $session->get($key);
    // value_of($obj, $key, $delim, $scalar, $strict_search)
    return value_of($session->get(), $key, '.', null, true);
}

function cookie ($key = null, $value = null) {
    if (!$key) return (object)$_COOKIE;
    if (is_array($key)) {
        foreach ($key as $k => $v) $_COOKIE[$k] = $v;
        return (object)$_COOKIE;
    }
    if ($key && $value) {
        $_COOKIE[$key] = $value;
        return (object)$_COOKIE;
    }
    return isset($_COOKIE[$key]) ? $_COOKIE[$key] : null;
}

function get ($key = null, $value = null) {
    if (!$key) return (object)$_GET;
    if (is_array($key)) {
        foreach ($key as $k => $v) $_GET[$k] = $v;
        return (object)$_GET;
    }
    if ($key && null !== $value) {
        $_GET[$key] = $value;
        return (object)$_GET;
    }
    return isset($_GET[$key]) ? $_GET[$key] : null;
}

function post ($key = null, $value = null) {
    if (!$key) return (object)$_POST;
    if (is_array($key)) {
        foreach ($key as $k => $v) $_POST[$k] = $v;
        return (object)$_POST;
    }
    if ($key && null !== $value) {
        $_POST[$key] = $value;
        return (object)$_POST;
    }
    return isset($_POST[$key]) ? $_POST[$key] : null;
}

function upload ($key) {
    if (!$key) return (object)$_FILES;
    return isset($_FILES[$key]) ? $_FILES[$key] : null;
}

function server ($key = null) {
    $key = str_replace('.', '_', $key);
    $server = (object)null;

    foreach ($_SERVER as $k => $v) {
        if (!defined($k)) define($k, $v);

        $server->{strtolower($k)} = $v;
    }

    return ($key && isset($server->$key)) ? $server->$key : $server;
}

function server_part ($prefix, $key = null) {
    $server = (object)null;

    foreach ($_SERVER as $k => $v) {
        if (-1 < strpos($k, $prefix)) {
            $_key = strtolower(str_replace($prefix, '', $k));
            $server->$_key = $v;
        }
    }

    return ($key && isset($server->$key)) ? $server->$key : $server;
}

function http ($key = null) {
    $key = str_replace('.', '_', $key);
    return server_part('HTTP_', $key);
}

/*function redirect ($key = null) {
    $key = str_replace('.', '_', $key);
    return server_part('REDIRECT_', $key);
}*/

function redirect ($path) {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: '.$path);
    exit();
}

function refresh () {
    header('Location: '.server('request.uri'));
    exit();
}

function remote ($key = null) {
    $key = str_replace('.', '_', $key);
    return server_part('REMOTE_', $key);
}

// function find_view (string $file = 'index', string $prefix = '', $modules = []) {
// function find_view (string $file = 'index', string $prefix = '') {//, $modules = []) {
function find_view (string $file = '', string $prefix = '') {//, $modules = []) {
    $module = strtolower(request('module'));
    $class = strtolower(request('class'));
    $method = strtolower(request('method'));

    if (!$file) $file = request('method');
    /*dump($module);
    dump($class);
    dump($method);
    dump($prefix);*/

    $mods = [
        [$file,  $class.'/'.$method.'/'.$prefix], // app/view/class/method/prefix/file.xml
        [$file,  $class.'/'.$prefix], // app/view/class/prefix/file.xml

        [$file,  $module.'/'.$class.'/'.$method.'/'.$prefix], // app/view/module/class/method/prefix/file.xml
        [$file,  $module.'/'.$class.'/'.$prefix], // app/view/module/class/prefix/file.xml
        [$file,  $module.'/'.$prefix], // app/view/module/prefix/file.xml

        [$method,  $module.'/'.$class.'/'.$prefix], // app/view/module/class/prefix/method.xml

        [$class.'_'.$method,  $module.'/'.$prefix], // app/view/module/prefix/class_method.xml
        [$class.'_'.$method,  'main'.'/'.$prefix], // app/view/main/prefix/class_method.ml

        [$class,  $module.'/'.$prefix], // app/view/module/prefix/class.xml
        [$module,  'main'.'/'.$prefix], // app/view/main/prefix/module.xml

        ['index',  $module.'/'.$class.'/'.$method.'/'.$prefix], //app/view/module/class/method/prefix/index.xml
        ['index',  $module.'/'.$class.'/'.$prefix], // app/view/module/class/prefix/index.xml
        ['index',  $module.'/'.$prefix], // app/view/module/prefix/index.xml

        ['index', 'main' ]
    ];

    /*if (!$modules) $modules = [$module];
    if ($class) array_unshift($modules, $module.'/'.$class);
    // if (!is_array($modules)) settype($modules, 'array');
    $modules[] = 'main';
    $modules = array_unique($modules);

    dump($modules);*/
    // dump('>>> '.$file);

    foreach ($mods as $each) {
        // $path = 'app/view/'.$each[1].'/'.$prefix.$each[0].'.xml';
        $path = 'app/view/'.$each[1].'/'.$each[0].'.xml';
        $path = str_replace('//', '/', $path);

        // dump($file.' >> '.$each[0].' >> '.$each[1].' >> '.$path);

        // if (file_exists($path)) return realpath($path);
        if (file_exists($path)) return ROOT.$path;
    }

    return false;
}



function call_request ($default_all = false, $prefix = '', $remote = false) {
    $request = request();

    // dump($request);

    /*$module = isset($request->module) ? $request->module : 'main';
    $class_name = isset($request->class) ? $request->class : 'Main';
    $method = isset($request->method) ? $prefix.$request->method : 'index';
    $args = isset($request->args) ? $request->args : [];

    $namespace = '\\app\\control\\'.$module;
    $class = $namespace.'\\'.$class_name;

    if (!class_exists($class)) {
        $class = $namespace.'\\Main';
        $method = strtolower($class_name);
    }

    if (!method_exists($class, $method)) {
        $method = 'index';
    }

    dump('MODULE: '.$module);
    dump('CLASS: '.$class);
    dump('METHOD: '.$method);
    dump($args, true);*/
    $method = isset($request->method) ? $prefix.$request->method : 'index';

    // if ((class_exists($class) && method_exists($class, $method)) || $remote) {
    if ((class_exists($request->class_ns) && method_exists($request->class_ns, $method)) || $remote) {
        // return \call_user_func_array([new $class, $method], $request->args);
        return \call_user_func_array([new $request->class_ns, $method], $request->args);
    }

    return false;
}

function errors () {
    global $errors;
    return $errors;
}

function get_defined ($key = '') {
    $map = (object) [
        'interfaces' => get_declared_interfaces(),
        'constants' => get_defined_constants(),
        'classes' => get_declared_classes(),
        'functions' => get_defined_functions(),
        'variables' => get_defined_vars()
    ];

    //dump($key);
    if (!$key) return $map;
    if (isset($map->$key)) return $map->$key;

    return value_of($map, $key);
}

function resource_handle ($obj, $key = '') {
    ob_start();
    var_dump($obj);
    $resource = ob_get_clean();

    if (preg_match_all('/^(\w+)\((\w+)\)#(\d+)\s\((\d+)\)\s\{/i', $resource, $match, PREG_SET_ORDER)) {
        return $match ? value_of((object) [
            'type' => $match[0][1],
            'class' => $match[0][2],
            'id' => $match[0][3],
            'entries' => $match[0][4]
        ], $key) : false;
    }
}

// $route = route('template', 'home');
// $route = route('id', 58);
// $route = route('id');
// $route = route();
// $route = route('/(\w+)\.(\w+)$/', function ($matches, $u, $r) {
//  route('reg', $matches);
//  return $r;
// });
function route ($key = null, $value = null) {
    global $__global_routing;

    $uri = request('meta.uri');

    // Fetch only mode
    if (!$key) return $__global_routing; 

    // Unset only mode
    if ($key && null === $value) unset($__global_routing->$key);

    // Regex matching with callback function
    if ($key && is_callable($value) && preg_match($key, $uri, $matches)) {
        return $value($matches, $uri, $__global_routing);
    } 

    // Retrieve only mode
    if ($key && !$value && isset($__global_routing->$key)) return $__global_routing->$key;

    // Assign only mode
    if ($key && $value) $__global_routing->$key = $value;
    
    return $__global_routing;
}

function ifpost ($key, $callback) {
    if (post($key)) return $callback(post($key), post());
}

function ifnpost ($key, $callback) {
    if (!post($key)) return $callback($key);
}

function ifget ($key, $callback) {
    if (get($key)) return $callback(get($key), get());
}

function ifnget ($key, $callback) {
    if (!get($key)) return $callback($key);
}

function yesno ($value, $type, $yes = 'Yes', $no = 'No') {
    settype($value, $type);
    return $value === 0 ? $no : $yes;
}

function lowercase ($str) {
    return strtolower($str);
}

function uppercase ($str) {
    return strtoupper($str);
}

function hiphenate ($str) {
    return preg_replace('/\s+/', '-', $str);
}

function relate ($ref, $col, $func = null) {
    //$value = isset($ref[0]->$col) ? $ref[0]->$col : '';
    $value = value_of($ref[0], $col);
    return function_exists($func) ? $func($value) : $value;
}

function dateformat ($date, $format = 'Y-m-d H:i:s') {
    $args = func_get_args();
    $argnum = func_num_args();
    $fn = null;

    if (1 < $argnum) {
        if (function_exists($args[$argnum - 1])) {
            $fn = $args[$argnum - 1];
            array_pop($args);
        }
        $format = implode(' ', array_slice($args, 1));
    }

    $date = date($format, strtotime($date));

    return $fn ? $fn($date) : $date;
}

function timeperiod ($date) {
    
}

function quote ($value, $quotation = "'") {
    if (is_array($value)) {
        foreach ($value as $key => $val) {
            $value->$key = $quotation.$val.$quotation;
        }
        return $value;
    }
    return $quotation.$value.$quotation;
}

function first ($arr) {
    return array_slice($arr, 0, 1);
}

function last ($arr) {
    $len = sizeof($arr);
    return (1 < $len) ? array_slice($arr, $len - 1) : $arr;
}

function session_expired ($sid = 'app', $key = 'user.id', $callback = null) {
    $check = session($sid.'.'.$key);

    if (!$check) {
        if (is_callable($callback)) return $callback($check);
        return true;
    }

    return false;
}

function log_action ($test, $description = 'Unspecified', $return = null) {
    $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
    $method = explode('\\', $backtrace['class'], 3)[2];
    $method = strtolower(preg_replace('/\\\|::/', '.', $method)).'.'.$backtrace['function'];

    if ($test) {
        (new app\control\main\Audit())->log($method, $description);
        return $return ? $return : $test;
    }

    return isset($test->validation_errors) ? $test->validation_errors : false;
}

function fail_rc ($message = '') {
    if (!$message) $message = Ajax::STATUS_MESSAGE_FAILURE;

    return (object) [
        'code' => Speed\Util\Ajax::STATUS_CODE_FAILURE, 
        'message' => $message
    ];
}

function pass_rc ($message = '') {
    if (!$message) $message = Speed\Util\Ajax::STATUS_MESSAGE_SUCCESS;

    return (object) [
        'code' => Speed\Util\Ajax::STATUS_CODE_SUCCESS, 
        'message' => $message
    ];
}

function snippet ($xpath, $values = [], $file = ROOT.'app/view/partials/snippets.xml') {
    $xml = new Speed\Templater\XMLDocument($file);
    if (!$xml->dom) return false;
    return $xml->parse_node($xpath, $values);
}

function selector_options ($data, $default = null, $value_col = 'id', $label_col = 'name') {
    if (!$data) return '';

    $html = '';
    foreach ($data as $each) {
        
        if (is_callable($value_col)) {
            $value = $value_col($each);
        }
        else {
            $value = $each->$value_col;
        }

        if (is_callable($label_col)) {
            $label = $label_col($each);
        }
        else {
            $label = $each->$label_col;
        }
        
        $value = ($default == $value) ? $value.'" selected="selected' : $value;
        $html .= snippet('/snippets/general/option-single', [
            'value' => $value, 
            'label' => $label
        ]);
    }

    return $html;
}

function set_meta ($key, $value = null) {
    return \Speed\Templater\Layout::set_meta($key, $value);
}

function set_view ($view) {
    return \Speed\Templater\Layout::set_view($view);
}

function register ($identity, $callback) {
    return \Speed\Templater\Layout::register($identity, $callback);
}