<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Math_BC extends phunction_Math
{
	public function __construct()
	{
	}

	public static function Ceil($number)
	{
		if ((strpos($number, '.') !== false) && (strpos($number = rtrim(rtrim($number, '0'), '.'), '.') !== false))
		{
			$result = 1;

			if (strncmp('-', $number, 1) === 0)
			{
				--$result;
			}

			$number = bcadd($number, $result, 0);
		}

		return $number;
	}

	public static function Floor($number)
	{
		if ((strpos($number, '.') !== false) && (strpos($number = rtrim(rtrim($number, '0'), '.'), '.') !== false))
		{
			$result = 0;

			if (strncmp('-', $number, 1) === 0)
			{
				--$result;
			}

			$number = bcadd($number, $result, 0);
		}

		return $number;
	}

	public static function Round($number, $precision = 0)
	{
		if ((strpos($number, '.') !== false) && (strpos($number = rtrim(rtrim($number, '0'), '.'), '.') !== false))
		{
			$result = sprintf('0.%s5', str_repeat('0', $precision));

			if (strncmp('-', $number, 1) === 0)
			{
				$result = sprintf('-%s', $result);
			}

			$number = bcadd($number, $result, $precision);

			if (($precision > 0) && (strpos($number, '.') !== false))
			{
				$number = rtrim(rtrim($number, '0'), '.');
			}
		}

		return $number;
	}
}

?>