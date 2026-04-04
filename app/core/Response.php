<?php declare(strict_types=1);

/**
 * Класс для формирования ответа клиенту
 *
 * @final
 */

final class Response {
	/** @var array<string,string> $headers */
	protected array $headers = [
		'Referrer-Policy' => 'origin-when-cross-origin',
		'X-Frame-Options' => 'DENY',
		'X-XSS-Protection' => '1; mode=block',
		'X-Content-Type-Options' => 'nosniff',
		'Content-Security-Policy' => "frame-ancestors 'none'",
	];

	/** @var string */
	protected string $body = '';

	/** @var int */
	protected int $status = 200;

	/** @var array<int,string> */
	protected static array $messages = [
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',

		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',

		400 => 'Bad Request',
		401 => 'Unauthorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		429 => 'Too Many Requests',

		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported',
	];

  /**
   * Init of new response
   * @param int $status HTTP Status of response
   * @return void
   */
	final protected function __construct(int $status = 200) {
		$this->status($status);
	}

  /**
   * Return current instance or initialize and parse
   * @param bool $reset Force new instance (for Swoole per-request reset)
   */
	public static function current(bool $reset = false): self {
		static $instance;
		if (!isset($instance) || $reset) {
			$instance = new static(200);
		}

		return $instance;
	}


  /**
   * Change HTTP status of response
   * @param int $status New HTTP status to be set
   * @return $this
   */
	public function status(int $status): self {
		assert(isset(self::$messages[$status]));
		$this->status = $status;
		return $this;
	}

  /**
   * Get current HTTP status code
   * @return int
   */
	public function getStatus(): int {
		return $this->status;
	}

  /**
  * Get response body
  * @access public
  * @return string данные ответа клиенту
  */
	public function __toString(): string {
		return $this->body;
	}

  /**
   * Send body to output
   * @return $this
   */
	public function sendBody(): self {
		echo (string)$this;
		return $this;
	}

  /**
   * Send all staff to output: headers, body and so on
	 *
	 * @param string $content
   * @return self
   */
	public function send(string $content = ''): self {
		$this->sendHeaders();
		$this->setBody($content);
		$this->sendBody();
		return $this;
	}

  /**
  * Relocate user to url
	*
  * @param string $url
  * @param int $code (301 | 302)
  */
	public static function redirect(string $url, int $code = 302): never {
		assert(in_array($code, [301, 302]));

		(new static($code))
		->header('Content-type', '')
		->header('Location', $url)
		->sendHeaders();
		exit;
	}

  /**
  * Reset headers stack
  * @return Response
  */
	public function flushHeaders(): self {
		$this->headers = [];
		return $this;
	}

  /**
  * Push header to stack to be sent
  * @param string $header
  * @param string $value
  * @return Response
  */
	public function header(string $header, string $value): self {
		$this->headers[$header] = $value;
		return $this;
	}

	/**
	 * Send stacked headers to output
	 * @return Response
	 */
	public function sendHeaders(?callable $header_fn = null, ?callable $cookie_fn = null): self {
		if (!$header_fn) {
			$header_fn = function (string $key, string $value, bool $replace = true): void {
				header($key . ': ' . $value, $replace);
			};
		}
		Cookie::send($cookie_fn); // This is not good but fuck it :D
		if (headers_sent()) {
			return $this;
		}

	  // HTTP-строка статуса
		http_response_code($this->status);

		foreach ($this->headers as $header => $value) {
			$header_fn($header, $value, true);
		}

	  // Send header with execution time
		$header_fn('X-Server-Time', (string)(int)(Request::$time_float * 1000));
		$header_fn('X-Response-Time', (string)(int)((microtime(true) - Request::$time_float) * 1000));
		return $this;
	}

  /**
  * Set boy data to response
  * @access public
  * @param string $body
  * @return $this
  */
	public function setBody(string $body): self {
		$this->body = $body;
		return $this;
	}
}
