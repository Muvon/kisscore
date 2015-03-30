<?php


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
