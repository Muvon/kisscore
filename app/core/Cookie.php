<?php declare(strict_types=1);

/**
 * Cookie management.
 *
 * Parsed cookies, pending updates and the parser closure are coroutine-local
 * (see Coro), so concurrent requests in a worker keep separate cookie jars.
 */
final class Cookie {
	/**
	 * Per-coroutine cookie state.
	 * @return array{parsed:bool,cookies:array<string,mixed>,update:array<string,mixed>,parser:?Closure}
	 */
	private static function &state(): array {
		/** @var array{parsed:bool,cookies:array<string,mixed>,update:array<string,mixed>,parser:?Closure} $s */
		$s = &Coro::bag(
			'cookie',
			static fn(): array => ['parsed' => false, 'cookies' => [], 'update' => [], 'parser' => null]
		);
		return $s;
	}

	/**
	 * Set parser for the current coroutine and reset its cookie jar.
	 * @param Closure $fn
	 * @return void
	 */
	public static function setParser(Closure $fn): void {
		$s = &static::state();
		$s['parsed'] = false;
		$s['cookies'] = [];
		$s['update'] = [];
		$s['parser'] = $fn;
	}

	/**
	 * @return void
	 */
	protected static function parse(): void {
		$s = &static::state();
		$parser = $s['parser'] ?? static fn(): array => [];
		$raw = $parser();
		if (is_array($raw)) {
			foreach ($raw as $name => $value) {
				$s['cookies'][$name] = $value;
			}
		}
		$s['parsed'] = true;
	}

	/**
	 * Get cookie by name.
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get(string $name, mixed $default = null): mixed {
		$s = &static::state();
		if (!$s['parsed']) {
			static::parse();
		}
		return $s['cookies'][$name] ?? $default;
	}

	/**
	 * Get all cookies.
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		$s = &static::state();
		if (!$s['parsed']) {
			static::parse();
		}
		return $s['cookies'];
	}

	/**
	 * Set new cookie. Replace if exists.
	 * @param string $name
	 * @param string $value
	 * @param array<string, mixed> $options
	 * @return void
	 */
	public static function set(string $name, string $value, array $options = []): void {
		$s = &static::state();
		// Parse incoming cookies first; otherwise a later lazy parse would
		// overwrite this freshly-set value with the request's original cookie of
		// the same name. set() must win — it is an explicit override.
		if (!$s['parsed']) {
			static::parse();
		}
		$s['cookies'][$name] = $value;
		if (!$options) {
			return;
		}

		$s['update'][$name] = [
			'name' => $name,
			'value' => $value,
			'options' => $options,
		];
	}

	/**
	 * Add new cookie. Create new only if not exists.
	 * @param string $name
	 * @param string $value
	 * @param array<string, mixed> $options
	 * @return void
	 */
	public static function add(string $name, string $value, array $options = []): void {
		$s = &static::state();
		if (!$s['parsed']) {
			static::parse();
		}
		if (isset($s['cookies'][$name])) {
			return;
		}
		static::set($name, $value, $options);
	}

	/**
	 * Send cookies headers.
	 * @param ?callable $cookie_fn
	 * @return void
	 */
	public static function send(?callable $cookie_fn = null): void {
		$s = &static::state();
		foreach ($s['update'] as $cookie) {
			/** @var array{name: string, value: string, options: array<string, mixed>} $cookie */
			/** @var array{expires?: int, path?: string, domain?: string, secure?: bool, httponly?: bool, samesite?: 'Lax'|'None'|'Strict'} $options */
			$options = array_merge(
				[
					'domain' => config('common.domain'),
					'path' => '/',
					'expires' => 0,
					'secure' => config('common.proto') === 'https',
					'httponly' => true,
				], $cookie['options']
			);
			if ($cookie_fn) {
				$cookie_fn($cookie['name'], $cookie['value'], $options);
			} else {
				setcookie($cookie['name'], $cookie['value'], $options);
			}
		}
	}
}
