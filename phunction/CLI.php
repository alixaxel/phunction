<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_CLI extends phunction
{
	public function __construct()
	{
	}

	public static function Argument($key = null, $default = false)
	{
		if (array_key_exists('argv', $GLOBALS) === true)
		{
			return parent::Value($GLOBALS['argv'], $key, $default);
		}

		return $default;
	}

	public static function Call($command, $arguments = null, $delimiter = ' ')
	{
		foreach (($arguments = (array) $arguments) as $key => $value)
		{
			if (isset($value) === true)
			{
				$value = $delimiter . escapeshellarg($value);
			}

			$arguments[$key] = (is_int($key) === true) ? trim($value) : $key . $value;
		}

		return escapeshellcmd(trim(sprintf('%s %s', $command, implode(' ', $arguments))));
	}

	public static function Error($string, $eol = true)
	{
		if (defined('STDERR') === true)
		{
			if ($eol === true)
			{
				$string .= PHP_EOL;
			}

			return fwrite(STDERR, $string);
		}

		return false;
	}

	public static function Format($string, $foreground = null, $background = null, $extra = null)
	{
		if (strncasecmp('WIN', PHP_OS, 3) !== 0)
		{
			$format = array(0 => 'reset', 1 => 'bold', 4 => 'underline', 7 => 'negative');
			$colors = array_merge(array_flip(explode('|', 'black|red|green|yellow|blue|magenta|cyan|white')), array('default' => 9));

			foreach (array('foreground', 'background') as $key)
			{
				$$key = parent::Value($colors, strtolower($$key), 9);
			}

			if (strlen($extra = implode(';', array_keys(array_intersect($format, explode('|', $extra))))) == 0)
			{
				$extra = 0;
			}

			return sprintf('%c[%s;%u;%um%s%c[0m', 27, $extra, $foreground + 30, $background + 40, $string, 27);
		}

		return $string;
	}

	public static function Read($bytes = null, $format = null)
	{
		if (defined('STDIN') === true)
		{
			$result = (isset($bytes) === true) ? fread(STDIN, $bytes) : rtrim(fgets(STDIN), PHP_EOL);

			if (($result !== false) && (isset($format) === true))
			{
				return sscanf($result, $format);
			}

			return $result;
		}

		return false;
	}

	public static function Write($string, $eol = true)
	{
		if (defined('STDOUT') === true)
		{
			if ($eol === true)
			{
				$string .= PHP_EOL;
			}

			return fwrite(STDOUT, $string);
		}

		return false;
	}
}

?>