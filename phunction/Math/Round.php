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
		if ($precision == 0)
		{
			return self::Multiple($number, pow(10, strlen(preg_replace('~[.].+|[^0]~', '', $precision))), $callback);
		}

		return self::Multiple($number - abs($precision), pow(10, intval(log(abs($precision), 10) + 1)), $callback) + abs($precision);
	}

	public static function Multiple($number, $precision, $callback = 'round')
	{
		if ($precision == 0)
		{
			return 0;
		}

		return call_user_func($callback, $number / $precision) * $precision;
	}

	public static function Significant($number, $precision, $callback = 'round')
	{
		if ($precision == 0)
		{
			return 0;
		}

		return self::Multiple($number, pow(10, intval(log($number, 10) + 1) - abs($precision)), $callback);
	}
}

?>