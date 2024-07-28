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
	 * @param string $map_file File with map of args or empty to use default
	 * @return array
	 */
	public static function getImportVarsArgs(string $file, string $map_file = null): array {
		$params = Env::load($map_file ?: config('common.param_map_file'));
		$args = [];
		if (isset($params[$file])) {
			foreach ($params[$file] as $param) {
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

		return json_decode(file_get_contents($file), true);
	}

	/**
	 * Log any message
	 * @param string $message
	 * @param array|object $dump
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
			. json_encode(filter_input_array(INPUT_COOKIE), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL
		;
		error_log($message, 3, $log_file);
		return $id;
	}

	/**
	 * Иницилизация работы приложения
	 * @param array $config
	 */
	public static function start(array $config = []): void {
		foreach ($config as $param => $value) {
			static::${$param} = $value;
		}

		if (!isset(static::$debug)) {
			static::$debug = getenv('PROJECT_ENV') === 'dev';
			static::$log_level = (int)config('common.cli_level');
		}

		// Locale settings
		setlocale(LC_ALL, 'en_US.UTF8');

		// Timezone settings
		date_default_timezone_set(timezone_name_from_abbr('', (int)Cookie::get('tz_offset'), 0) ?: 'UTC');

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
			static::$action_map = Env::load(config('common.action_map_file'));
		}

		$Request = Request::current();
		$Response = Response::current();

		$process = function () use ($Request): array {
			$action = static::$action_map[$Request->getAction()];
			extract(Input::get(static::getImportVarsArgs($action)));
			$response = include $action;

			return [get_defined_vars(), $response];
		};

		[$vars, $response] = $process();

		switch (true) {
			case $response === 1:
				$Response->header('Content-type', 'text/html;charset=utf-8');
				return View::create($Request->getAction())->set($vars);
				break;

			case $response instanceof View:
				$Response->header('Content-type', 'text/html;charset=utf-8');
				return $response->set($vars);
				break;

			case is_string($response):
				$Response->header('Content-type', 'text/plain;charset=utf-8');
				return View::fromString($response);
				break;

			case is_array($response):
			case is_object($response):
				$accept = filter_input(INPUT_SERVER, 'HTTP_ACCEPT') ?? '';
				$type = match (true) {
					str_contains('application/json', $accept) => 'json',
					str_contains('application/msgpack', $accept) => 'msgpack',
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
					throw new Error('Failed to encode ' . $type  . ' response');
				}
				return View::fromString($encoded);
				break;

			default:
				$Response->header('Content-type', 'text/plain;charset=utf-8');
				return View::fromString((string)$response);
		}
	}

	/**
	 * Замена стандартного обработчика ошибок на эксепшены
	 */
	public static function handleError(int $errno, string $errstr, string $errfile, int $errline): void {
		assert(isset($errno) && isset($errfile) && isset($errline)); // for phpcs
		static::error($errstr);
	}

	/**
	 * Handle exception. Call handlers and do some staff
	 * @param Throwable $Exception
	 */
	public static function handleException(Throwable $Exception) {
		static::log($Exception->getMessage(), ['trace' => $Exception->getTraceAsString()], 'error');

		$exception = $Exception::class;
		do {
			if (isset(static::$e_handlers[$exception])) {
				return static::$e_handlers[$exception]($Exception);
			}
			$exception = get_parent_class($exception);
		} while (false !== $exception);

		$implements = class_implements($Exception);
		while ($implement = array_pop($implements)) {
			if (isset(static::$e_handlers[$implement])) {
				return static::$e_handlers[$implement]($Exception);
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
		throw new $class($error);
	}

	/**
	 * Execute shell command in KISS core environment
	 * @param string $cmd Command to be executed
	 * @return string Result of execution
	 */
	public static function exec(string $cmd): string {
		$project_dir = getenv('PROJECT_DIR');
		$output = `
		set -e
		cd $project_dir
		source ./env.sh
		$cmd
		`;
		return $output ? trim($output) : '';
	}
}

