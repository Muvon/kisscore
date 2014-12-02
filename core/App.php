<?php
class App {
  /** @property bool $debug */
  public static $debug = true;
  
  /**
   * Прекомпиляция исходного кода для работы
   * @return void 
   */
  public static function compile() {
    // Generate map for autoload classes
    App::generateAutoloadMap();
    App::generateURIMap();
    App::generateNginxRouteMap();
    // Some more staff may be in future
    // ...
  }


  /**
   * Генерация карты для автолоада классов
   * @return void 
   */
  protected static function generateAutoloadMap() {
    $map = [];
    $app_dir = getenv('APP_DIR');
    $files = explode(PHP_EOL, trim(`cd $app_dir && find -L lib models plugins services | grep \.php$`));

    foreach($files as $file) {
      $class = substr(basename($file), 0, -4);
      $file = "$app_dir/$file";
      
      $content = file_get_contents($file);
      if (preg_match("/(class|interface|trait) +$class/", $content)) {
        if (preg_match("/\n *namespace +\\\\?([a-zA-Z0-9_\\\\]+)/", $content, $matches)) {
            $class = $matches[1] . '\\' . $class;
        }
        $map[$class] = $file;
      }
    }
    file_put_contents(config('common.autoload_map_file'), json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }

  /**
   * Генерация карты для обработки входящих запросов по URI
   */
  protected static function generateURIMap() {
    $map = [];
    $action_dir = getenv('APP_DIR') . '/actions';
    $files = explode(PHP_EOL, trim(`cd $action_dir && find -L | grep \.php$`));
    foreach ($files as $file) {
      $file = "$action_dir/$file";
      $action = substr(basename($file), 0, -4);
      $content = file_get_contents($file);
      if (preg_match_all('/^##([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, $action);
          $map[$pattern] = $params;
        }
      }
    }

    file_put_contents(config('common.uri_map_file'), json_encode($map, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
  }

  protected static function generateNginxRouteMap() {
    $routes = json_decode(file_get_contents(config('common.uri_map_file')), true);
    uasort($routes, function ($a, $b) {
      return (sizeof($a) > sizeof($b)) ? 1 : -1;
    });

    $rewrites = [];
    foreach ($routes as $route => $action) {
      $uri = '/?route=' . array_shift($action);
      if ($action) {
        foreach ($action as $k => $v) {
          $uri .= '&' . $v . '=$' . ($k + 1);
        }
      }
      $rewrites[] = "rewrite '^/$route/?$' '$uri';";
    }
    // @TODO fix configs prepares
    file_put_contents(getenv('RUN_DIR'). '/nginx-route-map.conf', implode(PHP_EOL, $rewrites));
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

    // Timezone
    date_default_timezone_set('Europe/Moscow');

    // Autoloading models
    spl_autoload_register([static::class, 'handleAutoload']);

    // Error handler
    set_error_handler([static::class, 'handleError'], E_ALL);

    ini_set('display_errors', static::$debug ? 'on' : 'off');

    // Ini settings
    if (static::$debug) {
      ini_set('assert.active', 1);
      ini_set('assert.bail', 0);
      ini_set('assert.callback', static::class .'::handleAssertion');
      ini_set('xdebug.profiler_enable', 1);
      ini_set('xdebug.profiler_append', 1);
      ini_set('xdebug.profiler_output_dir', getenv('LOG_DIR'));
      ini_set('xdebug.profiler_output_name', 'xdebug');
    }
    
    ini_set('apc.enabled', !static::$debug);

    // Получаем шарды для кэша
    // @todo add shards for memcache
  }

  /**
   * Завершение исполнени приложени
   */
  public static function stop() {
    // Todo some work here
  }
  
  /**
   * Замена стандартного обработчика ошибок на эксепшены
   */
  public static function handleError($errno, $errstr, $errfile, $errline, $errcontext) {
    return static::error($errstr);
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
      if (!file_exists($map_file = config('common.autoload_map_file'))) {
        throw new Exception('Cant find autoload map file. Run App::compile action first and try again');
      }
      $map = json_decode(file_get_contents($map_file), true);
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
}