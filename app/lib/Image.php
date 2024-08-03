<?php declare(strict_types=1);

namespace Lib;

use Result;

/** @package Lib */
final class Image {
	/**
	 * Download image from url to temporarily file path and return it
	 * @param string $url
	 * @return Result<string>
	 */
	public static function download(string $url): Result {
		$path = tempnam(sys_get_temp_dir(), 'image');
		$ch = curl_init($url);
		if (!$ch) {
			return err('e_image_download_failed', 'Cant create curl handle');
		}
		$fp = fopen($path, 'wb');
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_exec($ch);
		$err = curl_error($ch);
		if ($err) {
			return err('e_image_download_failed', $err);
		}
		return ok($path);
	}

	/**
	 * Store image from the url or just binary data
	 * @param string $path path to the image
	 * @return Result<string> URL to the image
	 */
	public static function store(string $path): Result {
		// 1. Prepare bucket path first
		$hash = bin2hex(random_bytes(16));
		$bucket_path = gmdate('Ym') . '/' . gmdate('d') . '/' . $hash[0] . '/' . $hash[1] . '/' . $hash;

		// 2. Create thumb in webp with a minimum side of 700 pixels while maintaining aspect ratio
		$webp_path = $path . '.webp';
		exec(
			'convert ' . escapeshellarg($path)
			. ' -resize "700x700^>" -quality 75'
			. ' -define webp:lossless=false -define webp:method=6'
			. ' -define webp:alpha-quality=85 -strip '
			. escapeshellarg($webp_path)
		);
		if (!file_exists($webp_path)) {
			return err('e_image_convert_failed');
		}

		// 3. Upload it all
		/** @var \Lib\R2 $R2 */
		$R2 = container('r2');
		$pathRes = $R2->upload($path, "{$bucket_path}.png");
		unlink($path);
		if ($pathRes->err) {
			return $pathRes;
		}
		$webPathRes = $R2->upload($webp_path, "{$bucket_path}.webp");
		if ($webPathRes->err) {
			return $webPathRes;
		}
		unlink($webp_path);

		return $pathRes;
	}
}
