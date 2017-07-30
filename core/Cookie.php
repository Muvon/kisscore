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
class Cookie {
  protected static $cookies = [];

  /**
   * Get cookie by name
   * @param string $name
   * @param mixed $default
   */
  public static function get($name, $default = null) {
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
  public static function set($name, $value, $time, $path = '/') {
    assert(is_string($name));

    static::$cookies[$name] = [
      'name' => $name,
      'value' => $value,
      'time' => $time,
      'path' => $path,
    ];
  }

  /**
   * Add new cookie. Create new only if not exists
   * @param string $name
   * @param string $value
   * @param int $time Expire at time as timestamp
   * @param string $path Cookie save path
   * @return void
   */
  public static function add($name, $value, $time, $path = '/') {
    if (!filter_has_var(INPUT_COOKIE, $name)) {
      static::set($name, $value, $time, $path);
    }
  }

  /**
   * Send cookies headers
   */
  public static function send() {
    foreach (static::$cookies as $cookie) {
      setcookie($cookie['name'], $cookie['value'], $cookie['time'], $cookie['path']);
    }
  }
}
