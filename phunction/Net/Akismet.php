<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

class phunction_Net_Akismet extends phunction_Net
{
	public function __construct()
	{
	}

	public static function Check($api, $text, $name = null, $email = null, $domain = null, $type = null)
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

		if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/comment-check', $api), array_merge($data, preg_grep('~^HTTP_~', $_SERVER)), 'POST')) !== false)
		{
			return (strcmp('true', $result) === 0) ? true : false;
		}

		return false;
	}

	public static function Submit($api, $text, $name = null, $email = null, $domain = null, $type = null, $endpoint = null)
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

			if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/submit-%s', $api, $endpoint), $data, 'POST')) !== false)
			{
				return true;
			}
		}

		return false;
	}

	public static function Verify($api)
	{
		$data = array
		(
			'key' => $api,
			'blog' => parent::URL(null, false, false),
		);

		if (($result = parent::CURL(sprintf('http://%s.rest.akismet.com/1.1/verify-key', $api), $data, 'POST')) !== false)
		{
			return (strcmp('valid', $result) === 0) ? true : false;
		}

		return false;
	}
}

?>