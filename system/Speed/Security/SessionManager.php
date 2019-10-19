<?php

namespace Speed\Security;

use \Speed\Session;

/**
 * Manage multiple instances of session objects.
 * All sessions are automatically created from related configurations in the
 * global config.yaml file
 * @uses \Speed\Session
 */
class SessionManager
{
	// Global private property for storing all session instances
	private static $instances;

	/**
	 * Initialize all declared session instances (from config file)
	 *
	 * @param stdClass $sessions passed from YAML config file
	 */
	public static function Init ()
	{
		$sessions = config('sessions');

		if ($sessions) {
			foreach ($sessions as $sid => $cfg)
			{
				// We are creating a new session based on our config file definitions,
				// so its safer to use createSession than getSession, since we're sure
				// there's no session in the instances container with same SID.
				self::$instances->$sid = self::createSession($sid, $cfg);
				if (true == self::$instances->$sid->autostart) {
					self::$instances->$sid->start();
				}
			}
		}
	}

	/**
	 * Try retrieving a session instance matching given SID.
	 * If the object is not found, a new instance will be created
	 *
	 * @param  String $sid identity SID key
	 * @return \Speed\Session      instance of Session
	 */
	public static function getSession ($sid, $cfg = null)
	{
		// Session already created and register in instances container,
		// so we fetch it and handle its autostart flag
		if (isset(self::$instances->$sid)) {
			$sess = self::$instances->$sid;
			if ($sess->autostart) $sess->start();
		}

		// Session not found in instances,
		// so we create a new raw session
		else $sess = self::createSession($sid, $cfg);

		return $sess;
	}

	/**
	 * End a session with given SID
	 *
	 * @param  String $sid session identifier key
	 */
	public static function endSession ($sid)
	{
		$sess = self::$instances->$sid;
		$sess->end();
		unset(self::$instances->$sid);

		// Destroy the global session object after the
		// last session has been closed
		if (0 === sizeof(self::$instances)) {
			session_destroy();
		}
	}

	/**
	 * Get all session instances from the private global container
	 *
	 * @return stdClass all instances
	 */
	public static function getInstances ()
	{
		return self::$instances;
	}

	/**
	 * Create a new session instance.
	 * This method is made private for internal use, to prevent accidentally
	 * creating new sessions that may not have their timeout settings attached.
	 *
	 * @param  String $sid session identifier key
	 * @param stdClass $cfg 	session config settings
	 * @return \Speed\Session      Session instance
	 */
	private static function createSession ($sid, $cfg = null)
	{
		if (!self::$instances) self::$instances = (object)null;
		$sess = new Session($sid);

		// Attach default configurations as defined in the config file
		if (isset($cfg->timeout)) $sess->timeout = $cfg->timeout;
		if (isset($cfg->autostart)) $sess->autostart = $cfg->autostart;
		if (isset($cfg->autoend)) $sess->autoend = $cfg->autoend;

		// Register session instance in the instances container
		self::$instances->$sid = $sess;

		return $sess;
	}

	public static function forceLogoutWhen ($flag)
	{
		if ($flag) {
			exit;
		}
	}
}