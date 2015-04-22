<?php
class Autoload {
  protected static $inited = false;
  protected static $prefixes = [];

  /**
   * Init autoload mecahnism
   */
  protected static function init() {
    spl_autoload_register([static::class, 'load']);
    static::$inited = true;
  }

  /**
   * @param string $class Class to be loaded
   * @return bool
   */
  protected static function load($class) {
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
   * Register new namespace and folder to be loaded from
   * @param string $prefix
   * @param string $dir
   * @param bool $prepend Priority for this 
   */
  public static function register($prefix, $dir, $prepend = false) {
    assert('is_string($prefix)');
    assert("is_string(\$dir) && is_dir(\$dir) /* Dir $dir does not exist */");
    assert('is_bool($prepend)');
    
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
