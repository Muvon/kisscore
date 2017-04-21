<?php
App::exec('echo "' . config('nginx.auth_name') . ':"$(openssl passwd -apr1 ' . escapeshellarg(config('nginx.auth_pass')) . ') > $CONFIG_DIR/.htpasswd');

$routes = App::getJSON(config('common.uri_map_file'));
uasort($routes, function ($a, $b) {
  return (sizeof($a) > sizeof($b)) ? 1 : -1;
});

$rewrites = [];
foreach ($routes as $route => $action) {
  $i = 0; // route like (bla (bla bla)) with uff8 cant handle by nginx. so hack it
  $uri = '/?ROUTE='
       . preg_replace_callback(
          '|\([^\)]+\)|is',
          function ($item) use (&$i) {
            return '$' . ++$i;
          },
          $route
        )
       . '&ACTION=' . array_shift($action)
  ;

  if ($action) {
    foreach ($action as $k => $v) {
      $uri .= '&' . $v . '=$' . ($k + 1);
    }
  }
  $rewrites[] = "rewrite '(*UTF8)^/$route/?$' '$uri';";
}

Env::configure(__DIR__, [
  '{{UPLOAD_MAX_FILESIZE}}' => config('common.upload_max_filesize'),
  '{{SERVER_NAME}}' => config('common.domain'),
  '{{AUTH}}' => config('nginx.auth'),
  '{{REWRITE_RULES}}' => implode(PHP_EOL, $rewrites),
  '{{CORS_ORIGIN}}' => config('cors.origin'),
  '{{CORS_METHODS}}' => config('cors.methods'),
  '{{CORS_HEADERS}}' => config('cors.headers'),
  '{{CORS_CREDENTIALS}}' => config('cors.credentials'),
  '{{OPEN_FILE_CACHE}}' => config('nginx.open_file_cache'),
]);
