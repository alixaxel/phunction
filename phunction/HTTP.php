<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

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
				$result = parent::Value($_SERVER, 'SERVER_PROTOCOL');
			}

			header(rtrim(sprintf('%s %03u %s', $result, $code, $string)), $replace, $code);
		}
	}

	public static function Cookie($key, $value = null, $expire = null, $http = true)
	{
		if (isset($value) === true)
		{
			if (is_array($key) === true)
			{
				$key = preg_replace('~[[](.*?)[]]~', '$1', sprintf('[%s]', implode('][', $key)), 1);
			}

			return (headers_sent() !== true) ? setcookie($key, strval($value), intval($expire), '/', null, self::Secure(), $http) : false;
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
		if ((in_array(parent::Value($_SERVER, 'HTTPS'), array(1, 'on')) === true) || (strcasecmp('https', parent::Value($_SERVER, 'HTTP_X_FORWARDED_PROTO')) === 0))
		{
			return true;
		}

		return false;
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

			return (isset($value) === true) ? true : $result;
		}

		return false;
	}
}

?>