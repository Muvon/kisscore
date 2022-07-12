<?php

/**
 * Class Session
 * Work with sessions
 *
 * <code>
 * Session::start();
 * Session::set('key', 'Test value');
 * Session::get('key');
 * Session::remove('key');
 * if (Session::has('key')) echo 'Found key in Session';
 * Session::regenerate();
 * </code>
 *
 * Add calculated data if key not exists
 * <code>
 * Session::add('key', function () { return time(); });
 * </code>
 *
 * Get key from session with default value
 * <code>
 * Session:get('key', 'default');
 * </code>
 */
final class Session {
  /** @var Session $Instance */
  protected static self $Instance;

  /** @var array $container */
  protected static array $container = [];

  public final function __construct() {}

  public static function start(): void {
    session_name(config('session.name'));
    session_start();
    static::$container = &$_SESSION;
  }

  public static function id(): string {
    return session_id();
  }

  public static function destroy(): bool {
    return session_destroy();
  }

  /**
   * Regenerate new session ID
   */
  public static function regenerate(bool $destroy = false): void {
    session_regenerate_id($destroy);
  }

  /**
   * @param string $key
   * @return bool
   */
  public static function has(string $key): bool {
    return isset(static::$container[$key]);
  }

  /**
   * Add new session var if it not exists
   * @param string $key
   * @param mixed $value Can be callable function, so it executes and pushes
   * @return void
   */
  public static function add(string $key, mixed $value): void {
    if (!static::has($key)) {
      static::set($key, is_callable($value) ? $value() : $value);
    }
  }

  /**
   * Set new var into session
   * @param string $key
   * @param mixed $value
   * @return void
   */
  public static function set(string $key, mixed $value): void {
    static::$container[$key] = $value;
  }

  /**
   * Remove the key from session array
   * @param string $key
   * @return bool
   */
  public static function remove(string $key): bool {
    if (isset(static::$container[$key])) {
      unset(static::$container[$key]);
      return true;
    }
    return  false;
  }

  /**
   * Alias for self::remove
   * @see self::remove
   */
  public static function delete(string $key): bool {
    return static::remove($key);
  }

  /**
   * Get var with key from session array
   * @param string $key
   * @param mixed $default Return default there is no such key, set on closure
   * @return mixed
   */
  public static function get(string $key, mixed $default = null): mixed {
    if (!static::has($key) && $default && is_callable($default)) {
      $default = $default();
      static::set($key, $default);
    }
    return static::has($key) ? static::$container[$key] : $default;
  }
}
