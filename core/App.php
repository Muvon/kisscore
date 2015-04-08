<?php
class App {
  /** @property bool $debug */
  public static $debug;
  protected static $e_handlers = [];

  public static function getImportVarsArgs($file, $map_file = null) {
    $params = static::getJSON($map_file ?: config('common.param_map_file'));
    $args = [];
    if (isset($params[$file])) {
      foreach ($params[$file] as $param) {
        $args[] = $param['name'] . ':' . $param['type'] . (isset($param['default']) ? '=' . $param['default'] : '');
      }
    }
    return $args;
  }

  public static function writeJSON($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }

  public static function getJSON($file) {
    if (!is_file($file)) {
      throw new Exception('Cant find file ' . $file . '. Be sure you started init script to compile application');
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
  public static function log($message, array $dump = [], $type = 'error') {
    assert('is_string($message)');
    assert('is_string($type)');
    $id = bin2hex($message . ':' . implode('.', array_keys($dump)) . ':' . $type);
    $log_file = getenv('LOG_DIR') . '/' . date('Ymd') . '-' . $type . '.log';
    $message =
      date('[Y-m-d H:i:s T]')
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
  public static function start(array $config = []) {
    foreach ($config as $param => $value) {
      static::$$param = $value;
    }

    if (!isset(static::$debug)) {
      static::$debug = getenv('PROJECT_ENV') === 'dev';
    }

    // Locale settings
    setlocale(LC_ALL, 'ru_RU.UTF8');

    // Error handler
    set_error_handler([static::class, 'handleError'], E_ALL);

    // Handle uncatched exceptions
    set_exception_handler([static::class, 'handleException']);

    // Register default Exception handler
    if (App::$debug) {
      static::setExceptionHandler(Exception::class, function (Exception $Exception) {
        return static::printException($Exception);
      });
    }

    Autoload::register('App', getenv('APP_DIR') . '/src');
    Autoload::register('Plugin', getenv('APP_DIR') . '/plugin');
    Autoload::register('Lib', getenv('APP_DIR') . '/lib');
    Autoload::register('', getenv('APP_DIR') . '/vendor');
  }

  /**
   * Завершение исполнени приложени
   */
  public static function stop() {
    // Todo some work here
  }

  /**
   * @param Request $Request
   * @return View
   */
  public static function process(Request $Request, Response $Response) {
    $process = function (&$_RESPONSE) use ($Request, $Response) {
      $_ACTION = getenv('APP_DIR') . '/actions/' . $Request->getAction() . '.php';
      extract(Input::get(static::getImportVarsArgs($_ACTION)));
      $_RESPONSE = include $_ACTION;

      return get_defined_vars();
    };

    $vars = $process($View);

    if (!$View instanceof View) {
      $View = View::create($Request->getAction());
    }

    return $View->set($vars);
  }

  /**
   * Замена стандартного обработчика ошибок на эксепшены
   */
  public static function handleError($errno, $errstr, $errfile, $errline, $errcontext) {
    return static::error($errstr);
  }


  public static function handleException(Exception $Exception) {
    $Exception->id = static::log($Exception->getMessage(), ['trace' => $Exception->getTraceAsString()], 'error');

    $exception = get_class($Exception);
    do {
      if (isset(static::$e_handlers[$exception])) {
        $func = static::$e_handlers[$exception];
        return $func($Exception);
      }
    } while (false !== $exception = get_parent_class($exception));
  }

  /**
   * Print unhandled excpetion in html format
   */
  protected static function printException(Exception $Exception) {
    echo '<html><head><title>Error</title></head><body>';
    echo '<p>Unhandled exceptions <b>' . get_class($Exception) . '</b> with message "' . $Exception->getMessage() . '" in file "' . $Exception->getFile() . ':' . $Exception->getLine() . '"</p>';
    echo '<p><ul>';
    echo implode('<br/>', array_map(function ($item) { return '<li>' . $item . '</li>'; }, explode(PHP_EOL, $Exception->getTraceAsString())));
    echo '</ul></p>';
    echo '</body></html>';
  }

  public static function setExceptionHandler($exception, Callable $handler) {
    static::$e_handlers[$exception] = $handler;
  }

  /**
   * Хэндлер для управления ошибками ассертов
   * @param	stirng  $file
   * @param	string	$line
   * @param	string	$code
   * @throws Exception
   */
  public static function handleAssertion($file, $line, $code) {
    throw new Exception('Assertion failed in file ' . $file . ' at line ' . $line . ' with code ' . $code);
  }

  /**
   * Генерация ошибки приложения (прерывание выполнения)
   * @param string $error
   * @param string $class Имя класса, например, Exception::class
   * @throws \Exception
   */
  public static function error($error, $class = 'Exception') {
    throw new $class($error);
  }

  public static function exec($cmd) {
    $project = getenv('PROJECT');
    $cmd = addcslashes($cmd, '"');
    return trim(`bash -c "source ~/.kissrc; kiss $project; $cmd"`);
  }
}
