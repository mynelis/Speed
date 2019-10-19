<?php

namespace Speed\Security;

class InputValidator
{
	const WORDS = '\w+';
	const SINGLE_WORD = '^\w+$';
	const BINDING = '^(\w[\.]+){3}$';
	const SINGLE_NUMBER = '^\d+$';
	const NUMBER = '\d+';
	const DECIMAL = '\d[\.\d]{0,}';
	const PHONE = '^\d{10,20}$';
	const PHONE_INTERNATIONAL = '^\+(\d+)-(\d+)-(\d+)-(\d+)$';
	const EMAIL = '^(.+?)\@(.+?)\.(.+?)$';
	const DOMAIN = '.+';
	const SUB_DOMAIN_NAME = '.+';
	const URL = 'http[s]:\/\/.+\..+$';
	const LINK = '\w[-]+';
	const ANY = '.+';
	const FILE = '.+\..+$';

	public static function ValidateUsingRegex ($regex, $input)
	{
		return preg_match('/'.$regex.'/', $input);
	}

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

	public static function ValidatePhone ($input) {
		return preg_match(self::PHONE, $input);
	}

	public static function ValidatePhoneInternational ($input) {
		return preg_match(self::PHONE_INTERNATIONAL, $input);
	}

	public static function ValidateFile ($input) {
		return preg_match(self::FILE, $input);
	}

	public static function ValidateSingleWord ($input) {
		return preg_match(self::SINGLE_WORD, $input);
	}

	public static function ValidateWords ($input) {
		return preg_match(self::WORDS, $input);
	}

	public static function ValidateDomain ($input) {
		return filter_var($input, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
	}

	public static function ValidateMacAddress ($input) {
		return filter_var($input, FILTER_VALIDATE_MAC);
	}
}