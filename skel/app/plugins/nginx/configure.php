<?php
App::configure(__DIR__, [
  '%NGINX_ROUTE_FILE%' => config('common.nginx_route_file'),
  '%UPLOAD_MAX_FILESIZE%' => config('common.upload_max_filesize'),
  '%SERVER_NAME%' => config('common.domain'),
]);