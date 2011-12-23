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
			return strcasecmp('id', $key) ? key($result) : current($result);
		}

		return false;
	}

	public static function Check()
	{
		if ((strlen(session_id()) > 0) && (count(parent::Value($_SESSION, __CLASS__, null)) == 1))
		{
			return true;
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

?>