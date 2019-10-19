<?php

namespace Speed\Security;

class Sanitizer
{
	const sanitize_filters = array(
		'email' => FILTER_SANITIZE_EMAIL,
		'string' => FILTER_SANITIZE_STRING,
		'url' => FILTER_SANITIZE_URL,
		'int' => FILTER_SANITIZE_NUMBER_INT,
		'float' => FILTER_SANITIZE_NUMBER_FLOAT,
		'encoded' => FILTER_SANITIZE_ENCODED
	);

	public static function Sanitize ($str, $type = 'string')
	{
		if ('' == $type) $type = 'string';
		return  filter_var($str, self::sanitize_filters[$type]);
	}

	public static function SanitizeEmail ($email)
	{
		return filter_var($email, FILTER_SANITIZE_EMAIL);
	}

	public static function SanitizeString ($string)
	{
		return filter_var($string, FILTER_SANITIZE_STRING);
	}

	public static function SanitizeUrl ($url)
	{
		return filter_var($url, FILTER_SANITIZE_URL);
	}

	public static function SanitizeInt ($int)
	{
		return filter_var($int, FILTER_SANITIZE_NUMBER_INT);
	}

	public static function SanitizeFloat ($int)
	{
		return filter_var($int, FILTER_SANITIZE_NUMBER_FLOAT);
	}

	public static function SanitizeEncoded ($encoded)
	{
		return filter_var($encoded, FILTER_SANITIZE_ENCODED);
	}

	public static function Unscript ($str)
	{
		return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $str);
	}
}

class Validator
{
	public static function ValidateEmail ($email)
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL);
	}

	public static function ValidateUrl ($url)
	{
		return filter_var($url, FILTER_VALIDATE_URL);
	}

	public static function ValidateInt ($int)
	{
		return filter_var($int, FILTER_VALIDATE_INT);
	}

	public static function ValidateBoolean ($int)
	{
		return filter_var($int, FILTER_VALIDATE_BOOLEAN);
	}

	public static function ValidateFloat ($int)
	{
		return filter_var($int, FILTER_VALIDATE_FLOAT);
	}

	public static function ValidateIp ($ip)
	{
		return filter_var($ip, FILTER_VALIDATE_IP);
	}

	public static function ValidateRegex ($encoded)
	{
		return filter_var($regex, FILTER_VALIDATE_REGEXP);
	}
}
