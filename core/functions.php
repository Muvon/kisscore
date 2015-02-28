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
      $config[$key] = $item;
      foreach ($item as $key2 => $item2) {
        $config[$key . '.' . $key2] = $item2;
      }
    }
  }
  return $config[$param];
}