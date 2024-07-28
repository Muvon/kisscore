<?php declare(strict_types=1);

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
 * @param mixed $var Reference to the var that should be typified
 * @param string $type [int|integer, uint|uinteger, double|float, udboule|ufloat, bool|boolean, array, string]
 * @return mixed
 *
 * <code>
 * $var = '1'; // string(1) "1"
 * $var = typify($var, $type);
 * var_dump($var); // int(1)
 * </code>
 */
function typify(mixed $var, string $type): mixed {
	switch ($type) {
		case 'int':
		case 'integer':
			$var = (int)$var;
			break;
		case 'uinteger':
		case 'uint':
			$var = (int)$var;
			if ($var < 0) {
				$var = 0;
			}
			break;
		case 'double':
		case 'float':
			$var = (float)$var;
			break;
		case 'udouble':
		case 'ufloat':
			$var = (float)$var;
			if ($var < 0) {
				$var = 0.0;
			}
			break;
		case 'boolean':
		case 'bool':
			$var = (in_array($var, ['no', 'none', 'false', 'off'], true) ? false : (bool)$var);
			break;
		case 'array':
			$var = $var ? (array)$var : [];
			break;
		case 'string':
			$var = (string)$var;
			break;
		default: // Do nothing here
			break;
	}

	return $var;
}

/**
 * Triggered events
 * @param string $event
 * @param array $payload Дополнительные данные для манипуляции
 * @return void
 */
function trigger_event(string $event, array $payload = []): void {
	static $map;
	if (!isset($map)) {
		$map = Env::load(config('common.trigger_map_file'));
	}

	if (!isset($map[$event])) {
		return;
	}

	array_walk(
		$map[$event], function (string $file) use ($payload) {
			extract(
				Input::extractTypified(
					App::getImportVarsArgs($file, config('common.trigger_param_file')),
					function ($key, $default = null) use ($payload) {
						return $payload[$key] ?? $default;
					}
				)
			);
			include $file;
		}
	);
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
/**
 * @param string $hex
 * @return string
 */
function bchexdec(string $hex): string {
	$dec = 0;
	$len = strlen($hex);
	for ($i = 1; $i <= $len; $i++) {
		$dec = bcadd("$dec", bcmul((string)(hexdec($hex[$i - 1])), bcpow('16', (string)($len - $i))));
	}
	return $dec;
}

/**
 * @param string $dec
 * @return string
 */
function bcdechex(string $dec): string {
	$hex = '';
	do {
		$last = bcmod("$dec", '16');
		$hex = dechex((int)$last) . $hex;
		$dec = bcdiv(bcsub($dec, $last), '16');
	} while ($dec > 0);
	return $hex;
}


// bench("a") -> start benching labeled
// bench() -> return array with results
// bench("reset") -> reset data (reserved)
/**
 * @param ?string $txt
 * @return ?string[]
 */
function bench(?string $txt = null): ?array {
	static $t = [], $r = [];
	if ($txt === null) {
		$lines = [];
		foreach ($r as $label => $vals) {
			$lines[$label] = sprintf('%.3f', (array_sum($vals) / sizeof($vals)) * 1000) . 'ms';
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

/**
 * @param object $obj
 * @return array
 */
function as_array(object $obj): array {
	return (array)$obj;
}

/**
 * Get ref to the value in the array by list of keys or dot notation
 * @param array<mixed> $container
 * @param array<string|int>|string $keys
 * @return &mixed
 */
function &array_value_ref(array &$container, array|string $keys): mixed {
	if (is_string($keys)) {
		$keys = explode('.', $keys);
	}
	$reference = &$container;
	foreach ($keys as $key) {
		if (!isset($reference[$key])) {
			$reference[$key] = [];
		}
		$reference = &$reference[$key];
	}
	return $reference;
}
/**
 * @param array $arrays
 * @return array
 */
function array_cartesian(array $arrays): array {
	$result = [];
	$keys = array_keys($arrays);
	$reverse_keys = array_reverse($keys);
	$size = (int)(sizeof($arrays) > 0);
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
			}

			if (!isset($arrays[$j])) {
				continue;
			}

			reset($arrays[$j]);
		}
	}
	return $result;
}

// $state_map = array_order_by($state_map, 'created_at', SORT_DESC, SORT_NUMERIC/*, 'id', SORT_DESC, SORT_NUMERIC*/);
/**
 * @return array
 */
function array_order_by(): array {
	$args = func_get_args();
	$data = array_shift($args);
	foreach ($args as $n => $field) {
		if (!is_string($field)) {
			continue;
		}

		$tmp = [];
		foreach ($data as $key => $row) {
			$tmp[$key] = $row[$field];
		}
		$args[$n] = $tmp;
	}
	$args[] = &$data;
	call_user_func_array('array_multisort', $args);
	return array_pop($args);
}

// Helpers for Result class
/**
 * Shortcut for Result::ok()
 *
 * @template T
 * @param T $res
 * @return Result<T>
 */
function ok(mixed $res = null): Result {
	return Result::ok($res);
}


/**
 * Shortcut for Result::err()
 *
 * @template U
 * @param string $err
 * @param U $res
 * @return Result<never>
 */
function err(string $err, mixed $res = null): Result {
	return Result::err($err, $res);
}

/**
 * Multiple errors creation for single response
 * @template U
 * @param array<string> $errs
 * @return Result<U>
 */
function err_list(array $errs): Result {
	return Result::err('e_error_list', $errs);
}

if (!function_exists('defer')) {
	/**
	 * @param ?SplStack $ctx
	 * @param callable $cb
	 * @return void
	 */
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
}

// Filter function to format output
/**
 * @param string $v
 * @return string
 */
function view_filter_date(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('Y-m-d', $ts) : $v;
}

/**
 * @param string $v
 * @return string
 */
function view_filter_time(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('H:i', $ts) : $v;
}

/**
 * @param string $v
 * @Return string
 */
function view_filter_datetime(string $v): string {
	$ts = is_numeric($v) ? (int)$v : strtotime("$v UTC");
	return $ts ? date('Y-m-d H:i:s', $ts) : $v;
}

/**
 * @param string $v
 * @return string
 */
function view_filter_timestamp(string $v): string {
	return (string)(strtotime($v) ?: $v);
}
