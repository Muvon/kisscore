<?php
App::configure(__DIR__, [
  '%NGINX_ROUTE_FILE%' => config('common.nginx_route_file'),
]);

App::exec('task add "php-fpm -c $CONFIG_DIR/php.ini -y $CONFIG_DIR/php-fpm.conf -F #php-fpm"');