<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Math_Round extends phunction_Math
{
	public function __construct()
	{
	}

	public static function Digit($number, $precision, $callback = 'round')
	{
		if (($precision != 0) && ($precision = abs($precision)))
		{
			return self::Multiple($number - $precision, pow(10, intval(log($precision, 10) + 1)), $callback) + $precision;
		}

		return self::Multiple($number, pow(10, strspn(strpbrk($precision, '0'), '0')), $callback);
	}

	public static function Multiple($number, $precision, $callback = 'round')
	{
		if (($precision != 0) && ($precision = abs($precision)))
		{
			return call_user_func($callback, $number / $precision) * $precision;
		}

		return 0;
	}

	public static function Significant($number, $precision, $callback = 'round')
	{
		if (($precision != 0) && ($precision = abs($precision)))
		{
			return self::Multiple($number, pow(10, intval(log($number, 10) + 1) - $precision), $callback);
		}

		return 0;
	}
}

?>