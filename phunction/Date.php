<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.12.18 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
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
		if (($date = parent::Date('U', $date, true)) !== false)
		{
			if (($date = $_SERVER['REQUEST_TIME'] - $date) != 0)
			{
				$units = array
				(
					31536000 => 'year',
					2592000 => 'month',
					604800 => 'week',
					86400 => 'day',
					3600 => 'hour',
					60 => 'minute',
					1 => 'second',
				);

				foreach ($units as $key => $value)
				{
					if (($result = intval(abs($date) / $key)) >= 1)
					{
						return str_replace(' ago', ($date >= 1) ? ' ago' : ' from now', array('%u ' . $value . ' ago', '%u ' . $value . 's ago', $result));
					}
				}
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
			$timestamp = parent::Date('U', 'now', null, '-6 months');

			if ((strlen($country) == 2) && (defined('DateTimeZone::PER_COUNTRY') === true))
			{
				$timezones = DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, $country);
			}

			foreach (preg_grep('~' . preg_quote($continent, '~') . '/~i', $timezones) as $id)
			{
				$timezone = new DateTimeZone($id);

				if (is_array($transitions = $timezone->getTransitions()) === true)
				{
					while ((isset($result[$id]) !== true) && (is_null($transition = array_shift($transitions)) !== true))
					{
						$result[$id] = (($transition['isdst'] !== true) && ($transition['ts'] >= $timestamp)) ? $transition['offset'] : null;
					}
				}
			}

			if (array_multisort($result, SORT_NUMERIC, preg_replace('~^[^/]+/~', '', array_keys($result)), SORT_REGULAR, $result) === true)
			{
				foreach ($result as $key => $value)
				{
					$result[$key] = sprintf('(GMT %+03d:%02u) %s', $value / 3600, abs($value) % 3600 / 60, ltrim(strstr($key, '/'), '/'));
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

?>