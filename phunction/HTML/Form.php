<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.12.18 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
**/

class phunction_HTML_Form extends phunction_HTML
{
    public function __construct()
	{
	}

	public static function Checkbox($id, $value = null, $default = null)
	{
		$result = array('type' => 'checkbox', 'name' => $id, 'value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['checked'] = true;
		}

		return $result;
	}

	public static function Input($id, $default = null, $type = 'text')
	{
		$result = array('type' => $type, 'name' => $id, 'value' => $default);

		if (array_key_exists($id, $_REQUEST) === true)
		{
			$result['value'] = htmlspecialchars_decode(parent::Value($_REQUEST, $id));
		}

		return $result;
	}

	public static function Option($id, $value = null, $default = null)
	{
		$result = array('value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['selected'] = true;
		}

		return $result;
	}

	public static function Radio($id, $value = null, $default = null)
	{
		$result = array('type' => 'radio', 'name' => $id, 'value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['checked'] = true;
		}

		return $result;
	}

	public static function Token($id, $default = null, $type = 'text')
	{
		return array('type' => 'hidden', 'name' => $id, 'value' => ph()->HTTP->Token($id));
	}
}

?>