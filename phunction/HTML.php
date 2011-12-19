<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* phunction 1.12.19 (github.com/alixaxel/phunction/)
* Copyright (c) 2011 Alix Axel <alix.axel@gmail.com>
**/

class phunction_HTML extends phunction
{
	public function __construct()
	{
	}

	public function __get($key)
	{
		return $this->$key = parent::__get(sprintf('%s_%s', ltrim(strrchr(__CLASS__, '_'), '_'), $key));
	}

	public static function Autolink($string)
	{
		return $string;
	}

	public static function Decode($string)
	{
		if (is_string($string) === true)
		{
			$string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
		}

		return $string;
	}

	public static function DOM($html, $xpath = null, $key = null, $default = false)
	{
		if ((extension_loaded('dom') === true) && (extension_loaded('SimpleXML') === true))
		{
			if (is_object($html) === true)
			{
				if (isset($xpath) === true)
				{
					$html = $html->xpath($xpath);
				}

				return (isset($key) === true) ? parent::Value($html, $key, $default) : $html;
			}

			else if ((is_string($html) === true) && (is_bool(libxml_use_internal_errors(true)) === true))
			{
				return self::DOM(@simplexml_import_dom(DOMDocument::loadHTML(ph()->Unicode->mb_html_entities($html))), $xpath, $key, $default);
			}
		}

		return false;
	}

	public static function Encode($string, $entities = false)
	{
		if (is_string($string) === true)
		{
			$string = call_user_func(($entities === true) ? 'htmlentities' : 'htmlspecialchars', self::Decode($string), ENT_QUOTES, 'UTF-8');
		}

		return $string;
	}

	public static function Obfuscate($string, $reverse = false)
	{
		if (count($string = ph()->Unicode->str_split($string)) > 0)
		{
			foreach (array_map(array('phunction_Unicode', 'ord'), $string) as $key => $value)
			{
				$string[$key] = sprintf('&#%s;', (mt_rand(0, 1) > 0) ? $value : ('x' . dechex($value)));
			}

			if ($reverse === true)
			{
				$string = array(self::Tag('span', implode('', array_reverse($string)), array('style' => 'direction: rtl; unicode-bidi: bidi-override;')));
			}
		}

		return implode('', $string);
	}

	public static function Purify($html, $whitelist = null, $protocols = 'http|https|mailto')
	{
		if (extension_loaded('dom') === true)
		{
			if (is_object($html) === true)
			{
				if (in_array($html->nodeName, array_keys($whitelist)) === true)
				{
					if ($html->hasAttributes() === true)
					{
						foreach (range($html->attributes->length - 1, 0) as $i)
						{
							$attribute = $html->attributes->item($i);

							if (in_array($attribute->name, $whitelist[$html->nodeName]) !== true)
							{
								$html->removeAttributeNode($attribute);
							}

							else if (preg_match('~(?:action|background|cite|classid|codebase|data|href|icon|desc|manifest|poster|profile|src|usemap)$~i', $attribute->name) > 0)
							{
								$protocol = trim(ph()->Text->Regex(self::Decode($attribute->value), '^([^/:]*):', array(1, 0)));

								if ((strlen($protocol) > 0) && (in_array(strtolower($protocol), explode('|', strtolower($protocols))) !== true))
								{
									$html->removeAttributeNode($attribute);
								}
							}
						}
					}

					if ($html->hasChildNodes() === true)
					{
						foreach (range($html->childNodes->length - 1, 0) as $i)
						{
							self::Purify($html->childNodes->item($i), $whitelist, $protocols);
						}
					}
				}

				else
				{
					$html->parentNode->removeChild($html);
				}
			}

			else if ((is_string($html) === true) && (is_bool(libxml_use_internal_errors(true)) === true))
			{
				if (is_object($html = @DOMDocument::loadHTML(ph()->Unicode->mb_html_entities($html))) === true)
				{
					if (is_array($whitelist) !== true)
					{
						$whitelist = explode('|', $whitelist);
					}

					$whitelist = array_change_key_case(($whitelist === array_values($whitelist)) ? array_flip($whitelist) : $whitelist, CASE_LOWER);

					foreach ($whitelist as $tag => $attributes)
					{
						if (is_array($attributes) !== true)
						{
							$attributes = explode('|', $attributes);
						}

						$whitelist[$tag] = preg_grep('~^(?:on|(?:1?|archive|content|style)$)~i', array_map('strtolower', $attributes), PREG_GREP_INVERT);
					}

					if (isset($html->documentElement) === true)
					{
						self::Purify($html->documentElement, array_merge(array_fill_keys(array('#text', 'html', 'body'), array()), $whitelist), $protocols);
					}

					return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $html->saveHTML());
				}
			}
		}

		return false;
	}

	public static function Tag($tag, $content = null)
	{
		$tag = self::Encode(strtolower(trim($tag)));
		$arguments = array_filter(array_slice(func_get_args(), 2), 'is_array');
		$attributes = (empty($arguments) === true) ? array() : call_user_func_array('array_merge', $arguments);

		if ((count($attributes) > 0) && (ksort($attributes) === true))
		{
			foreach ($attributes as $key => $value)
			{
				$attributes[$key] = sprintf(' %s="%s"', self::Encode($key), ($value === true) ? self::Encode($key) : self::Encode($value));
			}
		}

		if (in_array($tag, explode('|', 'area|base|basefont|br|col|frame|hr|img|input|link|meta|param')) === true)
		{
			return sprintf('<%s%s />' . "\n", $tag, implode('', $attributes));
		}

		return sprintf('<%s%s>%s</%s>' . "\n", $tag, implode('', $attributes), $content, $tag);
	}

	public static function Title($string, $raw = true)
	{
		if ((($result = ob_get_clean()) !== false) && (ob_start() === true))
		{
			echo preg_replace('~<title>([^<]*)</title>~i', '<title>' . (($raw === true) ? $string : addcslashes($string, '\\$')) . '</title>', $result, 1);
		}

		return false;
	}

	public static function Typography($string, $quotes = true)
	{
		if ($quotes === true)
		{
			$string = preg_replace(array("~'([^']+)'~", '~"([^"]+)"~'), array('‘$1’', '“$1”'), $string);
		}

		return preg_replace(array('~[.]{2,}~', '~--~', '~-~'), array('…', '—', '–'), $string);
	}
}

?>