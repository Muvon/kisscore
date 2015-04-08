<?php
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
    foreach (parse_ini_file(getenv('CONFIG_DIR') . '/config.ini', true) as $key => $item) {
      if (false !== strpos($key, ':') && split(':', $key)[1] === getenv('PROJECT_ENV')) {
        $origin = strtok($key, ':');
        $config[$origin] = array_merge($config[$origin], $item);
        $key = $origin;
      } else {
        $config[$key] = $item;
      }
      foreach ($config[$key] as $key2 => $item2) {
        $config[$key . '.' . $key2] = $item2;
      }
    }
  }
  return $config[$param];
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

/**
 * Triggered events
 *
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return mixed
 */
function trigger_event($event, array $payload = []) {
  assert('is_string($event)');

  static $map;
  if (!isset($map)) {
    $map = App::getJSON(config('common.trigger_map_file'));
  }

  if (isset($map[$event])) {
    array_walk($map[$event], function ($_file) use ($payload) {
      extract(
        Input::extractTypified(
          App::getImportVarsArgs($_file, config('common.trigger_param_file')),
          function ($key, $default = null) use ($payload) {
            return isset($payload[$key]) ? $payload[$key] : $default;
          }
        )
      );
      include $_file;
    });
  }
}