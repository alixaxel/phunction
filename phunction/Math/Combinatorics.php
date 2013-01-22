<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Math_Combinatorics extends phunction_Math
{
	public function __construct()
	{
	}

	public static function Binomial($n, $k)
	{
		if ($n >= $k)
		{
			$result = 1;

			for ($i = 0, $k = min($k, $n - $k); $i < $k; ++$i)
			{
				$result *= ($n - $i) / ($i + 1);
			}

			return $result;
		}

		return 0;
	}

	public static function Combination($n, $k, $data = null, $repetitions = false)
	{
		if (($k > 0) && ($n >= $k))
		{
			if (isset($data) === true)
			{
				$x = $r = 0;

				if (is_array($data) === true)
				{
					sort($data, SORT_NUMERIC);

					for ($i = 1; $i <= $k; ++$i)
					{
						$r = $n - $data[$k - $i];

						if ($r >= $i)
						{
							$x += self::Combination($r, $i);
						}
					}

					return self::Combination($n, $k) - $x;
				}

				else if (($data > 0) && ($data <= self::Combination($n, $k)))
				{
					$result = array_fill(0, $k, 0);

					for ($i = 0; $i < ($k - 1); ++$i)
					{
						if ($i > 0)
						{
							$result[$i] = $result[$i - 1];
						}

						while ($x < $data)
						{
							$result[$i]++; $x += $r = self::Combination($n - $result[$i], ($k - 1) - $i);
						}

						$x -= $r;
					}

					$result[$k - 1] = $data - $x;

					if (array_key_exists($k - 2, $result) === true)
					{
						$result[$k - 1] += $result[$k - 2];
					}

					return $result;
				}
			}

			else if (is_null($data) === true)
			{
				if ($repetitions === true)
				{
					return self::Binomial($n + $k - 1, $k);
				}

				return self::Binomial($n, $k);
			}
		}

		return false;
	}

	public static function Permutation($n, $k, $data = null, $repetitions = false)
	{
		if (($k > 0) && ($n >= $k))
		{
			if (isset($data) === true)
			{
				$factoriadic = array();

				if (is_array($data) === true)
				{
					$result = 0;
					$charset = range(1, $n);

					foreach (array_pad($data, $n, 0) as $key => $value)
					{
						array_splice($charset, $factoriadic[$n - $key - 1] = array_search($value, $charset), 1);
					}

					foreach (array_filter($factoriadic) as $key => $value)
					{
						$result += self::Permutation($key, $key) * $value;
					}

					if ($k < $n)
					{
						$result /= self::Permutation($n - $k, $n - $k);
					}

					return ++$result;
				}

				else if (($data > 0) && ($data <= self::Permutation($n, $k)))
				{
					$data--;
					$result = array();

					if ($k < $n)
					{
						$data *= self::Permutation($n - $k, $n - $k);
					}

					for ($j = 1; $j <= $n; ++$j)
					{
						$factoriadic[$n - $j] = ($data % $j) + 1; $data /= $j;
					}

					for ($i = $n - 1; $i >= 0; --$i)
					{
						$result[$i] = $factoriadic[$i];

						for ($j = $i + 1; $j < $n; ++$j)
						{
							if ($result[$j] >= $result[$i])
							{
								++$result[$j];
							}
						}
					}

					return array_reverse(array_slice($result, 0 - $k));
				}
			}

			else if (is_null($data) === true)
			{
				if ($repetitions === true)
				{
					return pow($n, $k);
				}

				return array_product(range($n - $k + 1, $n));
			}
		}

		return false;
	}

	public static function Translate() // TODO
	{
	}
}
