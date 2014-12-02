<?php
/**
 * Извлечение значений из массива по переданному ключу
 *
 * @package Core
 * @subpackage Array Functions
 * @param array $items
 * @param string $key
 * @return array
 *
 * <code>
 * $items = $Object->getItems( );
 * $ids = array_values_by_key($items, 'user_id');
 * </code>
 */
function array_values_by_key(array $items, $key) {
  $values = [];
  foreach ($items as $item) {
    if (isset($item[$key]))
      $values[] = $item[$key];
  }
  return $values;
}


/**
 * Прикрепление данных к уже имеющимуся массиву с указанным ключем
 *
 * @package Core
 * @subpackage Array Functions
 * @param array $items
 * @param array $data
 * @param string $data_key
 * @return void
 *
 * <code>
 * $items = $Object->getItems( );
 * $uids  = $User->getByIds(array_values_by_key($items, 'user_id'));
 * array_append_data($items, $uids, 'user');
 * </code>
 */
function array_append_data(array &$items, array $data, $data_key = 'data') {
  foreach ($items as $k => &$item) {
    $item[$data_key] = isset($data[$k]) ? $data[$k] : null;
  }
  return;
}

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
 * Функция для склонения сущ.
 *
 * @param int $n
 * @param array $forms
 *   Нулевая форма, используемая при отсутсвии количества
 *   Если задана нулева форма в виде строки, то именно эта строка и возвращается при нуле
 * @return string
 *
 * <code>
 * echo plural(1, ['Комментарий', 'Комментария', 'Комментариев', 'nil' => 'Комментариев нет']);
 * </code>
 */
function plural($n, array $forms = []) {
  $n = (int) $n;
  // Если нужно сразу же вернуть нулевую форму, ничего не определяя
  if (isset($forms['nil']) && !$n)
    return $forms['nil'];
  
  // Определяем форму и возвращаем результат
  if (!isset($forms[1]))
    $forms[1] = &$forms[0];

  if (!isset($forms[2]))
    $forms[2] = &$forms[1];

  return ((string) $n . ' ')
    . (($n % 10 === 1 && $n % 100 !== 11)
      ? $forms[0]
      : ($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 10 || $n % 100 >= 20)
        ? $forms[1]
        : $forms[2]
        )
      )
  ;
}

/**
 * Получение человеко-читаемой даты согласно текущей локали
 *
 * @param int | string $date
 * @param string $format
 *   Одно из значений date | time | datetime
 * @return string
 */
function hrdate($date, $format = 'datetime') {
  $ts   = is_int($date) ? $date : strtotime($date);
  $now  = $_SERVER['REQUEST_TIME'];
  $diff = $now - $ts;

  // Краткие формы только для даты со временем
  if ($format === 'datetime') {
    // Только что?
    if ($diff <= 60)
      return 'только что';
      
    // Сколько минут назад
    if ($diff < 3600) {
      $mago = floor($diff / 60);
      return plural($mago, ['минуту', 'минуты', 'минут']) . ' назад';
    }
    // Сколько часов назад
    if ($diff < 86400) {
      $hago = floor($diff / 3600);
      return plural($hago, ['час', 'часа', 'часов']) . ' назад';
    }
  }

  // Сколько дней назад
  $d = date('d', $ts);
  // Общая схема
  if (date('d') === $d) {
    $p = 'сегодня';
  } else if (date('d', strtotime('yesterday')) === $d) {
    $p = 'вчера';
  } else if ($diff < 604800) { // 7 days
    $dago = floor($diff / 86400);
    $p = plural($dago, ['день', 'дня', 'дней']) . ' назад';
  } else {
    $p = '%e %b' . (/* year? */date('Y') !== date('Y', $ts) ? ' %Y' : '');
  }

  if ($format === 'datetime')
    $p .= ' в %H:%M';

  if ($format === 'time')
    $p = '%H:%M:%S';

  return strtolower(trim(strftime($p, $ts)));
}

define('ALPHA_CHARS', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ');

/**
 * Кодирование числа по алфавиту
 *
 * @param int $val
 * @param string $alpha
 * @return string
 */
function alpha_encode($val, $alpha = ALPHA_CHARS) {
  if ($val > PHP_INT_MAX)
    return false;

  $base = strlen($alpha);
  $str  = '';
  do {
    $i   = $val % $base;
    $str = $alpha[$i] . $str;
    $val = ($val - $i) / $base;
  } while ($val > 0);
  return $str;
}

/**
 * Декодирование числа
 *
 * @param string $val
 * @param string $alpha
 * @return int
 */
function alpha_decode($val, $alpha = ALPHA_CHARS) {
  $base = strlen($alpha);
  $len = strlen($val);
  $num = 0;
  $arr = array_flip(str_split($alpha));
  for($i = 0; $i < $len; ++$i) {
    $num += $arr[$val[$i]] * pow($base, $len-$i-1);
  }
  return $num;
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


/**
 * Выполнение запроса к базе данных, выполняет коннект на запросе
 * 
 * @param string $query
 * @param array $params
 * @param int $shard_id
 * @throws Exception
 */
function db($query, array $params = [], $shard_id = 0) {
  return db_mysqli($query, $params, $shard_id);
}

/**
 * Выполнение запроса к базе данных, выполняет коннект на запросе
 * 
 * @param string $query
 * @param array $params
 * @param int $shard_id
 * @throws Exception
 */
function db_mysqli($query, array $params = [], $shard_id = 0) {
  assert("is_string(\$query)");
  assert("\is_array(\$params)");
  assert("is_int(\$shard_id)");
  assert("\$shard_id >= 0 && \$shard_id < 4096 /* only 4096 shards allowed */");
  
  static $time = 0;
  static $pool = [];
  static $shards = [];

  if (!$shards) {
    $shards = config('mysql.shard');
  }

  if (!$pool) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  }

  if (!isset($shards[$shard_id])) {
    trigger_error('No shards for mysql server specified');
  }

  // Если на этом шарде еще коннетка нет
  if (!isset($pool[$shard_id])) {
    $dsn = &$shards[$shard_id];
    $dsn_key = function ($key) use($dsn) {
      preg_match("|$key=([^;]+)|", $dsn, $m);
      return $m ? $m[1] : null;
    };

    $DB = &$pool[$shard_id];

    $DB = new mysqli($dsn_key('host'), $dsn_key('user'), $dsn_key('password'), $dsn_key('dbname'), $dsn_key('port'));
  }

  $DB = &$pool[$shard_id];
  if (range(0, sizeof($params) - 1) === array_keys($params)) {
    $query = preg_replace_callback('|\?|', function () { static $count = 0; return ':' . $count++; }, $query);
  }

  $params = array_combine(
    array_map(function ($k) { return ':' . $k; }, array_keys($params)),
    array_map(
      function ($item) use ($DB) { 
        return filter_var($item, FILTER_VALIDATE_INT | FILTER_VALIDATE_FLOAT | FILTER_VALIDATE_BOOLEAN) ? $item : '"' . $DB->real_escape_string($item) . '"';
      },
      $params
    )
  );
  $Result = $DB->query(strtr($query, $params));

  // Определяем результат работы функции в зависимости от типа запроса к базе
  switch (strtolower(strtok($query, ' '))) {
    case 'insert':
      return $DB->insert_id;
      break;

    case 'update':
    case 'delete':
      return $DB->affected_rows;
      break;

    case 'select':
    case 'describe':
      $result = $Result->fetch_all(MYSQLI_ASSOC);
      $Result->close();
      return $result;
      break;

    default:
      trigger_error('Undefined call for database query');
  }
}

/**
 * Выполнение запроса к базе данных, выполняет коннект на запросе
 * 
 * @param string $query
 * @param array $params
 * @param int $shard_id
 * @throws Exception
 */
function db_pdo($query, array $params = [], $shard_id = 0) {
  assert("is_string(\$query)");
  assert("\is_array(\$params)");
  assert("is_int(\$shard_id)");
  assert("\$shard_id >= 0 && \$shard_id < 4096 /* only 4096 shards allowed */");
  
  static $pool = [];
  static $shards = [];

  if (!$shards) {
    $shards = config('mysql.shard');
  }

  if (!$pool) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
  }

  if (!isset($shards[$shard_id])) {
    trigger_error('No shards for mysql server specified');
  }

  // Если на этом шарде еще коннетка нет
  if (!isset($pool[$shard_id])) {
    $dsn = &$shards[$shard_id];
    $dsn_key = function ($key) use($dsn) {
      preg_match("|$key=([^;]+)|", $dsn, $m);
      return $m ? $m[1] : null;
    };

    $DB = &$pool[$shard_id];

    $DB = new mysqli($dsn_key('host'), $dsn_key('user'), $dsn_key('password'), $dsn_key('dbname'), $dsn_key('port'));
  }

  $DB = &$pool[$shard_id];
  if (range(0, sizeof($params) - 1) === array_keys($params)) {
    $query = preg_replace_callback('|\?|', function () { static $count = 0; return ':' . $count++; }, $query);
  }

  $params = array_combine(
    array_map(function ($k) { return ':' . $k; }, array_keys($params)),
    array_map(
      function ($item) use ($DB) { 
        return filter_var($item, FILTER_VALIDATE_INT | FILTER_VALIDATE_FLOAT | FILTER_VALIDATE_BOOLEAN) ? $item : '"' . $DB->real_escape_string($item) . '"';
      },
      $params
    )
  );
  $Result = $DB->query(strtr($query, $params));

  // Определяем результат работы функции в зависимости от типа запроса к базе
  switch (strtolower(strtok($query, ' '))) {
    case 'insert':
      return $DB->insert_id;
      break;

    case 'update':
    case 'delete':
      return $DB->affected_rows;
      break;

    case 'select':
    case 'describe':
      $result = $Result->fetch_all(MYSQLI_ASSOC);
      $Result->close();
      return $result;
      break;

    default:
      trigger_error('Undefined call for database query');
  }
}