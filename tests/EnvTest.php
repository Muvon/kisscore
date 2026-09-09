<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase {
	public function testGetPhpFilesRecursesAndFiltersByExtension(): void {
		$dir = __DIR__ . '/fixtures/phpfiles';
		$method = new ReflectionMethod(Env::class, 'getPHPFiles');
		/** @var array<string> $files */
		$files = $method->invoke(null, $dir);

		$names = array_map('basename', $files);
		$this->assertContains('a.php', $names, 'top-level php file');
		$this->assertContains('b.php', $names, 'nested php file (recursion)');
		$this->assertNotContains('notphp.txt', $names, 'non-php excluded');

		foreach ($files as $file) {
			$this->assertStringStartsWith($dir, $file, 'returns full paths');
		}
	}

	public function testParseMethodAnnotationSingle(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		$this->assertSame('POST', $method->invoke(null, "<?php\n/**\n * @route foo\n * @method POST\n */"));
	}

	public function testParseMethodAnnotationMultipleAndNormalises(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		// mixed case + comma/space separators collapse to an uppercase CSV list
		$this->assertSame('GET,POST', $method->invoke(null, "/**\n * @method get, post\n */"));
	}

	public function testParseMethodAnnotationAbsentMeansAny(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		$this->assertSame('', $method->invoke(null, "/**\n * @route foo\n */"));
	}

	public function testConfigIsCurrentWhenMtimesTie(): void {
		// The regression that deadlocked a boot: mtime granularity is one second,
		// so init landing in the same second as a template edit looks stale under
		// a strict `>` — and stays stale forever, because init is the only writer.
		[$cnf, $tpl] = self::configPair(1_700_000_000, 1_700_000_000);
		$this->assertTrue(self::isConfigCurrent($cnf, $tpl));
	}

	public function testConfigIsStaleWhenOlderThanTemplate(): void {
		[$cnf, $tpl] = self::configPair(1_700_000_000, 1_700_000_001);
		$this->assertFalse(self::isConfigCurrent($cnf, $tpl));
	}

	public function testMissingConfigIsNeverCurrent(): void {
		[, $tpl] = self::configPair(1_700_000_000, 1_700_000_000);
		$this->assertFalse(self::isConfigCurrent(sys_get_temp_dir() . '/kc-absent-config.php', $tpl));
	}

	public function testConfigWithoutTemplateIsCurrent(): void {
		// Nothing to render from, so there is nothing to be stale against —
		// otherwise waitInit() would spin out on a project with no template.
		[$cnf] = self::configPair(1_700_000_000, 1_700_000_000);
		$this->assertTrue(self::isConfigCurrent($cnf, sys_get_temp_dir() . '/kc-absent-template.tpl'));
	}

	public function testInitConfigRendersTemplateWithoutBuildingMaps(): void {
		if (!function_exists('yaml_parse_file')) {
			$this->markTestSkipped('ext-yaml is required to compile a config');
		}

		$root = self::makeProject("common:\n  name: '{{APP_ENV}}'\n");
		$env = self::snapshotEnv();
		// App::$debug is a typed static with no default: reading it before anything
		// has initialized it is an Error, so only restore what was actually there.
		$debug = new ReflectionProperty(App::class, 'debug');
		$had_debug = $debug->isInitialized();
		$prev_debug = $had_debug && App::$debug;
		try {
			putenv('APP_ENV=dev');
			Env::initConfig($root);

			$config = include $root . '/env/etc/config.php';
			$this->assertSame('dev', $config['common']['name'], 'template params substituted');
			$this->assertSame('dev', $config['common.name'], 'dot notation compiled');
			$this->assertFileDoesNotExist(
				$root . '/env/etc/action_map.php',
				'initConfig is the config prefix of init() — it must not scan actions'
			);
		} finally {
			self::restoreEnv($env);
			if ($had_debug) {
				App::$debug = $prev_debug;
			}
		}
	}

	/**
	 * @param string $body app.yml.tpl contents
	 * @return string project root
	 */
	private static function makeProject(string $body): string {
		$root = sys_get_temp_dir() . '/kc-env-' . bin2hex(random_bytes(6));
		mkdir($root . '/app/config', 0700, true);
		mkdir($root . '/env/etc', 0700, true);
		file_put_contents($root . '/app/config/app.yml.tpl', $body);
		return $root;
	}

	/**
	 * Two real files stamped to the given mtimes.
	 *
	 * @return array{0:string,1:string} config path, template path
	 */
	private static function configPair(int $cnf_ts, int $tpl_ts): array {
		$dir = sys_get_temp_dir() . '/kc-mtime-' . bin2hex(random_bytes(6));
		mkdir($dir, 0700, true);
		$cnf = $dir . '/config.php';
		$tpl = $dir . '/app.yml.tpl';
		file_put_contents($cnf, '<?php return [];');
		file_put_contents($tpl, "common:\n");
		touch($cnf, $cnf_ts);
		touch($tpl, $tpl_ts);
		return [$cnf, $tpl];
	}

	private static function isConfigCurrent(string $cnf, string $tpl): bool {
		$method = new ReflectionMethod(Env::class, 'isConfigCurrent');
		return (bool)$method->invoke(null, $cnf, $tpl);
	}

	/** @return array<string,string|false> */
	private static function snapshotEnv(): array {
		$keys = ['APP_ENV', 'APP_DIR', 'STATIC_DIR', 'CONFIG_DIR', 'ENV_DIR', 'BIN_DIR', 'RUN_DIR', 'LOG_DIR', 'VAR_DIR', 'TMP_DIR'];
		$out = [];
		foreach ($keys as $key) {
			$out[$key] = getenv($key);
		}
		return $out;
	}

	/** @param array<string,string|false> $env */
	private static function restoreEnv(array $env): void {
		foreach ($env as $key => $value) {
			if ($value === false) {
				putenv($key);
			} else {
				putenv($key . '=' . $value);
			}
		}
	}
}
