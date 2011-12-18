<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.12.18 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
**/

class phunction_Text extends phunction
{
    public function __construct()
	{
	}

	public static function Comify($array, $last = ' and ')
	{
		if (count($array = array_filter(array_unique((array) $array), 'strlen')) >= 3)
		{
			$array = array(implode(', ', array_slice($array, 0, -1)), implode('', array_slice($array, -1)));
		}

		return implode($last, $array);
	}

	public static function Crypt($string, $key)
	{
		if (extension_loaded('mcrypt') === true)
		{
			$key = md5($key);
			$result = preg_replace('~[0-9a-f]{40}$~', '', $string);

			if (strcmp(sha1($result . $key), preg_replace('~^[0-9a-zA-Z/+]*={0,2}([0-9a-f]{40})$~', '$1', $string)) === 0)
			{
				$result = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($result), MCRYPT_MODE_CBC, md5($key)), "\0");
			}

			else if (preg_match('~^[a-zA-Z0-9/+]*={0,2}$~', $result = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, md5($key)))) > 0)
			{
				$result .= sha1($result . $key);
			}

			return $result;
		}

		return false;
	}

	public static function Cycle()
	{
		static $i = 0;

		if (func_num_args() > 0)
		{
			return func_get_arg($i++ % func_num_args());
		}

		return $i = 0;
	}

	public static function Enclose($string, $delimiter = null)
	{
		if (strlen($string = trim($string)) > 0)
		{
			$string = sprintf('%s%s%s', $delimiter, $string, $delimiter);
		}

		return $string;
	}

	public static function Entropy($string)
	{
		return strlen(count_chars($string, 3)) * 100 / 256;
	}

	public static function GUID()
	{
		if (function_exists('com_create_guid') !== true)
		{
			$result = array();

			for ($i = 0; $i < 8; ++$i)
			{
				switch ($i)
				{
					case 3:
						$result[$i] = mt_rand(16384, 20479);
					break;

					case 4:
						$result[$i] = mt_rand(32768, 49151);
					break;

					default:
						$result[$i] = mt_rand(0, 65535);
					break;
				}
			}

			return vsprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', $result);
		}

		return trim(com_create_guid(), '{}');
	}

	public static function Hash($string, $hash = null, $salt = null, $cost = 1024, $algorithm = 'sha256')
	{
		if (extension_loaded('hash') === true)
		{
			if (empty($hash) === true)
			{
				if (empty($salt) === true)
				{
					$salt = uniqid(null, true);
				}

				if (in_array($algorithm, hash_algos()) === true)
				{
					$cost = max(1024, intval($cost));
					$result = hash($algorithm, $salt . $string);

					for ($i = 1; $i < $cost; ++$i)
					{
						$result = hash($algorithm, $result . $string);
					}

					return sprintf('%s|%u|%s|%s', $algorithm, $cost, $salt, $result);
				}
			}

			else if (count($hash = explode('|', $hash)) == 4)
			{
				return (strcmp(implode('|', $hash), self::Hash($string, null, $hash[2], $hash[1], $hash[0])) === 0);
			}
		}

		return false;
	}

	public static function Indent($string, $indent = 1)
	{
		if (strlen($indent = str_repeat("\t", intval($indent))) > 0)
		{
			$string = rtrim($indent . implode("\n" . $indent, explode("\n", $string)), "\t");
		}

		return $string;
	}

	public static function Mnemonic($mnemonic)
	{
		$result = null;
		$charset = array(str_split('aeiou'), str_split('bcdfghjklmnpqrstvwxyz'));

		for ($i = 1; $i <= $mnemonic; ++$i)
		{
			$result .= $charset[$i % 2][array_rand($charset[$i % 2])];
		}

		return $result;
	}

	public static function Name($string, $limit = true)
	{
		$regex = array
		(
			'~\s+~' => ' ',
			'~\b([DO]\'|Fitz|Ma?c)([^\b]+)\b~ei' => 'stripslashes("$1" . phunction_Unicode::ucfirst("$2"))',
			'~\b(?:b[ei]n|d[aeio]|da[ls]|de[lr]|dit|dos|e|l[ae]s?|san|v[ao]n|vel|vit)\b~ei' => 'phunction_Unicode::strtolower("$0")',
			'~\b(?:M{0,4}(?:CM|CD|D?C{0,3})(?:XC|XL|L?X{0,3})(?:IX|IV|V?I{0,3}))(?:,|$)~ei' => 'phunction_Unicode::strtoupper("$0")',
		);

		$string = preg_replace(array_keys($regex), $regex, ph()->Unicode->ucwords(ph()->Unicode->strtolower(trim($string)), "'-"));

		if (is_int($limit) === true)
		{
			$string = explode(' ', $string);
			$result = array(0 => array(), 1 => array());

			foreach (range(1, $limit) as $i)
			{
				if ($i == ceil($limit / 2) + 1)
				{
					$string = array_reverse($string);
				}

				if (is_null($name = array_shift($string)) !== true)
				{
					$name = array($name);

					if ($i != ceil($limit / 2))
					{
						while (preg_match(parent::Value(array_keys($regex), 2), current($string)) > 0)
						{
							$name = array_merge($name, (array) array_shift($string));
						}
					}

					$result[($i > ceil($limit / 2))][] = implode(' ', ($i > ceil($limit / 2)) ? array_reverse($name) : $name);
				}
			}

			$string = implode(' ', array_merge($result[0], array_reverse($result[1])));
		}

		return $string;
	}

	public static function Reduce($string, $search, $modifiers = false)
	{
		return preg_replace('~' . preg_quote($search, '~') . '+~' . $modifiers, $search, $string);
	}

	public static function Regex($string, $pattern, $key = null, $modifiers = null, $flag = PREG_PATTERN_ORDER, $default = false)
	{
		$matches = array();

		if (preg_match_all('~' . $pattern . '~' . $modifiers, $string, $matches, $flag) > 0)
		{
			if (isset($key) === true)
			{
				return ($key === true) ? $matches : parent::Value($matches, $key, $default);
			}

			return true;
		}

		return $default;
	}

	public static function Slug($string, $slug = '-', $extra = null)
	{
		return strtolower(trim(preg_replace('~[^0-9a-z' . preg_quote($extra, '~') . ']+~i', $slug, self::Unaccent($string)), $slug));
	}

	public static function Split($string, $regex = null)
	{
		return preg_split('~(' . $regex . ')~iu', $string, null, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
	}

	public static function Title($string, $except = 'a(?:nd?|s|t)?|b(?:ut|y)|en|for|i[fn]|o[fnr]|t(?:he|o)|vs?[.]?|via')
	{
		$string = self::Split($string, '[-\s]+');

		foreach (preg_grep('~[&@0-9]|\p{L}\p{Lu}|[\p{L}\p{Nd}]{3,}[.][\p{L}\p{Nd}]{2,}]~u', $string, PREG_GREP_INVERT) as $key => $value)
		{
			$string[$key] = preg_replace('~\p{L&}~eu', 'stripslashes(phunction_Unicode::strtoupper("$0"))', $value, 1);
		}

		if (strlen(implode('', $string)) > 0)
		{
			$regex = array
			(
				'~(?<!^|["&.\'\p{Pi}\p{Ps}])\b(' . $except . ')(?:[.]|\b)(?!$|[!"&.?\'\p{Pe}\p{Pf}])~eiu' => 'stripslashes(phunction_Unicode::strtolower("$0"))',
				'~([!.:;?]\s+)\b(' . $except . ')\b~eu' => 'stripslashes("$1" . phunction_Unicode::ucfirst("$2"))',
			);

			$string = preg_replace(array_keys($regex), $regex, implode('', $string));
		}

		return $string;
	}

	public static function Truncate($string, $limit, $more = '...')
	{
		if (ph()->Unicode->strlen($string = trim($string)) > $limit)
		{
			return preg_replace('~^(.{1,' . $limit . '}(?<=\S)(?=\s)|.{' . $limit . '}).*$~su', '$1', $string) . $more;
		}

		return $string;
	}

	public static function Unaccent($string)
	{
		if (strpos($string = htmlentities($string, ENT_QUOTES, 'UTF-8'), '&') !== false)
		{
			$string = html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1', $string), ENT_QUOTES, 'UTF-8');
		}

		return $string;
	}
}

?>