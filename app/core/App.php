<?php declare(strict_types=1);

final class App {
	/** @var bool */
	public static bool $debug;
	public static int $log_level;

	/** @var array<string,callable> */
	protected static array $e_handlers = [];
	/** @var array<string,string> */
	protected static array $action_map;

	/**
	 * Fetch annotated variables from $file using $map_file
	 * @param string $file File that was annotated with import params (action or something else)
	 * @param ?string $map_file File with map of args or empty to use default
	 * @return array<string>
	 */
	public static function getImportVarsArgs(string $file, ?string $map_file = null): array {
		/** @var string $param_map_file */
		$param_map_file = config('common.param_map_file');
		$params = Env::load($map_file ?: $param_map_file);
		$args = [];
		if (isset($params[$file])) {
			/** @var array<array{name: string, type: string, default?: string}> $file_params */
			$file_params = $params[$file];
			foreach ($file_params as $param) {
				$args[] = $param['name'] . ':' . $param['type']
				. (isset($param['default']) ? '=' . $param['default'] : '')
				;
			}
		}
		return $args;
	}

	/**
	 * Write json data into file
	 * @param string $file File path to json
	 * @param mixed $data Data to put in json file
	 * @return bool
	 */
	public static function writeJSON(string $file, mixed $data): bool {
		return !!file_put_contents(
			$file,
			json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
		);
	}

	/**
	 * Get json data from file
	 * @param string $file
	 * @return mixed
	 */
	public static function getJSON(string $file): mixed {
		if (!is_file($file)) {
			throw new Error('Cant find file ' . $file . '. Be sure you started init script to compile application');
		}

		$contents = file_get_contents($file);
		if ($contents === false) {
			throw new Error('Failed to read file ' . $file);
		}
		return json_decode($contents, true);
	}

	/**
	 * Log any message
	 * @param string $message
	 * @param array<string, mixed>|object $dump
	 * @param string $type error, info, warn, notice
	 * @return string exception hash identifier
	 */
	public static function log(string $message, array|object $dump = [], string $type = 'error'): string {
		$encoded_dump = json_encode($dump, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		$id = hash('sha256', $message . ':' . $encoded_dump . ':' . $type);
		$log_file = getenv('LOG_DIR') . '/' . gmdate('Ymd') . '-' . $type . '.log';
		$message =
		gmdate('[Y-m-d H:i:s T]')
		. "\t" . $id
		. "\t" . $message
		. "\t" . $encoded_dump . "\t"
		. json_encode(Cookie::all(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
		;
		error_log($message, 3, $log_file);
		return $id;
	}

	/**
	 * Initialize the application
	 * @param array<string, mixed> $config
	 */
	public static function start(array $config = []): void {
		// First detect local envs from base vars, cuz we use it
		/** @var ?string $root */
		$root = $config['root'] ?? null;
		Env::initLocalEnv($root);
		unset($config['root']);
		foreach ($config as $param => $value) {
			static::${$param} = $value;
		}

		if (!isset(static::$debug)) {
			static::$debug = getenv('APP_ENV') === 'dev';
			/** @var int|string $cli_level */
			$cli_level = config('common.cli_level');
			static::$log_level = (int)$cli_level;
		}

		// Locale settings
		setlocale(LC_ALL, 'en_US.UTF8');

		// Timezone settings
		/** @var int|string $tz_offset */
		$tz_offset = Cookie::get('tz_offset');
		date_default_timezone_set(timezone_name_from_abbr('', (int)$tz_offset, 0) ?: 'UTC');

		// Error handler
		set_error_handler([static::class, 'handleError'], E_ALL);

		// Handle uncatched exceptions
		set_exception_handler([static::class, 'handleException']);

		// Register default Exception handler
		static::setExceptionHandler(Throwable::class, static::createExceptionHandler());

		Autoload::register('App', getenv('APP_DIR') . '/src');

		// If we have vendor dir with autoload file load it
		// This is required for composer packages
		$vendor_autoload_file = getenv('APP_DIR') . '/vendor/autoload.php';
		if (file_exists($vendor_autoload_file)) {
			include_once $vendor_autoload_file;
		}

		include_once getenv('APP_DIR') . '/start.php';
	}

	/**
	 * @param Throwable $T
	 * @param string $type
	 * @return string
	 */
	public static function logException(Throwable $T, string $type = 'error'): string {
		return App::log($T->getMessage(), ['trace' => $T->getTraceAsString()], $type);
	}

	/**
	 * Shutdown the application
	 */
	public static function stop(): void {
		include_once getenv('APP_DIR') . '/stop.php';
	}

	/**
	 * @param ?callable $fn
	 * @return void
	 */
	public static function checkExit(?callable $fn = null): void {
		pcntl_signal_dispatch();
		if (!container('exit')) {
			return;
		}

		Cli::print('[exit] request to stop app received');
		if (isset($fn)) {
			$fn();
		}
		exit(0);
	}

	/**
	 * Process current request action and return encoded response
	 */
	public static function process(): string {
		if (!isset(static::$action_map)) {
			/** @var string $action_map_file */
			$action_map_file = config('common.action_map_file');
			/** @var array<string, string> $loaded_map */
			$loaded_map = Env::load($action_map_file);
			static::$action_map = $loaded_map;
		}

		$Request = Request::current();
		$Response = Response::current();

		$action = static::$action_map[$Request->getAction()];

		// Enforce the action's @method annotation before running it: a 405 payload
		// short-circuits the include, null means the method is allowed.
		$response = static::enforceMethod($Request, $Response);
		if ($response === null) {
			/** @var array<string, mixed> $input_vars */
			$input_vars = Input::get(static::getImportVarsArgs($action));
			extract($input_vars);
			$response = include $action;
		}

		if (is_string($response)) {
			$Response->header('Content-type', 'text/plain;charset=utf-8');
			return $response;
		}

		if ($response instanceof Result) {
			// Default an err Result to 400 ONLY when the action didn't already
			// set an error status — actions call http_status(404/409/…) before
			// `return $Result` and that explicit mapping must win. An
			// unconditional 400 here silently clobbers every such mapping.
			if ($response->err && $Response->getStatus() < 400) {
				$Response->status(400);
			}
			$response = $response->toArray();
		}

		if (is_array($response) || is_object($response)) {
			$accept = Request::header('accept');
			$type = match (true) {
				str_contains($accept, 'application/msgpack') => 'msgpack',
				Input::isMsgpack() => 'msgpack',
				default => 'json',
			};

			$Response->header('Content-type', 'application/' . $type . ';charset=utf-8');
			$encoded = $type === 'msgpack'
			? msgpack_pack($response)
			: json_encode(
				$response,
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE
			);
			if (false === $encoded) {
				throw new Error('Failed to encode ' . $type . ' response');
			}
			return $encoded;
		}

		$Response->header('Content-type', 'text/plain;charset=utf-8');
		return (string)$response;
	}

	/**
	 * Enforce the matched route's @method annotation. Returns a 405 error
	 * payload (and stamps status + Allow header on the response) when the request
	 * method isn't allowed, or null when it is. An empty allow-list means the
	 * route accepts any method (enforcement is opt-in per action).
	 *
	 * @param Request $Request
	 * @param Response $Response
	 * @return array{error:string}|null
	 */
	protected static function enforceMethod(Request $Request, Response $Response): ?array {
		$allowed = $Request->getRouteMethod();
		if ($allowed === '' || in_array(strtoupper(Request::method()), explode(',', $allowed), true)) {
			return null;
		}

		$Response->status(405)->header('Allow', str_replace(',', ', ', $allowed));
		return ['error' => 'e_method_not_allowed'];
	}

	/**
	 * Error handler — converts errors to exceptions
	 */
	// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter
	public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
		// Deprecations are advisories about FUTURE breakage — turning them into
		// exceptions makes every dependency's deprecation fatal the day a new PHP
		// lands (8.5's curl_close() notice broke every Fetch call this way).
		// Log so they get cleaned up; never throw.
		if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
			error_log('[deprecated] ' . $errstr . ' in ' . $errfile . ':' . $errline);
			return true;
		}
		static::error($errstr);
		return true;
	}

	/**
	 * Handle exception by calling registered handlers
	 * @param Throwable $Exception
	 */
	public static function handleException(Throwable $Exception): void {
		$log_exception = !$Exception instanceof ResultError;
		if ($log_exception) {
			static::logException($Exception);
		}

		$exception = $Exception::class;
		do {
			if (isset(static::$e_handlers[$exception])) {
				static::$e_handlers[$exception]($Exception);
				return;
			}
			$exception = get_parent_class($exception);
		} while (false !== $exception);

		$implements = class_implements($Exception);
		while ($implement = array_pop($implements)) {
			if (isset(static::$e_handlers[$implement])) {
				static::$e_handlers[$implement]($Exception);
				return;
			}
		}
	}

	/**
	 * @param int $code
	 * @param ?string $type
	 * @param ?callable $format_func
	 * @return callable
	 */
	public static function createExceptionHandler(
		int $code = 500,
		?string $type = null,
		?callable $format_func = null
	): callable {
		static $content_types = [
			'json' => 'application/json',
			'html' => 'text/html',
			'text' => 'text/plain',
		];

		$type ??= match (true) {
			Input::isJson() => 'json',
			Input::isCli() => 'text',
			default => 'html'
		};

		return function (Throwable $Exception) use ($code, $type, $format_func, $content_types) {
			$response = match (true) {
				isset($format_func) => $format_func($Exception),
				$type === 'json' => json_encode(
					[
					'error' => $Exception->getMessage(),
					'trace' => App::$debug ? $Exception->getTrace() : [],
					], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
				),
				$type === 'html' => static::formatHtmlException($Exception),
				default => 'Error: ' . $Exception->getMessage()
				. (static::$debug ? PHP_EOL . $Exception->getTraceAsString() : ''),
			};

			Response::current()
				->status($code)
				->header('Content-type', $content_types[$type] . ';charset=utf8')
				->send((string)$response);
		};
	}

	/**
	 * @param Throwable $Exception
	 * @return string
	 */
	protected static function formatHtmlException(Throwable $Exception): string {
		$response = '<html><head><title>Error</title></head><body>'
		. '<p>Unhandled exception <b>'
		. $Exception::class . '</b> with message "' . $Exception->getMessage()
		. (static::$debug ? '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() : '')
		. '"</p>';

		if (static::$debug) {
			$trace = implode(
				'<br/>',
				array_map(
					fn($item) => '<li>' . $item . '</li>',
					explode(PHP_EOL, $Exception->getTraceAsString())
				)
			);
			$response .= '<p><ul>' . $trace . '</ul></p></body></html>';
		}

		return $response;
	}

	/**
	 * Register handler for a specific exception class
	 *
	 * @param string $exception
	 * @param callable $handler
	 * @return void
	 */
	public static function setExceptionHandler(string $exception, callable $handler): void {
		static::$e_handlers[$exception] = $handler;
	}

	/**
	 * Assertion failure handler
	 * @param	string  $file
	 * @param	string	$line
	 * @param	int	$code
	 * @throws Exception
	 */
	public static function handleAssertion(string $file, string $line, ?int $code): void {
		throw new Error('Assertion failed in file ' . $file . ' at line ' . $line . ' with code ' . $code);
	}

	/**
	 * Throw exception to stop execution
	 * @param string $error Message that describes error
	 * @param string $class Exception class name to be raised
	 * @throws \Exception
	 */
	public static function error(string $error, string $class = 'Exception'): void {
		/** @var \Exception $exception */
		$exception = new $class($error);
		throw $exception;
	}
}
