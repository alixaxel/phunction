<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

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

	public static function Closure($input, $type = null, $output = null, $chmod = null, $ttl = 3600)
	{
		if (isset($input) === true)
		{
			$data = array
			(
				'compilation_level' => 'SIMPLE_OPTIMIZATIONS',
				'output_format' => 'json',
				'output_info' => 'compiled_code',
			);

			if (strcasecmp($type, 'ADVANCED') === 0)
			{
				$data['compilation_level'] = 'ADVANCED_OPTIMIZATIONS';
			}

			$data = http_build_query($data, '', '&');

			foreach ((array) $input as $value)
			{
				$data .= sprintf('&code_url=%s', urlencode((ph()->Is->URL($value) === true) ? $value : ph()->URL(null, $value)));
			}

			if (($result = parent::CURL('http://closure-compiler.appspot.com/compile', $data, 'POST')) !== false)
			{
				if ((isset($output) === true) && (($result = parent::Value(json_decode($result, true), 'compiledCode')) !== false))
				{
					$result = ph()->Disk->File($output, $result, false, $chmod, $ttl);
				}

				return $result;
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

		if (($result = parent::CURL('http://maps.googleapis.com/maps/api/distancematrix/json', $data)) !== false)
		{
			return parent::Value(json_decode($result, true), 'rows');
		}

		return false;
	}

	public static function Feed($url, $entries = -1)
	{
		$data = array
		(
			'num' => intval($entries),
			'output' => 'json',
			'q' => $url,
			'v' => '1.0',
		);

		if (($result = parent::CURL('http://ajax.googleapis.com/ajax/services/feed/load', $data)) !== false)
		{
			return parent::Value(json_decode($result, true), array('responseData', 'feed'));
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

		if (($result = parent::CURL('http://maps.googleapis.com/maps/api/geocode/json', $data)) !== false)
		{
			return parent::Value(json_decode($result, true), ($reverse === true) ? array('results', 0, 'formatted_address') : array('results', 0, 'geometry', 'location'));
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

	public static function QR($query, $size = '500x500', $quality = 'Q')
	{
		$data = array
		(
			'chl' => $query,
			'chld' => sprintf('%s|2', $quality),
			'choe' => 'UTF-8',
			'chs' => $size,
			'cht' => 'qr',
		);

		return preg_replace('~^https?:~', '', parent::URL('http://chart.googleapis.com/', '/chart', $data));
	}

	public static function Rank($url)
	{
		if ((($url = parent::URL($url)) !== false) && (($result = parent::CURL('http://snurl.com/1invai', array('string' => $url))) !== false))
		{
			$data = array
			(
				'ch' => ph()->Text->Regex($result, '<pre>([0-9]+)</pre>', array(1, 0)),
				'client' => 'navclient-auto-ff',
				'features' => 'Rank',
				'q' => 'info:' . $url,
			);

			return intval(ltrim(strrchr(parent::CURL('http://toolbarqueries.google.com/search', $data), ':'), ':'));
		}

		return false;
	}

	public static function reCAPTCHA($api)
	{
		$data = array
		(
			'challenge' => parent::Value($_POST, 'recaptcha_challenge_field'),
			'privatekey' => $api,
			'remoteip' => ph()->HTTP->IP(),
			'response' => trim(parent::Value($_POST, 'recaptcha_response_field')),
		);

		if ((count(array_filter($data, 'strlen')) == 4) && (($result = parent::CURL('http://www.google.com/recaptcha/api/verify', $data, 'POST')) !== false))
		{
			return (strncasecmp('true', $result, 4) === 0) ? true : parent::Value(explode("\n", $result), 1, 'incorrect-captcha-sol');
		}

		return false;
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

		if (($result = parent::CURL('http://ajax.googleapis.com/ajax/services/search/' . $class, $data)) !== false)
		{
			return (is_array($result = parent::Value(json_decode($result, true), 'responseData')) === true) ? $result : false;
		}

		return false;
	}

	public static function Speed($url)
	{
		if (($result = parent::CURL('http://pagespeed.googlelabs.com/run_pagespeed', array('url' => $url))) !== false)
		{
			return parent::Value(json_decode($result, true), 'results');
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
				'<message from="%s" to="' . ph()->HTML->Encode($to) . '" type="chat"><body>' . ph()->HTML->Encode($message) . '</body></message>',
				'</stream:stream>',
			);

			while ((count($data) > 0) && (fwrite($stream, sprintf(array_shift($data), $id)) !== false))
			{
				while ((@stream_select($read = array($stream), $write = null, $except = null, 0, 100000) > 0) && (feof($stream) !== true))
				{
					if ((($result = fread($stream, 8192)) !== false) && (is_null($id) === true))
					{
						$result = ph()->HTML->DOM($result);

						if (is_object(ph()->HTML->DOM($result, '//jid', 0)) === true)
						{
							$id = strval(ph()->HTML->DOM($result, '//jid', 0));
						}

						else if (is_object(ph()->HTML->DOM($result, '//proceed', 0)) === true)
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

	public static function Weather($query)
	{
		$weather = ph()->HTML->DOM(parent::CURL('http://www.google.com/ig/api', array('weather' => $query)));

		if ($weather !== false)
		{
			$result = array();

			foreach (ph()->HTML->DOM($weather, '//forecast_conditions') as $key => $value)
			{
				$result[$key] = array(strval($value->low['data']), strval($value->high['data']));
			}

			return $result;
		}

		return false;
	}
}

?>