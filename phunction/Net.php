<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Net extends phunction
{
	public function __construct()
	{
	}

	public function __get($key)
	{
		return $this->$key = parent::__get(sprintf('%s_%s', ltrim(strrchr(__CLASS__, '_'), '_'), $key));
	}

	public static function Captcha($value = null, $background = null)
	{
		if (strlen(session_id()) > 0)
		{
			if (is_null($value) === true)
			{
				$result = self::CURL('http://services.sapo.pt/Captcha/Get/');

				if (is_object($result = ph()->HTML->DOM($result, '//captcha', 0)) === true)
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
			if (($countries = self::CURL('http://www.geonames.org/countryInfoJSON', array('lang' => $language))) !== false)
			{
				if (is_array($countries = parent::Value(json_decode($countries, true), 'geonames')) === true)
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
						if (is_array($data) === true)
						{
							foreach (preg_grep('~^@~', $data) as $key => $value)
							{
								$data[$key] = sprintf('@%s', ph()->Disk->Path(ltrim($value, '@')));
							}

							if (count($data) != count($data, COUNT_RECURSIVE))
							{
								$data = http_build_query($data, '', '&');
							}
						}

						curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					}

					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

					if (isset($cookie) === true)
					{
						curl_setopt_array($curl, array_fill_keys(array(CURLOPT_COOKIEJAR, CURLOPT_COOKIEFILE), strval($cookie)));
					}

					if ((intval(ini_get('safe_mode')) == 0) && (ini_set('open_basedir', null) !== false))
					{
						curl_setopt_array($curl, array(CURLOPT_MAXREDIRS => 5, CURLOPT_FOLLOWLOCATION => true));
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
			$currencies = ph()->HTML->DOM(self::CURL('http://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml'), '//cube/cube/cube');

			if (is_array($currencies) === true)
			{
				$result['EUR'] = 1;

				foreach ($currencies as $currency)
				{
					$result[strval($currency['currency'])] = 1 / floatval($currency['rate']);
				}

				if (is_null($ttl) === true)
				{
					$ttl = parent::Date('U', '6 PM', true) - $_SERVER['REQUEST_TIME'];

					if ($ttl < 0)
					{
						$ttl = parent::Date('U', '@' . $ttl, true, '+1 day');
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
					$$email = array_map('trim', explode(',', $$email));
				}

				$$email = array_change_key_case(($$email === array_values($$email)) ? array_flip($$email) : $$email, CASE_LOWER);

				if (count($$email = array_intersect_key($$email, array_flip(array_filter(array_keys($$email), array(ph()->Is, 'Email'))))) > 0)
				{
					$header[ucfirst($email)] = array();

					foreach ($$email as $key => $value)
					{
						$key = explode('@', $key);

						if (preg_match('~[^\x20-x7E]~', $value = preg_replace('~^\d+$|[[:cntrl:]]~', '', $value)) > 0)
						{
							$value = sprintf('=?UTF-8?B?%s?=', base64_encode($value));
						}

						$header[ucfirst($email)][] = imap_rfc822_write_address($key[0], $key[1], $value);
					}
				}
			}

			if (count($from) * count($recipients = array_keys(array_merge($to, $cc, $bcc))) > 0)
			{
				$header['Sender'] = $header['Reply-To'] = $header['From'][0];
				$header['Subject'] = preg_replace('~[[:cntrl:]]~', '', $subject);
				$header['Return-Path'] = sprintf('<%s>', key(array_slice($from, 0, 1)));
				$header['Content-Type'] = sprintf('multipart/alternative; boundary="%s"', $boundary);

				if (count($recipients) == 1)
				{
					$count = 0;
					$hashcash = sprintf('1:20:%u:%s::%u', parent::Date('ymd'), parent::Coalesce($recipients), mt_rand());

					while (strncmp('00000', sha1($hashcash . $count), 5) !== 0)
					{
						++$count;
					}

					$header['X-Hashcash'] = $hashcash . $count;
				}

				foreach (array_fill_keys(array('plain', 'html'), trim(str_replace("\r", '', $message))) as $key => $value)
				{
					if ($key == 'plain')
					{
						$value = preg_replace('~.*<body(?:\s[^>]*)?>(.+?)</body>.*~is', '$1', $value);

						if (preg_match('~</?[a-z][^>]*>~i', $value = strip_tags($value, '<a><p><br><li>')) > 0)
						{
							$regex = array
							(
								'~<a[^>]+?href="([^"]+)"[^>]*>(.+?)</a>~is' => '$2 ($1)',
								'~<p[^>]*>(.+?)</p>~is' => "\n\n$1\n\n",
								'~<br[^>]*>~i' => "\n",
								'~<li[^>]*>(.+?)</li>~is' => "\n - $1",
							);

							$value = strip_tags(preg_replace(array_keys($regex), $regex, $value));
						}

						$value = implode("\n", array_map('imap_8bit', explode("\n", preg_replace('~\n{3,}~', "\n\n", trim($value)))));
					}

					else if ($key == 'html')
					{
						$value = trim(imap_binary($value));
					}

					$value = array
					(
						sprintf('Content-Type: text/%s; charset=utf-8', $key),
						sprintf('Content-Disposition: %s', 'inline'),
						sprintf('Content-Transfer-Encoding: %s', ($key == 'plain') ? 'quoted-printable' : 'base64'),
						'', $value, '',
					);

					$content = array_merge($content, array(sprintf('--%s', $boundary)), $value);
				}

				$content[] = sprintf('--%s--', $boundary);

				if (count($attachments = (array) $attachments) > 0)
				{
					$attachments = ($attachments === array_values($attachments)) ? array_flip($attachments) : $attachments;

					if (count($attachments = array_intersect_key($attachments, array_flip(array_filter(array_keys($attachments), 'is_file')))) > 0)
					{
						if (array_unshift($content, sprintf('--%s', $boundary = str_rot13($boundary)), sprintf('Content-Type: %s', $header['Content-Type']), '') > 3)
						{
							$header['Content-Type'] = sprintf('multipart/mixed; boundary="%s"', $boundary);

							foreach ($attachments as $key => $value)
							{
								if (is_int($value) === true)
								{
									$value = basename($key);
								}

								if (preg_match('~[^\x20-x7E]~', $value) > 0)
								{
									$value = sprintf('=?UTF-8?B?%s?=', base64_encode($value));
								}

								$value = array
								(
									sprintf('Content-Type: application/%s; name="%s"', 'octet-stream', $value),
									sprintf('Content-Disposition: %s; filename="%s"', 'attachment', $value),
									sprintf('Content-Transfer-Encoding: %s', 'base64'),
									'', trim(imap_binary(file_get_contents($key))), '',
								);

								$content = array_merge($content, array(sprintf('--%s', $boundary)), $value);
							}

							$content[] = sprintf('--%s--', $boundary);
						}
					}
				}

				foreach ($header as $key => $value)
				{
					if (is_array($value) === true)
					{
						$value = implode(', ', $value);
					}

					foreach (array('Q', 'B') as $option)
					{
						$options = array
						(
							'scheme' => $option,
							'input-charset' => 'UTF-8',
							'output-charset' => 'UTF-8',
						);

						if (($header[$key] = iconv_mime_encode($key, $value, $options)) !== false)
						{
							break;
						}
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
						$data = array(sprintf('HELO %s', parent::Value($_SERVER, 'HTTP_HOST', 'localhost')));

						if (preg_match('~^220~', $result .= substr(ltrim(fread($stream, 8192)), 0, 3)) > 0)
						{
							if (count($auth = array_slice(func_get_args(), 8, 2)) == 2)
							{
								$data = array_merge($data, array('AUTH LOGIN'), array_map('base64_encode', $auth));
							}

							$data[] = sprintf('MAIL FROM: <%s>', key(array_slice($from, 0, 1)));

							foreach ($recipients as $value)
							{
								$data[] = sprintf('RCPT TO: <%s>', $value);
							}

							$data[] = 'DATA';
							$data[] = implode("\r\n", array_merge(array_diff_key($header, array('Bcc' => null)), array(''), $content, array('.')));
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

		else if (($result = ph()->HTML->DOM(self::CURL($id))) !== false)
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
						$server = strval(ph()->HTML->DOM($result, sprintf('//head/link[contains(@rel, "%s")]/@href', $namespace[0]), 0));
						$delegate = strval(ph()->HTML->DOM($result, sprintf('//head/link[contains(@rel, "%s")]/@href', $namespace[1]), 0, $id));
					}

					else if (is_object($xml = ph()->HTML->DOM($result, sprintf('//xrd/service[contains(type, "://%s")]', $namespace), 0)) === true)
					{
						$server = parent::Value($xml, 'uri');

						if ($key == 0)
						{
							$delegate = 'http://specs.openid.net/auth/2.0/identifier_select';

							if (strcmp($namespace, 'specs.openid.net/auth/2.0/server') !== 0)
							{
								$delegate = parent::Value($xml, 'localid', parent::Value($xml, 'canonicalid', $id));
							}
						}

						else if ($key == 1)
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

							parent::Redirect($server, null, $data);
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
						$message = ph()->Text->Unicode->str_split($message);

						foreach ($message as $key => $value)
						{
							$message[$key] = sprintf('%04x', ph()->Text->Unicode->ord($value));
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

	public static function Twitter($data, $endpoint = 'search')
	{
		if (($result = self::CURL(parent::URL('http://api.twitter.com/1/', trim($endpoint, '/') . '.json'), $data)) !== false)
		{
			return ((is_array($result = json_decode($result, true)) === true) && (array_key_exists('error', $result) !== true)) ? $result : false;
		}

		return false;
	}

	public static function uClassify($api, $data = null, $class = null, $username = null, $classifier = null, $endpoint = 'classify')
	{
		$headers = array(CURLOPT_HTTPHEADER => array('Content-Type: text/xml'));

		if ((extension_loaded('dom') === true) && (is_object($dom = new DOMDocument('1.0', 'UTF-8')) === true))
		{
			$dom->appendChild($root = $dom->createElementNS('http://api.uclassify.com/1/RequestSchema', 'uclassify'));

			if (is_object($call = $dom->createElement(((preg_match('~^(?:classify|getInformation)$~', $endpoint) > 0) ? 'read' : 'write') . 'Calls')) === true)
			{
				$tags = array
				(
					'uclassify' => array('version' => '1.01'),
					'readCalls' => array('readApiKey' => $api),
					'writeCalls' => array('writeApiKey' => $api, 'classifierName' => $classifier),
				);

				if (preg_match('~^(?:classify|(?:un)?train)$~', $endpoint) > 0)
				{
					$root->appendChild($dom->createElement('texts'));

					foreach (parent::Filter(array_map('base64_encode', (array) $data), false) as $id => $text)
					{
						if (is_object($node = $dom->createElement($endpoint)) === true)
						{
							$attributes = array('id' => $id, 'textId' => $id);

							if (preg_match('~^(?:un)?train$~', $endpoint) > 0)
							{
								$attributes['className'] = $class;
							}

							else if (preg_match('~^classify$~', $endpoint) > 0)
							{
								$attributes['username'] = $username;
								$attributes['classifierName'] = $classifier;
							}

							foreach (array_filter($attributes, 'strlen') as $key => $value)
							{
								$node->setAttribute($key, $value);
							}

							$call->appendChild($node);
						}

						if (is_object($node = $dom->createElement('textBase64', $text)) === true)
						{
							$attributes = array('id' => $id);

							foreach (array_filter($attributes, 'strlen') as $key => $value)
							{
								$node->setAttribute($key, $value);
							}

							$dom->getElementsByTagName('texts')->item(0)->appendChild($node);
						}
					}
				}

				else if (preg_match('~^(?:create|remove|(?:add|remove)Class|getInformation)$~', $endpoint) > 0)
				{
					if (is_object($node = $dom->createElement($endpoint)) === true)
					{
						$tags[$endpoint] = array('id' => $endpoint);

						if (preg_match('~^(?:add|remove)Class$~', $endpoint) > 0)
						{
							$tags[$endpoint]['className'] = $class;
						}

						else if (preg_match('~^getInformation$~', $endpoint) > 0)
						{
							$tags[$endpoint]['username'] = $username;
							$tags[$endpoint]['classifierName'] = $classifier;
						}

						$call->appendChild($node);
					}
				}

				$root->appendChild($call);

				foreach ($tags as $tag => $attributes)
				{
					if (is_object($node = $dom->getElementsByTagName($tag)->item(0)) === true)
					{
						foreach (array_filter($attributes, 'strlen') as $key => $value)
						{
							$node->setAttribute($key, $value);
						}
					}
				}

				if (is_object($xml = ph()->HTML->DOM(self::CURL('http://api.uclassify.com/', $dom->saveXML(), 'POST', null, $headers))) === true)
				{
					if ((in_array($endpoint, array('classify', 'getInformation')) === true) && (strcmp('true', ph()->HTML->DOM($xml, '//status/@success', 0)) === 0))
					{
						$result = array();

						if (strcmp('classify', $endpoint) === 0)
						{
							foreach (ph()->HTML->DOM($xml, '//classify/@id') as $id)
							{
								$result[$id = strval($id)] = array();

								foreach (ph()->HTML->DOM($xml, sprintf('//classify[@id="%s"]//class', $id)) as $class)
								{
									$result[$id][strval($class['classname'])] = floatval($class['p']);
								}

								arsort($result[$id]);
							}
						}

						else if (strcmp('getInformation', $endpoint) === 0)
						{
							foreach (ph()->HTML->DOM($xml, '//classinformation') as $class)
							{
								$result[strval($class['classname'])] = array
								(
									'total' => intval($class->totalcount),
									'unique' => intval($class->uniquefeatures),
								);
							}
						}

						return $result;
					}

					return (strcmp('true', ph()->HTML->DOM($xml, '//status/@success', 0)) === 0) ? true : false;
				}
			}
		}

		return false;
	}

	public static function VIES($vatin, $country, $key = 'valid', $default = null)
	{
		if ((preg_match('~[A-Z]{2}~', $country) > 0) && (preg_match('~[0-9A-Z.+*]{2,12}~', $vatin) > 0))
		{
			try
			{
				if (is_object($soap = new SoapClient('http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl', array('exceptions' => true))) === true)
				{
					return parent::Value($soap->__soapCall('checkVat', array(array('countryCode' => $country, 'vatNumber' => $vatin))), $key, $default);
				}
			}

			catch (SoapFault $e)
			{
				return $default;
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
}

?>