<?php
final class Cli {
  /**
   * This function reads hidden input (password) from stdin
   *
   * @param string|null $promt
   * @return string
   */
  public static function readSecret(?string $promt = null): string {
    if ($promt) {
      echo $promt;
    }

    system('stty -echo');
    $secret = trim(fgets(STDIN));
    system('stty echo');

    return $secret;
  }

  public static function print(string|array $lines): void {
    if (is_string($lines)) {
      $lines = [$lines];
    }
    $date = gmdate('[Y-m-d H:i:s T]');
    foreach ($lines as $line) {
      echo $date . ' ' . rtrim($line) . PHP_EOL;
    }
  }

  public static function dump(mixed $var): void {
    $date = gmdate('[Y-m-d H:i:s T]');
    echo $date . ' ' . var_export($var, true) . PHP_EOL;
  }

  public static function error(string $line, int $error_code = 1): void {
    static::print($line);
    exit($error_code);
  }
}
