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
   * @param int $time Expire at time as timestamp
   * @param string $path Cookie save path
   * @return void
   */
  public static function set(string $name, string $value, int $time, string $path = '/', string $domain = null): void {
    static::$cookies[$name] = [
      'name' => $name,
      'value' => $value,
      'time' => $time,
      'path' => $path,
      'domain' => $domain ?? config('common.domain')
    ];
  }

  /**
   * Add new cookie. Create new only if not exists
   * @param string $name
   * @param mixed $value
   * @param int $time Expire at time as timestamp
   * @param string $path Cookie save path
   * @return void
   */
  public static function add(string $name, mixed $value, int $time, string $path = '/', string $domain = null): void {
    if (!filter_has_var(INPUT_COOKIE, $name)) {
      static::set($name, $value, $time, $path, $domain);
    }
  }

  /**
   * Send cookies headers
   */
  public static function send(): void {
    foreach (static::$cookies as $cookie) {
      setcookie($cookie['name'], $cookie['value'], $cookie['time'], $cookie['path'], $cookie['domain'] ?? null, config('common.proto') === 'https', str_starts_with(getenv('SERVER_PROTOCOL'), 'HTTP'));
    }
  }
}
