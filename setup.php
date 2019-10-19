<?php

define('ROOT', '');
require 'lib/init.php';

// Create schema and controller mapping caches
echo '<h1>Creating application cache...</h1>';
usleep(1000);
\Speed\CacheControl::Initialize();

// Convert all HTML layout files
echo '<h1>Preparing layouts...</h1>';
usleep(1000);
\Speed\Templater\XMLDocument::convert_from_html(null, true, true);

/*\Speed\Templater\XMLDocument::convert_from_html(null, true, function ($file) {
	rename($file, $file.'.bak.html');
});*/
// exit;

echo '<h1>Done</h1>';

// (new \Speed\Templater\Layout())->render();