<?php declare(strict_types=1);

namespace Plugin\SEO;

final class Sitemap {
  /**
   * @param string $file
   * @param array $locs
   * @param string|null $host
   * @return void
   */
  public static function generate(string $file, array $locs, ?string $host = null): void {
    if (!$host) {
      $host = config('common.proto') . '://' . config('common.domain');
    }
    file_put_contents($file, '<?xml version="1.0" encoding="UTF-8"?>'
      . PHP_EOL . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0">'
      . PHP_EOL . '<url>'
      . PHP_EOL . '  <loc>' . $host . '</loc>'
      . PHP_EOL . '  <lastmod>' . gmdate('Y-m-d\TH:i:sP') . '</lastmod>'
      . PHP_EOL . '  <priority>1.0</priority>'
      . PHP_EOL . '</url>'
      . PHP_EOL . implode('',
        array_map(function ($loc) use ($host) {
          return
            PHP_EOL . '<url>'
            . PHP_EOL . '  <loc>' . $host . $loc['url'] . '</loc>'
            . PHP_EOL . '  <lastmod>' . $loc['date'] . '</lastmod>'
            . PHP_EOL . '  <priority>' . $loc['priority'] . '</priority>'
            . PHP_EOL . '</url>'
          ;
        }, $locs)
      )
      . PHP_EOL . '</urlset>'
    );
  }
}
