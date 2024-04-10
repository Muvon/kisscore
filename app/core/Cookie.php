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

  protected static array $update = [];
  protected static array $cookies = [];
  protected static Closure $parse_fn;

  /**
   * Set parser for the cookie
   * @param Closure $fn [description]
   */
  public static function setParser(Closure $fn): void {
    static::$is_parsed = false;
    static::$parse_fn = $fn;
  }

  protected static function parse(): void {
  	$fn = static::$parse_fn ?? function () {
  		$cookies = (array)filter_input_array(INPUT_COOKIE);
  		foreach ($cookies as $name => $value) {
  			static::set($name, $value);
  		}
  		return static::$cookies;
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
