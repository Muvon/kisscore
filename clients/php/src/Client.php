<?php declare(strict_types=1);

namespace KissCore;

/**
 * KissCore API Client
 * Raw cURL client for KissCore [err, data] response protocol
 */
final class Client {
	private string $base_url;
	private int $timeout;
	private int $connect_timeout;
	/** @var array<string, string> */
	private array $headers;
	/** @var ?callable(string, string): array<string, string> */
	private mixed $on_request;
	/** @var ?callable(string, mixed, int): void */
	private mixed $on_error;

	/**
	 * @param string $base_url API base URL
	 * @param array{
	 *   headers?: array<string, string>,
	 *   timeout?: int,
	 *   connect_timeout?: int,
	 *   on_request?: callable(string, string): array<string, string>,
	 *   on_error?: callable(string, mixed, int): void,
	 * } $options
	 */
	public function __construct(string $base_url, array $options = []) {
		$this->base_url = rtrim($base_url, '/');
		$this->timeout = $options['timeout'] ?? 30;
		$this->connect_timeout = $options['connect_timeout'] ?? 5;
		$this->headers = $options['headers'] ?? [];
		$this->on_request = $options['on_request'] ?? null;
		$this->on_error = $options['on_error'] ?? null;
	}

	/**
	 * @template T
	 * @param string $path
	 * @param array<string, string|int|bool> $query
	 * @return array{0: ?string, 1: T|null}
	 */
	public function get(string $path, array $query = []): array {
		return $this->request('GET', $path, null, $query);
	}

	/**
	 * @template T
	 * @param string $path
	 * @param mixed $body
	 * @return array{0: ?string, 1: T|null}
	 */
	public function post(string $path, mixed $body = null): array {
		return $this->request('POST', $path, $body);
	}

	/**
	 * @template T
	 * @param string $path
	 * @param mixed $body
	 * @return array{0: ?string, 1: T|null}
	 */
	public function put(string $path, mixed $body = null): array {
		return $this->request('PUT', $path, $body);
	}

	/**
	 * @template T
	 * @param string $path
	 * @param mixed $body
	 * @return array{0: ?string, 1: T|null}
	 */
	public function delete(string $path, mixed $body = null): array {
		return $this->request('DELETE', $path, $body);
	}

	/**
	 * @param string $method
	 * @param string $path
	 * @param mixed $body
	 * @param array<string, string|int|bool> $query
	 * @return array{0: ?string, 1: mixed}
	 */
	public function request(
		string $method,
		string $path,
		mixed $body = null,
		array $query = [],
	): array {
		$url = $this->base_url . '/' . ltrim($path, '/');
		if ($query !== []) {
			$url .= '?' . http_build_query($query, '', '&');
		}

		$headers = array_merge(
			['Accept' => 'application/json'],
			$this->headers
		);

		if ($this->on_request) {
			$extra = ($this->on_request)($method, $path);
			$headers = array_merge($headers, $extra);
		}

		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => $this->timeout,
			CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_HTTPHEADER => $this->formatHeaders($headers),
			CURLOPT_TCP_KEEPALIVE => 1,
			CURLOPT_TCP_NODELAY => 1,
		]);

		if ($body !== null && $method !== 'GET') {
			$json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders(
				array_merge($headers, ['Content-Type' => 'application/json'])
			));
		}

		$response = curl_exec($ch);
		$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err_no = curl_errno($ch);

		if ($err_no) {
			$err_msg = curl_error($ch);
			return [$this->curlError($err_no, $err_msg), null];
		}


		if (!is_string($response) || $response === '') {
			return ['e_empty_response', null];
		}

		/** @var mixed $parsed */
		$parsed = json_decode($response, true);
		if (!is_array($parsed) || !array_is_list($parsed) || sizeof($parsed) !== 2) {
			return ['e_invalid_response', $response];
		}

		/** @var array{0: ?string, 1: mixed} $result */
		$result = $parsed;

		if ($result[0] !== null && $this->on_error) {
			($this->on_error)($result[0], $result[1], $http_code);
		}

		return $result;
	}

	/**
	 * @param array<string, string> $headers
	 * @return array<string>
	 */
	private function formatHeaders(array $headers): array {
		$formatted = [];
		foreach ($headers as $key => $value) {
			$formatted[] = "$key: $value";
		}
		return $formatted;
	}

	/**
	 * @param int $code
	 * @param string $message
	 * @return string
	 */
	private function curlError(int $code, string $message): string {
		return match ($code) {
			7 => 'e_connection_refused',
			28 => 'e_timeout',
			52 => 'e_empty_response',
			default => 'e_network: ' . $message,
		};
	}
}
