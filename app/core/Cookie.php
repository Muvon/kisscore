<?php declare(strict_types=1);

/**
 * Class Cookie
 * Work with cookies
 *
 * <code>
 * Cookie::add('first', 'value', time() + 100);
 * Cookie::add('onemore', 'value', time() + 100);
 * Cookie::send(); // Be sure to send cookie before headers sent
 * </code>
 *
 * <code>
 * $first = Cookie:get('first');
 * </code>
 */
final class Cookie {
	public static bool $is_parsed = false;

	/** @var array<string,mixed> */
	protected static array $update = [];
	/** @var array<string,mixed> */
	protected static array $cookies = [];
	protected static Closure $parse_fn;

	/**
	 * Set parser for the cookie
	 * @param Closure $fn
	 */
	public static function setParser(Closure $fn): void {
		static::$is_parsed = false;
		static::$cookies = [];
		static::$update = [];
		static::$parse_fn = $fn;
	}

	protected static function parse(): void {
		$fn = static::$parse_fn ?? function () {
			return [];
		};
		$raw = $fn();
		if (is_array($raw)) {
			foreach ($raw as $name => $value) {
				static::$cookies[$name] = $value;
			}
		}
		static::$is_parsed = true;
	}

	/**
	 * Get cookie by name
	 * @param string $name
	 * @param mixed $default
	 * @return mixed
	 */
	public static function get(string $name, mixed $default = null): mixed {
		static::$is_parsed || static::parse();
		return static::$cookies[$name] ?? $default;
	}

	/**
	 * Get all cookies
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		static::$is_parsed || static::parse();
		return static::$cookies;
	}

	/**
	 * Set new cookie. Replace if exists
	 * @param string $name
	 * @param string $value
	 * @param array<string, mixed> $options
	 * @return void
	 */
	public static function set(string $name, string $value, array $options = []): void {
		static::$cookies[$name] = $value;
		if (!$options) {
			return;
		}

		static::$update[$name] = [
			'name' => $name,
			'value' => $value,
			'options' => $options,
		];
	}

	/**
	 * Add new cookie. Create new only if not exists
	 * @param string $name
	 * @param string $value
	 * @param array<string, mixed> $options
	 * @return void
	 */
	public static function add(string $name, string $value, array $options = []): void {
		static::$is_parsed || static::parse();
		if (isset(static::$cookies[$name])) {
			return;
		}
		static::set($name, $value, $options);
	}

	/**
	 * Send cookies headers
	 * @param ?callable $cookie_fn
	 */
	public static function send(?callable $cookie_fn = null): void {
		foreach (static::$update as $cookie) {
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
