<?php

namespace Speed\Security;

use \Speed\Time;

class SessionSecurity
{
	public static function Initialize ()
	{
		$cfg = config();

		$domain = '';
		$path = '/';
		$lifespan = isset($cfg->timestamp) ? Time::Parse($cfg->timestamp) : 60 * 60;

		// Use secure cookies if HTTPS is on
		$https_on = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443);
		//ini_set('session.cookie_secure', $https_on);

		// Preventing Session hijacking
		// Prevents javascript XSS attacks aimed to steal the session ID
		//ini_set('session.cookie_httponly', true);

		// Prohibit passing session ID through URL
		//ini_set('session.use_only_cookies', true);

		//ini_set('session.cookie_path', $path);

		//ini_set('session.cookie_domain', $domain);

		//ini_set('session.cache_expire', $lifespan);

		// In case ini_set fails, we have this as fallback strategy
		/*session_set_cookie_params(
			$lifespan, // 1 hour cookie life,
			$path, // cookie path
			$domain, // set for this domain only
			$https_on, // secure cookies for HTTPS mode
			true // HTTP cookies only (hide cookies from javascript)
		);*/
	}

	public static function Run ()
	{
		if (!isset($_SESSION)) {
			self::Initialize();
			session_start();
		}
	}
}