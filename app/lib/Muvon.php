<?php declare(strict_types=1);

namespace Lib;

use Error;
use Fetch;
use Result;
use SodiumException;

/** @package Lib */
final class Muvon {
	private Fetch $Fetch;
	/** @var non-empty-string */
	protected string $project;
	/** @var non-empty-string */
	protected string $api_token;
	/** @var non-empty-string */
	protected string $public_key;

	/**
	 * Initialize the Muvon KIT lib
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
		$this->Fetch = Fetch::new(['request_type' => 'json']);
	}

	/**
	 * Validate email if it's valid to send email and return true or false
	 * @param string $email
	 * @return Result<bool>
	 */
	public function validateEmail(string $email): Result {
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return err('e_email_not_valid');
		}
		return ok(true);
	}

	/**
	 * Subscribe the user to the newsletter
	 * @param string $email
	 * @return Result<bool>
	 */
	public function subscribe(string $email): Result {
		/** @var Result<bool> */
		return $this->sendRequest(
			'mailer', "{$this->project}/subscribe", [
				'email' => $email,
			]
		);
	}

	/**
	 * This function sends prepared template with provided replacements in data
	 * @param string $email
	 * @param string $template
	 * @param string $from
	 * @param array<string,string> $data
	 * @return Result<bool>
	 */
	public function sendEmail(string $email, string $template, string $from = 'noreply', array $data = []): Result {
		$ValidateResult = $this->validateEmail($email);
		if ($ValidateResult->err) {
			return $ValidateResult;
		}

		/** @var Result<bool> */
		return $this->sendRequest(
			'mail', "{$this->project}/email/send", [
				'email' => $email,
				'from' => $from,
				'template' => $template,
				'data' => $data,
			]
		);
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
			'pay', 'stripe/create', [
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
			'pay', 'stripe/subscription', [
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
	 * @param string $ns
	 * @param string $path Path for the API to call
	 * @param array<mixed> $payload Data that we will send with request
	 * @return Result<mixed>
	 */
	protected function sendRequest(string $ns, string $path, array $payload = []): Result {
		$url = "https://{$ns}.muvon.dev/{$path}";
		/** @var Result<array{?string,mixed}> */
		$Res = $this->Fetch->request(
			$url, $payload, 'POST', [
				"API-Token: {$this->api_token}",
			]
		);
		if ($Res->err) {
			return $Res;
		}
		[$err, $res] = $Res->unwrap();
		if ($err) {
			return err($err, $res);
		}

		return ok($res);
	}
}
