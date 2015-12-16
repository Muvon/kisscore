<?php
$ips = implode(
  PHP_EOL,
  array_map(
    function ($item) {
      return 'allow ' . $item . ';';
    },
    explode(' ', App::exec('$(which hostname) -I'))
  )
);

if (config('nginx.auth') !== 'off') {
  App::exec('echo "' . config('nginx.auth_name') . ':"$(openssl passwd -apr1 ' . escapeshellarg(config('nginx.auth_pass')) . ') > $CONFIG_DIR/.htpasswd');
}

Env::configure(__DIR__, [
  '%NGINX_ROUTE_FILE%' => config('common.nginx_route_file'),
  '%UPLOAD_MAX_FILESIZE%' => config('common.upload_max_filesize'),
  '%SERVER_NAME%' => config('common.domain'),
  '%IP_ALLOW%' => $ips,
  '%AUTH%' => config('nginx.auth'),
]);
