<?php declare(strict_types=1);

final class Env {
	protected static $params = [
		'APP_ENV',
		'APP_DIR',
		'STATIC_DIR',
		'CONFIG_DIR',
		'ENV_DIR',
		'BIN_DIR',
		'RUN_DIR',
		'LOG_DIR',
		'VAR_DIR',
		'TMP_DIR',
	];

  /**
   * Initialization of Application
   *
   * @return void
   */
	public static function init(): void {
		static::initLocalEnv();
		App::$debug = getenv('APP_ENV') === 'dev';
		App::$log_level = Cli::LEVEL_DEBUG;
		static::configure(getenv('APP_DIR') . '/config/app.ini.tpl');
		static::compileConfig();
		static::generateActionMap();
		static::generateURIMap();
		static::generateParamMap();
		static::generateTriggerMap();
		static::generateConfigs();
		static::prepareDirs();
	}

	/**
	 * Initialize local env variables and detect it from core onse
	 * @return void
	 */
	protected static function initLocalEnv(): void {
		// We know that it is placed under app/core.php in project, so get it
		$dir = realpath(__DIR__ . '/../');
		putenv("APP_DIR=$dir/app");
		putenv("STATIC_DIR=$dir/app/static");
		putenv("CONFIG_DIR=$dir/env/etc");
		putenv("ENV_DIR=$dir/env");
		putenv("BIN_DIR=$dir/env/bin");
		putenv("RUN_DIR=$dir/env/run");
		putenv("LOG_DIR=$dir/env/log");
		putenv("VAR_DIR=$dir/env/var");
		putenv("TMP_DIR=$dir/env/tmp");
	}

  // This method should be called in CLI only
	/**
	 * @param int $timeout
	 * @return void
	 */
	public static function waitInit(int $timeout = 5): void {
		$t = time();
		Env::initLocalEnv();
		$cnf_file = getenv('CONFIG_DIR') . '/config.php';
		do {
			$tpl_ts = filemtime(getenv('APP_DIR') . '/config/app.ini.tpl');
			$cnf_ts = file_exists($cnf_file) ? filemtime($cnf_file) : 0;

			if ($cnf_ts > $tpl_ts) {
				return;
			}

			usleep(250000); // 25ms
		} while ((time() - $t) <= $timeout);

		Cli::error('Env: wait init timeouted');
	}

  /**
   * Configure all config templates in dir $template or special $template file
   *
   * @param string $template
   * @param array $params
   * @return void
   */
	public static function configure(string $template, array $params = []): void {
	  // Add default params
		foreach (static::$params as $param) {
			$params['{{' . $param . '}}'] = getenv($param);
		}

	  // Add extra params
		$params += [
			'{{DEBUG}}' => (int)App::$debug,
		];

		foreach (is_dir($template) ? glob($template . '/*.tpl') : [$template] as $file) {
			file_put_contents(
				getenv('CONFIG_DIR') . '/' . basename($file, '.tpl'),
				strtr(file_get_contents($file), $params)
			);
		}
	}

  /**
   * Compile config.json into fast php array to include it ready to use optimized config
	 *
	 * @return void
   */
	protected static function compileConfig(): void {

		$config = static::parseConfig();

	  // Iterate to make dot.notation.direct.access
		$Iterator = new RecursiveIteratorIterator(new RecursiveArrayIterator($config));
		foreach ($Iterator as $leaf_value) {
			$keys = [];
			foreach (range(0, $Iterator->getDepth()) as $depth) {
				$keys[] = $Iterator->getSubIterator($depth)->key();
			}
			$config[join('.', $keys)] = $leaf_value;
		}

		static::store(getenv('CONFIG_DIR') . '/config.php', $config);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected static function parseConfig(): array {
		$config = [];

		$env = getenv('APP_ENV');
		// Prepare production config replacement
		foreach (parse_ini_file(getenv('CONFIG_DIR') . '/app.ini', true) as $group => $block) {
			if (str_contains($group, ':') && explode(':', $group)[1] === $env) {
				$origin = strtok($group, ':');
				$config[$origin] = array_merge($config[$origin], $block);
				$group = $origin;
			} else {
				$config[$group] = $block;
			}

			$config = static::appendDotNotationToConfig($config, $group);
		}
		return $config;
	}

	/**
	 * Make dot.notation for group access
	 *
	 * @param array<string,mixed> $config
	 * @param string $group
	 * @return array<string,mixed>
	 */
	protected static function appendDotNotationToConfig(array $config, string $group): array {
		foreach ($config[$group] as $key => $val) {
			$parts = explode('.', $key);
			$ref = &$config;
			for ($i = 0, $max_i = sizeof($parts) - 1; $i <= $max_i; $i++) {
				$key = ($i === 0 ? $group . '.' : '') . $parts[$i];

				if ($i === $max_i) {
					$ref[$key] = $val;
					$config[$group . '.' . $key] = &$ref[$key];
					unset($ref);
				} else {
					$ref[$key] ??= [];
					$ref = &$ref[$key];
				}
			}
		}

		return $config;
	}

  /**
   * Generate all configs for configurable plugins. It includes all plugin/_/configure.php files
	 *
   * @return void
   */
	protected static function generateConfigs(): void {
		$configure = function ($file) {
			return include $file;
		};

		foreach (glob(getenv('APP_DIR') . '/config/*/configure.php') as $file) {
			$configure($file);
		}
	}

	/**
	 * @return void
	 */
	protected static function prepareDirs(): void {
		static::createViewDirs();
		static::createSessionDirs();
	}

	/**
	 * @return void
	 */
	protected static function createViewDirs(): void {
		if (!is_dir(config('view.compile_dir'))) {
			mkdir(config('view.compile_dir'), 0700, true);
		}

		if (config('common.lang_type') === 'none') {
			return;
		}

		foreach (config('common.languages') as $lang) {
			$lang_dir = config('view.compile_dir') . '/' . $lang;
			if (is_dir($lang_dir)) {
				continue;
			}

			mkdir($lang_dir, 0700);
		}
	}

	/**
	 * @return void
	 */
	protected static function createSessionDirs(): void {
		$save_handler = config('session.save_handler');
		if ($save_handler !== 'files') {
			return;
		}
		$bits = ini_get('session.sid_bits_per_character');
		$chars = '0123456789abcdef';
		if ($bits >= 5) {
			$chars .= 'ghijklmnopqrstuv';
		}

		if ($bits >= 6) {
			$chars .= 'wxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-,';
		}

		$save_path = config('session.save_path');
		if (!is_dir($save_path)) {
			mkdir($save_path, 0700, true);
		}

		$depth = config('session.save_depth');
		if ($depth === 0) {
			return;
		}

		$arrays = [];
		for ($i = 0; $i < $depth; $i++) {
			$arrays[] = str_split($chars);
		}

		foreach (array_cartesian($arrays) as $paths) {
			$dir_path = $save_path . '/' . implode('/', $paths);
			if (is_dir($dir_path)) {
				continue;
			}

			mkdir($dir_path, 0700, true);
		}
	}

  /**
   * Generate nginx URI map for route request to special file
	 *
	 * @return void
   */
	protected static function generateURIMap(): void {
		$map = [];
		$default_zone = config('common.zones')[0];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
			$content = file_get_contents($file);
			if (false === $content) {
				throw new Exception("Failed to read file: $file");
			}

			$zone = $default_zone;
			if (preg_match('/^\s*\*\s*@zone\s+(\w+)/im', $content, $zoneMatch)) {
				$zone = $zoneMatch[1];
			}

			if (!preg_match_all('/^\s*\*\s*@route\s+([^:]+?)(:(.+))?$/ium', $content, $m)) {
				continue;
			}

			foreach (array_keys($m[0]) as $k) {
				$pattern = trim($m[1][$k]);
				$params  = isset($m[2][$k]) && $m[2][$k] ? array_map('trim', explode(',', substr($m[2][$k], 1))) : [];
				array_unshift($params, static::getActionByFile($file));
				$map[$pattern] = [$zone, ...$params];
			}
		}
		static::store(config('common.uri_map_file'), $map);
	}

  /**
   * Generate action => file_path map
	 *
	 * @return void
   */
	protected static function generateActionMap(): void {
		$map = [];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/actions') as $file) {
			$map[static::getActionByFile($file)] = $file;
		}
		static::store(config('common.action_map_file'), $map);
	}

  /**
   * Generate parameters map from annotations in actions and triggers files
	 *
	 * @return void
   */
	protected static function generateParamMap(): void {
		$map_files = [
			'actions'  => config('common.param_map_file'),
			'triggers' => config('common.trigger_param_file'),
		];
		foreach ($map_files as $folder => $map_file) {
			$map = [];
			foreach (static::getPHPFiles(getenv('APP_DIR') . '/' . $folder) as $file) {
				$content = file_get_contents($file);
				if (!preg_match_all('/^\s*\*\s*@(?:param|var)\s+([a-z]+)\s+(.+?)$/ium', $content, $m)) {
					continue;
				}

				foreach (array_keys($m[0]) as $k) {
					$param = substr(strtok($m[2][$k], ' '), 1);
					$map[$file][] = [
						'name'    => $param,
						'type'    => $m[1][$k],
						'default' => trim(substr($m[2][$k], strlen($param) + 1)) ?: null,
					];
				}
			}
			static::store($map_file, $map);
		}
	}

  /**
   * Generate trigger map to be called on some event
	 *
	 * @return void
   */
	protected static function generateTriggerMap(): void {
		$map = [];
		foreach (static::getPHPFiles(getenv('APP_DIR') . '/triggers') as $file) {
			$content = file_get_contents($file);
			if (!preg_match_all('/^\s*\*\s*@event\s+([^\$]+?)$/ium', $content, $m)) {
				continue;
			}

			foreach (array_keys($m[0]) as $k) {
				$pattern = trim($m[1][$k]);
				if (!isset($map[$pattern])) {
					$map[$pattern] = [];
				}
				$map[$pattern] = array_merge($map[$pattern], [$file]);
			}
		}
		static::store(config('common.trigger_map_file'), $map);
	}

	/**
	 * @param string $file
	 * @return string
	 */
	protected static function getActionByFile(string $file): string {
		return substr(trim(str_replace(getenv('APP_DIR') . '/actions', '', $file), '/'), 0, -4);
	}

  /**
   * Helper for getting list of all php files in dir
   * @param string $dir
   * @return string[]
   */
	protected static function getPHPFiles(string $dir): array {
		assert(is_dir($dir));
		$output = `find -L $dir -name '*.php'`;
		return $output ? explode(PHP_EOL, trim($output)) : [];
	}

  /**
   * This function uses for store variable in php file for next load
   * Its much faster than parse and encode jsons or whatever
   *
   * @param string $file
   * @param mixed $data
   * @return bool
   */
	protected static function store(string $file, mixed $data): bool {
		return !!file_put_contents($file, '<?php return ' . var_export($data, true) . ';');
	}

	/**
	 * @param string $file
	 * @return array
	 */
	public static function load(string $file): array {
		assert(is_file($file));
		return include $file;
	}
}
