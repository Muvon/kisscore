[common]
; Алфавит для кодирование чисел по словарю
alphabet    = 'nGWZFAQcUxV2fqJtMmyR7BHwPXNrL9DijhCsvuaezpTS3gEdk546Yb8K'
epoch       = %KISS_EPOCH%
secret      = '%KISS_SECRET_KEY%'

autoload_map_file = '%VAR_DIR%/php_autoload_map.json'
uri_map_file	  = '%VAR_DIR%/uri_request_map.json'
param_map_file    = '%VAR_DIR%/import_var_map.json'
nginx_route_file  = '%RUN_DIR%/nginx_route_map.conf'

[defaults]
route       = 'home'

[view]
source_dir          = '%APP_DIR%/views'
compile_dir         = '%TMP_DIR%'
template_extension  = 'tpl'

[session]
name          = 'KISS'
