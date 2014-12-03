<?php
/**
 * Функция типизации переменных
 *
 * @package Core
 * @subpackageType Functions
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
function typify(&$var, $type) {
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

//
// Вспомогательные функции
//


/**
 * Выполнение http-редиректа внутри сайта или на внешний сайт
 *
 * @package Core
 * @subpackage Help Functions
 *
 * @param string $to
 * @param int $code
 * @return void
*/
function redirect($to, $code = 302) {
  if ($to[0] === '/')
    $to = 'http://' . getenv('HTTP_HOST') . $to;

  Request::response()->redirect($to, $code);
  return;
}

/**
 * Импорт переменных в глобальное пространство экшена
 *
 * @package Core
 * @subpackage Help Functions
 *
 * @see Request::getParams()
 *
 * @return void
 *
 * <code>
 * import_vars('var1', 'var2', 'var3');
 * var_dump($var1);
 * </code>
 *
 * <code>
 * import_vars('form', ['id', 'name']);
 * var_dump($form); // array [id, name]
 * </code>
 */
function import_vars( ) {
  $args = func_get_args( );
  // Если импорт в одну переменную
  if (isset($args[1]) && is_array($args[1])) {
    $GLOBALS[$args[0]] = Request::instance()
      ->getParams($args[1])
    ;
  } else { // или в глобальную видимость
    $GLOBALS += Request::instance( )
      ->getParams($args)
    ;
  }
  return;
}

/**
 * Функция для получения конфигурационных параметров из файла
 * @param  string $param Параметр в виде раздел.параметр 
 * @return mixed        
 */
function config($param) {
  assert('is_string($param)');
  static $config = [];

  // Создаем специальный массив для доступа к элементам через namespace.param
  if (!$config) {
    foreach (parse_ini_file(getenv('CONFIG_DIR') . '/app.ini', true) as $key => $item) {
      if (is_array($item)) {
        $config[$key] = $item;
        foreach ($item as $key2 => $item2) {
          $config[$key . '.' . $key2] = $item2;
        }
      } else {
        $config[$key] = $item;
      }
    }
  }
  return $config[$param];
}