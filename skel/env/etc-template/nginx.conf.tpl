server {
  listen   80;
  server_name  %PROJECT%.lo;
  client_max_body_size 10m;
  include %RUN_DIR%/nginx-route-map.conf;


  location / {
    if ($request_method !~ ^(GET|HEAD|POST)$ ) {
      return 444;
    }

    root %HTML_DIR%;
    error_page     403 404 = @app;
    log_not_found  off;
    error_log      /dev/null;
  }

  location @app {
    access_log  %LOG_DIR%/nginx-access.log combined buffer=32k;
    expires off;
    #root /home/$user/app;
    #fastcgi_pass   127.0.0.1:9000;
    include        fastcgi_params;

    fastcgi_param  SCRIPT_FILENAME  %APP_DIR%/frontend.php;
    fastcgi_param  SCRIPT_NAME      %APP_DIR%/frontend.php;
    fastcgi_param  KISS_CORE        %APP_DIR%/core.php;        
    fastcgi_param  APP_DIR          %APP_DIR%;
    fastcgi_param  ENV_DIR          %ENV_DIR%;
    fastcgi_param  CONFIG_DIR       %CONFIG_DIR%;
    fastcgi_param  VAR_DIR          %VAR_DIR%;
    fastcgi_param  LOG_DIR          %LOG_DIR%;
    fastcgi_param  BIN_DIR          %BIN_DIR%;
    fastcgi_param  RUN_DIR          %RUN_DIR%;
    fastcgi_param  TMP_DIR          %TMP_DIR%;              
    fastcgi_param  HTML_DIR         %HTML_DIR%;

    fastcgi_pass   unix:%RUN_DIR%/php-fpm.sock;
  }
}