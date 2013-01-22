<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Date extends phunction
{
	public function __construct()
	{
	}

	public static function Age($date = 'now')
	{
		if (($date = parent::Date('Ymd', $date, true)) !== false)
		{
			return intval(substr(parent::Date('Ymd', 'now', true) - $date, 0, -4));
		}

		return false;
	}

	public static function Birthday($date = 'now')
	{
		if (($date = parent::Date('U', $date, true, sprintf('+%u years', self::Age($date)))) !== false)
		{
			if (($date -= parent::Date('U', 'today', true)) < 0)
			{
				$date = parent::Date('U', '@' . $date, true, '+1 year');
			}

			return round($date / 86400);
		}

		return false;
	}

	public static function Calendar($date = 'now', $events = null)
	{
		$result = array();

		if (($date = parent::Date('Ym01', $date, true)) !== false)
		{
			if (empty($result) === true)
			{
				$date = parent::Date('Ymd', $date, true, 'this week', '-1 day');
			}

			while (count($result, COUNT_RECURSIVE) < 48)
			{
				if (($date = parent::Date('DATE', $date, true, '+1 day')) !== false)
				{
					$result[parent::Date('W', $date, true)][$date] = parent::Value($events, $date, null);
				}
			}
		}

		return $result;
	}

	public static function Difference($since, $until = 'now', $keys = 'year|month|week|day|hour|minute|second')
	{
		$date = array(parent::Date('U', $since, true), parent::Date('U', $until, true));

		if ((in_array(false, $date, true) !== true) && (sort($date, SORT_NUMERIC) === true))
		{
			$result = array_fill_keys(preg_replace('~s$~i', '', explode('|', $keys)), 0);

			foreach (preg_grep('~^(?:year|month)~i', $result) as $key => $value)
			{
				while ($date[1] >= strtotime(sprintf('+%u %s', $value + 1, $key), $date[0]))
				{
					++$value;
				}

				$date[0] = strtotime(sprintf('+%u %s', $result[$key] = $value, $key), $date[0]);
			}

			foreach (preg_grep('~^(?:year|month)~i', $result, PREG_GREP_INVERT) as $key => $value)
			{
				if (($value = intval(abs($date[0] - $date[1]) / strtotime(sprintf('%u %s', 1, $key), 0))) > 0)
				{
					$date[0] = strtotime(sprintf('+%u %s', $result[$key] = $value, $key), $date[0]);
				}
			}

			return array_change_key_case($result, CASE_LOWER);
		}

		return false;
	}

	public static function Frequency($date = 'now')
	{
		if (($date = parent::Date('U', $date, true)) !== false)
		{
			if (($date = abs($_SERVER['REQUEST_TIME'] - $date)) != 0)
			{
				$frequency = array
				(
					3600 => 'hourly',
					86400 => 'daily',
					604800 => 'weekly',
					2592000 => 'monthly',
				);

				foreach ($frequency as $key => $value)
				{
					if ($date <= $key)
					{
						return $value;
					}
				}

				return 'yearly';
			}

			return 'always';
		}

		return false;
	}

	public static function Relative($date = 'now')
	{
		if (is_array($result = self::Difference($date)) === true)
		{
			if (count($result = array_filter($result)) > 0)
			{
				$result = array
				(
					'%u ' . key($result) . ' ago',
					'%u ' . key($result) . 's ago',
					current($result),
				);

				if (parent::Date('U', $date, true) > $_SERVER['REQUEST_TIME'])
				{
					$result = str_replace(' ago', ' from now', $result);
				}

				return $result;
			}

			return 'just now';
		}

		return false;
	}

	public static function Timezones($country = null, $continent = null)
	{
		$result = array();

		if (is_array($timezones = DateTimeZone::listIdentifiers()) === true)
		{
			if ((strlen($country) == 2) && (defined('DateTimeZone::PER_COUNTRY') === true))
			{
				$timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country);
			}

			foreach (preg_grep('~' . preg_quote($continent, '~') . '/~i', $timezones) as $id)
			{
				$timezone = new DateTimeZone($id);
				$transitions = $timezone->getTransitions();

				while ((isset($result[$id]) !== true) && (is_null($transition = array_pop($transitions)) !== true))
				{
					$result[$id] = ($transition['isdst'] !== true) ? $transition['offset'] : null;
				}
			}

			if (array_multisort($result, SORT_NUMERIC, preg_replace('~^[^/]+/~', '', array_keys($result)), SORT_REGULAR, $result) === true)
			{
				foreach ($result as $key => $value)
				{
					$result[$key] = sprintf('(UTC %+03d:%02u) %s', $value / 3600, abs($value) % 3600 / 60, ltrim(strstr($key, '/'), '/'));
				}
			}
		}

		return str_replace(array(' +00:00', '_', '/'), array('', ' ', ' - '), $result);
	}

	public static function Zodiac($date = 'now')
	{
		if (($date = parent::Date('md', $date, true)) !== false)
		{
			$zodiac = array
			(
				'1222' => 'Capricorn',
				'1122' => 'Sagittarius',
				'1023' => 'Scorpio',
				'0923' => 'Libra',
				'0823' => 'Virgo',
				'0723' => 'Leo',
				'0621' => 'Cancer',
				'0521' => 'Gemini',
				'0421' => 'Taurus',
				'0321' => 'Aries',
				'0220' => 'Pisces',
				'0121' => 'Aquarius',
				'0101' => 'Capricorn',
			);

			foreach ($zodiac as $key => $value)
			{
				if ($key <= $date)
				{
					return $value;
				}
			}
		}

		return false;
	}
}
