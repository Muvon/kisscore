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
	protected string $app_id;
	/** @var non-empty-string */
	protected string $app_token;
	/** @var non-empty-string */
	protected string $app_pubkey;

	/**
	 * Initialize the Muvon KIT lib
	 * @param string $app_id
	 * @param string $app_token
	 * @param string $app_pubkey
	 * @return void
	 */
	public function __construct(string $app_id, string $app_token, string $app_pubkey) {
		if (empty($app_id) || empty($app_token) || empty($app_pubkey)) {
			throw new Error('Muvon API requires App id, token and pubkey');
		}
		$this->app_id = $app_id;
		$this->app_token = $app_token;
		$this->app_pubkey = $app_pubkey;
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
			'mail', 'subscribe', [
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
			'mail', 'email/send', [
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
		return sodium_crypto_sign_verify_detached($signature, $payload, $this->app_pubkey);
	}

	/**
	 * Internal function that uses trait to communicate with mailer API
	 * @param string $ns
	 * @param string $path Path for the API to call
	 * @param array<mixed> $payload Data that we will send with request
	 * @return Result<mixed>
	 */
	protected function sendRequest(string $ns, string $path, array $payload = []): Result {
		$url = "https://{$ns}.muvon.dev/{$this->app_id}/{$path}";
		/** @var Result<array{?string,mixed}> */
		$Res = $this->Fetch->request(
			$url, $payload, 'POST', [
				"API-Token: {$this->app_token}",
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
