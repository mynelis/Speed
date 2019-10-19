<?php

namespace Speed\Security;

/**
* Protection against Cross Site Request Forgery (CSRF).
* This class generates tokens to be used in HTML forms, and also validates
* tokens submitted in forms.
*
* For a form to pass as authentic, the submitted token must match a global
* token already genereated and embedded as a hidden field. This will ensure that
* only forms on the website can be processed.
*
* It is reccommended that a token is generated for each form instance, to ensure
* a higher level os security
*/
class TokenFactory
{
	// Every token will have this string prefixed for additional level os
	// security. The security strength is directly proportional to the
	// complexity of this "salt"
	private static $salt = 'tXsqu8Tqvl3mbYvj';

	/**
	 * Generate a new token.
	 * Token expires at the end of every hour. This may not be so much user-friendly
	 * since users would have to reload a form page if it has been idle into the next hour;
	 * but it however ensures that forms are better protected and the integrity of it is
	 * more trustworthy.
	 *
	 * @param $key string 	Optional Unique token identifier in global scope. Could be
	 *                      name of form for which token is required
	 * @return string
	 */
	public static function GetToken ($key = '') {
		return md5(date('mHdY') . self::$salt . $key);
	}

	/**
	 * Validate a given token, passed from submitted form
	 * @param string $tk  token to validate
	 * @param string $key Optional token identifier
	 */
	public static function ValidateToken ($tk, $key = '') {
		return ($tk === self::GetToken($key));
	}

	/**
	 * Automatic form token validation. Send a 400 status if validation fails
	 * @param string $token [description]
	 * @param string $key   [description]
	 */
	public static function CheckFormToken ($token, $key) {
		if (!self::ValidateToken($token, $key)) {
			http_response_code(400); // Bad Request
		    exit();
		}
	}
}
