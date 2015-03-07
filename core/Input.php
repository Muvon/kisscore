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
    } else {
      static::$params = (array) filter_input_array(INPUT_POST) + (array) filter_input_array(INPUT_GET);
    }

    static::$is_parsed = true;
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

    // Exctract typifie var by mnemonic rules as array
    if (is_array($args[0])) {
      $params = [];
      foreach ($args[0] as $arg) {
        preg_match('#^([a-z0-9_]+)(?:\:([a-z]+))?(?:\=(.+))?$#', $arg, $m);
        $params[$m[1]]  = static::get($m[1], isset($m[3]) ? $m[3] : '');

        // Нужно ли типизировать
        if (isset($m[2])) {
          static::typify($params[$m[1]], $m[2]);
        }
      }
      return $params;
    }

    trigger_error('Error while fetch key from input');
  }

  /**
   * Функция типизации переменных
   *
   * @package Core
   * @param string $var
   * @param string $type [int|integer, uint|uinteger, float, ufloat, bool, array, string]
   * @return null Типизация происходит по ссылке
   *
   * <code>
   * $var = '1'; // string(1) "1"
   * typify($var, $type);
   * var_dump($var); // int(1)
   * </code>
   */
  public static function typify(&$var, $type) {
    switch ($type) {
      case 'int':
      case 'integer':
        $var = (int) $var;
        break;
      case 'uinteger':
      case 'uint':
        $var = (int) $var;
        if ($var < 0)
          $var = 0;
        break;
      case 'double':
      case 'float':
        $var = (float) $var;
        break;
      case 'udouble':
      case 'ufloat':
        $var = (float) $var;
        if ($var < 0)
          $var = 0.0;
        break;
      case 'boolean':
      case 'bool':
        $var = (in_array($var, ['no', 'none', 'false', 'off'], true) ? false : (bool) $var);
        break;
      case 'array':
        $var = $var ? (array) $var : [];
        break;
      case 'string':
        $var = (string) $var;
        break;
    }
    return;
  }
}
