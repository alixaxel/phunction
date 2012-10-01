<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Is extends phunction
{
	public function __construct()
	{
	}

	public static function ASCII($string = null)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[\x20-\x7E]*$~'))) !== false) ? true : false;
	}

	public static function Alpha($string, $numeric = false)
	{
		if ($numeric === true)
		{
			return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[0-9a-z]*$~i'))) !== false) ? true : false;
		}

		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[a-z]*$~i'))) !== false) ? true : false;
	}

	public static function Base64($string = null)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^(?:[0-9a-z+/]{4})*(?:[0-9a-z+/]{3}=|[0-9a-z+/]{2}==)?$~i'))) !== false) ? true : false;
	}

	public static function Email($email = null, $mx = false)
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
		{
			return (($mx === true) && (function_exists('checkdnsrr') === true)) ? checkdnsrr(ltrim(strrchr($email, '@'), '@'), 'MX') : true;
		}

		return false;
	}

	public static function Float($number = null, $minimum = null, $maximum = null)
	{
		if (filter_var($number, FILTER_VALIDATE_FLOAT) !== false)
		{
			return (((isset($minimum) === true) && ($number < $minimum)) || ((isset($maximum) === true) && ($number > $maximum))) ? false : true;
		}

		return false;
	}

	public static function Hex($string)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[0-9a-f]$~i'))) !== false) ? true : false;
	}

	public static function Integer($number = null, $minimum = null, $maximum = null)
	{
		return (filter_var($number, FILTER_VALIDATE_INT) !== false) ? self::Float($number, $minimum, $maximum) : false;
	}

	public static function IP($ip = null)
	{
		return (filter_var($ip, FILTER_VALIDATE_IP) !== false) ? true : false;
	}

	public static function Set($value = null)
	{
		return (filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~[[:graph:]]~'))) !== false) ? true : false;
	}

	public static function URL($url = null, $path = null, $query = null)
	{
		foreach (array('path', 'query') as $value)
		{
			$$value = (empty($$value) === true) ? 0 : constant(sprintf('FILTER_FLAG_%s_REQUIRED', $value));
		}

		return (filter_var($url, FILTER_VALIDATE_URL, $path + $query) !== false) ? true : false;
	}

	public static function Void($value = null)
	{
		return (filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[^[:graph:]]*$~'))) !== false) ? true : false;
	}
}

?>