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

	public static function Get($table, $id = null)
	{
		if (is_object(parent::DB()) === true)
		{
			if (isset($id) === true)
			{
				if (is_array($id) !== true)
				{
					$id = array('id' => $id);
				}

				foreach ($id as $key => $value)
				{
					$id[$key] = sprintf('%s LIKE %s', $key, parent::DB()->quote($value));
				}
			}

			if (is_array($result = parent::DB(sprintf('SELECT * FROM %s%s;', $table, (count($id) > 0) ? (' WHERE ' . implode(' AND ', $id)) : ''))) === true)
			{
				foreach ($result as $key => $value)
				{
					foreach (preg_grep('~^id_~', array_keys($value)) as $data)
					{
						$result[$key][$data] = parent::Value(parent::DB(sprintf('SELECT * FROM %s WHERE id LIKE ? LIMIT 1;', substr($data, 3)), $value[$data]), 0, $value[$data]);
					}
				}

				return $result;
			}
		}

		return false;
	}

	public static function Quote($data)
	{
		if (is_object(parent::DB()) === true)
		{
			return (is_array($data) === true) ? parent::DB()->quote($data) : array_map(array(parent::DB(), 'quote'), $data);
		}

		return false;
	}

	public static function Set($table, $id = null, $data = null)
	{
		if (is_object(parent::DB()) === true)
		{
			$data = (array) $data;

			if (isset($id) === true)
			{
				if (is_array($id) !== true)
				{
					$id = array('id' => $id);
				}

				foreach ($id as $key => $value)
				{
					$id[$key] = sprintf('%s LIKE %s', $key, parent::DB()->quote($value));
				}

				if (count($data) > 0)
				{
					foreach ($data as $key => $value)
					{
						$data[$key] = sprintf('%s = %s', $key, parent::DB()->quote($value));
					}

					return parent::DB(sprintf('UPDATE %s SET %s WHERE %s;', $table, implode(', ', $data), implode(' AND ', $id)));
				}

				return parent::DB(sprintf('DELETE FROM %s WHERE %s;', $table, implode(' AND ', $id)));
			}

			else if (is_null($id) === true)
			{
				foreach ($data as $key => $value)
				{
					$data[$key] = parent::DB()->quote($value);
				}

				return parent::DB(sprintf('REPLACE INTO %s (%s) VALUES (%s);', $table, implode(', ', array_keys($data)), implode(', ', $data)));
			}
		}

		return false;
	}

	public static function Tick($data)
	{
		if (is_object(parent::DB()) === true)
		{
			$data = preg_replace('~[`"]+~', '', $data);

			if (strcmp('mysql', parent::DB()->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0)
			{
				return str_ireplace(' `AS` ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '`$1`', $data));
			}

			return str_ireplace(' "AS" ', ' AS ', preg_replace('~\b(\w+)\b(?![(])~', '"$1"', $data));
		}

		return $data;
	}
}

?>