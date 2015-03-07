<?php
$ips = implode(
  PHP_EOL,
  array_map(
    function ($item) {
      return 'allow ' . $item . ';';
    },
    split(' ', App::exec('$(which hostname) -I'))
  )
);
App::configure(__DIR__, [
  '%NGINX_ROUTE_FILE%' => config('common.nginx_route_file'),
  '%UPLOAD_MAX_FILESIZE%' => config('common.upload_max_filesize'),
  '%SERVER_NAME%' => config('common.domain'),
  '%IP_ALLOW%' => $ips,
]);
