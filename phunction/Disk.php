<?php

/**
* The MIT License
* http://creativecommons.org/licenses/MIT/
*
* Copyright (c) Alix Axel <alix.axel@gmail.com>
**/

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

		$ip = implode(' -> ', array_unique(ph()->HTTP->IP(null, true), ph()->HTTP->IP(null, false)));

		if (self::File($path, sprintf('[%s] @ %s: %s', $ip, parent::Date('DATE TIME'), trim($log)) . "\n", true) === true)
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
		$path = strftime($path, time());

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
			if (($unit = array_search($unit, array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'))) === false)
			{
				$unit = intval(log($result, 1024));
			}

			$result = array($result / pow(1024, $unit), parent::Value(array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'), $unit));
		}

		return $result;
	}

	public static function Tag($path = null, $tags = null, $fuzzy = true)
	{
		if (count($tags = array_filter(array_unique(array_map(array(ph()->Text, 'Slug'), (array) $tags)), 'strlen')) > 0)
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

	public static function Temp($id, $path = null, $chmod = null)
	{
		if (empty($path) === true)
		{
			$path = array_map('getenv', array('TMP', 'TEMP', 'TMPDIR'));

			if (function_exists('sys_get_temp_dir') === true)
			{
				array_unshift($path, sys_get_temp_dir());
			}

			$path = parent::Value(array_filter($path, 'strlen'), 0);
		}

		if (($result = tempnam(self::Path($path), $id)) !== false)
		{
			self::Chmod($result, $chmod);
		}

		return $result;
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

?>