<?php declare(strict_types=1);

if (isset($_SERVER['REQUEST_METHOD'])) {
	fastcgi_finish_request();
}
