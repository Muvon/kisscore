<?php
Env::configure(__DIR__, [
  '%SESSION_NAME%' => config('session.name'),
	'%UPLOAD_MAX_FILESIZE%' => config('common.upload_max_filesize'),
]);
