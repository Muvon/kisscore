<?php declare(strict_types=1);

namespace App\Component\Base;

/**
 * We do not use enum because vk noverify does not support it yet,
 * so it's okay to change it later
 */
final class BaseError {
	const InvalidHttpContentType = 'e_invalid_http_content_type';
	const InvalidHttpMethod = 'e_invalid_http_method';
}
