<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_DB extends phunction
{
	public function __construct()
	{
	}

	public function __get($key)
	{
		return $this->$key = parent::__get(sprintf('%s_%s', ltrim(strrchr(__CLASS__, '_'), '_'), $key));
	}

	public static function Quote($data)
	{
		if (is_object(parent::DB()) === true)
		{
			return (is_array($data) === true) ? parent::DB()->quote($data) : array_map(array(parent::DB(), 'quote'), $data);
		}

		return false;
	}

	public static function Tick($string)
	{
		$string = preg_replace('~[`"]+~', '', $string);

		if ((is_object(parent::DB()) === true) && (strcmp('mysql', parent::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0))
		{
			return str_ireplace(' `AS` ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '`$1`', $string));
		}

		return str_ireplace(' "AS" ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '"$1"', $string));
	}

	public static function Wildcard($string, $wildcard = '%\\_')
	{
		$string = addcslashes($string, $wildcard);

		if ((is_object(parent::DB()) === true) && (strcmp('pgsql', parent::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0))
		{
		}

		return $string;
	}
}

?>