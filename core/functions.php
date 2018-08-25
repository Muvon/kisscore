<?php
/**
 * Config workout for whole app
 * @param  string $param Param using dot for separate packages
 * @return mixed
 */
function config($param) {
  assert(is_string($param));

  static $config = [];
  if (!$config) {
    $config = include getenv('CONFIG_DIR') . '/config.php';
  }

  return $config[$param];
}

/**
 * Typify var to special type
 * @package Core
 * @param string $var Reference to the var that should be typified
 * @param string $type [int|integer, uint|uinteger, double|float, udboule|ufloat, bool|boolean, array, string]
 * @return void
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
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return mixed
 */
function trigger_event($event, array $payload = []) {
  assert(is_string($event));

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

/**
 * Get short name for full qualified class name
 * @param string $class The name of class with namespaces
 * @return string
 */
function get_class_name($class) {
  return (new ReflectionClass($class))->getShortName();
}

// Missed functions for large integers for BCmath
function bchexdec($hex) {
  $dec = 0;
  $len = strlen($hex);
  for ($i = 1; $i <= $len; $i++) {
    $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
  }
  return $dec;
}

function bcdechex($dec) {
  $hex = '';
  do {
    $last = bcmod($dec, 16);
    $hex = dechex($last).$hex;
    $dec = bcdiv(bcsub($dec, $last), 16);
  } while($dec > 0);
  return $hex;
}

function bench($level = 0, $txt = null) {
  static $t = [], $r = [];
  if ($level === true) {
    foreach ($r as $txt => $vals) {
      echo $txt . ': ' . sprintf('%f', array_sum($vals) / sizeof($vals)) . 's' . PHP_EOL;
    }
    $t = $r = [];
    return;
  }
  $n = microtime(true);

  if ($txt && !isset($r[$txt])) {
    $r[$txt] = [];
  }

  if ($txt && isset($t[$level])) {
    $r[$txt][] = $n - $t[$level][sizeof($t[$level]) - 1];
  }
  $t[$level][] = $n;
}
