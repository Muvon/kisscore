<?php
/**
 * !ATTENTION! Currently NOT USED
 */
class Autoload {
  protected static $prefixes = [];

  public static function init() {
    spl_autoload_register([static::class, 'load']);
  }

  /**
   * @param string $class Class to be loaded
   * @return bool
   */
  public static function load($class) {
    assert('is_string($class)');

    $prefix = $class;
    while (false !== $pos = strrpos($prefix, '\\')) {
      $prefix = substr($class, 0, $pos + 1);
      $relative = substr($class, $pos + 1);
      $mapped = static::loadMapped($prefix, $relative);
      if ($mapped) {
        return $mapped;
      }
      $prefix = rtrim($prefix, '\\');
    }

    return false;
  } 

  /**
   * @param string $prefix
   * @param string $class
   */
  protected static function loadMapped($prefix, $class) {
    assert('is_string($prefix)');
    assert('is_string($class)');

    if (!isset(static::$prefixes[$prefix])) {
      return false;
    }

    foreach (static::$prefixes[$prefix] as $dir) {
      $file = $dir . str_replace('\\', '/', $class) . '.php';
      if (is_file($file)) {
        include $file;
        return $file;
      }
    }
    return false;
  }

  /**
   * @param string $prefix
   * @param string $dir
   * @param bool $prepend
   */
  public static function register($prefix, $dir, $prepend = false) {
    assert('is_string($prefix)');
    assert('is_string($dir) && is_dir($dir)');
    assert('is_bool($prepend)');

    $prefix = trim($prefix, '\\') . '\\';
    $dir = rtrim($dir, '/') . '/';

    if (!isset(static::$prefixes[$prefix])) {
      static::$prefixes[$prefix] = [];
    }

    call_user_func_array($prepend ? 'array_unshift' : 'array_push', [static::$prefixes[$prefix], $dir]);
  }
}
