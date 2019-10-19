<?php

namespace app\control;

use Speed\Templater\Layout;

class BaseControl extends \Speed\AppControl
{
	public function __construct ()
	{
		parent::__construct();

		Layout::set_meta([
			'title' => config('app.alias', true),
			'baseurl' => BASEURL, 
			'localdir' => config('site.localdir')
		]);

		// Layout::register('app_user', (session_expired()) ? 'none' : [session('app.user')]);

		setcookie('ld', config('site.localdir'), null, '/');
	}
}