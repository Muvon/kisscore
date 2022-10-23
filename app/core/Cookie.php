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
	protected static $cookies = [];

  /**
   * Get cookie by name
   * @param string $name
   * @param mixed $default
   */
	public static function get(string $name, mixed $default = null): mixed {
		return filter_has_var(INPUT_COOKIE, $name) ? filter_input(INPUT_COOKIE, $name) : $default;
	}

  /**
   * Set new cookie. Replace if exists
   * @param string $name
   * @param string $value
   * @param array $options
   * @return void
   */
	public static function set(string $name, string $value, array $options = []): void {
		static::$cookies[$name] = [
			'name' => $name,
			'value' => $value,
			'options' => $options,
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
		if (filter_has_var(INPUT_COOKIE, $name)) {
			return;
		}

		static::set($name, $value, $options);
	}

  /**
   * Send cookies headers
   */
	public static function send(): void {
		foreach (static::$cookies as $cookie) {
			$options = array_merge(
				$cookie['options'], [
					'domain' => $cookie['domain'] ?? config('common.domain'),
					'path' => $cookie['path'] ?? '/',
					'expires' => $cookie['expires'] ?? 0,
					'secure' => $cookie['secure'] ?? config('common.proto') === 'https',
					'httponly' => $cookie['httponly'] ?? str_starts_with(getenv('SERVER_PROTOCOL'), 'HTTP'),
				]
			);
			setcookie($cookie['name'], $cookie['value'], $options);
		}
	}
}
