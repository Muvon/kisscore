<?php declare(strict_types=1);

namespace Lib;

use Fetch;
use Result;
use ResultError;

/**
 * @phpstan-type ReplicateResponse array{id:string,error?:string,status:string}
 */
final class Replicate {
	private Fetch $Fetch;
	private string $api_token;

	private function __construct() {
	}

	/**
	 * Create a new instance of Replicate
	 * @param string $api_token
	 * @return Replicate
	 */
	public static function new(string $api_token): self {
		$Self = new self;
		$Self->Fetch = Fetch::new(['request_type' => 'json']);
		$Self->api_token = $api_token;
		return $Self;
	}

	/**
	 * Run prediction on Replicate and return its results
	 * @param string $version
	 * @param array<mixed> $input
	 * @return Result<string>
	 */
	public function run(string $version, array $input): Result {
		if (strpos($version, ':') === false) {
			$api_url = "https://api.replicate.com/v1/models/$version/predictions";
			$payload = [
				'input' => $input,
			];
		} else {
			[, $version] = explode(':', $version);
			$api_url = "https://api.replicate.com/v1/predictions";
			$payload = [
				'version' => $version,
				'input' => $input,
			];
		}
		$headers = [
			'Authorization: Bearer ' . $this->api_token,
			'Content-Type: application/json',
		];

		/** @var Result<ReplicateResponse> */
		$Res = $this->Fetch->request($api_url, $payload, 'POST', $headers);
		if ($Res->err) {
			return err('e_replicate_http_error', $Res->err);
		}
		$result = $Res->unwrap();
		if (isset($result['error'])) {
			return err('e_replicate_response_error', $result['error']);
		}
		return ok($result['id']);
	}

	/**
	 * Run prediction on Replicate and wait for it to be completed
	 * @param string $version
	 * @param array<mixed> $input
	 * @param int $timeout
	 * @return Result<ReplicateResponse>
	 * @throws ResultError
	 */
	public function runAndWait(string $version, array $input, int $timeout = -1): Result {
		$Res = $this->run($version, $input);
		if ($Res->err) {
			return err($Res->err);
		}

		$id = $Res->unwrap();
		return $this->wait($id, $timeout);
	}

	/**
	 * Get the status of the job that returned by get method
	 * @param ReplicateResponse $info
	 * @return bool
	 */
	public function isDone(array $info): bool {
		return $info['status'] !== 'starting' && $info['status'] !== 'processing';
	}

	/**
	 * Wait for the prediction to be completed
	 * @param string $id
	 * @param int $timeout
	 * @return Result<ReplicateResponse>
	 * @throws ResultError
	 */
	public function wait(string $id, int $timeout = -1): Result {
		$Res = $this->get($id);
		if ($Res->err) {
			return $Res;
		}

		$start = time();
		$info = $Res->unwrap();

		while (!$this->isDone($info)) {
			sleep(1);
			$start += 1;
			$Res = $this->get($id);
			if ($Res->err) {
				return $Res;
			}
			$info = $Res->unwrap();
			if ($timeout > 0 && $start > $timeout) {
				return err('e_replicate_timeout');
			}
		}

		return ok($info);
	}

	/**
	 * Get prediction result by its id
	 * @param string $id
	 * @return Result<ReplicateResponse>
	 */
	public function get(string $id): Result {
		$api_url = 'https://api.replicate.com/v1/predictions/' . $id;
		$headers = [
			'Authorization: Bearer ' . $this->api_token,
			'Content-Type: application/json',
		];
		/** @var Result<ReplicateResponse> */
		$Res = $this->Fetch->request($api_url, [], 'GET', $headers);
		if ($Res->err) {
			return err('e_replicate_http_error');
		}
		return $Res;
	}
}
