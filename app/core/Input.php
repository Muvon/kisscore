<?php declare(strict_types=1);

/**
 * Request input handling.
 *
 * Parsed parameters and the parser closure are coroutine-local (see Coro), so
 * concurrent requests sharing a worker never read each other's input.
 */
final class Input {
	/**
	 * Per-coroutine input state.
	 * @return array{parsed:bool,params:array<string,mixed>,parser:?Closure}
	 */
	private static function &state(): array {
		/** @var array{parsed:bool,params:array<string,mixed>,parser:?Closure} $s */
		$s = &Coro::bag(
			'input',
			static fn(): array => ['parsed' => false, 'params' => [], 'parser' => null]
		);
		return $s;
	}

	/**
	 * @return bool
	 */
	public static function isCli(): bool {
		return PHP_SAPI === 'cli';
	}

	/**
	 * @return bool
	 */
	public static function isJson(): bool {
		return str_starts_with(Request::$content_type, 'application/json');
	}

	/**
	 * @return bool
	 */
	public static function isMsgpack(): bool {
		return str_starts_with(Request::$content_type, 'application/msgpack');
	}

	/**
	 * @return bool
	 */
	public static function isRaw(): bool {
		return Request::$request_uri !== '' && !static::isJson();
	}

	/**
	 * Parse and store all request parameters for the current coroutine.
	 * @return void
	 */
	protected static function parse(): void {
		$s = &static::state();
		if ($s['parsed']) {
			return;
		}

		$parser = $s['parser'] ?? static function (): array {
			if (!static::isCli()) {
				// Swoole mode: parser must be set via setParser() before parse.
				return [];
			}

			global $argv;
			$args = $argv ?? [];
			array_shift($args); // strip script name
			$action = array_shift($args);
			// Keep positional args AND expose the action under ACTION. The action
			// is the first CLI argument; remaining args stay numerically indexed.
			$params = $args;
			if ($action !== null) {
				$params['ACTION'] = $action;
			}
			return $params;
		};
		$s['params'] = $parser();
		$s['parsed'] = true;
	}

	/**
	 * Set parser for the current coroutine.
	 * @param callable $fn
	 * @return void
	 */
	public static function setParser(callable $fn): void {
		$s = &static::state();
		$s['parsed'] = false;
		$s['parser'] = Closure::fromCallable($fn);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function set(string $key, mixed $value): void {
		$s = &static::state();
		if (!$s['parsed']) {
			static::parse();
		}
		$s['params'][$key] = $value;
	}

	/**
	 * Get request parameter(s).
	 *
	 * @param string|string[] $args
	 * @return mixed
	 */
	public static function get(...$args): mixed {
		$s = &static::state();
		if (!$s['parsed']) {
			static::parse();
		}

		if (!isset($args[0])) {
			return $s['params'];
		}

		// String key?
		if (is_string($args[0])) {
			return $s['params'][$args[0]] ?? ($args[1] ?? null);
		}

		return static::extractTypified(
			(array)$args[0], static function ($key, $default = null) {
				return static::get($key, $default);
			}
		);
	}

	/**
	 * Extract and typify parameters using a fetcher function.
	 *
	 * @param array<string> $args
	 * @param Closure $fetcher ($key, $default)
	 * @return array<string,mixed>
	 */
	public static function extractTypified(array $args, Closure $fetcher): array {
		$params = [];
		foreach ($args as $arg) {
			if (!preg_match('#^([a-zA-Z0-9_]+)(?::([a-z]+))?(?:=(.+))?$#', $arg, $m)) {
				continue;
			}
			$params[$m[1]] = $fetcher($m[1], $m[3] ?? '');

			// Typify if type specified
			if (!isset($m[2])) {
				continue;
			}

			$params[$m[1]] = typify($params[$m[1]], $m[2]);
		}
		return $params;
	}
}
