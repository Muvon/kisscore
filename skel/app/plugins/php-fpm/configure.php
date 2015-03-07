<?php
App::configure(__DIR__, [
	'%UPLOAD_MAX_FILESIZE%' => config('common.upload_max_filesize'),
]);
