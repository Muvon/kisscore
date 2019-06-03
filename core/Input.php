<?php
class Input {
  public static $is_parsed = false;
  public static $params = [];

  /**
   * Парсит и сохраняет все параметры в переменной self::$params
   *
   * @access protected
   * @return $this
   */
  protected static function parse() {
    if (filter_input(INPUT_SERVER, 'argc')) {
      $argv = filter_input(INPUT_SERVER, 'argv');
      array_shift($argv); // file
      static::$params['ACTION'] = array_shift($argv);
      static::$params += $argv;
    } elseif ((0 === strpos(filter_input(INPUT_SERVER, 'CONTENT_TYPE'), 'application/json'))) {
      static::$params = (array) filter_input_array(INPUT_GET) + (array) json_decode(file_get_contents('php://input'), true);
    } else {
      static::$params = (array) filter_input_array(INPUT_POST) + (array) filter_input_array(INPUT_GET);
    }

    static::$is_parsed = true;
  }

  public static function set(string $key, $value) {
    static::$is_parsed || static::parse();
    static::$params[$key] = $value;
  }

  /**
   * Получение переменной запроса
   *
   * <code>
   * $test = Input::get('test');
   *
   * $params = Input::get(['test:int=1']);
   * </code>
   */
  public static function get(...$args) {
    static::$is_parsed || static::parse();

    if (!isset($args[0])) {
      return static::$params;
    }

    // String key?
    if (is_string($args[0])) {
      return isset(static::$params[$args[0]])
        ? static::$params[$args[0]]
        : (isset($args[1]) ? $args[1] : null);
    }

    if (is_array($args[0])) {
      return static::extractTypified($args[0], function ($key, $default = null) {
        return static::get($key, $default);
      });
    }
    // Exctract typifie var by mnemonic rules as array


    trigger_error('Error while fetch key from input');
  }

  /**
   * Извлекает и типизирует параметры из массива args с помощью функции $fetcher, которая
   * принимает на вход ключ из массива args и значение по умолчанию, если его там нет
   *
   * @param array $args
   * @param Closure $fetcher ($key, $default)
   */
  public static function extractTypified(array $args, Closure $fetcher) {
    $params = [];
    foreach ($args as $arg) {
      preg_match('#^([a-zA-Z0-9_]+)(?:\:([a-z]+))?(?:\=(.+))?$#', $arg, $m);
      $params[$m[1]]  = $fetcher($m[1], isset($m[3]) ? $m[3] : '');

      // Нужно ли типизировать
      if (isset($m[2])) {
        typify($params[$m[1]], $m[2]);
      }
    }
    return $params;
  }
}
