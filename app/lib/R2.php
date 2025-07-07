<?php declare(strict_types=1);

namespace Lib;

use Aws\Credentials\Credentials;
use Aws\Exception\AwsException;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use Result;

/** @package Lib */
final class R2 {
	private S3Client $Client;

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
		$credentials = new Credentials($public, $secret);

		$options = [
			'region' => $region,
			'version' => 'latest',
			'credentials' => $credentials,
			'endpoint' => $endpoint,
		];

		$Self = new self;
		$Self->Client = new S3Client($options);
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
			$cmd = $this->Client->getCommand(
				'PutObject', [
					'Bucket' => $bucket,
					'Key' => $key,
					'ContentLength' => $maxFileSize,
				]
			);

			$mins = (int)ceil($ttl / 60);
			$expires = "+{$mins} mins";
			$request = $this->Client->createPresignedRequest($cmd, $expires);

			return ok((string)$request->getUri());
		} catch (AwsException $E) {
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

			// Upload the object using the pre-signed URL
			$this->Client->putObject(
				[
					'Bucket' => config('cloudflare.files_bucket'),
					'Key' => $path,
					'Body' => $data,
				]
			);

			return ok(config('cloudflare.files_url_prefix') . '/' . $path);
		} catch (AwsException $E) {
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
			/** @var array{Contents?:array<array{Key:string}>} $result */
			$result = $this->Client->listObjectsV2(
				[
					'Bucket' => $bucket,
					'Prefix' => $prefix,
				]
			);

			// Check if the result contains any contents
			if (isset($result['Contents'])) {
				foreach ($result['Contents'] as $object) {
					// Add the key to the keys array
					$keys[] = $object['Key'];
				}
			}

			// Return the list of keys as a successful result
			return ok($keys);
		} catch (AwsException $e) {
			// Return an error result if an exception occurs
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
				$this->Client->getObject(
					[
						'Bucket' => $bucket,
						'Key' => $key,
						'SaveAs' => $save_to . DIRECTORY_SEPARATOR . basename($key),
					]
				);
			}

			return ok(true);
		} catch (AwsException $e) {
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
			/** @var array{'@metadata':array{statusCode:int}} $result */
			$result = $this->Client->createBucket(
				[
				'Bucket' => $bucket,
				]
			);

			if ($result['@metadata']['statusCode'] === 200) {
				return ok(true);
			}

			return err('e_create_bucket_error');
		} catch (S3Exception $e) {
			return err('e_create_bucket_error', $e->getMessage());
		} catch (\Exception $e) {
			return err('e_create_bucket_error', 'Unexpected error occurred');
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
				$result = $this->Client->headObject(
					[
					'Bucket' => $bucket,
					'Key'    => $key,
					]
				);

				return ok((int)$result['ContentLength']);
			} catch (S3Exception $e) {
				if ($e->getAwsErrorCode() === 'NotFound') {
					return err('e_file_not_found');
				}
				throw $e;
			}
		} catch (AwsException $e) {
			return err('e_file_info_error', $e->getMessage());
		}
	}
}
