<?php declare(strict_types=1);

// Generate route map using Router
$route_map = Router::generateMap();

// Use Env::configure to write the route map to template
$route_php = var_export($route_map, true);

Env::configure(__DIR__, [
	'{{SWOOLE_ROUTES}}' => $route_php,
]);