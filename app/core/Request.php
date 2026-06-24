<?php declare(strict_types=1);

/**
 * HTTP Request wrapper.
 *
 * The per-request instance (url/route/action) is coroutine-local (see Coro).
 * The public static properties below (headers, ip, …) are part of the public
 * interface and remain process-shared; they are written once per request before
 * processing. Under runtime coroutine hooks they are NOT concurrency-safe — see
 * improve.md for the accessor-based migration that would make them so.
 */
final class Request {
	private string $action = '';
	private string $route = '';
	/** Allowed HTTP method(s) for the matched route ('' = any). @see App::process */
	private string $route_method = '';

	public static int $time = 0;
	public static float $time_float = 0;

	public static string $request_uri = '';
	public static string $content_type = '';
	public static string $method = 'GET';
	public static string $protocol = 'HTTP';
	public static string $referer = '';
	public static string $ip = '0.0.0.0';
	public static string $real_ip = '0.0.0.0';
	public static string $xff = '';
	public static string $host = '';
	public static string $user_agent = '';

	/** @var array<string,string> */
	public static array $headers = [];

	public static bool $is_ajax = false;

	final protected function __construct(protected string $url) {
	}

	/**
	 * Create request instance from current static state
	 */
	final protected static function create(): self {
		$url = rtrim(static::$request_uri, ';&?') ?: '/';
		$route_info = Router::match($url, static::$host);

		$Request = (new static($url));

		if ($route_info) {
			$Request->setRoute($route_info['route'])
					->setAction($route_info['action'])
					->setRouteMethod($route_info['method']);
			foreach ($route_info['params'] as $key => $value) {
				Input::set($key, $value);
			}
		} else {
			$Request->setRoute('home')
					->setAction('home');
		}

		return $Request;
	}

	/**
	 * Return current coroutine's instance or initialize and parse.
	 * @param ?Closure $init_fn
	 * @return self
	 */
	public static function current(?Closure $init_fn = null): self {
		/** @var array{instance:?self} $bag */
		$bag = &Coro::bag('request', static fn(): array => ['instance' => null]);
		if ($bag['instance'] === null || isset($init_fn)) {
			if (isset($init_fn)) {
				$init_fn();
			}
			static::parseRealIp();
			$bag['instance'] = static::create();
		}

		/** @var self $current */
		$current = $bag['instance'];
		return $current;
	}

	/**
	 * Reset static state for new request (Swoole: state persists across requests)
	 */
	public static function reset(): void {
		self::$time = 0;
		self::$time_float = 0;
		self::$request_uri = '';
		self::$content_type = '';
		self::$method = 'GET';
		self::$protocol = 'HTTP';
		self::$referer = '';
		self::$ip = '0.0.0.0';
		self::$real_ip = '0.0.0.0';
		self::$xff = '';
		self::$host = '';
		self::$user_agent = '';
		self::$headers = [];
		self::$is_ajax = false;
	}

	/**
	 * Parse real IP from X-Forwarded-For header
	 */
	protected static function parseRealIp(): void {
		self::$real_ip = self::$ip;
		if (!self::$xff || self::$xff === self::$ip) {
			return;
		}

		self::$real_ip = trim(strtok(self::$xff, ','));
	}

	/**
	 * @return string
	 */
	public function getUrl(): string {
		return $this->url;
	}

	/**
	 * Get path part of URL (without query string)
	 * @return string
	 */
	public function getUrlPath(): string {
		return parse_url($this->url, PHP_URL_PATH) ?: '/';
	}

	/**
	 * @return string
	 */
	public function getUrlQuery(): string {
		return parse_url($this->url, PHP_URL_QUERY) ?: '';
	}

	/**
	 * @param string $header
	 * @return string
	 */
	public function getHeader(string $header): string {
		return static::$headers[strtolower($header)] ?? '';
	}

	/**
	 * @param string|null $route
	 * @return $this
	 */
	public function setRoute(?string $route): self {
		$this->route = $route ?? '/home';
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRoute(): string {
		return $this->route ?? '';
	}

	/**
	 * Allowed HTTP method(s) for the matched route, comma-joined uppercase
	 * (e.g. "POST" or "GET,POST"). '' means any method is accepted.
	 * @param string $method
	 * @return self
	 */
	public function setRouteMethod(string $method): self {
		$this->route_method = $method;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getRouteMethod(): string {
		return $this->route_method;
	}

	/**
	 * @param string|null $action
	 * @return self
	 */
	public function setAction(?string $action): self {
		$this->action = $action
		? trim((string)preg_replace('|[^a-z0-9\_\-/]+|is', '', $action), '/')
		: 'home'
		;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAction(): string {
		/** @var string $defaultAction */
		$defaultAction = config('default.action');
		return $this->action ?: $defaultAction;
	}
}
