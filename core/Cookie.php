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

  public static function get($name, $default = null) {
    return filter_has_var(INPUT_COOKIE, $name) ? filter_input(INPUT_COOKIE, $name) : $default;
  }

  public static function set($name, $value, $time, $path = '/') {
    assert('is_string($name)');

    static::$cookies[$name] = [
      'name' => $name,
      'value' => $value,
      'time' => $time,
      'path' => $path,
    ];
  }

  public static function add($name, $value, $time, $path = '/') {
    if (!filter_has_var(INPUT_COOKIE, $name)) {
      static::set($name, $value, $time, $path);
    }
  }

  public static function send() {
    foreach (static::$cookies as $cookie) {
      setcookie($cookie['name'], $cookie['value'], $cookie['time'], $cookie['path']);
    }
  }
}
