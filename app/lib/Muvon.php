<?php declare(strict_types=1);

namespace Lib;

use Error;
use Muvon\KISS\RequestTrait;
use Result;
use SodiumException;

final class Muvon {

	use RequestTrait;

	/** @var non-empty-string */
	protected string $project;
	/** @var non-empty-string */
	protected string $api_token;
	/** @var non-empty-string */
	protected string $public_key;

	/**
	 * Initialize the mailer with token provided to use for api calls
	 * @param string $project
	 * @param string $api_token
	 * @param string $public_key
	 * @return void
	 */
	public function __construct(string $project, string $api_token, string $public_key) {
		if (empty($project) || empty($api_token) || empty($public_key)) {
			throw new Error('Muvon API requires project, api_token and public_key');
		}
		$this->project = $project;
		$this->api_token = $api_token;
		$this->public_key = $public_key;
		$this->request_type = 'json';
	}

	/**
	 * Generate payment link to initiate payment
	 * @param string $email
	 * @param int $account_id
	 * @param string $plan deposit or name of the plan
	 * @param int $value represents period for subscription or value for deposit
	 * @return Result<array{id:string,customer:string}>
	 */
	public function createPayment(string $email, int $account_id, string $plan, int $value): Result {
		/** @var Result<array{id:string,customer:string}> */
		return $this->sendRequest(
			'payment/create', [
				'project' => $this->project,
				'account_email' => $email,
				'account_id' => $account_id,
				'plan' => $plan,
				'value' => $value,
			]
		);
	}

	/**
	 * Get subscription url for the account to manage it
	 * @param string $customer
	 * @return Result<string>
	 */
	public function getSubscriptionUrl(string $customer): Result {
		/** @var Result<string> */
		return $this->sendRequest(
			'payment/subscription', [
				'project' => $this->project,
				'customer' => $customer,
			]
		);
	}

	/**
	 * Check the signature with a given payload
	 * @param string $payload
	 * @param string $signature
	 * @return bool
	 * @throws SodiumException
	 */
	public function checkSignature(string $payload, string $signature): bool {
		if (empty($signature) || strlen($signature) !== 64) {
			return false;
		}
		return sodium_crypto_sign_verify_detached($signature, $payload, $this->public_key);
	}

	/**
	 * Internal function that uses trait to communicate with mailer API
	 * @param string $path Path for the API to call
	 * @param array<mixed> $payload Data that we will send with request
	 * @return Result<mixed>
	 */
	protected function sendRequest(string $path, array $payload = []): Result {
		$url = "https://api.muvon.io/{$path}";
		/** @var array{?string,array{?string,mixed}} $response */
		$response = $this->request(
			$url, $payload, 'POST', [
				"API-Token: {$this->api_token}",
			]
		);
		[$err, $res] = $response;

		if ($err) {
			return err($err);
		}
		[$err, $res] = $res;
		if ($err) {
			return err($err, $res);
		}

		return ok($res);
	}
}
