<?php declare(strict_types=1);

namespace Lib;

use Result;

/** @package Lib */
final class R2 {
	private object $Client;

	private function __construct() {
	}

	/**
	 * @param string $public
	 * @param string $secret
	 * @param string $region
	 * @param string $endpoint
	 * @return self
	 */
	public static function new(string $public, string $secret, string $region, string $endpoint): self {
		/** @phpstan-ignore-next-line optional dependency: Aws\Credentials\Credentials */
		$credentials = new \Aws\Credentials\Credentials($public, $secret);

		$options = [
			'region' => $region,
			'version' => 'latest',
			'credentials' => $credentials,
			'endpoint' => $endpoint,
		];

		$Self = new self;
		/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
		$Self->Client = new \Aws\S3\S3Client($options);
		return $Self;
	}

	/**
	 * Get upload URL for single file to bucket
	 * @param  string $bucket
	 * @param  string $key
	 * @param  int $ttl
	 * @param  int $maxFileSize
	 * @return Result<string>
	 */
	public function getUploadUrl(string $bucket, string $key, $ttl = 1800, $maxFileSize = 10485760): Result {
		try {
			/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
			$cmd = $this->Client->getCommand(
				'PutObject', [
					'Bucket' => $bucket,
					'Key' => $key,
					'ContentLength' => $maxFileSize,
				]
			);

			$mins = (int)ceil($ttl / 60);
			$expires = "+{$mins} mins";
			/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
			$request = $this->Client->createPresignedRequest($cmd, $expires);

			/** @var Result<string> */
			return ok((string)$request->getUri());
		} catch (\Exception $E) {
			/** @var Result<string> */
			return err('e_upload_url_error', $E->getMessage());
		}
	}


	/**
	 * Upload the file to Cloudflare and return url
	 * @param  string $file
	 * @param  string $path
	 * @return Result<string>
	 */
	public function upload(string $file, string $path): Result {
		try {
			$data = file_get_contents($file);

			/** @var string $bucket */
			$bucket = config('cloudflare.files_bucket');
			/** @var string $url_prefix */
			$url_prefix = config('cloudflare.files_url_prefix');

			// Upload the object using the pre-signed URL
			/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
			$this->Client->putObject(
				[
					'Bucket' => $bucket,
					'Key' => $path,
					'Body' => $data,
				]
			);

			/** @var Result<string> */
			return ok($url_prefix . '/' . $path);
		} catch (\Exception $E) {
			/** @var Result<string> */
			return err('e_upload_failed', $E->getMessage());
		}
	}

	/**
	 * Get list of keys by prefix in specified bucket
	 * @param  string $bucket
	 * @param  string $prefix
	 * @return Result<array<int,string>> List of object keys
	 */
	public function getObjectKeys(string $bucket, string $prefix): Result {
		try {
			// Initialize an empty array to hold the keys of the objects
			$keys = [];

			// Use the ListObjectsV2 method and specify the Prefix parameter
			/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
			$result = $this->Client->listObjectsV2(
				[
					'Bucket' => $bucket,
					'Prefix' => $prefix,
				]
			);

			/** @var array{Contents?:array<array{Key:string}>} $result */
			// Check if the result contains any contents
			if (isset($result['Contents'])) {
				foreach ($result['Contents'] as $object) {
					// Add the key to the keys array
					$keys[] = $object['Key'];
				}
			}

			// Return the list of keys as a successful result
			/** @var Result<array<int,string>> */
			return ok($keys);
		} catch (\Exception $e) {
			/** @var Result<array<int,string>> */
			return err('e_list_keys_error', $e->getMessage());
		}
	}

	/**
	 * Download objects by keys specified
	 * @param string $bucket
	 * @param  array<string>  $keys
	 * @param string $save_to Path where to download objects
	 * @return Result<bool>
	 */
	public function downloadByKeys(string $bucket, array $keys, string $save_to): Result {
		try {
			foreach ($keys as $key) {
				/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
				$this->Client->getObject(
					[
						'Bucket' => $bucket,
						'Key' => $key,
						'SaveAs' => $save_to . DIRECTORY_SEPARATOR . basename($key),
					]
				);
			}

			/** @var Result<bool> */
			return ok(true);
		} catch (\Exception $e) {
			/** @var Result<bool> */
			return err('e_download_error', $e->getMessage());
		}
	}

	/**
	 * Create bucket for the user
	 * @param  string $bucket
	 * @return Result<bool>
	 */
	public function createBucket(string $bucket): Result {
		try {
			/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
			$result = $this->Client->createBucket(
				[
				'Bucket' => $bucket,
				]
			);

			/** @var array{'@metadata':array{statusCode:int}} $result */
			if ($result['@metadata']['statusCode'] === 200) {
				/** @var Result<bool> */
				return ok(true);
			}

			/** @var Result<bool> */
			return err('e_create_bucket_error');
		} catch (\Exception $e) {
			/** @var Result<bool> */
			return err('e_create_bucket_error', $e->getMessage());
		}
	}

	/**
	 * Get file information by key
	 * @param  string $bucket
	 * @param  string $key
	 * @return Result<int> Size of the file
	 */
	public function getFileInfo(string $bucket, string $key): Result {
		try {
			try {
				/** @phpstan-ignore-next-line optional dependency: Aws\S3\S3Client */
				$result = $this->Client->headObject(
					[
					'Bucket' => $bucket,
					'Key'    => $key,
					]
				);

				/** @var Result<int> */
				return ok((int)$result['ContentLength']);
			} catch (\RuntimeException $e) {
				if (method_exists($e, 'getAwsErrorCode') && $e->getAwsErrorCode() === 'NotFound') {
					/** @var Result<int> */
					return err('e_file_not_found');
				}
				throw $e;
			}
		} catch (\Exception $e) {
			/** @var Result<int> */
			return err('e_file_info_error', $e->getMessage());
		}
	}
}
