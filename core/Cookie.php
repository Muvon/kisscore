<?php
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

	protected static array $update = [];
	protected static array $cookies = [];
	protected static Closure $parse_fn;

	public static function setParser(Closure $fn): void {
		static::$is_parsed = false;
		static::$parse_fn = $fn;
	}

	protected static function parse(): void {
		$fn = static::$parse_fn ?? function() {
			return (array) filter_input_array(INPUT_COOKIE);
		};
		static::$cookies = $fn();

		static::$is_parsed = true;
	}

	/**
	 * Get cookie by name
	 * @param string $name
	 * @param mixed $default
	 */
	public static function get(string $name, mixed $default = null): mixed {
		static::$is_parsed || static::parse();
		return static::$cookies[$name] ?? $default;
	}

	/**
	 * Set new cookie. Replace if exists
	 * @param string $name
	 * @param string $value
	 * @param array $options
	 * @return void
	 */
	public static function set(string $name, string $value, array $options = []): void {
		static::$update[$name] = [
			'name' => $name,
			'value' => $value,
			'options' => $options
		];
	}

	/**
	 * Add new cookie. Create new only if not exists
	 * @param string $name
	 * @param string $value
	 * @param array $options
	 * @return void
	 */
	public static function add(string $name, string $value, array $options = []): void {
		if (!isset(static::$cookies[$name])) {
			static::set($name, $value, $options);
		}
	}

	/**
	 * Send cookies headers
	 */
	public static function send(?Callable $fn = null): void {
		$fn ??= setcookie(...);
		foreach (static::$update as $cookie) {
			$options = array_merge($cookie['options'], [
				'domain' => $cookie['domain'] ?? config('common.domain'),
				'path' => $cookie['path'] ?? '/',
				'expires' => $cookie['expires'] ?? 0,
				'secure' => $cookie['secure'] ?? config('common.proto') === 'https',
				'httponly' => $cookie['httponly'] ?? str_starts_with(Request::$protocol, 'HTTP'),
			]);
			$fn($cookie['name'], $cookie['value'], $options);
		}
	}
}
