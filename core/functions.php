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
      $config[$key] = $item;
      foreach ($item as $key2 => $item2) {
        $config[$key . '.' . $key2] = $item2;
      }
    }
  }
  return $config[$param];
}
