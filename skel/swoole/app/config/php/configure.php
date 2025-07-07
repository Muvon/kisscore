<?php declare(strict_types=1);

$routes = Env::load(config('common.uri_map_file'));
uasort($routes, function ($a, $b) {
	return (sizeof($a) > sizeof($b)) ? 1 : -1;
});

$lang_type = config('common.lang_type');
$lang_match = match($lang_type) {
	'path' => implode('|', config('common.languages')),
	default => null
};

$routeMap = [];
foreach ($routes as $route => $params) {
	$zone = array_shift($params);
	$action = array_shift($params);

	// Extract the prefix from route (first segment before /)
	$prefix = explode('/', $route)[0];
	if (empty($prefix)) $prefix = 'root';

	// Create route pattern and parameter mapping
	$i = 0;
	$pattern = preg_replace_callback('|\([^\)]+\)|is', fn() => '([^/]+)', $route);

	$paramMap = [];
	foreach ($params as $k => $v) {
		$paramMap[$v] = $k + 1;
	}

	$routeData = [
		'pattern' => $pattern,
		'zone' => $zone,
		'action' => $action,
		'params' => $paramMap
	];

	// Handle language prefix if needed
	if ($lang_match) {
		if ($route === 'home') {
			$routeData['pattern'] = "(?:$lang_match)/?";
		} else {
			$routeData['pattern'] = "(?:$lang_match)/" . $routeData['pattern'];
		}
		$routeData['hasLang'] = true;
	}

	// Group routes by prefix
	$routeMap[$prefix] ??= [];
	$routeMap[$prefix][] = $routeData;
}

// Sort routes within each prefix group by pattern length (more specific first)
foreach ($routeMap as &$routes) {
	usort($routes, function($a, $b) {
		return strlen($b['pattern']) - strlen($a['pattern']);
	});
}

// Convert to JSON
$jsonRouteMap = json_encode($routeMap, JSON_PRETTY_PRINT);
var_dump($jsonRouteMap);

// You can now use $jsonRouteMap for your routing system
// The structure will be organized by prefixes for faster initial matching
