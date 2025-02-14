<?php declare(strict_types=1);

/** @package  */
final class Fetch {
	protected int $request_connect_timeout = 5;
	protected int $request_timeout = 30;
	protected int $request_ssl_verify = 0;
	protected int $request_keepalive = 20;
	protected string $request_useragent = 'KISSCore/Fetch v0.9.0';

  // The contents of the "Accept-Encoding: " header. This enables decoding of the response. Supported encodings are "identity", "deflate", and "gzip". If an empty string, "", is set, a header containing all supported encoding types is sent.
	protected ?string $request_encoding = '';

  // Type of the request can be one of json, msgpack, binary, raw
  // In case if not supported we use raw
	protected string $request_type = 'raw';

	// Type of the response can be one of json, msgpack, binary, raw
	protected string $response_type = 'raw';

  // Array containing proxy info with next fields
	/** @var array{host:string,port:int,user?:string,password?:string,type?:string} */
	protected array $request_proxy = [];

	/** @var array<string> */
	protected array $request_json_bigint_keys = [];

	/** @var array<CurlHandle> */
	protected array $request_handlers = [];

	protected ?CurlMultiHandle $request_mh = null;

	protected Closure $encoder_fn;
	protected Closure $decoder_fn;

	private function __construct() {
	}

	/**
	 * @param array<string,mixed> $config One of available configs
	 * @return CurlRequest
	 */
	public static function new(array $config = []): self {
		$Self = new self;
		foreach ($config as $param => $value) {
			$Self->$param = $value;
		}
		if (!isset($Self->encoder_fn)) {
			$Self->encoder_fn = match ($Self->request_type) {
				'msgpack' => msgpack_pack(...),
				'json' => $Self->encodeJson(...),
				default => fn($payload) => http_build_query($payload, '', '&'),
			};
		}
		if (!isset($Self->decoder_fn)) {
			$Self->decoder_fn = match ($Self->response_type) {
				'msgpack' => msgpack_unpack(...),
				'json' => fn($response) => json_decode($response = preg_replace('/"\s*:\s*([0-9]+\.[0-9]+)([,\}\]])/ius', '":"$1"$2', $response), true, flags: JSON_BIGINT_AS_STRING),
				default => fn($response) => $response,
			};
		}
		return $Self;
	}

  /**
   * Run multi model
   *
   * @return self
   */
	public function multi(): self {
		$this->request_mh = curl_multi_init();
		return $this;
	}

  /**
	 * Do single request only, use add method for multi()
   *
   * @param string $url
   * @param array $payload
   * @param string $method Can be POST or GET only
   * @param array $headers Array with headers. Each entry as string
   * @return Result<mixed>
   */
	public function request(string $url, array $payload = [], string $method = 'POST', array $headers = []): Result {
		if ($this->request_mh) {
			return err('e_single_request_only');
		}
		$ch = $this->createCurlHandler($url, $payload, $method, $headers);
		return $this->process($ch);
	}

	/**
	 * This method adds new request to multi request
	 * @param string $url
	 * @param array $payload
	 * @param string $method
	 * @param array $headers
	 * @return Fetch
	 */
	public function add(string $url, array $payload = [], string $method = 'POST', array $headers = []): self {
		if (!$this->request_mh) {
			return err('e_multi_request_only');
		}

		$ch = $this->createCurlHandler($url, $payload, $method, $headers);
		$this->request_handlers[] = $ch;
		curl_multi_add_handle($this->request_mh, $ch);
		return $this;
	}

	/**
 * @param string $url
 * @param array $payload
 * @param string $method
 * @param array $headers
 * @return CurlHandle
 */
	protected function createCurlHandler(string $url, array $payload = [], string $method = 'POST', array $headers = []): CurlHandle {
		if ($method === 'GET' && $payload) {
			$url = rtrim($url, '?') . '?' . http_build_query($payload, '', '&');
		}

		$ch = curl_init($url);

		match ($this->request_type) {
			'msgpack' => array_push($headers, 'Content-type: application/msgpack', 'Accept: application/msgpack'),
			'json' => array_push($headers, 'Content-type: application/json', 'Accept: application/json'),
			'binary' => array_push($headers, 'Content-type: application/binary', 'Accept: application/binary'),
			default => null,
		};

		$opts = [
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_SSL_VERIFYPEER => $this->request_ssl_verify,
			CURLOPT_CONNECTTIMEOUT => $this->request_connect_timeout,
			CURLOPT_TIMEOUT => $this->request_timeout,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_ENCODING => $this->request_encoding,
			CURLOPT_TCP_KEEPALIVE => $this->request_keepalive,
			CURLOPT_USERAGENT => $this->request_useragent,
		];

		if ($this->request_proxy) {
			$opts[CURLOPT_PROXY] = $this->request_proxy['host'] . ':' . $this->request_proxy['port'];
			if (isset($this->request_proxy['user'])) {
				$opts[CURLOPT_PROXYUSERPWD] = $this->request_proxy['user'] . ':' . $this->request_proxy['password'];
			}
			$opts[CURLOPT_PROXYTYPE] = match ($this->request_proxy['type'] ?? 'http') {
				'socks4' => CURLPROXY_SOCKS4,
				'socks5' => CURLPROXY_SOCKS5,
				default => CURLPROXY_HTTP,
			};
		}

		if ($method === 'POST') {
			$opts[CURLOPT_POST] = 1;
			$opts[CURLOPT_POSTFIELDS] = call_user_func($this->encoder_fn, $payload);
		}

		curl_setopt_array($ch, $opts);
		unset($opts);
		return $ch;
	}

	/**
	 * This method execute multiple request in multi() mode
	 * If we call this methods without multi it throws Exception
	 * In case if one or more responses failed it throws Exception
	 *
	 * @return array list of results with structure same as single request
	 */
	public function exec(): array {
		if (!$this->request_mh) {
			throw new Error('Trying to exec request that ws not inited');
		}
		do {
			$status = curl_multi_exec($this->request_mh, $active);
			if (!$active) {
				continue;
			}

			curl_multi_select($this->request_mh);
		} while ($active && $status === CURLM_OK);

		$result = [];
		foreach ($this->request_handlers as $ch) {
			$result[] = $this->process($ch);
		}
		curl_multi_close($this->request_mh);
		unset($this->request_handlers);
		$this->request_handlers = [];
		$this->request_mh = null;

		return $result;
	}

	/**
	 * @param CurlHandle $ch
	 * @return Result<mixed>
	 */
	private function process(CurlHandle $ch): Result {
		try {
			$fetch_fn = $this->request_mh ? 'curl_multi_getcontent' : 'curl_exec';
			$response = $fetch_fn($ch);
			$err_code = curl_errno($ch);
			if ($err_code) {
				// https://curl.se/libcurl/c/libcurl-errors.html
				$error = match ($err_code) {
					7 => 'e_request_refused',
					9 => 'e_request_access_denied',
					28 => 'e_request_timedout',
					52 => 'e_request_got_nothing',
					default => 'e_request_failed',
				};
				return err($error, 'CURL ' . $err_code . ': ' . curl_error($ch));
			}
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if ($this->request_mh) {
				curl_multi_remove_handle($this->request_mh, $ch);
			}
			curl_close($ch);
			if (($httpcode !== 200 && $httpcode !== 201)) {
				$error = match ($httpcode) {
					429 => 'e_http_too_many_request',
					400 => 'e_http_bad_request',
					401 => 'e_http_unauthorized',
					403 => 'e_http_forbidden',
					404 => 'e_http_not_found',
					405 => 'e_http_method_not_allowed',
					413 => 'e_http_payload_too_large',
					414 => 'e_http_not_found',
					500 => 'e_http_server_error',
					501 => 'e_http_not_implemented',
					502 => 'e_http_bad_gateway',
					503 => 'e_http_service_unavailable',
					504 => 'e_http_gateway_timeout',
					default => 'e_request_failed',
				};
				return err($error, 'HTTP ' . $httpcode . ': ' . $response);
			}

			if (!$response) {
				return err('e_response_empty', $response);
			}

			$decoded = call_user_func($this->decoder_fn, $response);
			if (false === $decoded) {
				return err('e_response_decode_failed');
			}
			return ok($decoded);
		} catch (Throwable $T) {
			return err('e_request_failed', $T->getMessage());
		}
	}

	/**
	 * @param mixed $data
	 * @return string
	 */
	protected function encodeJson(mixed $data): string {
		$json = json_encode($data);
		if ($this->request_json_bigint_keys) {
			$json = preg_replace('/"(' . implode('|', $this->request_json_bigint_keys) . ')":"([0-9]+)"/ius', '"$1":$2', $json);
		}

		return $json;
	}
}
