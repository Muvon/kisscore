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
    echo gmdate('[Y-m-d H:i:s T]') . ' ' . trim($line) . PHP_EOL;
  }

  public static function printList(array $list): void {
    foreach ($list as $item) {
      static::print($item);
    }
  }
}
