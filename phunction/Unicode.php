<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.12.18 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
**/

class phunction_Unicode extends phunction
{
    public function __construct()
	{
	}

	public static function chr($string)
	{
		if (function_exists('mb_convert_encoding') !== true)
		{
			return html_entity_decode('&#' . $string . ';', ENT_QUOTES, 'UTF-8');
		}

		return mb_convert_encoding('&#' . $string . ';', 'UTF-8', 'HTML-ENTITIES');
	}

	public static function count_chars($string)
	{
		return array_count_values(self::str_split($string));
	}

	public static function lcfirst($string)
	{
		return self::strtolower(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	public static function lcwords($string, $search = null)
	{
		return implode('', array_map('self::lcfirst', ph()->Text->Split($string, '[\s.!?¡¿' . preg_quote($search, '~') . ']+')));
	}

	public static function mb_html_entities($string)
	{
		if (function_exists('mb_convert_encoding') !== true)
		{
			return preg_replace('~([^\x00-\x7F])~eu', '"&#" . self::ord("$1") . ";"', $string);
		}

		return mb_convert_encoding($string, 'HTML-ENTITIES', 'UTF-8');
	}

	public static function ord($string)
	{
		return parent::Value(@unpack('N', iconv('UTF-8', 'UCS-4BE', $string)), 1);
	}

	public static function str_ireplace($string, $search, $replace)
	{
		$search = (array) $search;

		foreach ($search as $key => $value)
		{
			$search[$key] = sprintf('~%s~iu', preg_quote($value, '~'));
		}

		return preg_replace($search, array_map('preg_quote', (array) $replace), $string);
	}

	public static function str_shuffle($string)
	{
		if (count($string = self::str_split($string)) > 1)
		{
			shuffle($string);
		}

		return implode('', $string);
	}

	public static function str_split($string, $length = 1)
	{
		$string = preg_split('~~u', $string, null, PREG_SPLIT_NO_EMPTY);

		if ($length > 1)
		{
			$string = array_map('implode', array_chunk($string, $length));
		}

		return $string;
	}

	public static function str_word_count($string, $format = 0, $search = null)
	{
		$string = preg_split('~[^\p{L}\p{Mn}\p{Pd}\'\x{2019}' . preg_quote($search, '~') . ']+~u', $string, null, PREG_SPLIT_NO_EMPTY);

		if ($format == 0)
		{
			return count($string);
		}

		return $string;
	}

	public static function strcasecmp($string, $search)
	{
		return strcmp(self::strtolower($string), self::strtolower($search));
	}

	public static function stripos($string, $search, $offset = 0)
	{
		$string = self::substr($string, $offset);
		$result = ph()->Text->Regex($string, preg_quote($search, '~'), array(0, 0, 1), 'iu', PREG_OFFSET_CAPTURE);

		if ($result !== false)
		{
			$result = self::strlen(substr($string, 0, $result));
		}

		return $result;
	}

	public static function stristr($string, $search, $before = false)
	{
		if (($result = self::stripos($string, $search)) !== false)
		{
			$result = ($before !== true) ? self::substr($string, $result) : self::substr($string, 0, $result);
		}

		return $result;
	}

	public static function strlen($string)
	{
		return strlen(utf8_decode($string));
	}

	public static function strpos($string, $search, $offset = 0)
	{
		$string = self::substr($string, $offset);
		$result = ph()->Text->Regex($string, preg_quote($search, '~'), array(0, 0, 1), 'u', PREG_OFFSET_CAPTURE);

		if ($result !== false)
		{
			$result = self::strlen(substr($string, 0, $result));
		}

		return $result;
	}

	public static function strrev($string)
	{
		return implode('', array_reverse(self::str_split($string)));
	}

	public static function strstr($string, $search, $before = false)
	{
		if (($result = self::strpos($string, $search)) !== false)
		{
			$result = ($before !== true) ? self::substr($string, $result) : self::substr($string, 0, $result);
		}

		return $result;
	}

	public static function strtolower($string)
	{
		if (function_exists('mb_strtolower') !== true)
		{
			foreach (preg_grep('~\p{Lu}~u', $string = self::str_split($string)) as $key => $value)
			{
				if (preg_match('~^' . preg_quote($string[$key], '~') . '$~iu', $value = self::chr(self::ord($value) + 32)) > 0)
				{
					$string[$key] = $value;
				}
			}

			return implode('', $string);
		}

		return mb_strtolower($string, 'UTF-8');
	}

	public static function strtoupper($string)
	{
		if (function_exists('mb_strtoupper') !== true)
		{
			foreach (preg_grep('~\p{Ll}~u', $string = self::str_split($string)) as $key => $value)
			{
				if (preg_match('~^' . preg_quote($string[$key], '~') . '$~iu', $value = self::chr(self::ord($value) - 32)) > 0)
				{
					$string[$key] = $value;
				}
			}

			return implode('', $string);
		}

		return mb_strtoupper($string, 'UTF-8');
	}

	public static function substr($string, $offset = null, $length = null)
	{
		return implode('', array_slice(self::str_split($string), $offset, $length));
	}

	public static function substr_count($string, $search, $offset = 0, $length = null)
	{
		return substr_count(self::substr($string, $offset, $length), $search);
	}

	public static function ucfirst($string)
	{
		return self::strtoupper(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	public static function ucwords($string, $search = null)
	{
		return implode('', array_map('self::ucfirst', ph()->Text->Split($string, '[\s.!?¡¿' . preg_quote($search, '~') . ']+')));
	}
}

?>