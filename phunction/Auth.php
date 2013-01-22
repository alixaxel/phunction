<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Auth extends phunction
{
	public function __construct()
	{
	}

	public function __get($key)
	{
		if ((strlen(session_id()) > 0) && (is_array($result = parent::Value($_SESSION, __CLASS__)) === true))
		{
			return (strcasecmp('id', $key) === 0) ? key($result) : current($result);
		}

		return false;
	}

	public static function ACL($role, $object = null, $action = null, $auth = null)
	{
		static $result = array();

		if (is_null($auth) === true)
		{
			return parent::Value($result, array($role, $object, $action));
		}

		return $result[$role][$object][$action] = (bool) $auth;
	}

	public static function Check($object = null, $action = null)
	{
		if ((strlen(session_id()) > 0) && (is_array($result = parent::Value($_SESSION, __CLASS__)) === true))
		{
			return (strlen($role = current($result)) > 0) ? self::ACL($role, $object, $action) : true;
		}

		return false;
	}

	public static function Login($id, $role = null)
	{
		if ((strlen(session_id()) > 0) && (session_regenerate_id(true) === true))
		{
			$_SESSION[__CLASS__] = array(trim($id) => trim($role));
		}

		return (count(parent::Value($_SESSION, __CLASS__, null)) == 1) ? true : false;
	}

	public static function Logout()
	{
		if ((strlen(session_id()) > 0) && (session_regenerate_id(true) === true))
		{
			$_SESSION[__CLASS__] = null;
		}

		return (count(parent::Value($_SESSION, __CLASS__, null)) == 0) ? true : false;
	}
}
