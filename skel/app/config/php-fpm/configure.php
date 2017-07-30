<?php
Env::configure(__DIR__, [
	'{{UPLOAD_MAX_FILESIZE}}' => config('common.upload_max_filesize'),
  '{{ASSERTIONS}}' => App::$debug ? 1 : -1,
]);
