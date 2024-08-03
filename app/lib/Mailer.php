<?php declare(strict_types=1);

namespace Lib;

use Muvon\KISS\RequestTrait;
use Result;

/** @package Lib */
final class Mailer {
	use RequestTrait;

	/**
	 * Initialize the mailer with token provided to use for api calls
	 * @param string $ns
	 * @param string $api_token
	 * @return void
	 */
	public function __construct(protected string $ns, protected string $api_token) {
		$this->request_type = 'json';
	}

	/**
	 * Validate email if it's valid to send email and return true or false
	 * @param string $email
	 * @return Result<bool>
	 */
	public function validate(string $email): Result {
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
			'subscribe', [
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
	public function send(string $email, string $template, string $from = 'noreply', array $data = []): Result {
		$ValidateResult = $this->validate($email);
		if ($ValidateResult->err) {
			return $ValidateResult;
		}

		/** @var Result<bool> */
		return $this->sendRequest(
			'email/send', [
				'email' => $email,
				'from' => $from,
				'template' => $template,
				'data' => $data,
			]
		);
	}

	/**
	 * Internal function that uses trait to communicate with mailer API
	 * @param string $path Path for the API to call
	 * @param array<mixed> $payload Data that we will send with request
	 * @return Result<mixed>
	 */
	protected function sendRequest(string $path, array $payload = []): Result {
		$url = "https://mailer.muvon.dev/{$this->ns}/{$path}";
		/** @var array{?string,mixed} $response */
		$response = $this->request(
			$url, $payload, 'POST', [
				"API-Token: {$this->api_token}",
			]
		);
		[$err, $res] = $response;
		if ($err) {
			return err($err);
		}

		return ok($res);
	}
}
