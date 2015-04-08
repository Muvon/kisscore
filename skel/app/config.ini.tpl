[common]
; Алфавит для кодирование чисел по словарю
alphabet    = 'nGWZFAQcUxV2fqJtMmyR7BHwPXNrL9DijhCsvuaezpTS3gEdk546Yb8K'
epoch       = %KISS_EPOCH%
secret      = '%KISS_SECRET_KEY%'

trigger_map_file   = '%VAR_DIR%/trigger_event_map.json'
trigger_param_file = '%VAR_DIR%/trigger_param_map.json'
uri_map_file	  = '%VAR_DIR%/uri_request_map.json'
param_map_file    = '%VAR_DIR%/import_var_map.json'
nginx_route_file  = '%RUN_DIR%/nginx_route_map.conf'

upload_max_filesize = '10M'

domain = '%PROJECT%.lo'

[common:test]
domain = '%PROJECT%.dev'

[common:productin]
domain = '%PROJECT%.ru'

[default]
action = 'home'

[view]
source_dir          = '%APP_DIR%/views'
compile_dir         = '%TMP_DIR%'
template_extension  = 'tpl'

[session]
name          = 'KISS'
