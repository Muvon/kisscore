<?php
final class App {
  /** @property bool $debug */
  public static bool $debug;
  protected static array $e_handlers = [];
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
        $args[] = $param['name'] . ':' . $param['type'] . (isset($param['default']) ? '=' . $param['default'] : '');
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
    return !!file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
   * @param array $dump
   * @param string $type error, info, wanr, notice
   * @return string идентификатор исключения
   */
  public static function log(string $message, array $dump = [], string $type = 'error'): string {
    $id = hash('sha256', $message . ':' . implode('.', array_keys($dump)) . ':' . $type);
    $log_file = getenv('LOG_DIR') . '/' . gmdate('Ymd') . '-' . $type . '.log';
    $message =
      gmdate('[Y-m-d H:i:s T]')
      . "\t" . $id
      . "\t" . $message
      . "\t" . json_encode($dump, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\t"
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
      static::$$param = $value;
    }

    if (!isset(static::$debug)) {
      static::$debug = getenv('PROJECT_ENV') === 'dev';
    }

    if (Input::isCLI()) {
      ini_set('error_reporting', E_ALL | E_STRICT);
    }

    // Locale settings
    setlocale(LC_ALL, 'ru_RU.UTF8');

    // Timezone settings
    date_default_timezone_set(timezone_name_from_abbr('', intval(Cookie::get('tz_offset')), 0) ?: 'UTC');

    // Error handler
    set_error_handler([static::class, 'handleError'], E_ALL);

    // Handle uncatched exceptions
    set_exception_handler([static::class, 'handleException']);

    // Register default Exception handler
    static::setExceptionHandler(Throwable::class, static::createExceptionHandler());

    Autoload::register('Plugin', getenv('APP_DIR') . '/plugin');
    Autoload::register('App', getenv('APP_DIR') . '/src');
    Autoload::register('App\Model', getenv('APP_DIR') . '/src/model');
    Autoload::register('App\Component', getenv('APP_DIR') . '/src/component');
    Autoload::register('App\Lib', getenv('APP_DIR') . '/src/lib');

    // If we have vendor dir with autoload file load it
    // This is required for composer packages
    $vendor_autoload_file = getenv('APP_DIR') . '/vendor/autoload.php';
    if (file_exists($vendor_autoload_file)) {
      include_once $vendor_autoload_file;
    }

    include_once getenv('APP_DIR') . '/start.php';
  }

  /**
   * Завершение исполнени приложени
   */
  public static function stop(): void {
    include_once getenv('APP_DIR') . '/stop.php';
  }

  /**
   * @param Request $Request
   * @return View
   */
  public static function process(Request $Request, Response $Response): View {
    if (!isset(static::$action_map)) {
      static::$action_map = Env::load(config('common.action_map_file'));
    }

    $process = function (&$_RESPONSE) use ($Request, $Response) {
      $_ACTION = static::$action_map[$Request->getAction()];
      extract(Input::get(static::getImportVarsArgs($_ACTION)));
      $_RESPONSE = include $_ACTION;

      return get_defined_vars();
    };

    $vars = $process($response);

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
        $Response->header('Content-type', 'application/json;charset=utf-8');
        $encoded = json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (false === $encoded) {
          throw new Error('Failed to encode JSON response');
        }
        return View::fromString($encoded);
        break;

      default:
        $Response->header('Content-type', 'text/plain;charset=utf-8');
        return View::fromString((string) $response);
    }
  }

  /**
   * Замена стандартного обработчика ошибок на эксепшены
   */
  public static function handleError(int $errno, string $errstr, string $errfile, string $errline): void {
    static::error($errstr);
  }

  /**
   * Handle exception. Call handlers and do some staff
   * @param Throwable $Exception
   */
  public static function handleException(Throwable $Exception) {
    $Exception->id = static::log($Exception->getMessage(), ['trace' => $Exception->getTraceAsString()], 'error');

    $exception = get_class($Exception);
    do {
      if (isset(static::$e_handlers[$exception])) {
        $func = static::$e_handlers[$exception];
        return $func($Exception);
      }
    } while (false !== $exception = get_parent_class($exception));
  }

  public static function createExceptionHandler(int $code = 500, string $type = null, Callable $format_func = null): Callable {
    static $types = [
      'json' => 'application/json',
      'html' => 'text/html',
      'text' => 'text/plain',
    ];

    if (!isset($type)) {
      $type = match(true) {
        Input::isJSON() => 'json',
        Input::isCLI() => 'text',
        default => 'html'
      };
    }

    return function (Throwable $Exception) use ($code, $type, $format_func, $types) {
      switch (true) {
        case isset($format_func):
          $response = $format_func($Exception);
          break;
        case $type === 'json':
          $response = json_encode([
            'error' => $Exception->getMessage(),
            'trace' => App::$debug ? $Exception->getTrace() : [],
          ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
          break;

        case $type === 'html':
          $response = '<html><head><title>Error</title></head><body>'
             . '<p>Unhandled exception <b>'
             . get_class($Exception) . '</b> with message "' . $Exception->getMessage()
             . (static::$debug ? '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() : '')
             . '"</p>';

          if (static::$debug) {
            $response .= '<p><ul>'
             . implode('<br/>', array_map(function ($item) { return '<li>' . $item . '</li>'; }, explode(PHP_EOL, $Exception->getTraceAsString())))
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

      return Response::create($code)
        ->header('Content-type', $types[$type] . ';charset=utf8')
        ->send($response)
      ;
    };
  }

  /**
   * Assign handler for special exception that will be called when exception raises
   * @param string $exception
   * @param Callable $handler
   */
  public static function setExceptionHandler(string $exception, Callable $handler): void {
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
