<?php

namespace Speed;

class CacheControl
{
	public static function Initialize ()
	{
		$rebuild_cache = 'rebuild_cache' == request('meta.url.query') ? true : false;
		$cache_dir = ROOT.config('app.cache_dir', true);

		if (!is_dir($cache_dir)) {
			mkdir($cache_dir, 0600);
		}
		
		if (!is_dir($cache_dir.'/view')) {
			mkdir($cache_dir.'/view', 0600);
		}

		if (!is_readable($cache_dir.'/ControllerMapping.php') || true == $rebuild_cache) {
			\Speed\AppControl::WriteClassPaths();
		}

		if (!is_readable($cache_dir.'/SchemaData.php') || true == $rebuild_cache) {
			\Speed\DataLibrary\Data::WriteSchemaData();
		}
	} 
}