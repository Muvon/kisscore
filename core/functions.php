<?php
/**
 * Config workout for whole app
 * @param  string $param Param using dot for separate packages
 * @return mixed
 */
function config(string $param): mixed {
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
function typify(&$var, string $type): void {
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
}

/**
 * Triggered events
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return mixed
 */
function trigger_event(string $event, array $payload = []): mixed {
  static $map;
  if (!isset($map)) {
    $map = Env::load(config('common.trigger_map_file'));
  }

  if (isset($map[$event])) {
    array_walk($map[$event], function ($_file) use ($payload) {
      extract(
        Input::extractTypified(
          App::getImportVarsArgs($_file, config('common.trigger_param_file')),
          function ($key, $default = null) use ($payload) {
            return $payload[$key] ?? $default;
          }
        )
      );
      include $_file;
    });
  }
}
/**
 * This is helper function to control dependencies in one container
 *
 * @param string $name
 * @param mixed $value if not set we do get container if set we do set container value
 * @return mixed
 */
function container(string $name, mixed $value = null): mixed {
  static $container = [];

  // Set container logic
  if (isset($value)) {
    assert(!isset($container[$name]));
    $container[$name] = $value;
    return $value;
  }

  // Get container logic
  assert(isset($container[$name]));
  $res = &$container[$name];
  if (is_callable($res)) {
    $res = $res();
  }
  return $res;
}

/**
 * Get short name for full qualified class name
 * @param string $class The name of class with namespaces
 * @return string
 */
function get_class_name(string $class): string {
  return (new ReflectionClass($class))->getShortName();
}

// Missed functions for large integers for BCmath
function bchexdec(string $hex): string {
  $dec = 0;
  $len = strlen($hex);
  for ($i = 1; $i <= $len; $i++) {
    $dec = bcadd($dec, bcmul(strval(hexdec($hex[$i - 1])), bcpow('16', strval($len - $i))));
  }
  return $dec;
}

function bcdechex(string $dec): string {
  $hex = '';
  do {
    $last = bcmod($dec, 16);
    $hex = dechex($last).$hex;
    $dec = bcdiv(bcsub($dec, $last), 16);
  } while($dec > 0);
  return $hex;
}


// bench("a") -> start benching labeled
// bench() -> return array with results
// bench("reset") -> reset data (reserved)
function bench(?string $txt = null): ?array {
  static $t = [], $r = [];
  if ($txt === null) {
    $lines = [];
    foreach ($r as $txt => $vals) {
      $lines[$txt] = sprintf('%.3f', (array_sum($vals) / sizeof($vals)) * 1000) . 'ms';
    }
    $t = $r = [];
    return $lines;
  }

  if ($txt === 'reset') {
    $t = $r = [];
    return null;
  }
  $n = microtime(true);

  if ($txt && !isset($r[$txt])) {
    $r[$txt] = [];
  }

  if ($txt && isset($t[$txt])) {
    $r[$txt][] = $n - $t[$txt][array_key_last($t[$txt])];
  }
  $t[$txt][] = $n;

  return null;
}

function array_cartesian(array $arrays): array {
  $result = [];
  $keys = array_keys($arrays);
  $reverse_keys = array_reverse($keys);
  $size = intval(sizeof($arrays) > 0);
  foreach ($arrays as $array) {
    $size *= sizeof($array);
  }
  for ($i = 0; $i < $size; $i ++) {
    $result[$i] = [];
    foreach ($keys as $j) {
      $result[$i][$j] = current($arrays[$j]);
    }
    foreach ($reverse_keys as $j) {
      if (next($arrays[$j])) {
        break;
      } elseif (isset ($arrays[$j])) {
        reset($arrays[$j]);
      }
    }
  }
  return $result;
}

/**
 * This is simple helper in case we need to throw exception when has error
 *
 * @param $response
 *   Stanadrd array in presentation [err, result]
 *   Where err should be string and result mixed
 */
function result(array $response, string $error = 'result'): mixed {
  if (isset($response[0]) && is_array($response[0])) {
    if ($errors = array_filter(array_column($response, 0))) {
      throw new Error('Errors while ' . $error . ' in multiple result: ' . var_export($errors, true));
    }
    return array_column($response, 1);
  } else {
    [$err, $result] = $response;
    if ($err) {
      throw new Error('Error while ' . $error . ': ' . $err . '. Got result: ' . var_export($result, true));
    }
    return $result;
  }
}

function defer(?SplStack &$ctx, callable $cb): void {
  $ctx = $ctx ?? new SplStack();

  $ctx->push(
    new class($cb) {
      protected $cb;
      public function __construct(callable $cb) {
        $this->cb = $cb;
      }

      public function __destruct() {
        \call_user_func($this->cb);
      }
    }
  );
}

// Filter function to format output
function view_filter_date(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('Y-m-d', $ts) : $v;
}

function view_filter_time(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('H:i', $ts) : $v;
}

function view_filter_datetime(string $v): string {
  $ts = is_numeric($v) ? intval($v) : strtotime("$v UTC");
  return $ts ? date('Y-m-d H:i:s', $ts) : $v;
}

function view_filter_timestamp(string $v): string {
  return strval(strtotime(intval($v)) ?: $v);
}
