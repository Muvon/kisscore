<?php declare(strict_types=1);

/**
 * @route home
 */

Response::current()->status(404);

return [
	'e_endpoint_missing',
	null,
];
