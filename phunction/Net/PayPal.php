<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

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

?>