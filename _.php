<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.2.11 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
**/

class phunction
{
	public static $id = null;

	public function __construct()
	{
		ob_start();
		set_time_limit(0);
		error_reporting(-1);
		ignore_user_abort(true);
		ini_set('html_errors', 0);
		ini_set('display_errors', 1);
		date_default_timezone_set('GMT');

		if (headers_sent() !== true)
		{
			header('Content-Type: text/html; charset=utf-8');

			if (strncasecmp('www.', $_SERVER['HTTP_HOST'], 4) === 0)
			{
				self::Redirect(sprintf('%s://%s', getservbyport($_SERVER['SERVER_PORT'], 'tcp'), substr($_SERVER['HTTP_HOST'], 4) . $_SERVER['REQUEST_URI']), true);
			}

			else if (strlen(session_id()) == 0)
			{
				session_start();
			}
		}

		if (version_compare(PHP_VERSION, '5.3.0', '<') === true)
		{
			set_magic_quotes_runtime(false);
		}

		else if ((get_magic_quotes_gpc() === 1) && (version_compare(PHP_VERSION, '6.0.0', '<') === true))
		{
			$_GET = json_decode(stripslashes(json_encode($_GET, JSON_HEX_APOS | JSON_HEX_QUOT)), true);
			$_POST = json_decode(stripslashes(json_encode($_POST, JSON_HEX_APOS | JSON_HEX_QUOT)), true);
			$_COOKIE = json_decode(stripslashes(json_encode($_COOKIE, JSON_HEX_APOS | JSON_HEX_QUOT)), true);
			$_REQUEST = json_decode(stripslashes(json_encode($_REQUEST, JSON_HEX_APOS | JSON_HEX_QUOT)), true);
		}
	}

	public function __get($key)
	{
		$class = __CLASS__ . '_' . $key;

		if (class_exists($class, false) === true)
		{
			return $this->$key = new $class();
		}

		return false;
	}

	public static function ACL($resource, $action, $role, $access = null)
	{
		static $result = array();

		if (is_bool($access) === true)
		{
			$result[$resource][$action][$role] = $access;
		}

		return self::Value($result, array($resource, $action, $role));
	}

	public static function Cache($key, $value = null, $ttl = 60)
	{
		if (extension_loaded('apc') === true)
		{
			if (isset($value) === true)
			{
				apc_store($key, $value, intval($ttl));
			}

			return (apc_exists($key) === true) ? apc_fetch($key) : false;
		}

		else if (extension_loaded('xcache') === true)
		{
			if (isset($value) === true)
			{
				xcache_set($key, $value, intval($ttl));
			}

			return (xcache_isset($key) === true) ? xcache_get($key) : false;
		}

		return (isset($value) === true) ? $value : false;
	}

	public static function Coalesce()
	{
		$arguments = self::Flatten(func_get_args());

		foreach ($arguments as $argument)
		{
			if (isset($argument) === true)
			{
				return $argument;
			}
		}

		return null;
	}

	public static function Date($format = 'U', $time = 'now')
	{
		$result = date_create($time, timezone_open(date_default_timezone_get()));

		if (is_object($result) === true)
		{
			foreach (array_filter(array_slice(func_get_args(), 2), 'strtotime') as $argument)
			{
				date_modify($result, $argument);
			}

			return date_format($result, str_replace(array('DATETIME', 'DATE', 'TIME', 'YEAR'), array('DATE TIME', 'Y-m-d', 'H:i:s', 'Y'), $format));
		}

		return false;
	}

	public static function DB($query = null)
	{
		static $db = array();
		static $result = array();

		if (isset($db[self::$id], $query) === true)
		{
			$hash = md5($query);

			if (empty($result[self::$id][$hash]) === true)
			{
				$result[self::$id][$hash] = $db[self::$id]->prepare($query);
			}

			if (is_object($result[self::$id][$hash]) === true)
			{
				if ($result[self::$id][$hash]->execute(array_slice(func_get_args(), 1)) === true)
				{
					if (preg_match('~^(?:INSERT|REPLACE)\b~i', $query) > 0)
					{
						return $db[self::$id]->lastInsertId();
					}

					else if (preg_match('~^(?:UPDATE|DELETE)\b~i', $query) > 0)
					{
						return $result[self::$id][$hash]->rowCount();
					}

					else if (preg_match('~^(?:SELECT|EXPLAIN|SHOW|PRAGMA)\b~i', $query) > 0)
					{
						return $result[self::$id][$hash]->fetchAll(PDO::FETCH_ASSOC);
					}

					return true;
				}
			}

			return false;
		}

		else if (preg_match('~^(?:mysql|pgsql):~', $query) > 0)
		{
			$db[self::$id] = new PDO(preg_replace('~^([^:]+):/{0,2}([^:/]+)(?::(\d+))?/(\w+)/?$~', '$1:host=$2;port=$3;dbname=$4', $query), func_get_arg(1), func_get_arg(2));

			if (preg_match('~^mysql:~', $query) > 0)
			{
				self::DB(self::$id, 'SET time_zone = ?;', date_default_timezone_get());
				self::DB(self::$id, 'SET NAMES ? COLLATE ?;', 'utf8', 'utf8_unicode_ci');
			}
		}

		else if (preg_match('~^(?:sqlite|firebird):~', $query) > 0)
		{
			$db[self::$id] = new PDO(preg_replace('~^([^:]+):(?:/{2})?(.+)$~', '$1:$2', $query));
		}

		return (isset($db[self::$id]) === true) ? $db[self::$id] : false;
	}

	public static function Dump()
	{
		foreach (func_get_args() as $argument)
		{
			echo '<pre style="background: #df0; margin: 5px; padding: 5px; text-align: left;">';

			if (is_resource($argument) === true)
			{
				echo sprintf('%s (#%u)', get_resource_type($argument), $argument);
			}

			else if ((is_array($argument) === true) || (is_object($argument) === true))
			{
				echo htmlspecialchars(print_r($argument, true), ENT_QUOTES);
			}

			else
			{
				echo htmlspecialchars(stripslashes(preg_replace("~^'|'$~", '', var_export($argument, true))), ENT_QUOTES);
			}

			echo '</pre>' . "\n";
		}
	}

	public static function Export($name, $data)
	{
		$result = null;

		if (is_scalar($data) === true)
		{
			$result .= sprintf("%s = %s;\n", $name, var_export($data, true));
		}

		else if (is_array($data) === true)
		{
			$result .= sprintf("%s = array();\n", $name);

			foreach ($data as $key => $value)
			{
				$result .= self::Export($name . '[' . var_export($key, true) . ']', $value);
			}

			if (array_keys($data) === array_keys(array_keys($data)))
			{
				$result = preg_replace('~^' . sprintf(preg_quote($name . '[%s]', '~'), '\d+') . '~m', $name . '[]', $result);
			}
		}

		else if (is_object($data) === true)
		{
			$result .= sprintf("%s = %s;\n", $name, preg_replace('~\n[[:space:]]*~', '', var_export($data, true)));
		}

		else
		{
			$result .= sprintf("%s = %s;\n", $name, 'null');
		}

		return $result;
	}

	public static function Flatten($array)
	{
		$result = array();

		if (is_array($array) === true)
		{
			foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($array)) as $value)
			{
				$result[] = $value;
			}
		}

		return $result;
	}

	public static function Input($input, $filters = null, $callbacks = null, $required = true)
	{
		if (array_key_exists($input, $_REQUEST) === true)
		{
			$result = array_map('trim', (array) $_REQUEST[$input]);

			if (($required === true) || (count($result) > 0))
			{
				foreach (array_filter(explode('|', $filters), 'is_callable') as $filter)
				{
					if (in_array(false, array_map($filter, $result)) === true)
					{
						return false;
					}
				}
			}

			foreach (array_filter(explode('|', $callbacks), 'is_callable') as $callback)
			{
				$result = array_map($callback, $result);
			}

			return (is_array($_REQUEST[$input]) === true) ? $result : $result[0];
		}

		return ($required === true) ? false : null;
	}

	public static function Mongo($query = null)
	{
		static $db = array();

		if (extension_loaded('mongo') === true)
		{
			if (isset($db[self::$id], $query) === true)
			{
				if (is_a($db[self::$id], 'MongoDB') === true)
				{
					return $result->selectCollection($query);
				}

				else if (is_a($db[self::$id], 'Mongo') === true)
				{
					$db[self::$id] = $db[self::$id]->selectDB($query);
				}
			}

			else if (preg_match('~^mongodb:~', $query) > 0)
			{
				$db[self::$id] = new Mongo($query, array('connect' => true));
			}
		}

		return (isset($db[self::$id]) === true) ? $db[self::$id] : false;
	}

	public static function Object($object)
	{
		static $result = array();

		if (class_exists($object, false) === true)
		{
			if (array_key_exists($object, $result) !== true)
			{
				$result[$object] = new $object();
			}

			return $result[$object];
		}

		else if (is_file($object . '.php') === true)
		{
			$class = basename($object);

			if (array_key_exists($class, $result) !== true)
			{
				if (class_exists($class, false) !== true)
				{
					require($object . '.php');
				}

				$result[$class] = new $class();
			}

			return $result[$class];
		}

		return false;
	}

	public static function Redirect($url, $permanent = false)
	{
		if (headers_sent() !== true)
		{
			header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
		}

		exit();
	}

	public static function Route($route, $class = null, $function = null, $method = null, $throttle = null)
	{
		static $result = null;

		if (strlen($method) * strcasecmp($method, $_SERVER['REQUEST_METHOD']) == 0)
		{
			$matches = array();

			if (is_null($result) === true)
			{
				$result = rtrim(preg_replace('~/+~', '/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME']))), '/');
			}

			if (preg_match('~' . rtrim(str_replace(array(':any:', ':num:'), array('[^/]+', '[0-9]+'), $route), '/') . '$~i', $result, $matches) > 0)
			{
				if (intval($throttle) > 0)
				{
					usleep(intval(floatval($throttle) * 1000000));
				}

				if (empty($class) === true)
				{
					if (empty($function) === true)
					{
						return true;
					}

					exit(call_user_func_array($function, array_slice($matches, 1)));
				}

				exit(call_user_func_array(array(self::Object($class), $function), array_slice($matches, 1)));
			}
		}

		return false;
	}

	public static function Segment($key, $default = false)
	{
		static $result = null;

		if (is_null($result) === true)
		{
			$result = array_values(array_filter(explode('/', substr($_SERVER['PHP_SELF'], strlen($_SERVER['SCRIPT_NAME']))), 'strlen'));
		}

		return self::Value($result, (is_int($key) === true) ? $key : (array_search($key, $result) + 1), $default);
	}

	public static function Sort($array, $unicode = true, $natural = true, $reverse = false)
	{
		natcasesort($array);

		if ($reverse === true)
		{
			$array = array_reverse($array, true);
		}

		return $array;
	}

	public static function Tag($tag, $content = null)
	{
		$tag = htmlspecialchars(strtolower(trim($tag)));
		$arguments = array_filter(array_slice(func_get_args(), 2), 'is_array');
		$attributes = (empty($arguments) === true) ? array() : call_user_func_array('array_merge', $arguments);

		if (count($attributes) > 0)
		{
			ksort($attributes);

			foreach ($attributes as $key => $value)
			{
				$attributes[$key] = sprintf(' %s="%s"', htmlspecialchars($key), ($value === true) ? htmlspecialchars($key) : htmlspecialchars($value));
			}
		}

		if (in_array($tag, array('br', 'hr', 'img', 'input', 'link', 'meta')) === true)
		{
			return sprintf('<%s%s />', $tag, implode('', $attributes)) . "\n";
		}

		return sprintf('<%s%s>%s</%s>', $tag, implode('', $attributes), htmlspecialchars($content), $tag) . "\n";
	}

	public static function Text($single, $plural = null, $number = null, $domain = null, $path = null)
	{
		if (extension_loaded('gettext') === true)
		{
			if (defined('LC_MESSAGES') === true)
			{
				setlocale(LC_MESSAGES, 'en_US');
			}

			foreach (array('LANG', 'LANGUAGE', 'LC_ALL', 'LC_MESSAGES') as $value)
			{
				putenv(sprintf('%s=%s', $value, 'en_US'));
			}

			if (isset($path) === true)
			{
				$path = ph()->Disk->Path($path);

				foreach (glob($path . '*.mo') as $value)
				{
					bindtextdomain(basename($value, '.mo'), $path);
					bind_textdomain_codeset(basename($value, '.mo'), 'UTF-8');
				}
			}

			if (isset($domain, $single) === true)
			{
				if (isset($plural, $number) === true)
				{
					return dngettext($domain, $single, $plural, $number);
				}

				return dgettext($domain, $single);
			}
		}

		else if (isset($plural, $number) === true)
		{
			if (abs($number) !== 1)
			{
				return $plural;
			}
		}

		return $single;
	}

	public static function Throttle($ttl = 60, $exit = 60, $count = 1)
	{
		$ip = ph()->HTTP->IP();

		if ($ip !== false)
		{
			$key = array(__METHOD__, ip2long($ip));

			if (extension_loaded('apc') === true)
			{
				if (apc_exists(vsprintf('%s:%u', $key)) !== true)
				{
					apc_store(vsprintf('%s:%u', $key), 0, $ttl);
				}

				$result = apc_inc(vsprintf('%s:%u', $key), intval($count));
			}

			else if (extension_loaded('xcache') === true)
			{
				if (xcache_isset(vsprintf('%s:%u', $key)) !== true)
				{
					xcache_set(vsprintf('%s:%u', $key), 0, $ttl);
				}

				$result = xcache_inc(vsprintf('%s:%u', $key), intval($count));
			}

			if (isset($result) === true)
			{
				return ($result < $exit) ? ($result / $ttl) : true;
			}
		}

		return false;
	}

	public static function Value($data, $key = null, $default = false)
	{
		if (isset($key) === true)
		{
			foreach ((array) $key as $value)
			{
				if (is_object($data) === true)
				{
					$data = get_object_vars($data);
				}

				if ((is_array($data) !== true) || (array_key_exists($value, $data) !== true))
				{
					return $default;
				}

				$data = $data[$value];
			}
		}

		return $data;
	}

	public static function View($view, $data = null, $return = false)
	{
		if (is_file($view . '.php') === true)
		{
			extract((array) $data);

			if ($return === true)
			{
				if (ob_start() === true)
				{
					require($view . '.php');
				}

				return ob_get_clean();
			}

			require($view . '.php');
		}
	}
}

class phunction_Date extends phunction
{
	public function __construct()
	{
	}

	public static function Age($date = 'now')
	{
		$date = parent::Date('Ymd', $date);

		if ($date !== false)
		{
			return intval(substr(parent::Date('Ymd') - $date, 0, -4));
		}

		return false;
	}

	public static function Birthday($date = 'now')
	{
		$date = parent::Date('U', $date, sprintf('+%u years', self::Age($date)));

		if ($date !== false)
		{
			$date -= parent::Date('U', 'today');

			if ($date < 0)
			{
				$date = parent::Date('U', '@' . $date, '+1 year');
			}

			return round($date / 86400);
		}

		return false;
	}

	public static function Calendar($date = 'now', $events = null)
	{
		$date = parent::Date('Ym01', $date);

		if ($date !== false)
		{
			$result = array();

			if (empty($result) === true)
			{
				$date = parent::Date('Ymd', $date, 'this week', '-1 day');
			}

			while (count($result, COUNT_RECURSIVE) < 48)
			{
				$date = parent::Date('DATE', $date, '+1 day');

				if ($date !== false)
				{
					$result[parent::Date('W', $date)][parent::Date('DATE', $date)] = parent::Value($events, parent::Date('DATE', $date), null);
				}
			}

			return $result;
		}

		return false;
	}

	public static function Frequency($date = 'now')
	{
		$date = parent::Date('U', $date);

		if ($date !== false)
		{
			$date = abs($_SERVER['REQUEST_TIME'] - $date);

			if ($date !== 0)
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
		$date = parent::Date('U', $date);

		if ($date !== false)
		{
			$date = $_SERVER['REQUEST_TIME'] - $date;

			if ($date !== 0)
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
					$result = floor(abs($date) / $key);

					if ($result >= 1)
					{
						return sprintf('%u %s%s %s', $result, $value, ($result == 1) ? '' : 's', ($date >= 1) ? 'ago' : 'from now');
					}
				}
			}

			return 'just now';
		}

		return false;
	}

	public static function Zodiac($date = 'now')
	{
		$date = parent::Date('md', $date);

		if ($date !== false)
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

class phunction_DB extends phunction
{
	public function __construct()
	{
	}

	public static function Get($table, $id = null)
	{
		if (is_object(parent::DB()) === true)
		{
			if (isset($id) === true)
			{
				if (is_array($id) !== true)
				{
					$id = array('id' => $id);
				}

				foreach ($id as $key => $value)
				{
					$id[$key] = sprintf('%s LIKE %s', $key, parent::DB()->quote($value));
				}

				$data = parent::DB(sprintf('SELECT * FROM %s WHERE %s;', $table, implode(' AND ', $id)));
			}

			else if (is_null($id) === true)
			{
				$data = parent::DB(sprintf('SELECT * FROM %s;', $table));
			}

			if (is_array($data) === true)
			{
				foreach ($data as $cursor => $result)
				{
					foreach ($result as $key => $value)
					{
						if (strncmp('id_', $key, 3) === 0)
						{
							$data[$cursor][$key] = parent::Value(parent::DB(sprintf('SELECT * FROM %s WHERE id LIKE ? LIMIT 1;', substr($key, 3)), $value), 0, $value);
						}
					}
				}

				return $data;
			}
		}

		return false;
	}

	public static function Set($table, $id = null, $data = null)
	{
		if (is_object(parent::DB()) === true)
		{
			$data = (array) $data;

			if (isset($id) === true)
			{
				if (is_array($id) !== true)
				{
					$id = array('id' => $id);
				}

				foreach ($id as $key => $value)
				{
					$id[$key] = sprintf('%s LIKE %s', $key, parent::DB()->quote($value));
				}

				if (count($data) > 0)
				{
					foreach ($data as $key => $value)
					{
						$data[$key] = sprintf('%s = %s', $key, parent::DB()->quote($value));
					}

					return parent::DB(sprintf('UPDATE %s SET %s WHERE %s;', $table, implode(', ', $data), implode(' AND ', $id)));
				}

				return parent::DB(sprintf('DELETE FROM %s WHERE %s;', $table, implode(' AND ', $id)));
			}

			else if (is_null($id) === true)
			{
				foreach ($data as $key => $value)
				{
					$data[$key] = ph()->DB()->quote($value);
				}

				return parent::DB(sprintf('INSERT INTO %s (%s) VALUES (%s);', $table, implode(', ', array_keys($data)), implode(', ', $data)));
			}
		}

		return false;
	}
}

class phunction_Disk extends phunction
{
	public function __construct()
	{
	}

	public static function Chmod($path, $chmod = null)
	{
		if (file_exists($path) === true)
		{
			if (is_null($chmod) === true)
			{
				$chmod = (is_file($path) === true) ? 644 : 755;

				if (in_array(get_current_user(), explode('|', 'apache|httpd|nobody|system|webdaemon|www|www-data')) === true)
				{
					$chmod += 22;
				}
			}

			return chmod($path, octdec(intval($chmod)));
		}

		return false;
	}

	public static function Download($path, $speed = null)
	{
		if (is_file($path) === true)
		{
			$file = @fopen($path, 'rb');
			$speed = (isset($speed) === true) ? round($speed * 1024) : 524288;

			if (is_resource($file) === true)
			{
				set_time_limit(0);
				ignore_user_abort(false);

				while (ob_get_level() > 0)
				{
					ob_end_clean();
				}

				header('Expires: 0');
				header('Pragma: public');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Content-Type: application/octet-stream');
				header('Content-Length: ' . sprintf('%u', filesize($path)));
				header('Content-Disposition: attachment; filename="' . basename($path) . '"');
				header('Content-Transfer-Encoding: binary');

				while (feof($file) !== true)
				{
					ph()->HTTP->Flush(fread($file, $speed));
					ph()->HTTP->Sleep(1);
				}

				fclose($file);
			}

			exit();
		}

		return false;
	}

	public static function File($path, $content = null, $append = true, $chmod = null, $ttl = null)
	{
		if (isset($content) === true)
		{
			if (file_put_contents($path, $content, ($append === true) ? FILE_APPEND : LOCK_EX) !== false)
			{
				return self::Chmod($path, $chmod);
			}
		}

		else if (is_file($path) === true)
		{
			if ((empty($ttl) === true) || (parent::Date('U', 'now', '-' . filemtime($path) . ' seconds') <= intval($ttl)))
			{
				return file_get_contents($path);
			}

			return unlink($path);
		}

		return false;
	}

	public static function Image($input, $crop = null, $scale = null, $merge = null, $sharpen = true, $output = null)
	{
		$input = @ImageCreateFromString(@file_get_contents($input));

		if (is_resource($input) === true)
		{
			$size = array(ImageSX($input), ImageSY($input));
			$crop = array_values(array_filter(explode('/', $crop), 'is_numeric'));
			$scale = array_values(array_filter(explode('*', $scale), 'is_numeric'));

			if (count($crop) == 2)
			{
				$crop = array($size[0] / $size[1], $crop[0] / $crop[1]);

				if ($crop[0] > $crop[1])
				{
					$size[0] = round($size[1] * $crop[1]);
				}

				else if ($crop[0] < $crop[1])
				{
					$size[1] = round($size[0] / $crop[1]);
				}

				$crop = array(ImageSX($input) - $size[0], ImageSY($input) - $size[1]);
			}

			else
			{
				$crop = array(0, 0);
			}

			if (count($scale) >= 1)
			{
				if (empty($scale[0]) === true)
				{
					$scale[0] = round($scale[1] * $size[0] / $size[1]);
				}

				else if (empty($scale[1]) === true)
				{
					$scale[1] = round($scale[0] * $size[1] / $size[0]);
				}
			}

			else
			{
				$scale = array($size[0], $size[1]);
			}

			$image = ImageCreateTrueColor($scale[0], $scale[1]);

			if (is_resource($image) === true)
			{
				ImageFill($image, 0, 0, IMG_COLOR_TRANSPARENT);
				ImageSaveAlpha($image, true);
				ImageAlphaBlending($image, true);

				if (ImageCopyResampled($image, $input, 0, 0, round($crop[0] / 2), round($crop[1] / 2), $scale[0], $scale[1], $size[0], $size[1]) === true)
				{
					if ($sharpen === true)
					{
						$matrix = array(-1, -1, -1, -1, 16, -1, -1, -1, -1);

						if (function_exists('ImageConvolution') === true)
						{
							ImageConvolution($image, array_chunk($matrix, 3), array_sum($matrix), 0);
						}
					}

					if (isset($merge) === true)
					{
						$merge = @ImageCreateFromString(@file_get_contents($merge));

						if (is_resource($merge) === true)
						{
							ImageCopy($image, $merge, round(0.95 * $scale[0] - ImageSX($merge)), round(0.95 * $scale[1] - ImageSY($merge)), 0, 0, ImageSX($merge), ImageSY($merge));
						}
					}

					if (isset($output) === true)
					{
						$result = false;

						if (preg_match('~gif$~i', $output) > 0)
						{
							$output = preg_replace('~^[.]?gif$~i', '', $output);

							if (empty($output) === true)
							{
								header('Content-Type: image/gif');
							}

							$result = ImageGIF($image, $output);
						}

						else if (preg_match('~png$~i', $output) > 0)
						{
							$output = preg_replace('~^[.]?png$~i', '', $output);

							if (empty($output) === true)
							{
								header('Content-Type: image/png');
							}

							$result = ImagePNG($image, $output, 9);
						}

						else if (preg_match('~jpe?g$~i', $output) > 0)
						{
							$output = preg_replace('~^[.]?jpe?g$~i', '', $output);

							if (empty($output) === true)
							{
								header('Content-Type: image/jpeg');
							}

							$result = ImageJPEG($image, $output, 90);
						}

						return (empty($output) === true) ? $result : self::Chmod($output);
					}
				}
			}
		}

		return false;
	}

	public static function Mime($path, $magic = null)
	{
		$path = self::Path($path);

		if ($path !== false)
		{
			if (function_exists('finfo_open') === true)
			{
				$finfo = finfo_open(FILEINFO_MIME_TYPE, $magic);

				if (is_resource($finfo) === true)
				{
					$result = finfo_file($finfo, $path);
				}

				finfo_close($finfo);
			}

			else if (function_exists('mime_content_type') === true)
			{
				$result = mime_content_type($path);
			}

			else if (function_exists('exif_imagetype') === true)
			{
				$result = image_type_to_mime_type(exif_imagetype($path));
			}

			return preg_replace('~^(.+);.+$~', '$1', $result);
		}

		return false;
	}

	public static function Path($path)
	{
		if (file_exists($path) === true)
		{
			return rtrim(str_replace('\\', '/', realpath($path)), '/') . (is_dir($path) ? '/' : '');
		}

		return false;
	}

	public static function Size($path, $recursive = true)
	{
		$result = 0;

		if (is_dir($path) === true)
		{
			$path = self::Path($path);
			$files = array_diff(scandir($path), array('.', '..'));

			foreach ($files as $file)
			{
				if (is_dir($path . $file) === true)
				{
					$result += ($recursive === true) ? self::Size($path . $file, $recursive) : 0;
				}

				else if (is_file($path . $file) === true)
				{
					$result += sprintf('%u', filesize($path . $file));
				}
			}
		}

		else if (is_file($path) === true)
		{
			$result += sprintf('%u', filesize($path));
		}

		return $result;
	}

	public static function Upload($input, $output, $chmod = null)
	{
		$result = array();
		$output = self::Path($output);

		if ((is_dir($output) === true) && (array_key_exists($input, $_FILES) === true))
		{
			if (count($_FILES[$input], COUNT_RECURSIVE) == 5)
			{
				foreach ($_FILES[$input] as $key => $value)
				{
					$_FILES[$input][$key] = array($value);
				}
			}

			foreach (array_map('basename', $_FILES[$input]['name']) as $key => $value)
			{
				$result[$value] = false;

				if ($_FILES[$input]['error'][$key] == UPLOAD_ERR_OK)
				{
					$file = ph()->Text->Slug($value, '_', '.');

					if (file_exists($output . $file) === true)
					{
						$file = substr_replace($file, '_' . md5_file($_FILES[$input]['tmp_name'][$key]), strrpos($value, '.'), 0);
					}

					if (move_uploaded_file($_FILES[$input]['tmp_name'][$key], $output . $file) === true)
					{
						if (self::Chmod($output . $file, $chmod) === true)
						{
							$result[$value] = $output . $file;
						}
					}
				}
			}
		}

		return $result;
	}

	public static function Zip($input, $output, $chmod = null)
	{
		if (extension_loaded('zip') === true)
		{
			$input = self::Path($input);

			if ($input !== false)
			{
				$zip = new ZipArchive();

				if ($zip->open($input) === true)
				{
					$zip->extractTo($output);
				}

				else if ($zip->open($output, ZIPARCHIVE::CREATE) === true)
				{
					if (is_dir($input) === true)
					{
						$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($input), RecursiveIteratorIterator::SELF_FIRST);

						foreach ($files as $file)
						{
							$file = self::Path($file);

							if (is_dir($file) === true)
							{
								$zip->addEmptyDir(str_replace($input, '', $file));
							}

							else if (is_file($file) === true)
							{
								$zip->addFromString(str_replace($input, '', $file), self::File($file));
							}
						}
					}

					else if (is_file($input) === true)
					{
						$zip->addFromString(basename($input), self::File($input));
					}
				}

				if ($zip->close() === true)
				{
					return self::Chmod($output, $chmod);
				}
			}
		}

		return false;
	}
}

class phunction_HTTP extends phunction
{
	public function __construct()
	{
	}

	public static function Cookie($key, $value = null, $expire = null)
	{
		if (isset($value) === true)
		{
			if (is_array($key) === true)
			{
				$key = preg_replace('~[[](.*?)[]]~', '$1', sprintf('[%s]', implode('][', $key)), 1);
			}

			return (headers_sent() !== true) ? setcookie($key, strval($value), intval($expire), '/') : false;
		}

		return parent::Value($_COOKIE, $key);
	}

	public static function FirePHP($message, $label = null, $type = 'LOG')
	{
		static $i = 0;

		if (headers_sent() !== true)
		{
			$type = (in_array($type, array('LOG', 'INFO', 'WARN', 'ERROR')) === true) ? $type : 'LOG';

			if ((strpos($_SERVER['HTTP_USER_AGENT'], 'FirePHP') !== false) && (strcmp($_SERVER['SERVER_ADDR'], $_SERVER['REMOTE_ADDR']) === 0))
			{
				$message = json_encode(array(array('Type' => $type, 'Label' => $label), $message));

				if ($i == 0)
				{
					header('X-Wf-Protocol-1: http://meta.wildfirehq.org/Protocol/JsonStream/0.2');
					header('X-Wf-1-Plugin-1: http://meta.firephp.org/Wildfire/Plugin/FirePHP/Library-FirePHPCore/0.3');
					header('X-Wf-1-Structure-1: http://meta.firephp.org/Wildfire/Structure/FirePHP/FirebugConsole/0.1');
				}

				header('X-Wf-1-1-1-' . ++$i . ': ' . strlen($message) . '|' . $message . '|');
			}
		}
	}

	public static function Flush($buffer = null)
	{
		echo $buffer;

		while (ob_get_level() > 0)
		{
			ob_end_flush();
		}

		flush();
	}

	public static function IP($ip = null, $proxy = false)
	{
		if (isset($ip) === true)
		{
			return (ph()->Is->IP($ip) === true) ? $ip : self::IP(null, $proxy);
		}

		else if ($proxy === true)
		{
			foreach (array('HTTP_CLIENT_IP', 'HTTP_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_X_FORWARDED_FOR') as $key)
			{
				foreach (array_map('trim', explode(',', parent::Value($_SERVER, $key))) as $value)
				{
					if (ph()->Is->IP($value) === true)
					{
						return $value;
					}
				}
			}
		}

		return parent::Value($_SERVER, 'REMOTE_ADDR', '127.0.0.1');
	}

	public static function Method($method, $ajax = false)
	{
		if (in_array(parent::Value($_SERVER, 'REQUEST_METHOD', 'GET'), array_map('strtoupper', (array) $method)) === true)
		{
			if ($ajax === true)
			{
				return (strcasecmp('XMLHttpRequest', parent::Value($_SERVER, 'HTTP_X_REQUESTED_WITH')) === 0) ? true : false;
			}

			return true;
		}

		return false;
	}

	public static function Note($key, $value = null)
	{
		if (is_null($value) === true)
		{
			$result = self::Cookie(array(__FUNCTION__, $key));

			if ($result !== false)
			{
				self::Cookie(array(__FUNCTION__, $key), false);
			}

			return (is_bool($result) === true) ? $result : json_decode($result);
		}

		return self::Cookie(array(__FUNCTION__, $key), json_encode($value));
	}

	public static function Sleep($time = 1)
	{
		return usleep(intval(floatval($time) * 1000000));
	}

	public static function Token($key, $value = null)
	{
		if (strlen(session_id()) > 0)
		{
			$result = self::Value($_SESSION, array(__METHOD__, $key), null);

			if ((is_null($result) === true) || ((isset($value) === true) && (strcmp($value, $result) !== 0)))
			{
				return $_SESSION[__METHOD__][$key] = sha1(uniqid(mt_rand(), true));
			}

			return true;
		}

		return false;
	}
}

class phunction_Is extends phunction
{
	public function __construct()
	{
	}

	public static function ASCII($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[\x20-\x7E]*$~')));
	}

	public static function Alpha($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[a-z]*$~i')));
	}

	public static function Alphanum($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[0-9a-z]*$~i')));
	}

	public static function Email($value, $mx = true)
	{
		if (filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
		{
			return (($mx === true) && (function_exists('checkdnsrr') === true)) ? checkdnsrr(ltrim(strstr($value, '@'), '@'), 'MX') : true;
		}

		return false;
	}

	public static function Float($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_FLOAT);
	}

	public static function Hash($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^(?:[[:xdigit:]]{8})+$~')));
	}

	public static function Integer($value, $minimum = null, $maximum = null)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_INT, array('options' => array_filter(array('min_range' => $minimum, 'max_range' => $maximum), 'strlen')));
	}

	public static function IP($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
	}

	public static function Number($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[+-]?\d+(?:[.]\d+)?$~')));
	}

	public static function Set($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~[[:graph:]]~')));
	}

	public static function URL($value)
	{
		return (bool) filter_var($value, FILTER_VALIDATE_URL);
	}
}

class phunction_Math extends phunction
{
	public function __construct()
	{
	}

	public static function Average()
	{
		$result = 0;
		$arguments = parent::Flatten(func_get_args());

		foreach ($arguments as $argument)
		{
			$result += $argument;
		}

		return ($result / max(1, count($arguments)));
	}

	public static function Base($number, $input, $output, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
		if (strlen($charset) >= 2)
		{
			$input = self::Between($input, 2, strlen($charset));
			$output = self::Between($output, 2, strlen($charset));
			$number = ltrim(preg_replace('~[^' . substr($charset, 0, $input) . ']+~', '', $number), $charset[0]);

			if (strlen($number) > 0)
			{
				if ($input != 10)
				{
					$result = 0;

					foreach (str_split(strrev($number)) as $key => $value)
					{
						$result += pow($input, $key) * intval(strpos($charset, $value));
					}

					$number = $result;
				}

				if ($output != 10)
				{
					$result = $charset[$number % $output];

					while (($number = intval($number / $output)) > 0)
					{
						$result = $charset[$number % $output] . $result;
					}

					$number = $result;
				}

				return $number;
			}

			return $charset[0];
		}

		return false;
	}

	public static function Benchmark($function, $arguments = null, $iterations = 10000)
	{
		if (is_callable($function) === true)
		{
			$result = microtime(true);

			for ($i = 1; $i <= $iterations; ++$i)
			{
				call_user_func_array($function, (array) $arguments);
			}

			return self::Round(microtime(true) - $result, 8);
		}

		return false;
	}

	public static function Between($number, $minimum = null, $maximum = null)
	{
		$number = floatval($number);

		if ((isset($minimum) === true) && ($number < $minimum))
		{
			$number = floatval($minimum);
		}

		if ((isset($maximum) === true) && ($number > $maximum))
		{
			$number = floatval($maximum);
		}

		return $number;
	}

	public static function Chance($chance, $universe = 100)
	{
		return ($chance >= mt_rand(1, $universe)) ? true : false;
	}

	public static function Checksum($string, $encode = false)
	{
		if ($encode === true)
		{
			$result = 0;
			$string = str_split($string);

			foreach ($string as $value)
			{
				$result = ($result + ord($value) - 48) * 10 % 97;
			}

			return implode('', $string) . sprintf('%02u', (98 - $result * 10 % 97) % 97);
		}

		else if ($string == self::Checksum(substr($string, 0, -2), true))
		{
			return substr($string, 0, -2);
		}

		return false;
	}

	public static function Deviation()
	{
		$result = self::Average(func_get_args());
		$arguments = parent::Flatten(func_get_args());

		foreach ($arguments as $key => $value)
		{
			$arguments[$key] = pow($value - $result, 2);
		}

		return sqrt(self::Average($arguments));
	}

	public static function Enum($id)
	{
		static $enum = array();

		if (func_num_args() > 1)
		{
			$result = 0;

			if (empty($enum[$id]) === true)
			{
				$enum[$id] = array();
			}

			foreach (array_unique(array_slice(func_get_args(), 1)) as $argument)
			{
				if (empty($enum[$id][$argument]) === true)
				{
					$enum[$id][$argument] = pow(2, count($enum[$id]));
				}

				$result += $enum[$id][$argument];
			}

			return $result;
		}

		return false;
	}

	public static function ifMB($id, $reference, $amount = 0.00, $entity = 10559)
	{
		$stack = 0;
		$weights = array(51, 73, 17, 89, 38, 62, 45, 53, 15, 50, 5, 49, 34, 81, 76, 27, 90, 9, 30, 3);
		$argument = sprintf('%05u%03u%04u%08u', $entity, $id, $reference % 10000, round($amount * 100));

		foreach (str_split($argument) as $key => $value)
		{
			$stack += $value * $weights[$key];
		}

		return array
		(
			'entity' => sprintf('%05u', $entity),
			'reference' => sprintf('%03u%04u%02u', $id, $reference % 10000, 98 - ($stack % 97)),
			'amount' => number_format($amount, 2, '.', ''),
		);
	}

	public static function Luhn($string, $encode = false)
	{
		if ($encode === true)
		{
			$result = 0;
			$string = str_split(strrev($string), 1);

			foreach ($string as $key => $value)
			{
				if ($key % 2 == 0)
				{
					$value = array_sum(str_split($value * 2, 1));
				}

				$result += $value;
			}

			$result %= 10;

			if ($result != 0)
			{
				$result -= 10;
			}

			return implode('', array_reverse($string)) . abs($result);
		}

		else if ($string == self::Luhn(substr($string, 0, -1), true))
		{
			return substr($string, 0, -1);
		}

		return false;
	}

	public static function Pagination($data, $limit = null, $current = null, $adjacents = null)
	{
		$result = array();

		if (isset($data, $limit) === true)
		{
			$result = range(1, ceil($data / $limit));

			if (isset($current, $adjacents) === true)
			{
				if (($adjacents = floor($adjacents / 2) * 2 + 1) >= 1)
				{
					$result = array_slice($result, max(0, min(count($result) - $adjacents, intval($current) - ceil($adjacents / 2))), $adjacents);
				}
			}
		}

		return $result;
	}

	public static function Prime($number)
	{
		if (function_exists('gmp_prob_prime') === true)
		{
			return (gmp_prob_prime(abs($number)) > 0) ? true : false;
		}

		return (preg_match('~^1?$|^(11+?)\1++$~', str_repeat('1', abs($number))) + preg_last_error() === 0) ? true : false;
	}

	public static function Probability($data, $number = 1)
	{
		$result = array();

		if (is_array($data) === true)
		{
			$data = array_map('abs', $data);
			$number = min(max(1, abs($number)), count($data));

			while ($number-- > 0)
			{
				$chance = 0;
				$probability = mt_rand(1, array_sum($data));

				foreach ($data as $key => $value)
				{
					$chance += $value;

					if ($chance >= $probability)
					{
						$result[] = $key; unset($data[$key]); break;
					}
				}
			}
		}

		return $result;
	}

	public static function Relative()
	{
		$result = 0;

		foreach (parent::Flatten(func_get_args()) as $argument)
		{
			if (substr($argument, -1) == '%')
			{
				$argument = $result * floatval(rtrim($argument, '%') / 100);
			}

			$result += floatval($argument);
		}

		return floatval($result);
	}

	public static function Round($number, $precision = 0)
	{
		return number_format($number, intval($precision), '.', '');
	}
}

class phunction_Net extends phunction
{
	public function __construct()
	{
	}

	public static function Captcha($value = null, $background = null)
	{
		if (strlen(session_id()) > 0)
		{
			if (is_null($value) === true)
			{
				$result = simplexml_load_string(self::CURL('http://services.sapo.pt/Captcha/Get/'));

				if (is_object($result) === true)
				{
					$_SESSION[__METHOD__] = strval($result->code);

					if ($result->msg == 'ok')
					{
						$result = strval($result->id);
						$background = ltrim($background, '#');

						if (strlen($background) > 0)
						{
							$result .= sprintf('&background=%s', $background);

							if (hexdec($background) < 0x7FFFFF)
							{
								$result .= sprintf('&textcolor=%s', 'ffffff');
							}
						}

						return sprintf('http://services.sapo.pt/Captcha/Show/?id=%s', strtolower($result));
					}
				}
			}

			return (strcasecmp(trim($value), parent::Value($_SESSION, __METHOD__)) === 0);
		}

		return false;
	}

	public static function CURL($url, $data = null, $method = 'GET', $options = null)
	{
		$result = false;

		if ((extension_loaded('curl') === true) && (ph()->Is->URL($url) === true))
		{
			$curl = curl_init($url);

			if (is_resource($curl) === true)
			{
				curl_setopt($curl, CURLOPT_FAILONERROR, true);
				curl_setopt($curl, CURLOPT_AUTOREFERER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

				if (preg_match('~^(?:GET|HEAD)$~i', $method) > 0)
				{
					curl_setopt($curl, CURLOPT_HTTPGET, true);

					if (preg_match('~^(?:HEAD)$~i', $method) > 0)
					{
						curl_setopt($curl, CURLOPT_NOBODY, true);
						curl_setopt($curl, CURLOPT_HEADER, true);
					}
				}

				else if (preg_match('~^(?:POST)$~i', $method) > 0)
				{
					curl_setopt($curl, CURLOPT_POST, true);

					if ((is_array($data) === true) && (preg_match('~"[^"]+":"@[^"]+"~', json_encode($data)) === 0))
					{
						$data = http_build_query($data, '', '&');
					}

					curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
				}

				else if (preg_match('~^(?:PUT|DELETE)$~i', $method) > 0)
				{
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

					if (preg_match('~^(?:PUT)$~i', $method) > 0)
					{
						curl_setopt($curl, CURLOPT_POSTFIELDS, (is_array($data) === true) ? json_encode($data) : $data);
					}
				}

				if (array_key_exists('HTTP_USER_AGENT', $_SERVER) === true)
				{
					curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
				}

				if (is_array($options) === true)
				{
					curl_setopt_array($curl, $options);
				}

				for ($i = 1; $i <= 5; ++$i)
				{
					$result = curl_exec($curl);

					if (($i == 5) || ($result !== false))
					{
						break;
					}

					usleep(pow(2, $i - 2) * 1000000);
				}

				curl_close($curl);
			}
		}

		return $result;
	}

	public static function Currency($value = 1, $input = null, $output = null, $ttl = null)
	{
		$key = array(__METHOD__);
		$result = parent::Cache(vsprintf('%s', $key));

		if ($result === false)
		{
			$result = array();
			$currencies = self::XML(self::CURL('http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'), '//cube/cube/cube');

			if (is_array($currencies) === true)
			{
				$result['EUR'] = 1;

				foreach ($currencies as $currency)
				{
					$result[strval($currency['currency'])] = 1 / floatval($currency['rate']);
				}

				if (is_null($ttl) === true)
				{
					$ttl = parent::Date('U', '6 PM') - $_SERVER['REQUEST_TIME'];

					if ($ttl < 0)
					{
						$ttl = parent::Date('U', '@' . $ttl, '+1 day');
					}

					$ttl = round(max(3600, $ttl / 2));
				}

				$result = parent::Cache(vsprintf('%s', $key), $result, $ttl);
			}
		}

		if (is_array($result) === true)
		{
			if (isset($result[$input], $result[$output]) === true)
			{
				return floatval($value) * $result[$input] / $result[$output];
			}

			return floatval($value);
		}

		return false;
	}

	public static function Email($to, $from, $subject, $message, $cc = null, $bcc = null, $attachments = null, $smtp = null)
	{
		$content = array();
		$boundary = sprintf('=%s=', rtrim(base64_encode(uniqid()), '='));

		if (extension_loaded('imap') === true)
		{
			$header = array
			(
				'Date' => parent::Date('r'),
				'Message-ID' => sprintf('<%s@%s>', md5(microtime(true)), $_SERVER['HTTP_HOST']),
				'MIME-Version' => '1.0',
			);

			foreach (array('from', 'to', 'cc', 'bcc') as $email)
			{
				$$email = array_filter(filter_var_array(preg_replace('~\s|[<>]|%0[ab]|[[:cntrl:]]~i', '', (is_array($$email) === true) ? $$email : explode(',', $$email)), FILTER_VALIDATE_EMAIL));

				if (count($$email) > 0)
				{
					$header[ucfirst($email)] = array();

					foreach ($$email as $key => $value)
					{
						$key = preg_replace('~%0[ab]|[[:cntrl:]]~i', '', $key);
						$value = (is_array($value) === true) ? $value : explode('@', $value);

						if (preg_match('~[^\x20-\x7E]~', $key) > 0)
						{
							$key = '=?UTF-8?B?' . base64_encode($key) . '?=';
						}

						$header[ucfirst($email)][] = imap_rfc822_write_address($value[0], $value[1], preg_replace('~^\d+$~', '', $key));
					}
				}
			}

			if (count($from) * (count($to) + count($cc) + count($bcc)) > 0)
			{
				$header['Sender'] = $header['Reply-To'] = $header['From'][0];
				$header['Subject'] = preg_replace('~%0[ab]|[[:cntrl:]]~i', '', $subject);
				$header['Return-Path'] = sprintf('<%s>', implode('', array_slice($from, 0, 1)));
				$header['Content-Type'] = sprintf('multipart/alternative; boundary="%s"', $boundary);

				if ((count($to) + count($cc) + count($bcc)) == 1)
				{
					$count = 0;
					$hashcash = sprintf('1:20:%u:%s::%s:', parent::Date('ymd'), parent::Coalesce($to, $cc, $bcc), mt_rand());

					while (strncmp('00000', sha1($hashcash . $count), 5) !== 0)
					{
						++$count;
					}

					$header['X-Hashcash'] = $hashcash . $count;
				}

				foreach (array_fill_keys(array('plain', 'html'), trim(str_replace("\r", '', $message))) as $key => $value)
				{
					if ($key == 'html')
					{
						$value = trim(imap_binary($value));
					}

					else if ($key == 'plain')
					{
						$value = preg_replace('~<br[^>]*>~i', "\n", strip_tags(preg_replace('~.*<body(?:\s[^>]*)?>(.+?)</body>.*~is', '$1', $value), '<a><p><br><li>'));

						if (preg_match('~</?[a-z][^>]*>~i', $value) > 0)
						{
							$value = strip_tags(preg_replace(array('~<a[^>]+?href="(.+?)"[^>]*>(.+?)</a>~is', '~<p[^>]*>(.+?)</p>~is', '~<li[^>]*>(.+?)</li>~is'), array('$2 ($1)', "\n\n$1\n\n", "\n - $1"), $value));
						}

						$value = implode("\n", array_map('imap_8bit', explode("\n", preg_replace('~\n{3,}~', "\n\n", trim($value)))));
					}

					$value = array
					(
						sprintf('Content-Type: text/%s; charset=utf-8', $key), 'Content-Disposition: inline',
						sprintf('Content-Transfer-Encoding: %s', ($key == 'html') ? 'base64' : 'quoted-printable'), '', $value, '',
					);

					$content = array_merge($content, array('--' . $boundary), $value);
				}

				$content[] = '--' . $boundary . '--';

				if (isset($attachments) === true)
				{
					$boundary = str_rot13($boundary);
					$attachments = array_filter((array) $attachments, 'is_file');

					if (count($attachments) > 0)
					{
						array_unshift($content, '--' . $boundary, 'Content-Type: ' . $header['Content-Type'], '');

						foreach ($attachments as $key => $value)
						{
							$key = (is_int($key) === true) ? basename($value) : $key;

							if (preg_match('~[^\x20-\x7E]~', $key) > 0)
							{
								$key = '=?UTF-8?B?' . base64_encode($key) . '?=';
							}

							$value = array
							(
								sprintf('Content-Type: application/octet-stream; name="%s"', $key),	sprintf('Content-Disposition: attachment; filename="%s"', $key),
								'Content-Transfer-Encoding: base64', '', trim(imap_binary(file_get_contents($value))), '',
							);

							$content = array_merge($content, array('--' . $boundary), $value);
						}

						$header['Content-Type'] = sprintf('multipart/mixed; boundary="%s"', $boundary);
						$content[] = '--' . $boundary . '--';
					}
				}

				foreach ($header as $key => $value)
				{
					$value = (is_array($value) === true) ? implode(', ', $value) : $value;
					$header[$key] = iconv_mime_encode($key, $value, array('scheme' => 'Q', 'input-charset' => 'UTF-8', 'output-charset' => 'UTF-8'));

					if ($header[$key] === false)
					{
						$header[$key] = iconv_mime_encode($key, $value, array('scheme' => 'B', 'input-charset' => 'UTF-8', 'output-charset' => 'UTF-8'));
					}

					if (preg_match('~^[\x20-\x7E]*$~', $value) > 0)
					{
						$header[$key] = wordwrap(iconv_mime_decode($header[$key], 0, 'UTF-8'), 76, "\r\n" . ' ', true);
					}
				}

				if (isset($smtp) === true)
				{
					$result = null;
					$stream = stream_socket_client($smtp);

					if (is_resource($stream) === true)
					{
						$data = array('HELO ' . $_SERVER['HTTP_HOST']);
						$result .= substr(ltrim(fread($stream, 8192)), 0, 3);

						if (preg_match('~^220~', $result) > 0)
						{
							$auth = array_slice(func_get_args(), 8, 2);

							if (count($auth) == 2)
							{
								$data = array_merge($data, array('AUTH LOGIN'), array_map('base64_encode', $auth));
							}

							$data[] = sprintf('MAIL FROM: <%s>', implode('', array_slice($from, 0, 1)));

							foreach (array_merge(array_values($to), array_values($cc), array_values($bcc)) as $value)
							{
								$data[] = sprintf('RCPT TO: <%s>', $value);
							}

							$data[] = 'DATA';
							$data[] = implode("\r\n", array_merge(array_diff_key($header, array('Bcc' => true)), array(''), $content, array('.')));
							$data[] = 'QUIT';

							while (preg_match('~^220(?>250(?>(?>334){1,2}(?>235)?)?(?>(?>250){1,}(?>354(?>250)?)?)?)?$~', $result) > 0)
							{
								if (fwrite($stream, array_shift($data) . "\r\n") !== false)
								{
									$result .= substr(ltrim(fread($stream, 8192)), 0, 3);
								}
							}

							if (count($data) > 0)
							{
								if (fwrite($stream, array_pop($data) . "\r\n") !== false)
								{
									$result .= substr(ltrim(fread($stream, 8192)), 0, 3);
								}
							}
						}

						fclose($stream);
					}

					return (preg_match('~221$~', $result) > 0) ? true : false;
				}

				return @mail(null, substr($header['Subject'], 9), implode("\n", $content), implode("\r\n", array_diff_key($header, array('Subject' => true))));
			}
		}

		return false;
	}

	public static function Geo($query, $country = null)
	{
		$data = array
		(
			'address' => $query,
			'region' => $country,
			'sensor' => 'false',
		);

		$result = self::CURL('http://maps.googleapis.com/maps/api/geocode/json?' . http_build_query($data, '', '&'));

		if ($result !== false)
		{
			return parent::Value(json_decode($result, true), array('results', 0, 'geometry', 'location'));
		}

		return false;
	}

	public static function GeoIP($ip = null, $proxy = false, $ttl = 86400)
	{
		$ip = ph()->HTTP->IP($ip, $proxy);
		$key = array(__METHOD__, $ip, $proxy);
		$result = parent::Cache(vsprintf('%s:%s:%b', $key));

		if ($result === false)
		{
			$result = self::CURL('http://ipinfodb.com/ip_query_country.php?ip=' . $ip);

			if ($result !== false)
			{
				$result = parent::Cache(vsprintf('%s:%s:%b', $key), strval(self::XML($result, '//response/countrycode', 0)), $ttl);
			}
		}

		return $result;
	}

	public static function Online()
	{
		return ph()->Is->IP(gethostbyname('google.com'));
	}

	public static function PayPal($email, $status = 'Completed', $sandbox = false)
	{
		static $result = null;

		if ((is_null($result) === true) && (preg_match('~^(?:.+[.])?paypal[.]com$~', gethostbyaddr($_SERVER['REMOTE_ADDR'])) > 0))
		{
			$result = self::CURL('https://www' . (($sandbox !== true) ? '' : '.sandbox') . '.paypal.com/cgi-bin/webscr/', array_merge(array('cmd' => '_notify-validate'), $_POST), 'POST');
		}

		if (strcmp('VERIFIED', $result) === 0)
		{
			$email = strlen($email) * strcasecmp($email, parent::Value($_POST, 'receiver_email'));
			$status = strlen($status) * strcasecmp($status, parent::Value($_POST, 'payment_status'));

			if (($email == 0) && ($status == 0))
			{
				return (object) $_POST;
			}
		}

		return false;
	}

	public static function SMS($to, $from, $message, $username, $password, $unicode = false)
	{
		$data = array();
		$message = trim($message);

		if (strlen($message) > 0)
		{
			$message = ph()->Text->Reduce($message, ' ');

			if (preg_match('~[^\x20-\x7E]~', $message) > 0)
			{
				$message = ph()->Text->Filter($message);

				if ($unicode === true)
				{
					$message = ph()->Unicode->str_split($message);

					foreach ($message as $key => $value)
					{
						$message[$key] = sprintf('%04x', ph()->Unicode->ord($value));
					}

					$message = implode('', $message);
				}

				$message = ph()->Text->Unaccent($message);
			}

			if (is_array($data) === true)
			{
				$data['to'] = $to;
				$data['from'] = $from;
				$data['type'] = (preg_match('^(?:[[:xdigit:]]{4})*$', $message) > 0);

				if ($data['type'] === true)
				{
					$data['hex'] = $message;
				}

				else if ($data['type'] === false)
				{
					$data['text'] = $message;
				}

				$data['type'] = intval($data['type']) + 1;
				$data['username'] = $username;
				$data['password'] = $password;
				$data['maxconcat'] = '10';
			}

			if (strpos(self::CURL('https://www.intellisoftware.co.uk/smsgateway/sendmsg.aspx', $data, 'POST'), 'ID:') !== false)
			{
				return true;
			}
		}

		return false;
	}

	public static function Socket($host, $port, $timeout = 15)
	{
		$time = microtime(true);
		$socket = @fsockopen($host, intval($port), $errno, $errstr, floatval($timeout));

		if (is_resource($socket) === true)
		{
			if (fclose($socket) === true)
			{
				return microtime(true) - $time;
			}
		}

		return false;
	}

	public static function Translate($string, $input = null, $output = null)
	{
		$data = array
		(
			'v' => '1.0',
			'q' => $string,
			'langpair' => sprintf('%s|%s', $input, $output),
		);

		$result = self::CURL('http://ajax.googleapis.com/ajax/services/language/translate?' . http_build_query($data, '', '&'));

		if ($result !== false)
		{
			return parent::Value(json_decode($result, true), array('responseData', 'translatedText'));
		}

		return false;
	}

	public static function VIES($vatin, $country)
	{
		$data = array('BtnSubmitVat' => 'Verify');

		foreach (array('iso', 'ms', 'vat') as $value)
		{
			$data[$value] = $vatin;
			$data['requester' . ucfirst($value)] = $vatin;

			if (strcmp('vat', $value) !== 0)
			{
				$data[$value] = $country;
				$data['requester' . ucfirst($value)] = $country;
			}
		}

		return (strpos(ph()->Net->CURL('http://ec.europa.eu/taxation_customs/vies/viesquer.do', $data, 'POST'), 'Yes, valid VAT number') !== false) ? true : false;
	}

	public static function Weather($query)
	{
		$weather = ph()->Net->XML(self::CURL('http://www.google.com/ig/api?weather=' . urlencode($query)));

		if ($weather !== false)
		{
			$result = array();

			foreach (ph()->Net->XML($weather, '//forecast_conditions') as $key => $value)
			{
				$result[$key] = array(strval($value->low['data']), strval($value->high['data']));
			}

			return $result;
		}

		return false;
	}

	public static function XML($xml, $xpath = null, $key = null, $default = false)
	{
		if (extension_loaded('SimpleXML') === true)
		{
			libxml_use_internal_errors(true);

			if ((is_string($xml) === true) && (class_exists('DOMDocument') === true))
			{
				$dom = new DOMDocument();

				if ($dom->loadHTML($xml) === true)
				{
					return self::XML(simplexml_import_dom($dom), $xpath, $key, $default);
				}
			}

			else if (is_object($xml) === true)
			{
				if (isset($xpath) === true)
				{
					$xml = $xml->xpath($xpath);

					if (isset($key) === true)
					{
						$xml = parent::Value($xml, $key, $default);
					}
				}

				return $xml;
			}
		}

		return false;
	}
}

class phunction_Tag extends phunction
{
	public function __construct()
	{
	}

	public static function Checkbox($id, $value, $default = null)
	{
		$result = array('type' => 'checkbox', 'name' => $id, 'value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['checked'] = true;
		}

		return $result;
	}

	public static function Input($id, $default = null, $type = 'text')
	{
		$result = array('type' => $type, 'name' => $id, 'value' => $default);

		if (array_key_exists($id, $_REQUEST) === true)
		{
			$result['value'] = htmlspecialchars_decode(parent::Value($_REQUEST, $id));
		}

		return $result;
	}

	public static function Option($id, $value = null, $default = null)
	{
		$result = array('value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['selected'] = true;
		}

		return $result;
	}

	public static function Radio($id, $value, $default = null)
	{
		$result = array('type' => 'radio', 'name' => $id, 'value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['checked'] = true;
		}

		return $result;
	}
}

class phunction_Text extends phunction
{
	public function __construct()
	{
	}

	public static function Comify($array, $last = ' and ')
	{
		$array = array_filter(array_unique((array) $array), 'strlen');

		if (count($array) >= 3)
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
			$result = self::Regex($string, '^([0-9a-zA-Z/+]*={0,2})[0-9a-f]{40}$', array(1, 0));

			if (strcmp(sha1($result . $key), self::Regex($string, '^[0-9a-zA-Z/+]*={0,2}([0-9a-f]{40})$', array(1, 0))) === 0)
			{
				$result = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, base64_decode($result), MCRYPT_MODE_CBC, md5($key)), "\0");
			}

			else
			{
				$result = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $string, MCRYPT_MODE_CBC, md5($key)));

				if (self::Regex($result, '^[a-zA-Z0-9/+]*={0,2}$') === true)
				{
					$result .= sha1($result . $key);
				}
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

	public static function Entropy($string)
	{
		return strlen(count_chars($string, 3)) * 100 / 256;
	}

	public static function Filter($string, $control = true)
	{
		$string = iconv('UTF-8', 'UTF-8//IGNORE', $string);

		if ($control !== true)
		{
			return preg_replace(array('~\r[\n]?~', '~[^\P{C}\t\n]+~u'), array("\n", ''), $string);
		}

		return preg_replace('~\p{C}+~u', '', $string);
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

	public static function Hash($string, $hash = null, $salt = null, $iterations = 1024, $algorithm = 'sha256')
	{
		if (extension_loaded('hash') === true)
		{
			if (empty($hash) === true)
			{
				if (empty($salt) === true)
				{
					$salt = uniqid(mt_rand(), true);
				}

				if (in_array($algorithm, hash_algos()) === true)
				{
					$result = hash($algorithm, $salt . $string);
					$iterations = max(1024, intval($iterations));

					for ($i = 1; $i < $iterations; ++$i)
					{
						$result = hash($algorithm, $result . $string);
					}

					return sprintf('%s|%u|%s|%s', $algorithm, $iterations, $salt, $result);
				}
			}

			else if (count($hash = explode('|', $hash)) == 4)
			{
				return (strcmp(implode('|', $hash), self::Hash($string, null, $hash[2], $hash[1], $hash[0])) === 0);
			}
		}

		return false;
	}

	public static function Humanify($string)
	{
		return ph()->Unicode->ucwords(trim(str_replace('_', ' ', ph()->Unicode->strtolower($string))));
	}

	public static function Indent($string, $indentation = 1)
	{
		$indentation = str_repeat("\t", intval($indentation));

		if (strlen($indentation) > 0)
		{
			$string = rtrim($indentation . implode("\n" . $indentation, explode("\n", $string)), "\t");
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

	public static function Namify($string)
	{
		return ph()->Unicode->ucwords(ph()->Unicode->strtolower(preg_replace('~\s*\b(\p{L}+)\b.+\b(\p{L}+)\b\s*~u', '$1 $2', $string)));
	}

	public static function Obfuscate($string, $css = true)
	{
		if (ph()->Unicode->strlen($string) > 0)
		{
			$string = array_map(array('phunction_Unicode', 'ord'), ph()->Unicode->str_split($string));

			if ($css !== true)
			{
				return sprintf('&#%s;', implode(';&#', $string));
			}

			return sprintf('<span style="unicode-bidi:bidi-override; direction: rtl;">&#%s;</span>', implode(';&#', array_reverse($string)));
		}

		return false;
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

	public static function Surround($string, $surround = null)
	{
		$string = trim($string);

		if (strlen($string) > 0)
		{
			$string = sprintf('%s%s%s', $surround, $string, $surround);
		}

		return $string;
	}

	public static function Title($string, $raw = false)
	{
		if (ob_get_level() > 0)
		{
			if (preg_match('~<title>[^<]*</title>~i', ob_get_contents()) > 0)
			{
				echo preg_replace('~<title>[^<]*</title>~i', '<title>' . (($raw === true) ? $string : addcslashes($string, '\\$')) . '</title>', ob_get_clean(), 1);
			}
		}

		return false;
	}

	public static function Truncate($string, $limit, $more = '...')
	{
		$string = trim($string);

		if (ph()->Unicode->strlen($string) > $limit)
		{
			return preg_replace('~^(.{1,' . $limit . '}(?<=\S)(?=\s)|.{' . $limit . '}).*$~su', '$1', $string) . $more;
		}

		return $string;
	}

	public static function Unaccent($string)
	{
		return html_entity_decode(preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1', htmlentities($string, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
	}

	public static function XSS($string)
	{
		return htmlspecialchars($string, ENT_QUOTES);
	}
}

class phunction_Unicode extends phunction
{
	public function __construct()
	{
	}

	public static function chr($string)
	{
		return html_entity_decode('&#' . $string . ';', ENT_QUOTES, 'UTF-8');
	}

	public static function count_chars($string)
	{
		return array_count_values(self::str_split($string));
	}

	public static function lcfirst($string)
	{
		return self::strtolower(self::substr($string, 0, 1)) . self::substr($string, 1);
	}

	public static function lcwords($string)
	{
		$result = array_unique(self::str_word_count($string, 1));

		if (count($result) > 0)
		{
			$string = str_replace($result, array_map('self::lcfirst', $result), $string);
		}

		return $string;
	}

	public static function ord($string)
	{
		return parent::Value(unpack('N', iconv('UTF-8', 'UCS-4BE', $string)), 1);
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
		$string = self::str_split($string);

		if (count($string) > 0)
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
		$result = self::stripos($string, $search);

		if ($result !== false)
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
		$result = self::strpos($string, $search);

		if ($result !== false)
		{
			$result = ($before !== true) ? self::substr($string, $result) : self::substr($string, 0, $result);
		}

		return $result;
	}

	public static function strtolower($string)
	{
		$string = self::str_split($string);

		foreach ($string as $key => $value)
		{
			if (preg_match('~\p{Lu}~u', $value) > 0)
			{
				$string[$key] = '&#' . (self::ord($value) + 32) . ';';
			}
		}

		return html_entity_decode(implode('', $string), ENT_QUOTES, 'UTF-8');
	}

	public static function strtoupper($string)
	{
		$string = self::str_split($string);

		foreach ($string as $key => $value)
		{
			if (preg_match('~\p{Ll}~u', $value) > 0)
			{
				$string[$key] = '&#' . (self::ord($value) - 32) . ';';
			}
		}

		return html_entity_decode(implode('', $string), ENT_QUOTES, 'UTF-8');
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

	public static function ucwords($string)
	{
		$result = array_unique(self::str_word_count($string, 1));

		if (count($result) > 0)
		{
			$string = str_replace($result, array_map('self::ucfirst', $result), $string);
		}

		return $string;
	}
}

function ph($ph = null)
{
	static $result = null;

	if (is_null($result) === true)
	{
		$result = new phunction();
	}

	if (is_object($result) === true)
	{
		$result::$id = strval($ph);
	}

	return $result;
}

?>