<?php declare(strict_types=1);

/**
 * HTTP Request wrapper.
 *
 * The per-request instance (url/route/action) and request metadata are
 * coroutine-local (see Coro). Populate metadata once per request with init();
 * read it through the accessor methods (method(), ip(), header(), …).
 */
final class Request {
	private string $action = '';
	private string $route = '';
	/** Allowed HTTP method(s) for the matched route ('' = any). @see App::process */
	private string $route_method = '';

	private const META_DEFAULTS = [
		'time' => 0,
		'time_float' => 0.0,
		'request_uri' => '',
		'content_type' => '',
		'method' => 'GET',
		'protocol' => 'HTTP',
		'referer' => '',
		'ip' => '0.0.0.0',
		'real_ip' => '0.0.0.0',
		'xff' => '',
		'host' => '',
		'user_agent' => '',
		'headers' => [],
		'is_ajax' => false,
	];

	final protected function __construct(protected string $url) {
	}

	/**
	 * Populate the current coroutine's request metadata (call once per request
	 * from the server entry point).
	 *
	 * real_ip defaults to the first X-Forwarded-For token, which ANY client can
	 * forge. Deployments that stamp it on audit records or gate on it must pass
	 * their own trusted-proxy-resolved value via $real_ip, which wins when set.
	 *
	 * @param array<string,string> $headers lowercase header names
	 * @return void
	 */
	public static function init(
		int $time = 0,
		float $time_float = 0.0,
		string $request_uri = '',
		string $content_type = '',
		string $method = 'GET',
		string $protocol = 'HTTP',
		string $referer = '',
		string $ip = '0.0.0.0',
		string $xff = '',
		string $host = '',
		string $user_agent = '',
		array $headers = [],
		bool $is_ajax = false,
		string $real_ip = '',
	): void {
		if ($real_ip === '') {
			$real_ip = $ip;
			if ($xff !== '' && $xff !== $ip) {
				$real_ip = trim(strtok($xff, ','));
			}
		}

		$bag = &Coro::bag('request_meta', static fn(): array => []);
		$bag = [
			'time' => $time,
			'time_float' => $time_float,
			'request_uri' => $request_uri,
			'content_type' => $content_type,
			'method' => $method,
			'protocol' => $protocol,
			'referer' => $referer,
			'ip' => $ip,
			'real_ip' => $real_ip,
			'xff' => $xff,
			'host' => $host,
			'user_agent' => $user_agent,
			'headers' => $headers,
			'is_ajax' => $is_ajax,
		];
	}

	/**
	 * Current coroutine's metadata; META_DEFAULTS until init() is called.
	 *
	 * @return array{time:int,time_float:float,request_uri:string,content_type:string,method:string,protocol:string,referer:string,ip:string,real_ip:string,xff:string,host:string,user_agent:string,headers:array<string,string>,is_ajax:bool}
	 */
	private static function meta(): array {
		/** @var array{time:int,time_float:float,request_uri:string,content_type:string,method:string,protocol:string,referer:string,ip:string,real_ip:string,xff:string,host:string,user_agent:string,headers:array<string,string>,is_ajax:bool} */
		return Coro::bag('request_meta', static fn(): array => self::META_DEFAULTS);
	}

	public static function time(): int {
		return self::meta()['time'];
	}

	public static function timeFloat(): float {
		return self::meta()['time_float'];
	}

	public static function uri(): string {
		return self::meta()['request_uri'];
	}

	public static function contentType(): string {
		return self::meta()['content_type'];
	}

	public static function method(): string {
		return self::meta()['method'];
	}

	public static function protocol(): string {
		return self::meta()['protocol'];
	}

	public static function referer(): string {
		return self::meta()['referer'];
	}

	public static function ip(): string {
		return self::meta()['ip'];
	}

	public static function realIp(): string {
		return self::meta()['real_ip'];
	}

	public static function xff(): string {
		return self::meta()['xff'];
	}

	public static function host(): string {
		return self::meta()['host'];
	}

	public static function userAgent(): string {
		return self::meta()['user_agent'];
	}

	public static function isAjax(): bool {
		return self::meta()['is_ajax'];
	}

	/**
	 * @return array<string,string>
	 */
	public static function headers(): array {
		/** @var array<string,string> */
		return self::meta()['headers'];
	}

	/**
	 * Single header by name (case-insensitive; Swoole delivers lowercase keys).
	 * @param string $name
	 * @param string $default
	 * @return string
	 */
	public static function header(string $name, string $default = ''): string {
		return self::headers()[strtolower($name)] ?? $default;
	}

	/**
	 * Create request instance from current metadata
	 */
	final protected static function create(): self {
		$url = rtrim(static::uri(), ';&?') ?: '/';
		$route_info = Router::match($url, static::host());

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
			$bag['instance'] = static::create();
		}

		/** @var self $current */
		$current = $bag['instance'];
		return $current;
	}

	/**
	 * Reset request metadata for the current coroutine
	 */
	public static function reset(): void {
		Coro::clear('request_meta');
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
		return static::header($header);
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
