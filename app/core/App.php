<?php declare(strict_types=1);

final class App {
	/** @property bool $debug */
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
	 * @param string $type error, info, wanr, notice
	 * @return string идентификатор исключения
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
	 * Иницилизация работы приложения
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
	 * Завершение исполнени приложени
	 *
	 * @return void
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
	 * @return View
	 */
	public static function process(): View {
		if (!isset(static::$action_map)) {
			/** @var string $action_map_file */
			$action_map_file = config('common.action_map_file');
			/** @var array<string, string> $loaded_map */
			$loaded_map = Env::load($action_map_file);
			static::$action_map = $loaded_map;
		}

		$Request = Request::current();
		$Response = Response::current();

		$process = function () use ($Request): array {
			$action = static::$action_map[$Request->getAction()];
			/** @var array<string, mixed> $input_vars */
			$input_vars = Input::get(static::getImportVarsArgs($action));
			extract($input_vars);
			$response = include $action;

			return [get_defined_vars(), $response];
		};

		[$vars, $response] = $process();

		switch (true) {
			case $response === 1:
				$Response->header('Content-type', 'text/html;charset=utf-8');
				return View::create($Request->getAction())->set($vars);

			case $response instanceof View:
				$Response->header('Content-type', 'text/html;charset=utf-8');
				return $response->set($vars);

			case is_string($response):
				$Response->header('Content-type', 'text/plain;charset=utf-8');
				return View::fromString($response);

			case is_array($response):
			case is_object($response):
				$accept = Request::$headers['accept'] ?? '';
				$type = match (true) {
					str_contains($accept, 'application/json') => 'json',
					str_contains($accept, 'application/msgpack') => 'msgpack',
					default => Input::isMsgpack() ? 'msgpack' : 'json',
				};
				if ($response instanceof Result) {
					$response = $response->toArray();
				}
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
				return View::fromString($encoded);

			default:
				$Response->header('Content-type', 'text/plain;charset=utf-8');
				return View::fromString((string)$response);
		}
	}

	/**
	 * Замена стандартного обработчика ошибок на эксепшены
	 */
	// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter, Generic.CodeAnalysis.UnusedFunctionParameter
	public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
		static::error($errstr);
		return true;
	}

	/**
	 * Handle exception. Call handlers and do some staff
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
		static $types = [
			'json' => 'application/json',
			'html' => 'text/html',
			'text' => 'text/plain',
		];

		if (!isset($type)) {
			$type = match (true) {
				Input::isJson() => 'json',
				Input::isCli() => 'text',
				default => 'html'
			};
		}

		return function (Throwable $Exception) use ($code, $type, $format_func, $types) {
			switch (true) {
				case isset($format_func):
					$response = $format_func($Exception);
						break;
				case $type === 'json':
					$response = json_encode(
						[
							'error' => $Exception->getMessage(),
							'trace' => App::$debug ? $Exception->getTrace() : [],
						], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
					);
						break;

				case $type === 'html':
					$response = '<html><head><title>Error</title></head><body>'
					. '<p>Unhandled exception <b>'
					. $Exception::class . '</b> with message "' . $Exception->getMessage()
					. (static::$debug ? '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() : '')
					. '"</p>';

					if (static::$debug) {
						$response .= '<p><ul>'
						. implode(
							'<br/>', array_map(
								function ($item) {
									return '<li>' . $item . '</li>';
								}, explode(PHP_EOL, $Exception->getTraceAsString())
							)
						)
						. '</ul></p>'
						. '</body></html>'
						;
					}
						break;

				default:
					$response = 'Error: ' . $Exception->getMessage();
					if (static::$debug) {
						$response .= PHP_EOL . $Exception->getTraceAsString();
					}
			}

			return Response::current()
					->status($code)
					->header('Content-type', $types[$type] . ';charset=utf8')
					->send($response);
		};
	}

		/**
	 * Assign handler for special exception that will be called when exception raises
	 *
	 * @param string $exception
	 * @param callable $handler
	 * @return void
	 */
	public static function setExceptionHandler(string $exception, callable $handler): void {
		static::$e_handlers[$exception] = $handler;
	}

		/**
	 * Хэндлер для управления ошибками ассертов
	 * @param	string  $file
	 * @param	string	$line
	 * @param	int	$code
	 * @throws Exception
	 */
	public static function handleAssertion(string $file, string $line, ?int $code): void {
		throw new Error('Assertion failed in file ' . $file . ' at line ' . $line . ' with code ' . $code);
	}

		/**
	 * Generate error to stop next steps using special exception class name
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
