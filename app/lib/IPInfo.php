<?php declare(strict_types=1);

namespace Lib;

use Result;
use Throwable;

/** @package Lib */
final class IPInfo {
	/**
	 * Fetch information about provided IP
	 * @param  string $ip
	 * @return Result<mixed>
	 */
	public static function fetch(string $ip): Result {
		$token = getenv('IPINFO_TOKEN');
		$ch = curl_init();
		$url = "https://ipinfo.io/$ip";
		$header = [
			"Authorization: Bearer $token",
		];

		// set URL and other appropriate options
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		// grab URL and pass it to the browser
		$response = curl_exec($ch);

		// Check for errors and handle appropriately
		$errno = curl_errno($ch);
		if ($errno) {
			return err('e_curl_error', $errno);
		}

		if (!is_string($response)) {
			return err('e_curl_error: Unexpected error occurred');
		}

		// close cURL resource, and free up system resources
		curl_close($ch);

		// Decode JSON response
		try {
			$result = json_decode($response, true);
			return ok($result);
		} catch (Throwable) {
			return err('e_json_decode');
		}
	}
}
