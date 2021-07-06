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

  public static function print(string $line): void {
    echo gmdate('[Y-m-d H:i:s T]') . ' ' . rtrim($line) . PHP_EOL;
  }

  public static function error(string $line, int $error_code = 1): void {
    static::print($line);
    exit($error_code);
  }

  public static function printList(array $list): void {
    foreach ($list as $item) {
      static::print($item);
    }
  }
}
