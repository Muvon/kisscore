<?php declare(strict_types=1);

/**
 * Swoole Router for KissCore
 */
final class Router
{
    private static ?array $routes = null;
    private static ?array $compiled = null;
    
    /**
     * Generate route map from action files
     */
    public static function generateMap(): array
    {
        $routes = Env::load(config('common.uri_map_file'));
        uasort($routes, function ($a, $b) {
            return (sizeof($a) > sizeof($b)) ? 1 : -1;
        });

        $lang_type = config('common.lang_type');
        $lang_match = match($lang_type) {
            'path' => implode('|', config('common.languages')),
            default => null
        };

        $map = [];

        foreach ($routes as $route => $params) {
            $zone = array_shift($params);
            $action = array_shift($params);

            $data = [
                'pattern' => $route,
                'zone' => $zone,
                'action' => $action,
                'params' => array_values($params),
            ];

            if ($lang_match) {
                if ($route === 'home') {
                    $data['lang_pattern'] = "(?:$lang_match)/?";
                } else {
                    $data['lang_pattern'] = "(?:$lang_match)/" . $route;
                }
                $data['has_lang'] = true;
            }

            $map[] = $data;
        }

        // Sort by specificity
        usort($map, function($a, $b) {
            $a_wildcards = substr_count($a['pattern'], '(');
            $b_wildcards = substr_count($b['pattern'], '(');
            
            if ($a_wildcards !== $b_wildcards) {
                return $a_wildcards <=> $b_wildcards;
            }
            
            return strlen($b['pattern']) - strlen($a['pattern']);
        });

        return $map;
    }
    
    /**
     * Initialize router
     */
    public static function init(): void
    {
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
    private static function compile(): void
    {
        static::$compiled = [];
        
        foreach (static::$routes as $route) {
            $pattern = $route['pattern'];
            
            if (isset($route['has_lang']) && isset($route['lang_pattern'])) {
                $regex = '/^' . str_replace('/', '\/', $route['lang_pattern']) . '$/';
            } else {
                $regex = '/^' . str_replace('/', '\/', $pattern) . '$/';
            }
            
            static::$compiled[] = [
                'pattern' => $pattern,
                'regex' => $regex,
                'zone' => $route['zone'],
                'action' => $route['action'],
                'params' => $route['params'] ?? [],
                'has_lang' => $route['has_lang'] ?? false
            ];
        }
    }
    
    /**
     * Match URL against routes
     */
    public static function match(string $url, string $host = ''): ?array
    {
        static::init();
        
        $clean_url = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        if ($clean_url === '') {
            $clean_url = 'home';
        }
        
        $zone = static::getZone($host);
        
        foreach (static::$compiled as $route) {
            if ($route['zone'] !== $zone) {
                continue;
            }
            
            if (preg_match($route['regex'], $clean_url, $matches)) {
                array_shift($matches);
                
                $params = [];
                foreach ($route['params'] as $i => $param_name) {
                    if (isset($matches[$i])) {
                        $params[$param_name] = $matches[$i];
                    }
                }
                
                return [
                    'action' => $route['action'],
                    'params' => $params,
                    'route' => $route['pattern'],
                    'zone' => $route['zone']
                ];
            }
        }
        
        return null;
    }
    
    /**
     * Get zone from host
     */
    private static function getZone(string $host): string
    {
        if (!$host) {
            return config('common.zones')[0] ?? 'www';
        }
        
        $domain = config('common.domain');
        $zones = config('common.zones');
        
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
    public static function clearCache(): void
    {
        static::$routes = null;
        static::$compiled = null;
    }
}