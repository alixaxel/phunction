<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Math_Mean extends phunction_Math
{
	public function __construct()
	{
	}

	public static function Arithmetic()
	{
		if (count($arguments = parent::Flatten(func_get_args())) > 0)
		{
			$result = 0;

			foreach ($arguments as $argument)
			{
				$result += $argument;
			}

			return ($result / count($arguments));
		}

		return 0;
	}

	public static function Geometric()
	{
		if (count($arguments = parent::Flatten(func_get_args())) > 0)
		{
			$result = 1;

			foreach ($arguments as $argument)
			{
				$result *= $argument;
			}

			return pow($result, 1 / count($arguments));
		}

		return 0;
	}

	public static function Harmonic()
	{
		if (count($arguments = parent::Flatten(func_get_args())) > 0)
		{
			if (round($result = array_sum(array_map('pow', $arguments, array_fill(0, count($arguments), -1))), ini_get('precision')) != 0)
			{
				return (count($arguments) / $result);
			}
		}

		return 0;
	}
}
