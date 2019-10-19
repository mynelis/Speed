<?php

namespace Speed;

class Time
{
	const TIMESTAMP = array(
		's' => 1,
		'm' => (1 * 60),
		'h' => (1 * 60 * 60),
		'd' => (24 * 1 * 60 * 60),
		'w' => (7 * 24 * 1 * 60 * 60)
	);

	const DATE_FORMAT_NORMAL = 'Y-m-d';
	const TIME_FORMAT_NORMAL = 'Y-m-d H:i:s';
	const TIME_FORMAT_UTC = 'Y-m-d H:i:s P';

	public static function parse ($str)
	{
		$_time = array();
		preg_match_all('/(\d+) (\w)/', $str, $_time, PREG_SET_ORDER);

		$time = 0;
		if ($_time) {
			foreach ($_time as $each) {
				$time += ($each[1] * self::TIMESTAMP[$each[2]]);
			}
		}
		return $time;
	}

	public static function today ()
	{
		return date(self::DATE_FORMAT_NORMAL);
	}

	public static function today_utc ()
	{
		return date(self::TIME_FORMAT_UTC);
	}

	public static function default_timezone ()
	{
		return date_default_timezone_get();
	}
}