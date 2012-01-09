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
		if (is_array($data) === true)
		{
			return array_map('self::Quote', $data);
		}

		else if (is_object(parent::DB()) === true)
		{
			switch (gettype($data))
			{
				case 'boolean':
				case 'integer':
					return intval($data);
				break;

				case 'object':
					return strval($data);
				break;
			}

			return parent::DB()->quote($data);
		}

		return false;
	}

	public static function Tick($data)
	{
		$data = preg_replace('~[`"]+~', '', $data);

		if ((is_object(parent::DB()) === true) && (strcmp('mysql', parent::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0))
		{
			return str_ireplace(' `AS` ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '`$1`', $data));
		}

		return str_ireplace(' "AS" ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '"$1"', $data));
	}

	public static function Wildcard($data, $escape = '%_')
	{
		if (is_array($data) === true)
		{
			foreach ($data as $key => $value)
			{
				$data[$key] = self::Wildcard($value, $escape);
			}

			return $data;
		}

		return addcslashes($data, $escape . '\\');
	}
}

?>