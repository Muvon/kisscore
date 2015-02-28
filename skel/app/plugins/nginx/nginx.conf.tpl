log_format  %PROJECT%
  '$remote_addr - $remote_user [$time_local] "$request" '
  '$status $body_bytes_sent "$http_referer" '
  '"$http_user_agent" "$http_x_forwarded_for" '
  '$upstream_response_time sec "$host"';

server {
  error_log %LOG_DIR%/nginx-error.log;

  listen   80;
  server_name  %SERVER_NAME%;
  client_max_body_size %UPLOAD_MAX_FILESIZE%;
  include %NGINX_ROUTE_FILE%;

  root %HTML_DIR%;
  location = / {
    if ($request_method !~ ^(GET|HEAD|POST)$ ) {
      return 444;
    }

    try_files $uri @app;
    log_not_found  off;
    error_log      /dev/null;
  }

  location @app {
    access_log  %LOG_DIR%/nginx-access.log %PROJECT% buffer=32k;

    expires off;

    include        %CONFIG_DIR%/nginx_fastcgi_params;

    fastcgi_param  SCRIPT_FILENAME  %APP_DIR%/frontend.php;
    fastcgi_param  SCRIPT_NAME      %APP_DIR%/frontend.php;
    fastcgi_param  KISS_CORE        %APP_DIR%/core.php;
    fastcgi_param  APP_DIR          %APP_DIR%;
    fastcgi_param  ENV_DIR          %ENV_DIR%;
    fastcgi_param  CONFIG_DIR       %CONFIG_DIR%;
    fastcgi_param  PROJECT_ENV      %PROJECT_ENV%;
    fastcgi_param  VAR_DIR          %VAR_DIR%;
    fastcgi_param  LOG_DIR          %LOG_DIR%;
    fastcgi_param  BIN_DIR          %BIN_DIR%;
    fastcgi_param  RUN_DIR          %RUN_DIR%;
    fastcgi_param  TMP_DIR          %TMP_DIR%;
    fastcgi_param  HTML_DIR         %HTML_DIR%;

    fastcgi_pass   unix:%RUN_DIR%/php-fpm.sock;
  }

  # This location block is used to view PHP-FPM stats
  location = /php_status {
    %IP_ALLOW%
    deny all;

    include        %CONFIG_DIR%/nginx_fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
    fastcgi_pass unix:%RUN_DIR%/php-fpm.sock;
  }

  # This location block is used to view nginx stats
  location = /nginx_status {
    %IP_ALLOW%
    deny all;

    stub_status on;
    access_log off;
  }
}