<?php

namespace Speed;

use \Speed\Time;

class Session
{
	public $timeout;
	public $autostart;
	public $autoend;

	private $sid = '';

	public function __construct ($sid)
	{
		$this->sid = md5($sid);
	}

	public final function getToken ()
	{
		return $this->get('token');
	}

	// Generate a new tracking token every hour, for this session
	public final function generateToken ()
	{
		$this->set('token', $this->sid.md5(date('Y-m-d H')));
		return $this;
	}

	public final function validateToken ($token)
	{
		$pass = (bool)($token == $this->getToken());
		if (!$pass) {
			$this->generateToken();
		}
		return $pass;
	}

	public final function requireToken ($var, $renew = false)
	{
		$pass = false;

		if (isset($var->xtoken) and is_string($var->xtoken)) {
			$pass = $this->validateToken($var->xtoken);
		}

		if (!$pass) {
			$this->generateToken();
			echo 'Invalid token';
			exit;
		}

		return $this;
	}

	public function get ($property = null)
	{
		if (!$property) return $_SESSION[$this->sid];

		if (isset($_SESSION[$this->sid]->$property)) {
			return $_SESSION[$this->sid]->$property;
		}
	}

	public function set ($property, $value)
	{
		if (isset($_SESSION[$this->sid])) {
			$_SESSION[$this->sid]->$property = $value;
		}

		return $this;
	}

	public function update ($property, $vars)
	{
		$obj = $this->get($property);

		if ($obj && is_scalar($vars)) {
			$this->set($property, $vars);
		}

		elseif ($obj) {
			foreach ($vars as $key => $value) {
				$obj->$key = $value;
			}
		}

		$this->set($property, $obj);

		return $obj;
	}

	public function delete ($property)
	{
		if (isset($_SESSION[$this->sid]) and isset($_SESSION[$this->sid]->$property)) {
			unset($_SESSION[$this->sid]->$property);
		}
	}

	public function start ()
	{
		session_start();

		if (!isset($_SESSION) or !isset($_SESSION[$this->sid])) {
			$_SESSION[$this->sid] = (object)null;
		}

		if (!$this->get('token')) {
			$this->generateToken();
		}

		$now = time();
		$timeout = Time::parse($this->timeout);
		$autoend = $this->get('autoend');
		$started = $this->get('start_time');
		$ends = $started + $timeout;

		// dump('sid: '.$this->sid.', started: '.$started.', ends: '.$ends.', now: '.$now.', remaining: '.($ends - $now));

		if ($this->autoend && $started and $now > $ends) {
			$this->end();
		}

		return $this;
	}

	public function getTimeout ()
	{
		return Time::parse($this->timeout);
	}

	public function end ()
	{
		if (isset($_SESSION[$this->sid])) {
			unset($_SESSION[$this->sid]);
		}

		return $this;
	}
}