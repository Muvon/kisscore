<?php
Env::configure(__DIR__, [
  '{{UPLOAD_MAX_FILESIZE}}' => config('common.upload_max_filesize'),
  '{{ASSERTIONS}}' => App::$debug ? 1 : -1,
  '{{SESSION_SAVE_HANDLER}}' => 'files',
  '{{SESSION_COOKIE_SECURE}}' => config('common.proto') === 'https' ? 'on' : 'off',
  '{{SESSION_SAVE_PATH}}' => getenv('TMP_DIR')
]);
