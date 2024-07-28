<?php declare(strict_types=1);

final class Robots {
  /**
   * @param string $file
   * @param array<string> $lines
   * @param null|string $host
   * @return void
   */
  public static function generate(string $file, array $lines = [], ?string $host = null): void {
    if (!$host) {
      $host = config('common.proto') . '://' . config('common.domain');
    }

    file_put_contents($file,
      'User-agent: *'
      . PHP_EOL . 'Crawl-delay: 1'
      . PHP_EOL . 'Host: ' . $host
      . PHP_EOL . 'Sitemap: ' . $host . '/sitemap.xml'
      . ($lines ? PHP_EOL . implode(PHP_EOL, $lines) : '')
    );
  }
}
