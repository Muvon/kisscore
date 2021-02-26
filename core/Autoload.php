<?php
final class Autoload {
  protected static bool $inited = false;
  protected static array $prefixes = [];

  /**
   * Init autoload mecahnism
   */
  protected static function init(): void {
    spl_autoload_register([static::class, 'load']);
    static::$inited = true;
  }

  /**
   * @param string $class Class to be loaded
   * @return bool|string
   */
  protected static function load(string $class): bool|string {
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
  protected static function loadMapped(string $prefix, string $class): false|string {
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
   * Register new namespace and folder to be loaded from
   * @param string $prefix
   * @param string $dir
   * @param bool $prepend Priority for this
   */
  public static function register(string $prefix, string $dir, bool $prepend = false): void {
    assert(is_dir($dir) /* Dir $dir does not exist */);

    if (!static::$inited) {
      static::init();
    }

    $prefix = trim($prefix, '\\') . '\\';
    $dir = rtrim($dir, '/') . '/';

    if (!isset(static::$prefixes[$prefix])) {
      static::$prefixes[$prefix] = [];
    }

    if ($prepend) {
      array_unshift(static::$prefixes[$prefix], $dir);
    } else {
      static::$prefixes[$prefix][] = $dir;
    }
  }
}
