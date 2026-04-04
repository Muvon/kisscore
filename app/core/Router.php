<?php declare(strict_types=1);

/**
 * Swoole Router for KissCore
 */
final class Router
{
	/** @var array<array<string,mixed>>|null */
	private static ?array $routes = null;
	/** @var array<array<string,mixed>>|null */
	private static ?array $compiled = null;

	/**
	 * Generate route map from action files
	 * @return array<array<string,mixed>>
	 */
	public static function generateMap(): array {
		/** @var string $uriMapFile */
		$uriMapFile = config('common.uri_map_file');
		$routes = Env::load($uriMapFile);
		uasort(
			$routes, function (mixed $a, mixed $b): int {
				/** @var array<mixed> $aArr */
				$aArr = $a;
				/** @var array<mixed> $bArr */
				$bArr = $b;
				return (sizeof($aArr) > sizeof($bArr)) ? 1 : -1;
			}
		);

		/** @var string $lang_type */
		$lang_type = config('common.lang_type');
		/** @var array<string> $configLanguages */
		$configLanguages = config('common.languages');
		$lang_match = match ($lang_type) {
			'path' => implode('|', $configLanguages),
			default => null
		};

		$map = [];

		foreach ($routes as $route => $routeParams) {
			/** @var array<mixed> $params */
			$params = $routeParams;
			$zone = array_shift($params);
			$action = array_shift($params);

			$routeKey = (string)$route;
			$data = [
				'pattern' => $routeKey,
				'zone' => $zone,
				'action' => $action,
				'params' => array_values($params),
			];

			if ($lang_match) {
				if ($routeKey === 'home') {
					$data['lang_pattern'] = "(?:$lang_match)/?";
				} else {
					$data['lang_pattern'] = "(?:$lang_match)/" . $routeKey;
				}
				$data['has_lang'] = true;
			}

			$map[] = $data;
		}

		// Sort by specificity
		usort(
			$map, function ($a, $b) {
				$a_wildcards = substr_count($a['pattern'], '(');
				$b_wildcards = substr_count($b['pattern'], '(');

				if ($a_wildcards !== $b_wildcards) {
					return $a_wildcards <=> $b_wildcards;
				}

				return strlen($b['pattern']) - strlen($a['pattern']);
			}
		);

		return $map;
	}

	/**
	 * Initialize router
	 */
	public static function init(): void {
		if (static::$routes !== null) {
			return;
		}

		$file = getenv('CONFIG_DIR') . '/routes.php';

		if (file_exists($file)) {
			static::$routes = include $file;
		} else {
			static::$routes = static::generateMap();
		}

		static::compile();
	}

	/**
	 * Compile routes for fast matching
	 */
	private static function compile(): void {
		static::$compiled = [];
		if (static::$routes === null) {
			return;
		}

		foreach (static::$routes as $route) {
			/** @var string $pattern */
			$pattern = $route['pattern'];

			if (isset($route['has_lang']) && isset($route['lang_pattern'])) {
				/** @var string $langPattern */
				$langPattern = $route['lang_pattern'];
				$regex = '/^' . str_replace('/', '\/', $langPattern) . '$/';
			} else {
				$regex = '/^' . str_replace('/', '\/', $pattern) . '$/';
			}

			static::$compiled[] = [
				'pattern' => $pattern,
				'regex' => $regex,
				'zone' => $route['zone'],
				'action' => $route['action'],
				'params' => $route['params'] ?? [],
				'has_lang' => $route['has_lang'] ?? false,
			];
		}
	}

	/**
	 * Match URL against routes
	 * @return array{action:string,params:array<string,string>,route:string,zone:string}|null
	 */
	public static function match(string $url, string $host = ''): ?array {
		static::init();

		$clean_url = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
		if ($clean_url === '') {
			$clean_url = 'home';
		}

		$zone = static::getZone($host);

		foreach (static::$compiled ?? [] as $route) {
			/** @var string $routeZone */
			$routeZone = $route['zone'];
			if ($routeZone !== $zone) {
				continue;
			}

			/** @var string $regex */
			$regex = $route['regex'];
			if (preg_match($regex, $clean_url, $matches)) {
				array_shift($matches);

				$params = [];
				/** @var array<string> $routeParams */
				$routeParams = $route['params'] ?? [];
				foreach ($routeParams as $i => $param_name) {
					if (!isset($matches[$i])) {
						continue;
					}

					$params[$param_name] = $matches[$i];
				}

				/** @var string $routeAction */
				$routeAction = $route['action'];
				/** @var string $routePattern */
				$routePattern = $route['pattern'];
				return [
					'action' => $routeAction,
					'params' => $params,
					'route' => $routePattern,
					'zone' => $routeZone,
				];
			}
		}

		return null;
	}

	/**
	 * Get zone from host
	 */
	private static function getZone(string $host): string {
		/** @var array<string> $zones */
		$zones = config('common.zones');
		if (!$host) {
			return $zones[0] ?? 'www';
		}

		/** @var string $domain */
		$domain = config('common.domain');

		if ($host === $domain) {
			return 'www';
		}

		foreach ($zones as $zone) {
			if ($host === "{$zone}.{$domain}") {
				return $zone;
			}
		}

		return $zones[0] ?? 'www';
	}

	/**
	 * Clear cache
	 */
	public static function clearCache(): void {
		static::$routes = null;
		static::$compiled = null;
	}
}
