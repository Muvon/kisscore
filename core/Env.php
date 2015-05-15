<?php
class Env {
  protected static $params = [
    'USER',
    'PROJECT',
    'PROJECT_DIR',
    'PROJECT_ENV',
    'APP_DIR',
    'HTML_DIR',
    'CONFIG_DIR',
    'ENV_DIR',
    'BIN_DIR',
    'RUN_DIR',
    'LOG_DIR',
    'VAR_DIR',
    'TMP_DIR',
    'KISS_CORE',
    'HTTP_HOST',
  ];

  /**
   * Initialization of Application
   *
   * @return void
   */
  public static function init() {
    static::configure(getenv('APP_DIR') . '/config.ini.tpl');
    static::compileConfig();
    static::generateConfigs();
    static::generateURIMap();
    static::generateParamMap();
    static::generateNginxRouteMap();
    static::generateTriggerMap();
  }

  /**
   * Configure all config tempaltes in dir $template or special $template file
   * 
   * @param string $template
   * @param array $params
   * @return void
   */
  public static function configure($template, array $params = []) {
    // Add default params
    foreach (static::$params as $param) {
      $params['%' . $param . '%'] = getenv($param);
    }

    // Add extra params
    $params += [
      '%DEBUG%' => (int) App::$debug,
    ];

    foreach(is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
      file_put_contents(getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'), strtr(file_get_contents($file), $params));
    }
  }

  /**
   * Compile config.json into fast php array to include it ready to use optimized config
   */
  protected static function compileConfig() {
    $env = getenv('PROJECT_ENV');

    // Prepare production config replacement
    foreach (parse_ini_file(getenv('CONFIG_DIR') . '/config.ini', true) as $group => $block) {
      if (false !== strpos($group, ':') && explode(':', $group)[1] === $env) {
        $origin = strtok($group, ':');
        $config[$origin] = array_merge($config[$origin], $block);
        $group = $origin;
      } else {
        $config[$group] = $block;
      }

      // Make dot.notation for group access
      foreach ($config[$group] as $key => &$val) {
        $config[$group . '.' . $key] = &$val;
      }
    }

    // Iterate to make dot.notation.direct.access
    $Iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
    foreach ($Iterator as $leaf_value) {
      $keys = [];
      foreach (range(0, $Iterator->getDepth()) as $depth) {
        $keys[] = $Iterator->getSubIterator($depth)->key();
      }
      $config[join('.', $keys)] = $leaf_value;
    }

    file_put_contents(getenv('CONFIG_DIR') . '/config.php', '<?php return ' . var_export($config, true) . ';');
  }

  /**
   * Generate all configs for configurable plugins. It includes all plugin/_/configure.php files
   * @return void
   */
  protected static function generateConfigs() {
    $configure = function ($file) {
      return include $file;
    };

    foreach (glob(getenv('APP_DIR') . '/plugin/*/configure.php') as $file) {
      $configure($file);
    }
  }

  /**
   * Generate nginx URI map for route request to special file
   */
  protected static function generateURIMap() {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@route\s+([^\:]+?)(\:(.+))?$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          $params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
          array_unshift($params, basename($file, '.php'));
          $map[$pattern] = $params;
        }
      }
    }
    App::writeJSON(config('common.uri_map_file'), $map);
  }

  /**
   * Generate parameters map from annotations in actions and triggers files
   */
  protected static function generateParamMap() {
    $map_files = [
      'actions'  => config('common.param_map_file'),
      'triggers' => config('common.trigger_param_file'),
    ];
    foreach ($map_files as $folder => $map_file) {
      $map = [];
      foreach (static::getPHPFiles(getenv('APP_DIR') . '/' . $folder) as $file) {
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
      App::writeJSON($map_file, $map);
    }
  }

  /**
   * Generate rewrite rules for nginx to route requests to action files
   */
  protected static function generateNginxRouteMap() {
    $routes = App::getJSON(config('common.uri_map_file'));
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
    file_put_contents(config('common.nginx_route_file'), implode(PHP_EOL, $rewrites));
  }

  /**
   * Generate trigger map to be called on some event
   */
  protected static function generateTriggerMap() {
    $map = [];
    foreach (static::getPHPFiles(getenv('APP_DIR') . '/triggers') as $file) {
      $content = file_get_contents($file);
      if (preg_match_all('/^\s*\*\s*\@event\s+([^\$]+?)$/ium', $content, $m)) {
        foreach ($m[0] as $k => $matches) {
          $pattern = trim($m[1][$k]);
          if (!isset($map[$pattern])) {
            $map[$pattern] = [];
          }
          $map[$pattern] = array_merge($map[$pattern], [$file]);
        }
      }
    }
    App::writeJSON(config('common.trigger_map_file'), $map);
  }

  /**
   * Helper for getting list of all php files in dir
   * @param string $dir
   * @return array
   */
  protected static function getPHPFiles($dir) {
    return ($res = trim(`find -L $dir -name '*.php'`)) ? explode(PHP_EOL, $res) : [];
  }
}