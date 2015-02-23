<?php
class App {
  /** @property bool $debug */
  public static $debug = true;
  protected static $e_handlers = [];

  /**
   * Прекомпиляция исходного кода для работы
   * @return void
   */
  public static function compile() {
    static::reconfigure();
    static::generateAutoloadMap();
    static::generateURIMap();
    static::generateParamMap();
    static::generateNginxRouteMap();
  }

  public static function reconfigure() {
    static::configure(getenv('PROJECT_DIR') . '/app.ini.tpl');
    static::generateConfigs();
  }

  public static function generateConfigs() {
    $configure = function ($file) {
      return include $file;
    };

    foreach (glob(getenv('APP_DIR') . '/plugins/*/configure.php') as $file) {
      $configure($file);
    }
  }

  /**
   * @param string $template Path to template or dir with templates
   * @param array $params %Params% to be replaced
   * @return void
   */
  public static function configure($template, array $params = []) {
    assert('is_string($template)');

    $params += [
      '%USER%'          => getenv('USER'),
      '%PROJECT%'       => getenv('PROJECT'),
      '%PROJECT_DIR%'   => getenv('PROJECT_DIR'),
      '%APP_DIR%'       => getenv('APP_DIR'),
      '%HTML_DIR%'      => getenv('HTML_DIR'),
      '%CONFIG_DIR%'    => getenv('CONFIG_DIR'),
      '%ENV_DIR%'       => getenv('ENV_DIR'),
      '%BIN_DIR%'       => getenv('BIN_DIR'),
      '%RUN_DIR%'       => getenv('RUN_DIR'),
      '%LOG_DIR%'       => getenv('LOG_DIR'),
      '%VAR_DIR%'       => getenv('VAR_DIR'),
      '%TMP_DIR%'       => getenv('TMP_DIR'),
      '%KISS_CORE%'     => getenv('KISS_CORE'),
      '%HTTP_HOST%'     => getenv('HTTP_HOST'),
      '%CONFIG_TEMPLATE_DIR%' => getenv('CONFIG_TEMPLATE_DIR'),
      '%DEBUG%'         => (int) static::$debug,
    ];

    foreach(is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
      file_put_contents(getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'), strtr(file_get_contents($file), $params));
    }
  }

  /**
   * Генерация карты для автолоада классов
   * @return void
   */
  protected static function generateAutoloadMap() {
    $map = [];
    $app_dir = getenv('APP_DIR');
    $files = explode(PHP_EOL, trim(`find -L $app_dir -name '*.php'`));

    foreach($files as $file) {
      $class = substr(basename($file), 0, -4);

      $content = file_get_contents($file);
      if (preg_match("/(class|interface|trait) +$class/", $content)) {
        if (preg_match("/\n *namespace +\\\\?([a-zA-Z0-9_\\\\]+)/", $content, $matches)) {
            $class = $matches[1] . '\\' . $class;
        }
        $map[$class] = $file;
      }
    }
    static::writeJson(config('common.autoload_map_file'), $map);
  }

  /**
   * Генерация карты для обработки входящих запросов по URI
   */
  protected static function generateURIMap() {
    $map = [];
    $action_dir = getenv('APP_DIR') . '/actions';
    $files = explode(PHP_EOL, trim(`find -L $action_dir -name '*.php'`));
    foreach ($files as $file) {
      $action = substr(basename($file), 0, -4);
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@route\s+([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, $action);
          $map[$pattern] = $params;
        }
      }
    }
    static::writeJson(config('common.uri_map_file'), $map);
  }

  protected static function generateParamMap() {
    $map = [];
    $action_dir = getenv('APP_DIR') . '/actions';
    $files = explode(PHP_EOL, trim(`find -L $action_dir -name '*.php'`));
    foreach ($files as $file) {
      $action = substr(basename($file), 0, -4);
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@param\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $map[$file][] = [
            'name'    => $param = substr(strtok($m[2][$k], ' '), 1),
            'type'    => $m[1][$k],
            'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
          ];
        }
      }
    }
    static::writeJson(config('common.param_map_file'), $map);
  }

  protected static function generateNginxRouteMap() {
    $routes = static::getJson(config('common.uri_map_file'));
    uasort($routes, function ($a, $b) {
      return (sizeof($a) > sizeof($b)) ? 1 : -1;
    });

    $rewrites = [];
    foreach ($routes as $route => $action) {
      $i = 0; // route like (bla (bla bla)) with uff8 cant handle by nginx. so hack it
      $uri = '/?ROUTE='
           . preg_replace_callback(
              '|\([^\)]+\)|is',
              function ($item) use (&$i) {
                return '$' . ++$i;
              },
              $route
            )
           . '&ACTION=' . array_shift($action)
      ;

      if ($action) {
        foreach ($action as $k => $v) {
          $uri .= '&' . $v . '=$' . ($k + 1);
        }
      }
      $rewrites[] = "rewrite '(*UTF8)^/$route/?$' '$uri';";
    }
    // @TODO fix configs prepares
    file_put_contents(config('common.nginx_route_file'), implode(PHP_EOL, $rewrites));
  }

  public static function getImportVarsArgs($file) {
    $params = static::getJson(config('common.param_map_file'));
    $args = [];
    if (isset($params[$file])) {
      foreach ($params[$file] as $param) {
        $args[] = $param['name'] . ':' . $param['type'] . (isset($param['default']) ? '=' . $param['default'] : '');
      }
    }
    return $args;
  }

  public static function writeJson($file, $data) {
    return file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }

  public static function getJson($file) {
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
    assert('in_array($type, ["info", "error", "warn", "notice"])');
    $id = bin2hex($message . ':' . implode('.', array_keys($dump)) . ':' . $type);
    $log_file = getenv('LOG_DIR') . '/' . date('Ymd') . '-' . $type . '.log';
    $message = date('[Y-m-d H:i:s T]')
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

    // Locale settings
    setlocale(LC_ALL, 'ru_RU.UTF8');

    // Autoloading models
    spl_autoload_register([static::class, 'handleAutoload']);

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
    } while ($exception = get_parent_class($Exception));
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
   * Обработка автозагрузки класса
   * @param string $name Имя класса
   * @return void
   */
  public static function handleAutoload($name) {
    static $map = [];

    // Load map
    if (!$map) {
      $map = static::getJson(config('common.autoload_map_file'));
    }

    // Find and include file
    if (isset($map[$name])) {
      include $map[$name];
    }
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