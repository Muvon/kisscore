<?php declare(strict_types=1);

/**
 * Get config value by dot-notation key
 * @param string $param
 * @return mixed
 */
function config(string $param): mixed {
	/** @var array<string, mixed> $config */
	static $config = [];
	if ($config === []) {
		$config = include getenv('CONFIG_DIR') . '/config.php';
	}

	return $config[$param];
}

/**
 * Cast variable to specified type
 * @param mixed $var
 * @param string $type int|uint|float|ufloat|bool|array|string
 * @return mixed
 */
function typify(mixed $var, string $type): mixed {
	/** @var scalar|null $s */
	$s = $var;
	switch ($type) {
		case 'int':
		case 'integer':
			$var = (int)$s;
			break;
		case 'uinteger':
		case 'uint':
			$var = (int)$s;
			if ($var < 0) {
				$var = 0;
			}
			break;
		case 'double':
		case 'float':
			$var = (float)$s;
			break;
		case 'udouble':
		case 'ufloat':
			$var = (float)$s;
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
			$var = (string)$s;
			break;
		default: // Do nothing here
			break;
	}

	return $var;
}

/**
 * Triggered events
 * @param string $event
 * @param array<string, mixed> $payload Additional data for event handlers
 * @return void
 */
function trigger_event(string $event, array $payload = []): void {
	static $map;
	if (!isset($map)) {
		/** @var string $trigger_map_file */
		$trigger_map_file = config('common.trigger_map_file');
		$map = Env::load($trigger_map_file);
	}

	if (!isset($map[$event])) {
		return;
	}

	/** @var string $trigger_param_file */
	$trigger_param_file = config('common.trigger_param_file');
	array_walk(
		$map[$event], function (string $file) use ($payload, $trigger_param_file) {
			extract(
				Input::extractTypified(
					App::getImportVarsArgs($file, $trigger_param_file),
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
 * Simple dependency container — set once, get many
 *
 * @param string $name
 * @param mixed $value If provided, sets the value; if null, retrieves it
 * @return mixed
 */
function container(string $name, mixed $value = null): mixed {
	/** @var array<string, mixed> $container */
	static $container = [];

	if (isset($value)) {
		$container[$name] = $value;
		return $value;
	}

	if (!isset($container[$name])) {
		throw new Error("Container key '$name' not found");
	}

	$res = &$container[$name];
	// Only Closures are resolved lazily-and-memoized. Using is_callable() here
	// would invoke any stored string/array that happens to name a function
	// (e.g. 'time', [$obj, 'method']) — a footgun for plain stored values.
	if ($res instanceof Closure) {
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
	/** @var class-string $class */
	return (new ReflectionClass($class))->getShortName();
}

// Missed functions for large integers for BCmath
/**
 * @param string $hex
 * @return string
 */
function bchexdec(string $hex): string {
	$dec = '0';
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
 * @return array<string, mixed>
 */
function as_array(object $obj): array {
	return (array)$obj;
}

/**
 * Get ref to the value in the array by list of keys or dot notation
 * @param array<mixed> $container
 * @param array<string|int>|string $keys
 * @return mixed
 */
function &array_value_ref(array &$container, array|string $keys): mixed {
	if (is_string($keys)) {
		$keys = explode('.', $keys);
	}
	$reference = &$container;
	$len = sizeof($keys);
	for ($i = 0; $i < $len; $i++) {
		$key = $keys[$i];
		if (!isset($reference[$key]) && $i < $len) {
			$reference[$key] = [];
		}
		$reference = &$reference[$key];
	}
	return $reference;
}
/**
 * @param array<string, array<mixed>> $arrays
 * @return array<int, array<string, mixed>>
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

/**
 * Sort multi-dimensional array by one or more columns
 * Usage: array_order_by($data, 'created_at', SORT_DESC, SORT_NUMERIC)
 *
 * @param array<mixed> $data
 * @param mixed ...$args Column name followed by SORT_* flags
 * @return array<mixed>
 */
function array_order_by(array $data, mixed ...$args): array {
	foreach ($args as $n => $field) {
		if (!is_string($field)) {
			continue;
		}

		$tmp = [];
		foreach ($data as $key => $row) {
			/** @var array<string, mixed> $row */
			$tmp[$key] = $row[$field];
		}
		$args[$n] = $tmp;
	}
	/** @var array<array<mixed>|int> $sort_params */
	$sort_params = $args;
	$sort_params[] = &$data;
	/** @var array<mixed> $first_arg */
	$first_arg = $sort_params[0];
	array_multisort($first_arg, ...array_slice($sort_params, 1));
	return $data;
}

// Helpers for Result class
/**
 * Shortcut for `Result::ok()`. The template parameter is inferred from `$res`
 * so callers get a precisely-typed `Result<T>` back (e.g. `ok($user)` produces
 * `Result<User>` rather than `Result<mixed>`).
 *
 * @template T
 * @param T $res
 * @return Result<T>
 */
function ok(mixed $res = null): Result {
	return Result::ok($res);
}


/**
 * Shortcut for `Result::err()`. Returns `Result<never>` because a failed result
 * never resolves a success value — and `never` is the bottom type, so the
 * failure flows into any `Result<X>` slot at the call site (with the `Result`
 * class declared `@template-covariant T`). This lets methods declare narrow
 * success types like `Result<User>` and still `return err('e_…')` without
 * having to widen every signature to `Result<mixed>`.
 *
 * @return Result<never>
 */
function err(string $err, mixed $res = null): Result {
	return Result::err($err, $res);
}

/**
 * Multiple errors creation for single response. Returns `Result<never>` for
 * the same reason as `err()` — failure carries no success value, so the
 * bottom-type return composes into any caller's `Result<X>` declaration.
 *
 * @param array<string> $errs
 * @return Result<never>
 */
function err_list(array $errs): Result {
	return Result::err('e_error_list', $errs);
}

if (!function_exists('defer')) {
	/**
	 * @param ?SplStack<object> $ctx
	 * @param callable $cb
	 * @return void
	 */
	function defer(?SplStack &$ctx, callable $cb): void {
		$ctx = $ctx ?? new SplStack();

		$ctx->push(
			new class($cb) {
				/** @var callable */
				protected $cb;
				public function __construct(callable $cb) {
					$this->cb = $cb;
				}

				public function __destruct() {
					($this->cb)();
				}
			}
		);
	}
}
