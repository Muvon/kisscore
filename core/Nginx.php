<?php
class Nginx {
  public static function configure() {
    App::configure(__DIR__, [
      '%NGINX_ROUTE_FILE%' => config('nginx_route_file'),
    ]);
  }
}