<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.7.16 (github.com/alixaxel/phunction/)
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
		date_default_timezone_set('GMT');

		if ((headers_sent() !== true) && (strncmp('cli', PHP_SAPI, 3) !== 0))
		{
			header('Content-Type: text/html; charset=utf-8');

			if (strncasecmp('www.', self::Value($_SERVER, 'HTTP_HOST'), 4) === 0)
			{
				self::Redirect(str_ireplace('://www.', '://', self::URL()), null, null, 301);
			}

			else if (strlen(session_id()) == 0)
			{
				@session_start();
			}
		}

		if (version_compare(PHP_VERSION, '5.3.0', '<') === true)
		{
			set_magic_quotes_runtime(false);
		}

		foreach (array('_GET', '_PUT', '_POST', '_COOKIE', '_REQUEST') as $global)
		{
			$GLOBALS[$global] = self::Value($GLOBALS, $global, null);

			if ((strcmp('_PUT', $global) === 0) && (strcasecmp('PUT', self::Value($_SERVER, 'REQUEST_METHOD')) === 0))
			{
				if ((($GLOBALS[$global] = file_get_contents('php://input')) !== false) && (preg_match('~/x-www-form-urlencoded$~', self::Value($_SERVER, 'CONTENT_TYPE')) > 0))
				{
					parse_str($GLOBALS[$global], $GLOBALS[$global]);
				}
			}

			$GLOBALS[$global] = self::Filter(self::Voodoo($GLOBALS[$global]), false);
		}

		array_map('ini_set', array('html_errors', 'display_errors', 'default_socket_timeout'), array(0, 1, 3));
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

	public static function ACL($resource, $action, $role, $auth = null)
	{
		static $result = array();

		if (is_bool($auth) === true)
		{
			$result[$resource][$action][$role] = $auth;
		}

		return self::Value($result, array($resource, $action, $role));
	}

	public static function Auth($id = null, $role = null)
	{
		if (strlen(session_id()) > 0)
		{
			if ((isset($role) === true) && ((empty($_SESSION[__METHOD__]) === true) || (session_regenerate_id(true) === true)))
			{
				$_SESSION[__METHOD__] = array($id => $role);
			}

			if ((($result = self::Value($_SESSION, __METHOD__)) !== false) && (current($result) !== false))
			{
				return $result;
			}
		}

		return false;
	}

	public static function Cache($key, $value = null, $ttl = 60)
	{
		if (extension_loaded('apc') === true)
		{
			if ((isset($value) === true) && (apc_store($key, $value, intval($ttl)) !== true))
			{
				return $value;
			}

			return apc_fetch($key);
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
			if (empty($result[self::$id][$hash = md5($query)]) === true)
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

					else if (preg_match('~^(?:SELECT|SHOW|EXPLAIN|DESC(?:RIBE)?|PRAGMA)\b~i', $query) > 0)
					{
						return $result[self::$id][$hash]->fetchAll(PDO::FETCH_ASSOC);
					}

					return true;
				}
			}

			return false;
		}

		else if (preg_match('~^(?:mysql|pgsql):~i', $query) > 0)
		{
			try
			{
				$db[self::$id] = new PDO(preg_replace('~^([^:]+):/{0,2}([^:/]+)(?::(\d+))?/(\w+)/?$~', '$1:host=$2;port=$3;dbname=$4', $query), @func_get_arg(1), @func_get_arg(2));

				if (strcmp('mysql', $db[self::$id]->getAttribute(PDO::ATTR_DRIVER_NAME)) === 0)
				{
					self::DB('SET time_zone = ?;', date_default_timezone_get());
					self::DB('SET NAMES ? COLLATE ?;', 'utf8', 'utf8_unicode_ci');
				}
			}

			catch (PDOException $e)
			{
				return false;
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
			if (is_resource($argument) === true)
			{
				$result = sprintf('%s (#%u)', get_resource_type($argument), $argument);
			}

			else if ((is_array($argument) === true) || (is_object($argument) === true))
			{
				$result = rtrim(print_r($argument, true));
			}

			else
			{
				$result = stripslashes(preg_replace("~^'|'$~", '', var_export($argument, true)));
			}

			if (strncmp('cli', PHP_SAPI, 3) !== 0)
			{
				$result = '<pre style="background: #df0; margin: 5px; padding: 5px; text-align: left;">' . htmlspecialchars($result, ENT_QUOTES) . '</pre>';
			}

			echo $result . "\n";
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
				$result .= self::Export($name . '[' . var_export($key, true) . ']', $value) . "\n";
			}

			if (array_keys($data) === range(0, count($data)))
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

		return rtrim($result, "\n");
	}

	public static function Filter($data, $control = true)
	{
		if (is_array($data) === true)
		{
			$result = array();

			foreach ($data as $key => $value)
			{
				$result[self::Filter($key, $control)] = self::Filter($value, $control);
			}

			return $result;
		}

		else if (is_string($data) === true)
		{
			$data = @iconv('UTF-8', 'UTF-8//IGNORE', $data);

			if ($control === true)
			{
				return preg_replace('~\p{C}+~u', '', $data);
			}

			return preg_replace(array('~\r[\n]?~', '~[^\P{C}\t\n]+~u'), array("\n", ''), $data);
		}

		return $data;
	}

	public static function Flatten($data, $key = null, $default = false)
	{
		$result = array();

		if (is_array($data) === true)
		{
			if (isset($key) === true)
			{
				foreach ($data as $value)
				{
					$result[] = self::Value($value, $key, $default);
				}
			}

			else if (is_null($key) === true)
			{
				foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($data)) as $value)
				{
					$result[] = $value;
				}
			}
		}

		return $result;
	}

	public static function Highway($path, $throttle = null)
	{
		if ((is_dir($path = ph()->Disk->Path($path)) === true) && (count($segments = self::Segment(null)) > 0))
		{
			$class = null;

			while ((is_null($segment = array_shift($segments)) !== true) && (is_dir($path . $class . $segment . '/') === true))
			{
				$class .= $segment . '/';
			}

			if (count($class = array_filter(glob($path . $class . '{,_}' . $segment . '.php', GLOB_BRACE), 'is_file')) > 0)
			{
				$class = preg_replace('~[.]php$~', '', current($class));
				$method = (count($segments) > 0) ? array_shift($segments) : self::Value($_SERVER, 'REQUEST_METHOD', 'GET');

				if (is_callable(array(self::Object($class), strtolower($method))) === true)
				{
					if ($throttle > 0)
					{
						usleep(intval(floatval($throttle) * 1000000));
					}

					exit(call_user_func_array(array(self::Object($class), strtolower($method)), $segments));
				}
			}

			throw new Exception('/' . implode('/', self::Segment(null)), 404);
		}

		return false;
	}

	public static function Mongo($query = null)
	{
		static $db = array();

		if (extension_loaded('mongo') === true)
		{
			if (isset($db[self::$id], $query) === true)
			{
				if (strcmp('MongoDB', get_class($db[self::$id])) === 0)
				{
					return $result->selectCollection($query);
				}

				else if (strcmp('Mongo', get_class($db[self::$id])) === 0)
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
			if (isset($result[self::$id][$object]) !== true)
			{
				$result[self::$id][$object] = new $object();
			}

			return $result[self::$id][$object];
		}

		else if (is_file($object . '.php') === true)
		{
			if (class_exists(basename($object), false) !== true)
			{
				require($object . '.php');
			}

			return self::Object(basename($object));
		}

		return false;
	}

	public static function Redirect($url, $path = null, $query = null, $code = 302)
	{
		if (strncmp('cli', PHP_SAPI, 3) !== 0)
		{
			if (headers_sent() !== true)
			{
				session_regenerate_id(true);
				session_write_close();

				if (strncmp('cgi', PHP_SAPI, 3) === 0)
				{
					header(sprintf('Status: %03u', $code), true, $code);
				}

				header('Location: ' . self::URL($url, $path, $query), true, (preg_match('~^30[1237]$~', $code) > 0) ? $code : 302);
			}

			exit();
		}
	}

	public static function Request($input, $filters = null, $callbacks = null, $required = true)
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

	public static function Route($route, $object = null, $callback = null, $method = null, $throttle = null)
	{
		static $result = null;

		if ((strlen($method) * strcasecmp($method, self::Value($_SERVER, 'REQUEST_METHOD'))) == 0)
		{
			$matches = array();

			if (is_null($result) === true)
			{
				$result = rtrim(preg_replace('~/+~', '/', substr(self::Value($_SERVER, 'PHP_SELF'), strlen(self::Value($_SERVER, 'SCRIPT_NAME')))), '/');
			}

			if (preg_match('~' . rtrim(str_replace(array(':any:', ':num:'), array('[^/]+', '[0-9]+'), $route), '/') . '$~i', $result, $matches) > 0)
			{
				if (empty($callback) !== true)
				{
					if ($throttle > 0)
					{
						usleep(intval(floatval($throttle) * 1000000));
					}

					if (empty($object) !== true)
					{
						$callback = array(self::Object($object), $callback);
					}

					exit(call_user_func_array($callback, array_slice($matches, 1)));
				}

				return true;
			}
		}

		return false;
	}

	public static function Segment($key = null, $default = false)
	{
		static $result = null;

		if (is_null($result) === true)
		{
			$result = array_values(array_filter(explode('/', substr(self::Value($_SERVER, 'PHP_SELF'), strlen(self::Value($_SERVER, 'SCRIPT_NAME')))), 'strlen'));
		}

		return (isset($key) === true) ? self::Value($result, (is_int($key) === true) ? $key : (array_search($key, $result) + 1), $default) : $result;
	}

	public static function Sort($array, $natural = true, $reverse = false)
	{
		if (is_array($array) === true)
		{
			if (extension_loaded('intl') === true)
			{
				if (is_object($collator = collator_create('root')) === true)
				{
					if ($natural === true)
					{
						$collator->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
					}

					$collator->asort($array);
				}
			}

			else if (function_exists('array_multisort') === true)
			{
				$data = array();

				foreach ($array as $key => $value)
				{
					if ($natural === true)
					{
						$value = preg_replace('~([0-9]+)~e', "sprintf('%032d', '$1')", $value);
					}

					if (strpos($value = htmlentities($value, ENT_QUOTES, 'UTF-8'), '&') !== false)
					{
						$value = html_entity_decode(preg_replace('~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|tilde|uml);~i', '$1' . chr(255) . '$2', $value), ENT_QUOTES, 'UTF-8');
					}

					$data[$key] = strtolower($value);
				}

				array_multisort($data, $array);
			}

			return ($reverse === true) ? array_reverse($array, true) : $array;
		}

		return false;
	}

	public static function Tag($tag, $content = null)
	{
		$tag = htmlspecialchars(strtolower(trim($tag)));
		$arguments = array_filter(array_slice(func_get_args(), 2), 'is_array');
		$attributes = (empty($arguments) === true) ? array() : call_user_func_array('array_merge', $arguments);

		if ((count($attributes) > 0) && (ksort($attributes) === true))
		{
			foreach ($attributes as $key => $value)
			{
				$attributes[$key] = sprintf(' %s="%s"', htmlspecialchars($key), ($value === true) ? htmlspecialchars($key) : htmlspecialchars($value));
			}
		}

		if (in_array($tag, explode('|', 'area|base|basefont|br|col|frame|hr|img|input|link|meta|param')) === true)
		{
			return sprintf('<%s%s />' . "\n", $tag, implode('', $attributes));
		}

		return sprintf('<%s%s>%s</%s>' . "\n", $tag, implode('', $attributes), htmlspecialchars($content), $tag);
	}

	public static function Text($single, $plural = null, $number = null, $domain = null, $path = null)
	{
		static $result = null;

		if (extension_loaded('gettext') === true)
		{
			foreach (array('LANG', 'LANGUAGE', 'LC_ALL', 'LC_MESSAGES') as $value)
			{
				if (defined($value) === true)
				{
					setlocale(constant($value), 'en_US');
				}

				putenv(sprintf('%s=%s', $value, 'en_US'));
			}

			if (isset($domain) === true)
			{
				$result = $domain;
			}

			if ((isset($path) === true) && (is_dir($path) === true))
			{
				$path = ph()->Disk->Path($path);

				foreach (glob($path . '*.mo') as $value)
				{
					bindtextdomain(basename($value, '.mo'), $path);
					bind_textdomain_codeset(basename($value, '.mo'), 'UTF-8');
				}
			}

			if (isset($result, $single) === true)
			{
				return (isset($plural, $number) === true) ? dngettext($result, $single, $plural, $number) : dgettext($result, $single);
			}
		}

		return ((isset($plural, $number) === true) && (abs($number) !== 1)) ? $plural : $single;
	}

	public static function Throttle($ttl = 60, $exit = 60, $count = 1, $proxy = false)
	{
		if (extension_loaded('apc') === true)
		{
			$ip = ph()->HTTP->IP(null, $proxy);
			$key = array(__METHOD__, $ip, $proxy);

			if (is_bool(apc_add(vsprintf('%s:%s:%b', $key), 0, $ttl)) === true)
			{
				$result = apc_inc(vsprintf('%s:%s:%b', $key), intval($count));

				if ($result < $exit)
				{
					return ($result / $ttl);
				}

				return true;
			}
		}

		return false;
	}

	public static function URL($url = null, $path = null, $query = null)
	{
		if (isset($url) === true)
		{
			if ((is_array($url = @parse_url($url)) === true) && (isset($url['scheme'], $url['host']) === true))
			{
				$result = strtolower($url['scheme']) . '://';

				if ((isset($url['user']) === true) || (isset($url['pass']) === true))
				{
					$result .= ltrim(rtrim(self::Value($url, 'user') . ':' . self::Value($url, 'pass'), ':') . '@', '@');
				}

				$result .= strtolower($url['host']) . '/';

				if ((isset($url['port']) === true) && (strcmp($url['port'], getservbyname($url['scheme'], 'tcp')) !== 0))
				{
					$result = rtrim($result, '/') . ':' . intval($url['port']) . '/';
				}

				if (($path !== false) && ((isset($path) === true) || (isset($url['path']) === true)))
				{
					if (is_scalar($path) === true)
					{
						if (($query !== false) && (preg_match('~[?&]~', $path) > 0))
						{
							$url['query'] = ltrim(rtrim(self::Value($url, 'query'), '&') . '&' . preg_replace('~^.*?[?&]([^#]*).*$~', '$1', $path), '&');
						}

						$url['path'] = '/' . ltrim(preg_replace('~[?&#].*$~', '', $path), '/');
					}

					while (preg_match('~/[.][.]?(?:/|$)~', $url['path']) > 0)
					{
						$url['path'] = preg_replace(array('~/+~', '~/[.](?:/|$)~', '~(?:^|/[^/]+)/[.]{2}(?:/|$)~'), '/', $url['path']);
					}

					$result .= preg_replace('~/+~', '/', ltrim($url['path'], '/'));
				}

				if (($query !== false) && ((isset($query) === true) || (isset($url['query']) === true)))
				{
					parse_str(self::Value($url, 'query'), $url['query']);

					if (is_array($query) === true)
					{
						$url['query'] = array_merge($url['query'], $query);
					}

					if ((count($url['query'] = self::Voodoo(array_filter($url['query'], 'count'))) > 0) && (ksort($url['query']) === true))
					{
						$result .= rtrim('?' . http_build_query($url['query'], '', '&'), '?');
					}
				}

				return preg_replace('~(%[0-9a-f]{2})~e', "strtoupper('$1')", $result);
			}

			return false;
		}

		else if (strlen($scheme = preg_replace('~^www$~i', 'http', getservbyport(self::Value($_SERVER, 'SERVER_PORT', 80), 'tcp'))) > 0)
		{
			return self::URL($scheme . '://' . self::Value($_SERVER, 'HTTP_HOST') . self::Value($_SERVER, 'REQUEST_URI'), $path, $query);
		}

		return false;
	}

	public static function Value($data, $key = null, $default = false)
	{
		if (isset($key) === true)
		{
			foreach ((array) $key as $value)
			{
				$data = (is_object($data) === true) ? get_object_vars($data) : $data;

				if ((is_array($data) !== true) || (array_key_exists($value, $data) !== true))
				{
					return $default;
				}

				$data = $data[$value];
			}
		}

		return $data;
	}

	public static function View($path, $data = null, $minify = true, $return = false)
	{
		if (is_file($path . '.php') === true)
		{
			extract((array) $data);

			if ((($minify === true) || ($return === true)) && (ob_start() === true))
			{
				require($path . '.php');

				if ((($result = ob_get_clean()) !== false) && (ob_start() === true))
				{
					echo ($minify === true) ? preg_replace('~^[[:blank:]]+~m', '', $result) : $result;
				}

				return ($return === true) ? ob_get_clean() : ob_end_flush();
			}

			require($path . '.php');
		}
	}

	public static function Voodoo($data)
	{
		if ((version_compare(PHP_VERSION, '6.0.0', '<') === true) && (get_magic_quotes_gpc() === 1))
		{
			if (is_array($data) === true)
			{
				$result = array();

				foreach ($data as $key => $value)
				{
					$result[self::Voodoo($key)] = self::Voodoo($value);
				}

				return $result;
			}

			return (is_string($data) === true) ? stripslashes($data) : $data;
		}

		return $data;
	}
}

class phunction_Date extends phunction
{
	public function __construct()
	{
	}

	public static function Age($date = 'now')
	{
		if (($date = parent::Date('Ymd', $date)) !== false)
		{
			return intval(substr(parent::Date('Ymd') - $date, 0, -4));
		}

		return false;
	}

	public static function Birthday($date = 'now')
	{
		if (($date = parent::Date('U', $date, sprintf('+%u years', self::Age($date)))) !== false)
		{
			if (($date -= parent::Date('U', 'today')) < 0)
			{
				$date = parent::Date('U', '@' . $date, '+1 year');
			}

			return round($date / 86400);
		}

		return false;
	}

	public static function Calendar($date = 'now', $events = null)
	{
		$result = array();

		if (($date = parent::Date('Ym01', $date)) !== false)
		{
			if (empty($result) === true)
			{
				$date = parent::Date('Ymd', $date, 'this week', '-1 day');
			}

			while (count($result, COUNT_RECURSIVE) < 48)
			{
				if (($date = parent::Date('DATE', $date, '+1 day')) !== false)
				{
					$result[parent::Date('W', $date)][parent::Date('DATE', $date)] = parent::Value($events, parent::Date('DATE', $date), null);
				}
			}
		}

		return $result;
	}

	public static function Frequency($date = 'now')
	{
		if (($date = parent::Date('U', $date)) !== false)
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
		if (($date = parent::Date('U', $date)) !== false)
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

	public static function Zodiac($date = 'now')
	{
		if (($date = parent::Date('md', $date)) !== false)
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
			}

			$data = parent::DB(sprintf('SELECT * FROM %s%s;', $table, (count($id) > 0) ? (' WHERE ' . implode(' AND ', $id)) : ''));

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
					$data[$key] = parent::DB()->quote($value);
				}

				return parent::DB(sprintf('REPLACE INTO %s (%s) VALUES (%s);', $table, implode(', ', array_keys($data)), implode(', ', $data)));
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
				$chmod = (is_dir($path) === true) ? 777 : 666;

				if ((extension_loaded('posix') === true) && (($user = parent::Value(posix_getpwuid(posix_getuid()), 'name')) !== false))
				{
					$chmod -= (in_array($user, explode('|', 'apache|httpd|nobody|system|webdaemon|www|www-data')) === true) ? 0 : 22;
				}
			}

			return chmod($path, octdec(intval($chmod)));
		}

		return false;
	}

	public static function Delete($path)
	{
		if (is_writable($path) === true)
		{
			if (is_dir($path) === true)
			{
				$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::CHILD_FIRST);

				foreach ($files as $file)
				{
					if (in_array($file->getBasename(), array('.', '..')) !== true)
					{
						if ($file->isDir() === true)
						{
							rmdir($file->getPathName());
						}

						else if (($file->isFile() === true) || ($file->isLink() === true))
						{
							unlink($file->getPathname());
						}
					}
				}

				return rmdir($path);
			}

			else if ((is_file($path) === true) || (is_link($path) === true))
			{
				return unlink($path);
			}
		}

		return false;
	}

	public static function Download($path, $speed = null, $multipart = false)
	{
		if (strncmp('cli', PHP_SAPI, 3) !== 0)
		{
			if (is_file($path = self::Path($path)) === true)
			{
				while (ob_get_level() > 0)
				{
					ob_end_clean();
				}

				$file = @fopen($path, 'rb');
				$size = sprintf('%u', filesize($path));
				$speed = (empty($speed) === true) ? 1024 : floatval($speed);

				if (is_resource($file) === true)
				{
					set_time_limit(0);
					session_write_close();

					if ($multipart === true)
					{
						$range = array(0, $size - 1);

						if (array_key_exists('HTTP_RANGE', $_SERVER) === true)
						{
							$range = array_map('intval', explode('-', preg_replace('~.*=([^,]*).*~', '$1', $_SERVER['HTTP_RANGE'])));

							if (empty($range[1]) === true)
							{
								$range[1] = $size - 1;
							}

							foreach ($range as $key => $value)
							{
								$range[$key] = max(0, min($value, $size - 1));
							}

							if (($range[0] > 0) || ($range[1] < ($size - 1)))
							{
								ph()->HTTP->Code(206, 'Partial Content');
							}
						}

						header('Accept-Ranges: bytes');
						header('Content-Range: bytes ' . sprintf('%u-%u/%u', $range[0], $range[1], $size));
					}

					else
					{
						$range = array(0, $size - 1);
					}

					header('Pragma: public');
					header('Cache-Control: public, no-cache');
					header('Content-Type: application/octet-stream');
					header('Content-Length: ' . sprintf('%u', $range[1] - $range[0] + 1));
					header('Content-Disposition: attachment; filename="' . basename($path) . '"');
					header('Content-Transfer-Encoding: binary');

					if ($range[0] > 0)
					{
						fseek($file, $range[0]);
					}

					while ((feof($file) !== true) && (connection_status() === CONNECTION_NORMAL))
					{
						ph()->HTTP->Flush(fread($file, round($speed * 1024)));
						ph()->HTTP->Sleep(1);
					}

					fclose($file);
				}

				exit();
			}

			else
			{
				ph()->HTTP->Code(404, 'Not Found');
			}
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
			if ((empty($ttl) === true) || ((time() - filemtime($path)) <= intval($ttl)))
			{
				return file_get_contents($path);
			}

			return @unlink($path);
		}

		return false;
	}

	public static function Image($input, $crop = null, $scale = null, $merge = null, $output = null, $sharp = true)
	{
		if (isset($input, $output) === true)
		{
			if (is_string($input) === true)
			{
				$input = @ImageCreateFromString(@file_get_contents($input));
			}

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
						$result = false;

						if ((empty($sharp) !== true) && (is_array($matrix = array_fill(0, 9, -1)) === true))
						{
							array_splice($matrix, 4, 1, (is_int($sharp) === true) ? $sharp : 16);

							if (function_exists('ImageConvolution') === true)
							{
								ImageConvolution($image, array_chunk($matrix, 3), array_sum($matrix), 0);
							}
						}

						if ((isset($merge) === true) && (is_resource($merge = @ImageCreateFromString(@file_get_contents($merge))) === true))
						{
							ImageCopy($image, $merge, round(0.95 * $scale[0] - ImageSX($merge)), round(0.95 * $scale[1] - ImageSY($merge)), 0, 0, ImageSX($merge), ImageSY($merge));
						}

						foreach (array('gif' => 0, 'png' => 9, 'jpe?g' => 90) as $key => $value)
						{
							if (preg_match('~' . $key . '$~i', $output) > 0)
							{
								$type = str_replace('?', '', $key);
								$output = preg_replace('~^[.]?' . $key . '$~i', '', $output);

								if (empty($output) === true)
								{
									header('Content-Type: image/' . $type);
								}

								$result = call_user_func_array('Image' . $type, array($image, $output, $value));
							}
						}

						return (empty($output) === true) ? $result : self::Chmod($output);
					}
				}
			}
		}

		else if (count($result = @GetImageSize($input)) >= 2)
		{
			return array_map('intval', array_slice($result, 0, 2));
		}

		return false;
	}

	public static function Log($log, $path, $debug = false)
	{
		if (is_file($path = strftime($path, time()) . '.php') !== true)
		{
			self::File(self::Path(dirname($path), true) . basename($path), '<?php exit(); ?>' . "\n\n", true);
		}

		if (self::File($path, sprintf('[%s]: %s', parent::Date('DATETIME'), trim($log)) . "\n", true) === true)
		{
			if (($debug === true) && (ob_start() === true))
			{
				debug_print_backtrace();

				if (($backtrace = ob_get_clean()) !== false)
				{
					self::File($path, ph()->Text->Indent(trim($backtrace)) . "\n\n", true);
				}
			}

			return true;
		}

		return false;
	}

	public static function Map($path, $pattern = '*', $flags = null)
	{
		if (is_dir($path = self::Path($path)) === true)
		{
			return parent::Sort(str_replace('\\', '/', glob($path . $pattern, GLOB_MARK | GLOB_BRACE | GLOB_NOSORT | $flags)), true);
		}

		return (empty($path) !== true) ? array($path) : false;
	}

	public static function Mime($path, $magic = null)
	{
		$result = false;

		if (($path = self::Path($path)) !== false)
		{
			if (extension_loaded('fileinfo') === true)
			{
				$finfo = call_user_func_array('finfo_open', array_filter(array(FILEINFO_MIME, $magic)));

				if (is_resource($finfo) === true)
				{
					if (function_exists('finfo_file') === true)
					{
						$result = finfo_file($finfo, $path);
					}

					finfo_close($finfo);
				}
			}

			if (empty($result) === true)
			{
				if (function_exists('mime_content_type') === true)
				{
					$result = mime_content_type($path);
				}

				else if (function_exists('exif_imagetype') === true)
				{
					$result = image_type_to_mime_type(exif_imagetype($path));
				}
			}
		}

		return (empty($result) !== true) ? preg_replace('~^(.+);.+$~', '$1', $result) : false;
	}

	public static function Path($path, $mkdir = false, $chmod = null)
	{
		if (($mkdir === true) && (file_exists($path) !== true))
		{
			if (is_null($chmod) === true)
			{
				$chmod = 777;

				if ((extension_loaded('posix') === true) && (($user = parent::Value(posix_getpwuid(posix_getuid()), 'name')) !== false))
				{
					$chmod -= (in_array($user, explode('|', 'apache|httpd|nobody|system|webdaemon|www|www-data')) === true) ? 0 : 22;
				}
			}

			mkdir($path, octdec(intval($chmod)), true);
		}

		if ((isset($path) === true) && (file_exists($path) === true))
		{
			return rtrim(str_replace('\\', '/', realpath($path)), '/') . (is_dir($path) ? '/' : '');
		}

		return false;
	}

	public static function Size($path, $unit = null, $recursive = true)
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
					$result += ($recursive === true) ? self::Size($path . $file, null, $recursive) : 0;
				}

				else if ((is_file($path . $file) === true) || (is_link($path . $file) === true))
				{
					$result += sprintf('%u', filesize($path . $file));
				}
			}
		}

		else if ((is_file($path) === true) || (is_link($path) === true))
		{
			$result += sprintf('%u', filesize($path));
		}

		if ((isset($unit) === true) && ($result > 0))
		{
			if (($unit = array_search($unit, array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'), true)) === false)
			{
				$unit = intval(log($result, 1024));
			}

			$result = array($result / pow(1024, $unit), parent::Value(array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'), $unit));
		}

		return $result;
	}

	public static function Tags($path = null, $tags = null, $fuzzy = true)
	{
		if (count($tags = array_filter(array_unique(array_map('phunction_Text::Slug', (array) $tags)), 'strlen')) > 0)
		{
			$tags = implode('+', parent::Sort($tags, true));

			if ((isset($path) === true) && (is_array($path = self::Map($path, '+*+', GLOB_ONLYDIR)) === true))
			{
				return preg_grep('~[+]' . str_replace('+', ($fuzzy === true) ? '[+]|[+]' : '[+]', $tags) . '[+]~', $path);
			}

			return sprintf('+%s+', $tags);
		}

		return false;
	}

	public static function Upload($input, $output, $mime = null, $magic = null, $chmod = null)
	{
		$result = array();
		$output = self::Path($output, true, $chmod);

		if ((is_dir($output) === true) && (array_key_exists($input, $_FILES) === true))
		{
			if (isset($mime) === true)
			{
				$mime = implode('|', (array) $mime);
			}

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
					if (isset($mime) === true)
					{
						$_FILES[$input]['type'][$key] = self::Mime($_FILES[$input]['tmp_name'][$key], $magic);
					}

					if (preg_match('~' . $mime . '~', $_FILES[$input]['type'][$key]) > 0)
					{
						$file = ph()->Text->Slug($value, '_', '.');

						if (file_exists($output . $file) === true)
						{
							$file = substr_replace($file, '_' . md5_file($_FILES[$input]['tmp_name'][$key]), strrpos($file, '.'), 0);
						}

						if ((move_uploaded_file($_FILES[$input]['tmp_name'][$key], $output . $file) === true) && (self::Chmod($output . $file, $chmod) === true))
						{
							$result[$value] = $output . $file;
						}
					}
				}
			}
		}

		return $result;
	}

	public static function Video($input, $crop = null, $scale = null, $image = null, $output = null, $options = null)
	{
		if (extension_loaded('ffmpeg') === true)
		{
			$input = @new ffmpeg_movie($input);

			if ((is_object($input) === true) && ($input->hasVideo() === true))
			{
				$size = array($input->getFrameWidth(), $input->getFrameHeight());

				if (isset($output) === true)
				{
					$crop = array_values(array_filter(explode('/', $crop), 'is_numeric'));
					$scale = array_values(array_filter(explode('*', $scale), 'is_numeric'));

					if ((is_callable('shell_exec') === true) && (is_executable($ffmpeg = trim(shell_exec('which ffmpeg'))) === true))
					{
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

							$crop = array($input->getFrameWidth() - $size[0], $input->getFrameHeight() - $size[1]);
						}

						else
						{
							$crop = array(0, 0);
						}

						if (count($scale) >= 1)
						{
							if (empty($scale[0]) === true)
							{
								$scale[0] = round($scale[1] * $size[0] / $size[1] / 2) * 2;
							}

							else if (empty($scale[1]) === true)
							{
								$scale[1] = round($scale[0] * $size[1] / $size[0] / 2) * 2;
							}
						}

						else
						{
							$scale = array(round($size[0] / 2) * 2, round($size[1] / 2) * 2);
						}

						$result = array();

						if (array_product($scale) > 0)
						{
							$result[] = sprintf('%s -i %s', escapeshellcmd($ffmpeg), escapeshellarg($input->getFileName()));

							if (array_sum($crop) > 0)
							{
								if (stripos(shell_exec(escapeshellcmd($ffmpeg) . ' -h | grep crop'), 'removed') !== false)
								{
									$result[] = sprintf('-vf "crop=in_w-2*%u:in_h-2*%u"', round($crop[0] / 4) * 2, round($crop[1] / 4) * 2);
								}

								else if ($crop[0] > 0)
								{
									$result[] = sprintf('-cropleft %u -cropright %u', round($crop[0] / 4) * 2, round($crop[0] / 4) * 2);
								}

								else if ($crop[1] > 0)
								{
									$result[] = sprintf('-croptop %u -cropbottom %u', round($crop[1] / 4) * 2, round($crop[1] / 4) * 2);
								}
							}

							if ($input->hasAudio() === true)
							{
								$result[] = sprintf('-ab %u -ar %u', min(131072, $input->getAudioBitRate()), $input->getAudioSampleRate());
							}

							$result[] = sprintf('-r %u -s %s -sameq', min(25, $input->getFrameRate()), implode('x', $scale));

							if (strlen($format = strtolower(ltrim(strrchr($output, '.'), '.'))) > 0)
							{
								$result[] = sprintf('-f %s %s -y %s', $format, escapeshellcmd($options), escapeshellarg($output . '.ffmpeg'));

								if ((strncmp('flv', $format, 3) === 0) && (is_executable($flvtool2 = trim(shell_exec('which flvtool2'))) === true))
								{
									$result[] = sprintf('&& %s -U %s %s', escapeshellcmd($flvtool2), escapeshellarg($output . '.ffmpeg'), escapeshellarg($output . '.ffmpeg'));
								}

								$result[] = sprintf('&& mv -u %s %s', escapeshellarg($output . '.ffmpeg'), escapeshellarg($output));

								if ((is_writable(dirname($output)) === true) && (is_resource($stream = popen('(' . implode(' ', $result) . ') 2>&1 &', 'r')) === true))
								{
									while (($buffer = fgets($stream)) !== false)
									{
										if (strpos($buffer, 'to stop encoding') !== false)
										{
											pclose($stream);

											if (isset($image) === true)
											{
												foreach ((array) $image as $key => $value)
												{
													if (is_object($frame = $input->getFrame(max(1, intval($input->getFrameCount() * (min(100, $key) / 100))))) === true)
													{
														self::Image($frame->toGDImage(), implode('/', $size), implode('*', $scale), null, $value, true);
													}
												}
											}

											return true;
										}
									}

									if (is_file($output . '.ffmpeg') === true)
									{
										unlink($output . '.ffmpeg');
									}

									pclose($stream);
								}
							}
						}
					}
				}

				else if (is_null($output) === true)
				{
					return $size;
				}
			}
		}

		return false;
	}

	public static function Zip($input, $output, $chmod = null)
	{
		if (extension_loaded('zip') === true)
		{
			if (($input = self::Path($input)) !== false)
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

class phunction_HTML extends phunction
{
	public function __construct()
	{
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

	public static function Obfuscate($string, $reverse = true)
	{
		if (ph()->Unicode->strlen($string) > 0)
		{
			$string = array_map(array('phunction_Unicode', 'ord'), ph()->Unicode->str_split($string));

			if ($reverse !== true)
			{
				return sprintf('&#%s;', implode(';&#', $string));
			}

			return sprintf('<span style="unicode-bidi: bidi-override; direction: rtl;">&#%s;</span>', implode(';&#', array_reverse($string)));
		}

		return false;
	}

	public static function Title($string, $raw = true)
	{
		if ((($result = ob_get_clean()) !== false) && (ob_start() === true))
		{
			echo preg_replace('~<title>([^<]*)</title>~i', '<title>' . (($raw === true) ? $string : addcslashes($string, '\\$')) . '</title>', $result, 1);
		}

		return false;
	}

	public static function XSS($string)
	{
		if (is_array($string) === true)
		{
			$result = array();

			foreach ($string as $key => $value)
			{
				$result[self::XSS($key)] = self::XSS($value);
			}

			return $result;
		}

		return (is_string($string) === true) ? htmlspecialchars($string, ENT_QUOTES) : $string;
	}
}

class phunction_HTML_Form extends phunction_HTML
{
	public function __construct()
	{
	}

	public static function Checkbox($id, $value = null, $default = null)
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

	public static function Radio($id, $value = null, $default = null)
	{
		$result = array('type' => 'radio', 'name' => $id, 'value' => $value);

		if (in_array($value, (array) parent::Value($_REQUEST, str_replace('[]', '', $id), $default)) === true)
		{
			$result['checked'] = true;
		}

		return $result;
	}
}

class phunction_HTTP extends phunction
{
	public function __construct()
	{
	}

	public static function Accept($key = null, $type = null, $match = null, $default = false)
	{
		$result = array();
		$header = parent::Value($_SERVER, rtrim('HTTP_ACCEPT_' . strtoupper($type), '_'));

		if (count($header = array_filter(array_map('trim', explode(',', $header)), 'strlen')) > 0)
		{
			foreach ($header as $accept)
			{
				if (count($accept = array_filter(array_map('trim', explode(';', $accept)), 'strlen')) > 0)
				{
					$result[parent::Value($accept, 0)] = floatval(str_replace('q=', '', parent::Value($accept, 1, 1)));
				}
			}

			if (strlen($match = implode('|', (array) $match)) > 0)
			{
				$result = array_intersect_key($result, array_flip(preg_grep('~' . $match . '|^[*](?:/[*])?$~i', array_keys($result))));
			}

			arsort($result, SORT_NUMERIC);
		}

		return (isset($key) === true) ? parent::Value(array_keys($result), intval($key), $default) : $result;
	}

	public static function Cart($id = null, $sku = null, $name = null, $price = null, $quantity = null, $attributes = null)
	{
		if (strlen(session_id()) > 0)
		{
			if (empty($_SESSION[__METHOD__]) === true)
			{
				$_SESSION[__METHOD__] = array();
			}

			if (isset($sku, $name, $price, $quantity) === true)
			{
				if ((is_array($attributes = (array) $attributes) === true) && (ksort($attributes) === true))
				{
					$id = implode('|', array_map('md5', array($sku, json_encode($attributes))));

					foreach (array('sku', 'name', 'price', 'quantity', 'attributes') as $value)
					{
						$_SESSION[__METHOD__][$id][$value] = $$value;
					}
				}
			}

			else if (array_key_exists($id, $_SESSION[__METHOD__]) === true)
			{
				if (($_SESSION[__METHOD__][$id]['quantity'] = intval($quantity)) <= 0)
				{
					$_SESSION[__METHOD__][$id] = null;
				}
			}

			if ((count($_SESSION[__METHOD__] = array_filter($_SESSION[__METHOD__], 'count')) > 1) && (ksort($_SESSION[__METHOD__]) === true))
			{
				return $_SESSION[__METHOD__];
			}
		}

		return array();
	}

	public static function Code($code = 200, $string = null, $replace = true)
	{
		if ((headers_sent() !== true) && (strncmp('cli', PHP_SAPI, 3) !== 0))
		{
			$result = 'Status:';

			if (strncmp('cgi', PHP_SAPI, 3) !== 0)
			{
				$result = parent::Value($_SERVER, 'SERVER_PROTOCOL', 'HTTP/1.1');
			}

			header(rtrim(sprintf('%s %03u %s', (preg_match('~Status:|HTTP/~i', $result) > 0) ? $result : 'HTTP/1.1', $code, $string)), $replace, $code);
		}
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

		if ((headers_sent() !== true) && (strncmp('cli', PHP_SAPI, 3) !== 0))
		{
			$type = (in_array($type, array('LOG', 'INFO', 'WARN', 'ERROR')) === true) ? $type : 'LOG';

			if (strpos(parent::Value($_SERVER, 'HTTP_USER_AGENT'), 'FirePHP') !== false)
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

	public static function JSON($data, $callback = null)
	{
		if (is_string($data = json_encode($data)) === true)
		{
			$callback = preg_replace('~[^[:alnum:]\[\]_.]~', '', $callback);

			if ((strncmp('cli', PHP_SAPI, 3) !== 0) && (headers_sent() !== true))
			{
				header(sprintf('Content-Type: %s', (empty($callback) === true) ? 'application/json' : 'application/javascript'));
			}

			self::Flush(preg_replace('~^[(](.+)[)];$~s', '$1', sprintf('%s(%s);', $callback, $data)));
		}

		exit();
	}

	public static function Method($method, $ajax = false)
	{
		if (in_array(parent::Value($_SERVER, 'REQUEST_METHOD', 'GET'), array_map('strtoupper', (array) $method)) === true)
		{
			if ($ajax === true)
			{
				return (strcasecmp('XMLHttpRequest', parent::Value($_SERVER, 'HTTP_X_REQUESTED_WITH')) === 0);
			}

			return true;
		}

		return false;
	}

	public static function Note($key, $value = null, $type = null)
	{
		if (is_null($value) === true)
		{
			if (isset($key) === true)
			{
				$result = self::Cookie(array(__METHOD__, (empty($type) === true) ? 0 : $type, $key));

				if ($result !== false)
				{
					self::Cookie(array(__METHOD__, (empty($type) === true) ? 0 : $type, $key), false);
				}

				return (is_bool($result) === true) ? $result : json_decode($result);
			}

			return array_keys(self::Cookie(array(__METHOD__, (empty($type) === true) ? 0 : $type)));
		}

		return self::Cookie(array(__METHOD__, (empty($type) === true) ? 0 : $type, $key), json_encode($value));
	}

	public static function Secure()
	{
		return (strcasecmp('off', parent::Value($_SERVER, 'HTTPS', 'off')) !== 0) ? true : false;
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

	public static function ASCII($string = null)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[\x20-\x7E]*$~'))) !== false) ? true : false;
	}

	public static function Alpha($string = null)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[a-z]*$~i'))) !== false) ? true : false;
	}

	public static function Alphanum($string = null)
	{
		return (filter_var($string, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[0-9a-z]*$~i'))) !== false) ? true : false;
	}

	public static function Email($email = null, $mx = false)
	{
		if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
		{
			return (($mx === true) && (function_exists('checkdnsrr') === true)) ? checkdnsrr(ltrim(strrchr($email, '@'), '@'), 'MX') : true;
		}

		return false;
	}

	public static function Float($number = null, $minimum = null, $maximum = null)
	{
		if (filter_var($number, FILTER_VALIDATE_FLOAT) !== false)
		{
			if ((isset($minimum) === true) && ($number < $minimum))
			{
				return false;
			}

			if ((isset($maximum) === true) && ($number > $maximum))
			{
				return false;
			}

			return true;
		}

		return false;
	}

	public static function Integer($number = null, $minimum = null, $maximum = null)
	{
		return (filter_var($number, FILTER_VALIDATE_INT) !== false) ? self::Float($number, $minimum, $maximum) : false;
	}

	public static function IP($ip = null)
	{
		return (filter_var($ip, FILTER_VALIDATE_IP) !== false) ? true : false;
	}

	public static function Set($value = null)
	{
		return (filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~[[:graph:]]~'))) !== false) ? true : false;
	}

	public static function URL($url = null, $path = null, $query = null)
	{
		foreach (array('path', 'query') as $key)
		{
			$$key = (empty($$key) === true) ? 0 : constant(sprintf('FILTER_FLAG_%s_REQUIRED', $key));
		}

		return (filter_var($value, FILTER_VALIDATE_URL, $path + $query) !== false) ? true : false;
	}

	public static function Void($value = null)
	{
		return (filter_var($value, FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '~^[^[:graph:]]*$~'))) !== false) ? true : false;
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

	public static function Base($input, $output, $number = 1, $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
	{
		if (strlen($charset) >= 2)
		{
			$input = max(2, min(intval($input), strlen($charset)));
			$output = max(2, min(intval($output), strlen($charset)));
			$number = ltrim(preg_replace('~[^' . preg_quote(substr($charset, 0, max($input, $output)), '~') . ']+~', '', $number), $charset[0]);

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

	public static function Benchmark($callback, $arguments = null, $iterations = 1000)
	{
		if (is_callable($callback) === true)
		{
			$result = microtime(true);

			for ($i = 1; $i <= $iterations; ++$i)
			{
				call_user_func_array($callback, (array) $arguments);
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

	public static function Cloud($data, $minimum = null, $maximum = null)
	{
		$result = array();

		if ((is_array($data) === true) && (count($data) > 0))
		{
			$data = array_map('abs', $data);
			$regression = array(min($data) => $minimum, max($data) => $maximum);

			foreach ($data as $key => $value)
			{
				$result[$key] = self::Regression($regression, $value);
			}
		}

		return $result;
	}

	public static function Deviation()
	{
		if (function_exists('stats_standard_deviation') !== true)
		{
			$result = self::Average(func_get_args());
			$arguments = parent::Flatten(func_get_args());

			foreach ($arguments as $key => $value)
			{
				$arguments[$key] = pow($value - $result, 2);
			}

			return sqrt(self::Average($arguments));
		}

		return stats_standard_deviation(parent::Flatten(func_get_args()));
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

	public static function Factor($number)
	{
		$i = 2;
		$result = array();

		if (fmod($number, 1) == 0)
		{
			while ($i <= sqrt($number))
			{
				while ($number % $i == 0)
				{
					$number /= $i;

					if (empty($result[$i]) === true)
					{
						$result[$i] = 0;
					}

					++$result[$i];
				}

				$i = (function_exists('gmp_nextprime') === true) ? gmp_strval(gmp_nextprime($i)) : ++$i;
			}

			if ($number > 1)
			{
				$result[$number] = 1;
			}
		}

		return $result;
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
		if ($encode > 0)
		{
			$encode += 1;

			while (--$encode > 0)
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

				$string = implode('', array_reverse($string)) . abs($result);
			}

			return $string;
		}

		else if ($string == self::Luhn(substr($string, 0, max(1, abs($encode)) * -1), max(1, abs($encode))))
		{
			return substr($string, 0, max(1, abs($encode)) * -1);
		}

		return false;
	}

	public static function Matrix($size, $length = 2)
	{
		if (count($size = array_filter(explode('*', $size), 'is_numeric')) == 2)
		{
			$size[0] = min(26, $size[0]);

			foreach (($result = range(1, array_product($size))) as $key => $value)
			{
				$result[$key] = str_pad(mt_rand(0, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
			}

			return array_combine(array_slice(range('A', 'Z'), 0, $size[0]), array_chunk($result, $size[1]));
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

		return (preg_match('~^1?$|^(11+?)\1++$~', str_repeat('1', abs($number))) + preg_last_error() == 0) ? true : false;
	}

	public static function Probability($data, $number = 1)
	{
		$result = array();

		if (is_array($data) === true)
		{
			$data = array_map('abs', $data);
			$number = min(max(1, abs($number)), count($data));

			while (--$number >= 0)
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

	public static function Rating($negative = 0, $positive = 0, $decay = 0, $power = 95)
	{
		$power = max(0, min(100, floatval($power))) / 100;
		$score = (function_exists('stats_cdf_normal') === true) ? stats_cdf_normal($power, 0, 1, 2) : ($power * 0.03115085 - 1.55754263);

		if (($n = $negative + $positive) > 0)
		{
			$p = $positive / $n;

			if (($decay = pow($decay, 0.5)) > 0)
			{
				return (($p + $score * $score / (2 * $n) - $score * sqrt(($p * (1 - $p) + $score * $score / (4 * $n)) / $n)) / (1 + $score * $score / $n)) / $decay;
			}

			return ($p + $score * $score / (2 * $n) - $score * sqrt(($p * (1 - $p) + $score * $score / (4 * $n)) / $n)) / (1 + $score * $score / $n);
		}

		return 0;
	}

	public static function Regression($data, $number = null)
	{
		$n = count($data);
		$sum = array_fill(0, 4, 0);

		if (is_array($data) === true)
		{
			foreach ($data as $key => $value)
			{
				$sum[0] += $key;
				$sum[1] += $value;
				$sum[2] += $key * $key;
				$sum[3] += $key * $value;
			}

			if (($result = $n * $sum[2] - pow($sum[0], 2)) != 0)
			{
				$result = ($n * $sum[3] - $sum[0] * $sum[1]) / $result;
			}

			$result = array('m' => $result, 'b' => ($sum[1] - $result * $sum[0]) / $n);

			if (isset($number) === true)
			{
				return floatval($number) * $result['m'] + $result['b'];
			}

			return $result;
		}

		return false;
	}

	public static function Relative()
	{
		$result = 0;
		$arguments = self::Flatten(func_get_args());

		foreach ($arguments as $argument)
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

	public static function Verhoeff($string, $encode = false)
	{
		if ($encode > 0)
		{
			$d = array_chunk(str_split('0123456789123406789523401789563401289567401239567859876043216598710432765982104387659321049876543210', 1), 10);
			$p = array_chunk(str_split('01234567891576283094580379614289160435279453126870428657390127938064157046913258', 1), 10);
			$inv = str_split('0432156789', 1);
			$encode += 1;

			while (--$encode > 0)
			{
				$result = 0;
				$string = str_split(strrev($string), 1);

				foreach ($string as $key => $value)
				{
					$result = $d[$result][$p[($key + 1) % 8][$value]];
				}

				$string = strrev(implode('', $string)) . $inv[$result];
			}

			return $string;
		}

		else if ($string == self::Verhoeff(substr($string, 0, max(1, abs($encode)) * -1), max(1, abs($encode))))
		{
			return substr($string, 0, max(1, abs($encode)) * -1);
		}

		return false;
	}
}

class phunction_Net extends phunction
{
	public function __construct()
	{
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

	public static function Captcha($value = null, $background = null)
	{
		if (strlen(session_id()) > 0)
		{
			if (is_null($value) === true)
			{
				$result = self::CURL('http://services.sapo.pt/Captcha/Get/');

				if (is_object($result = self::XML($result, '//captcha', 0)) === true)
				{
					$_SESSION[__METHOD__] = parent::Value($result, 'code');

					if (strcasecmp('ok', parent::Value($result, 'msg')) === 0)
					{
						$result = parent::Value($result, 'id');

						if (strlen($background = ltrim($background, '#')) > 0)
						{
							$result .= sprintf('&background=%s', $background);

							if (hexdec($background) < 0x7FFFFF)
							{
								$result .= sprintf('&textcolor=%s', 'ffffff');
							}
						}

						return preg_replace('~^https?:~', '', parent::URL('http://services.sapo.pt/', '/Captcha/Show/', array('id' => strtolower($result))));
					}
				}
			}

			return (strcasecmp(trim($value), parent::Value($_SESSION, __METHOD__)) === 0);
		}

		return false;
	}

	public static function Country($country = null, $language = 'en', $ttl = 604800)
	{
		$key = array(__METHOD__, $language);
		$result = parent::Cache(vsprintf('%s:%s', $key));

		if ($result === false)
		{
			$countries = self::CURL('http://www.geonames.org/countryInfoJSON', array('lang' => $language));

			if ($countries !== false)
			{
				$countries = parent::Value(json_decode($countries, true), 'geonames');

				if (is_array($countries) === true)
				{
					$result = array();

					foreach ($countries as $value)
					{
						$result[$value['countryCode']] = $value['countryName'];
					}

					$result = parent::Cache(vsprintf('%s:%s', $key), parent::Sort($result, false), $ttl);
				}
			}
		}

		if ((isset($country) === true) && (is_array($result) === true))
		{
			return parent::Value($result, strtoupper($country));
		}

		return $result;
	}

	public static function CURL($url, $data = null, $method = 'GET', $cookie = null, $options = null, $retries = 3)
	{
		$result = false;

		if ((extension_loaded('curl') === true) && (is_resource($curl = curl_init()) === true))
		{
			if (($url = parent::URL($url, null, (preg_match('~^(?:POST|PUT)$~i', $method) > 0) ? null : $data)) !== false)
			{
				curl_setopt($curl, CURLOPT_URL, $url);
				curl_setopt($curl, CURLOPT_FAILONERROR, true);
				curl_setopt($curl, CURLOPT_AUTOREFERER, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
				curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
				curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

				if (preg_match('~^(?:DELETE|GET|HEAD|OPTIONS|POST|PUT)$~i', $method) > 0)
				{
					if (preg_match('~^(?:HEAD|OPTIONS)$~i', $method) > 0)
					{
						curl_setopt_array($curl, array(CURLOPT_HEADER => true, CURLOPT_NOBODY => true));
					}

					else if (preg_match('~^(?:POST|PUT)$~i', $method) > 0)
					{
						if ((is_array($data) === true) && ((count($data) != count($data, COUNT_RECURSIVE)) || (count(preg_grep('~^@~', $data)) == 0)))
						{
							$data = http_build_query($data, '', '&');
						}

						curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					}

					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

					if (isset($cookie) === true)
					{
						curl_setopt_array($curl, array_fill_keys(array(CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE), strval($cookie)));
					}

					if (is_array($options) === true)
					{
						curl_setopt_array($curl, $options);
					}

					for ($i = 1; $i <= $retries; ++$i)
					{
						$result = curl_exec($curl);

						if (($i == $retries) || ($result !== false))
						{
							break;
						}

						usleep(pow(2, $i - 2) * 1000000);
					}
				}
			}

			curl_close($curl);
		}

		return $result;
	}

	public static function Currency($input, $output, $value = 1, $ttl = null)
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

		if ((is_array($result) === true) && (isset($result[$input], $result[$output]) === true))
		{
			return floatval($value) * $result[$input] / $result[$output];
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
				'Message-ID' => sprintf('<%s@%s>', md5(microtime(true)), parent::Value($_SERVER, 'HTTP_HOST', 'localhost')),
				'MIME-Version' => '1.0',
			);

			foreach (array('from', 'to', 'cc', 'bcc') as $email)
			{
				if (is_array($$email) !== true)
				{
					$$email = explode(',', $$email);
				}

				$$email = array_filter(filter_var_array(preg_replace('~\s|[<>]|%0[ab]|[[:cntrl:]]~i', '', $$email), FILTER_VALIDATE_EMAIL));

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

				if (array_sum(array(count($to), count($cc), count($bcc))) == 1)
				{
					$count = 0;
					$hashcash = sprintf('1:20:%u:%s::%u:', parent::Date('ymd'), parent::Coalesce($to, $cc, $bcc), mt_rand());

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
						$value = strip_tags(preg_replace('~.*<body(?:\s[^>]*)?>(.+?)</body>.*~is', '$1', $value), '<a><p><br><li>');

						if (preg_match('~</?[a-z][^>]*>~i', $value) > 0)
						{
							$tags = array
							(
								'~<a[^>]+?href="([^"]+)"[^>]*>(.+?)</a>~is' => '$2 ($1)',
								'~<p[^>]*>(.+?)</p>~is' => "\n\n$1\n\n",
								'~<br[^>]*>~i' => "\n",
								'~<li[^>]*>(.+?)</li>~is' => "\n - $1",
								'~\n{3,}~' => "\n\n",
							);

							$value = strip_tags(preg_replace(array_keys($tags), $tags, $value));
						}

						$value = implode("\n", array_map('imap_8bit', explode("\n", preg_replace('~\n{3,}~', "\n\n", trim($value)))));
					}

					$value = array
					(
						sprintf('Content-Type: text/%s; charset=utf-8', $key),
						'Content-Disposition: inline',
						sprintf('Content-Transfer-Encoding: %s', ($key == 'html') ? 'base64' : 'quoted-printable'),
						'', $value, '',
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
								sprintf('Content-Type: application/octet-stream; name="%s"', $key),
								sprintf('Content-Disposition: attachment; filename="%s"', $key),
								'Content-Transfer-Encoding: base64',
								'', trim(imap_binary(file_get_contents($value))), '',
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

					if (is_resource($stream = stream_socket_client($smtp)) === true)
					{
						$data = array('HELO ' . parent::Value($_SERVER, 'HTTP_HOST', 'localhost'));
						$result .= substr(ltrim(fread($stream, 8192)), 0, 3);

						if (preg_match('~^220~', $result) > 0)
						{
							if (count($auth = array_slice(func_get_args(), 8, 2)) == 2)
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

	public static function GeoIP($ip = null, $proxy = false, $ttl = 86400)
	{
		$ip = ph()->HTTP->IP($ip, $proxy);

		if (extension_loaded('geoip') !== true)
		{
			$key = array(__METHOD__, $ip, $proxy);
			$result = parent::Cache(vsprintf('%s:%s:%b', $key));

			if ($result === false)
			{
				if (($result = self::CURL('http://api.wipmania.com/' . $ip)) !== false)
				{
					$result = parent::Cache(vsprintf('%s:%s:%b', $key), trim($result), $ttl);
				}
			}

			return $result;
		}

		return (geoip_db_avail(GEOIP_COUNTRY_EDITION) === true) ? geoip_country_code_by_name($ip) : false;
	}

	public static function Gravatar($id, $size = 80, $rating = 'g', $default = 'identicon')
	{
		if (ph()->HTTP->Secure() === true)
		{
			return sprintf('https://secure.gravatar.com/avatar/%s?s=%u&r=%s&d=%s', md5(strtolower(trim($id))), $size, $rating, urlencode($default));
		}

		return sprintf('http://gravatar.com/avatar/%s?s=%u&r=%s&d=%s', md5(strtolower(trim($id))), $size, $rating, urlencode($default));
	}

	public static function OpenID($id, $realm = null, $return = null, $verify = true)
	{
		$data = array();

		if (($verify === true) && (array_key_exists('openid_mode', $_REQUEST) === true))
		{
			$result = parent::Value($_REQUEST, 'openid_claimed_id', parent::Value($_REQUEST, 'openid_identity'));

			if ((strcmp('id_res', parent::Value($_REQUEST, 'openid_mode')) === 0) && (ph()->Is->URL($result) === true))
			{
				$data['openid.mode'] = 'check_authentication';

				foreach (array('ns', 'sig', 'signed', 'assoc_handle') as $key)
				{
					$data['openid.' . $key] = parent::Value($_REQUEST, 'openid_' . $key);

					if (strcmp($key, 'signed') === 0)
					{
						foreach (explode(',', parent::Value($_REQUEST, 'openid_signed')) as $value)
						{
							$data['openid.' . $value] = parent::Value($_REQUEST, 'openid_' . str_replace('.', '_', $value));
						}
					}
				}

				return (preg_match('~is_valid\s*:\s*true~', self::CURL(self::OpenID($result, false, false, false), array_filter($data, 'is_string'), 'POST')) > 0) ? $result : false;
			}
		}

		else if (($result = self::XML(self::CURL($id))) !== false)
		{
			$server = null;
			$protocol = array
			(
				array('specs.openid.net/auth/2.0/server', 'specs.openid.net/auth/2.0/signon', array('openid2.provider', 'openid2.local_id')),
				array('openid.net/signon/1.1', 'openid.net/signon/1.0', array('openid.server', 'openid.delegate')),
			);

			foreach ($protocol as $key => $value)
			{
				while ($namespace = array_shift($value))
				{
					if (is_array($namespace) === true)
					{
						$server = strval(self::XML($result, sprintf('//head/link[contains(@rel, "%s")]/@href', $namespace[0]), 0));
						$delegate = strval(self::XML($result, sprintf('//head/link[contains(@rel, "%s")]/@href', $namespace[1]), 0, $id));
					}

					else if (is_object($xml = self::XML($result, sprintf('//xrd/service[contains(type, "http://%s")]', $namespace), 0)) === true)
					{
						$server = parent::Value($xml, 'uri');

						if ($key === 0)
						{
							$delegate = 'http://specs.openid.net/auth/2.0/identifier_select';

							if (strcmp($namespace, 'specs.openid.net/auth/2.0/server') !== 0)
							{
								$delegate = parent::Value($xml, 'localid', parent::Value($xml, 'canonicalid', $id));
							}
						}

						else if ($key === 1)
						{
							$delegate = parent::Value($xml, 'delegate', $id);
						}
					}

					if (ph()->Is->URL($server) === true)
					{
						if (($realm !== false) && ($return !== false))
						{
							$data['openid.mode'] = 'checkid_setup';
							$data['openid.identity'] = $delegate;
							$data['openid.return_to'] = parent::URL($return, null, null);

							if ($key === 0)
							{
								$data['openid.ns'] = 'http://specs.openid.net/auth/2.0';
								$data['openid.realm'] = parent::URL($realm, false, false);
								$data['openid.claimed_id'] = $delegate;
							}

							else if ($key === 1)
							{
								$data['openid.trust_root'] = parent::URL($realm, false, false);
							}

							parent::Redirect(parent::URL($server, null, $data));
						}

						return $server;
					}
				}
			}
		}

		return false;
	}

	public static function Reducisaurus($input, $type = null, $output = null, $chmod = null, $ttl = 3600)
	{
		if (isset($input, $type) === true)
		{
			$data = array_fill_keys(array('max-age', 'expire_urls'), intval($ttl));

			foreach ((array) $input as $value)
			{
				$data['url' . (count($data) - 1)] = (ph()->Is->URL($value) === true) ? $value : ph()->URL(null, $value);
			}

			if (empty($type) === true)
			{
				$input = preg_grep('~[.](?:js|css)$~i', (array) $input);

				foreach (array('js', 'css') as $value)
				{
					$type[$value] = count(preg_grep('~[.]' . $value . '$~i', $input));
				}

				$type = strtolower(array_search(max($type), $type));
			}

			if ((count($data) > 2) && (preg_match('~^(?:js|css)$~i', $type) > 0))
			{
				$result = parent::URL('http://reducisaurus.appspot.com/', '/' . $type, $data);

				if ((isset($output) === true) && (($result = self::CURL($result)) !== false))
				{
					$result = ph()->Disk->File($output, $result, false, $chmod, $ttl);
				}

				return preg_replace('~^https?:~', '', $result);
			}
		}

		return false;
	}

	public static function SMS($to, $from, $message, $username, $password, $unicode = false)
	{
		$data = array();
		$message = trim($message);

		if (isset($username, $password) === true)
		{
			$data['username'] = $username;
			$data['password'] = $password;

			if (isset($to, $from, $message) === true)
			{
				$message = ph()->Text->Reduce($message, ' ');

				if (preg_match('~[^\x20-\x7E]~', $message) > 0)
				{
					$message = parent::Filter($message);

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
					$data['maxconcat'] = '10';
				}

				return (strpos(self::CURL('https://www.intellisoftware.co.uk/smsgateway/sendmsg.aspx', $data, 'POST'), 'ID:') !== false) ? true : false;
			}

			return intval(preg_replace('~^BALANCE:~', '', self::CURL('https://www.intellisoftware.co.uk/smsgateway/getbalance.aspx', $data, 'POST')));
		}

		return false;
	}

	public static function Smush($input, $output = null, $chmod = null)
	{
		if (isset($input) === true)
		{
			$data = array('img' => $input);

			if ((is_file($input = ph()->Disk->Path($input)) === true) && (filesize($input) <= 1048576))
			{
				$data = array('files' => '@' . $input);
			}

			if (($result = self::CURL('http://www.smushit.com/ws.php', $data, 'POST')) !== false)
			{
				if ((($result = parent::Value(json_decode($result, true), 'dest')) !== false) && (isset($output) === true))
				{
					$result = ph()->Disk->File($output, self::CURL($result), false, $chmod);
				}

				return $result;
			}
		}

		return false;
	}

	public static function Socket($host, $port, $timeout = 3)
	{
		$time = microtime(true);
		$socket = @fsockopen($host, intval($port), $errno, $errstr, floatval($timeout));

		if ((is_resource($socket) === true) && (fclose($socket) === true))
		{
			return microtime(true) - $time;
		}

		return false;
	}

	public static function TinySRC($image, $scale = null, $format = null)
	{
		if (ph()->Is->URL($image) !== true)
		{
			$image = ph()->URL(null, $image);
		}

		return sprintf('http://src.sencha.io/%s%s%s', ltrim($format . '/', '/'), ltrim(implode('/', array_slice(explode('*', $scale), 0, 2)) . '/', '/'), $image);
	}

	public static function VIES($vatin, $country)
	{
		if ((preg_match('~[A-Z]{2}~', $country) > 0) && (preg_match('~[0-9A-Z.+*]{2,12}~', $vatin) > 0))
		{
			$soap = new SoapClient('http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl', array('exceptions' => false));

			if (is_object($soap) === true)
			{
				return parent::Value($soap->__soapCall('checkVat', array(array('countryCode' => $country, 'vatNumber' => $vatin))), 'valid');
			}
		}

		return false;
	}

	public static function Whois($domain)
	{
		if (strpos($domain, '.') !== false)
		{
			$tld = strtolower(ltrim(strrchr($domain, '.'), '.'));
			$socket = @fsockopen($tld . '.whois-servers.net', 43);

			if (is_resource($socket) === true)
			{
				if (preg_match('~com|net~', $tld) > 0)
				{
					$domain = sprintf('domain %s', $domain);
				}

				if (fwrite($socket, $domain . "\r\n") !== false)
				{
					$result = null;

					while (feof($socket) !== true)
					{
						$result .= fread($socket, 8192);
					}

					return $result;
				}
			}
		}

		return false;
	}

	public static function XML($xml, $xpath = null, $key = null, $default = false)
	{
		if (extension_loaded('libxml') === true)
		{
			libxml_use_internal_errors(true);

			if ((extension_loaded('dom') === true) && (extension_loaded('SimpleXML') === true))
			{
				if (is_string($xml) === true)
				{
					$dom = new DOMDocument();

					if (@$dom->loadHTML($xml) === true)
					{
						return self::XML(@simplexml_import_dom($dom), $xpath, $key, $default);
					}
				}

				else if ((is_object($xml) === true) && (strcmp('SimpleXMLElement', get_class($xml)) === 0))
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
		}

		return false;
	}
}

class phunction_Net_Akismet extends phunction_Net
{
	public function __construct()
	{
	}

	public static function Check($key, $text, $name = null, $email = null, $domain = null, $type = null)
	{
		$data = array
		(
			'blog' => parent::URL(null, false, false),
			'comment_author' => $name,
			'comment_author_email' => $email,
			'comment_author_url' => $domain,
			'comment_content' => $text,
			'comment_type' => $type,
			'permalink' => parent::URL(null, null, null),
			'referrer' => parent::Value($_SERVER, 'HTTP_REFERER'),
			'user_agent' => parent::Value($_SERVER, 'HTTP_USER_AGENT'),
			'user_ip' => ph()->HTTP->IP(null, false),
		);

		if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/comment-check', $key), array_merge($data, preg_grep('~^HTTP_~', $_SERVER)), 'POST')) !== false)
		{
			return (strcmp('true', $result) === 0) ? true : false;
		}

		return false;
	}

	public static function Submit($key, $text, $name = null, $email = null, $domain = null, $type = null, $endpoint = null)
	{
		if (in_array($endpoint, array('ham', 'spam')) === true)
		{
			$data = array
			(
				'blog' => parent::URL(null, false, false),
				'comment_author' => $name,
				'comment_author_email' => $email,
				'comment_author_url' => $domain,
				'comment_content' => $text,
				'comment_type' => $type,
				'permalink' => parent::URL(null, null, null),
				'referrer' => parent::Value($_SERVER, 'HTTP_REFERER'),
				'user_agent' => parent::Value($_SERVER, 'HTTP_USER_AGENT'),
				'user_ip' => ph()->HTTP->IP(null, false),
			);

			if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/submit-%s', $key, $endpoint), $data, 'POST')) !== false)
			{
				return true;
			}
		}

		return false;
	}

	public static function Verify($key)
	{
		$data = array
		(
			'key' => $key,
			'blog' => parent::URL(null, false, false),
		);

		if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/verify-key', $key), $data, 'POST')) !== false)
		{
			return (strcmp('valid', $result) === 0) ? true : false;
		}

		return false;
	}
}

class phunction_Net_Google extends phunction_Net
{
	public function __construct()
	{
	}

	public static function Calculator($input, $output, $query = 1)
	{
		$data = array
		(
			'q' => $query . $input . '=?' . $output,
		);

		if (($result = parent::CURL('http://www.google.com/ig/calculator', $data)) !== false)
		{
			$result = preg_replace(array('~([{,])~', '~:[[:blank:]]+~'), array('$1"', '":'), parent::Filter($result, true));

			if ((is_array($result = json_decode($result, true)) === true) && (strlen(parent::Value($result, 'error')) == 0))
			{
				return parent::Value($result, 'rhs');
			}
		}

		return false;
	}

	public static function Distance($input, $output, $mode = null, $avoid = null, $units = null)
	{
		$data = array
		(
			'avoid' => $avoid,
			'destinations' => implode('|', (array) $output),
			'mode' => $mode,
			'origins' => implode('|', (array) $input),
			'sensor' => 'false',
			'units' => $units,
		);

		if (is_array($result = json_decode(parent::CURL('http://maps.googleapis.com/maps/api/distancematrix/json', $data), true)) === true)
		{
			return parent::Value($result, 'rows');
		}

		return false;
	}

	public static function Geocode($query, $country = null, $reverse = false)
	{
		$data = array
		(
			'address' => $query,
			'region' => $country,
			'sensor' => 'false',
		);

		if (is_array($result = json_decode(parent::CURL('http://maps.googleapis.com/maps/api/geocode/json', $data), true)) === true)
		{
			return parent::Value($result, ($reverse === true) ? array('results', 0, 'formatted_address') : array('results', 0, 'geometry', 'location'));
		}

		return false;
	}

	public static function Icon($url)
	{
		return preg_replace('~^https?:~', '', parent::URL('http://www.google.com/', '/s2/favicons', array('domain' => $url)));
	}

	public static function Map($query, $type = 'roadmap', $size = '500x300', $zoom = 12, $markers = null)
	{
		$data = array
		(
			'center' => $query,
			'maptype' => $type,
			'markers' => implode('|', (array) $markers),
			'sensor' => 'false',
			'size' => $size,
			'zoom' => $zoom,
		);

		return preg_replace('~^https?:~', '', parent::URL('http://maps.google.com/', '/maps/api/staticmap', $data));
	}

	public static function Search($query, $class = 'web', $start = 0, $results = 4, $arguments = null)
	{
		$data = array
		(
			'q' => $query,
			'rsz' => $results,
			'start' => intval($start),
			'userip' => ph()->HTTP->IP(null, false),
			'v' => '1.0',
		);

		if (is_array($result = json_decode(parent::CURL('http://ajax.googleapis.com/ajax/services/search/' . $class, $data), true)) === true)
		{
			return (is_array($result = parent::Value($result, 'responseData')) === true) ? $result : false;
		}

		return false;
	}

	public static function Speed($url)
	{
		if (is_array($result = json_decode(parent::CURL('http://pagespeed.googlelabs.com/run_pagespeed', array('url' => $url)), true)) === true)
		{
			return parent::Value($result, 'results');
		}

		return false;
	}

	public static function Talk($to, $message, $username, $password)
	{
		$id = null;

		if (is_resource($stream = stream_socket_client('tcp://talk.google.com:5222/')) === true)
		{
			$data = array
			(
				'<stream:stream to="gmail.com" version="1.0" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client">',
				'<starttls xmlns="urn:ietf:params:xml:ns:xmpp-tls"><required /></starttls>',
				'<stream:stream to="gmail.com" version="1.0" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client">',
				'<auth mechanism="PLAIN" xmlns="urn:ietf:params:xml:ns:xmpp-sasl">' . base64_encode("\0" . $username . "\0" . $password) . '</auth>',
				'<stream:stream to="gmail.com" version="1.0" xmlns:stream="http://etherx.jabber.org/streams" xmlns="jabber:client">',
				'<iq id="1" type="set" xmlns="jabber:client"><bind xmlns="urn:ietf:params:xml:ns:xmpp-bind"><resource>' . __FUNCTION__ . '</resource></bind></iq>',
				'<iq id="2" type="set" xmlns="jabber:client"><session xmlns="urn:ietf:params:xml:ns:xmpp-session" /></iq>',
				'<message from="%s" to="' . htmlspecialchars($to) . '" type="chat"><body>' . htmlspecialchars($message) . '</body></message>',
				'</stream:stream>',
			);

			while ((count($data) > 0) && (fwrite($stream, sprintf(array_shift($data), $id)) !== false))
			{
				while ((@stream_select($read = array($stream), $write = null, $except = null, 0, 100000) > 0) && (feof($stream) !== true))
				{
					if ((($result = fread($stream, 8192)) !== false) && (is_null($id) === true))
					{
						$result = parent::XML($result);

						if (is_object(parent::XML($result, '//jid', 0)) === true)
						{
							$id = strval(parent::XML($result, '//jid', 0));
						}

						else if (is_object(parent::XML($result, '//proceed', 0)) === true)
						{
							stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
						}
					}
				}
			}

			fclose($stream);
		}

		return (isset($id) === true) ? true : false;
	}

	public static function Translate($input, $output, $query = null)
	{
		$data = array
		(
			'langpair' => sprintf('%s|%s', $input, $output),
			'q' => $query,
			'v' => '1.0',
		);

		if (($result = parent::CURL('http://ajax.googleapis.com/ajax/services/language/translate', $data)) !== false)
		{
			return parent::Value(json_decode($result, true), array('responseData', 'translatedText'));
		}

		return false;
	}

	public static function Weather($query)
	{
		$weather = parent::XML(parent::CURL('http://www.google.com/ig/api', array('weather' => $query)));

		if ($weather !== false)
		{
			$result = array();

			foreach (parent::XML($weather, '//forecast_conditions') as $key => $value)
			{
				$result[$key] = array(strval($value->low['data']), strval($value->high['data']));
			}

			return $result;
		}

		return false;
	}
}

class phunction_Net_PayPal extends phunction_Net
{
	public function __construct()
	{
	}

	public static function API($auth, $data, $method, $version, $endpoint = 'https://api-3t.paypal.com/nvp/')
	{
		if (($result = parent::CURL($endpoint, array_merge(array('METHOD' => $method, 'VERSION' => $version), (array) $auth, (array) $data), 'POST')) !== false)
		{
			parse_str($result, $result);

			if (is_array($result = parent::Voodoo($result)) === true)
			{
				foreach (preg_grep('~^L_[[:alnum:]_]+?[0-9]+$~', array_keys($result)) as $key)
				{
					$result[rtrim($key, '0..9')][] = $result[$key]; unset($result[$key]);
				}

				return $result;
			}
		}

		return false;
	}

	public static function IPN($email, $status = null, $endpoint = 'https://www.paypal.com/cgi-bin/webscr/')
	{
		if (preg_match('~(?:^|[.])paypal[.]com$~i', gethostbyaddr(ph()->HTTP->IP(null, false))) > 0)
		{
			if (strncmp('VERIFIED', parent::CURL($endpoint, array_merge(array('cmd' => '_notify-validate'), $_POST), 'POST'), 8) === 0)
			{
				$email = strlen($email) * strcasecmp($email, parent::Value($_POST, 'receiver_email'));
				$status = strlen($status) * strcasecmp($status, parent::Value($_POST, 'payment_status'));

				if (($email == 0) && ($status == 0))
				{
					return $_POST;
				}
			}
		}

		return false;
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

	public static function Humanify($string)
	{
		return ph()->Unicode->ucwords(trim(str_replace('_', ' ', ph()->Unicode->strtolower($string))));
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

	public static function Namify($string)
	{
		return ph()->Unicode->ucwords(ph()->Unicode->strtolower(preg_replace('~\s*\b(\p{L}+)\b.+\b(\p{L}+)\b\s*~u', '$1 $2', $string)));
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
		if (count($result = array_unique(self::str_word_count($string, 1))) > 0)
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
		if (count($string = self::str_split($string)) > 1)
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

	public static function strcasecmp($string, $search)
	{
		return strcmp(self::strtolower($string), self::strtolower($search));
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
		if (($result = self::stripos($string, $search)) !== false)
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
		if (($result = self::strpos($string, $search)) !== false)
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
				$string[$key] = html_entity_decode('&#' . (self::ord($value) + 32) . ';', ENT_QUOTES, 'UTF-8');
			}
		}

		return implode('', $string);
	}

	public static function strtoupper($string)
	{
		$string = self::str_split($string);

		foreach ($string as $key => $value)
		{
			if (preg_match('~\p{Ll}~u', $value) > 0)
			{
				$string[$key] = html_entity_decode('&#' . (self::ord($value) - 32) . ';', ENT_QUOTES, 'UTF-8');
			}
		}

		return implode('', $string);
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
		if (count($result = array_unique(self::str_word_count($string, 1))) > 0)
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
		phunction::$id = strval($ph);
	}

	return $result;
}

?>