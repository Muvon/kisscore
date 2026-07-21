<?php declare(strict_types=1);

namespace KC\Test;

use Closure;
use Throwable;

/**
 * The whole test runner: discover `*Test.php`, require them (each `test()`
 * registers a closure), run the matching ones in a single process, and report.
 * Blazing fast by construction — no reflection, no per-test process, no
 * container; just require + call the closure. Coverage is opt-in via pcov (the
 * fast, purpose-built extension); absent, everything still runs and coverage is
 * skipped with a note.
 *
 * Two entry points:
 *   main($argv, $root)  — the generic `bin/test` CLI (parses --dir/--bootstrap).
 *   run($root, $dirs, $bootstrap, $argv) — for an app's own thin run.php shim,
 *      which already knows its dirs + env bootstrap.
 *
 * Options (shared by both): --filter=<substr|/regex/> --coverage-clover=<file>
 *   --coverage-text --coverage-src=<prefix[,prefix]> --junit=<file>
 *   --stop-on-fail --list --quiet.
 */
final class Runner {
	/** Generic CLI entry: `bin/test [--dir=tests] [--bootstrap=file] [options] [dir ...]`. */
	public static function main(array $argv, string $root): int {
		[$opts, $positional] = self::parseOptions(array_slice($argv, 1));

		$dirs = $opts['dir'] ?? [];
		foreach ($positional as $p) {
			$dirs[] = $p;
		}
		if ($dirs === []) {
			$dirs = ['tests'];
		}
		$dirs = array_map(static fn(string $d): string => self::absolutize($d, $root), $dirs);

		$bootstrap = isset($opts['bootstrap']) ? self::absolutize((string)$opts['bootstrap'][0], $root) : null;
		return self::execute($root, $dirs, $bootstrap, $opts);
	}

	/**
	 * Programmatic entry for an app run.php shim. `$argv` is forwarded so
	 * `--filter=…` etc. still work on the app's own command line.
	 *
	 * @param list<string> $dirs
	 */
	public static function run(string $root, array $dirs, ?string $bootstrap = null, array $argv = []): int {
		[$opts, $positional] = self::parseOptions(array_slice($argv, 1));
		// Positional args on the shim override the default dirs (run one dir/file).
		if ($positional !== []) {
			$dirs = array_map(static fn(string $d): string => self::absolutize($d, $root), $positional);
		}
		return self::execute($root, $dirs, $bootstrap, $opts);
	}

	/**
	 * @param list<string> $dirs
	 * @param array<string,list<string>|bool> $opts
	 */
	private static function execute(string $root, array $dirs, ?string $bootstrap, array $opts): int {
		Kit::load();

		if ($bootstrap !== null) {
			if (!is_file($bootstrap)) {
				fwrite(STDERR, "bootstrap not found: {$bootstrap}\n");
				return 2;
			}
			require $bootstrap;
		}

		$files = self::discover($dirs);
		if ($files === []) {
			fwrite(STDERR, 'No test files found in: ' . implode(', ', $dirs) . "\n");
			return 2;
		}

		$before = count(Registry::$tests);
		foreach ($files as $file) {
			Registry::$current_file = $file;
			require $file;
		}
		Registry::$current_file = '';

		$filter = isset($opts['filter']) ? (string)$opts['filter'][0] : '';
		$tests = self::filter(array_slice(Registry::$tests, $before), $filter);

		if (isset($opts['list'])) {
			foreach ($tests as $t) {
				echo $t['name'] . "\n";
			}
			printf("\n%d test(s)\n", count($tests));
			return 0;
		}

		if ($tests === []) {
			fwrite(STDERR, 'No tests match filter: ' . var_export($filter, true) . "\n");
			return 2;
		}

		$quiet = isset($opts['quiet']);
		$stop = isset($opts['stop-on-fail']);
		$cover = isset($opts['coverage-clover']) || isset($opts['coverage-text']);
		$pcov = $cover && function_exists('pcov\\start');
		if ($cover && !$pcov) {
			fwrite(STDERR, "coverage requested but the pcov extension is not loaded — skipping coverage.\n");
			$cover = false;
		}

		if ($pcov) {
			\pcov\clear();
			\pcov\start();
		}

		$results = [];
		$failures = 0;
		$t_all = microtime(true);
		foreach ($tests as $t) {
			$t0 = microtime(true);
			$err = null;
			try {
				($t['fn'])();
			} catch (Throwable $e) {
				$err = $e;
				$failures++;
			}
			$dt = microtime(true) - $t0;
			$results[] = ['name' => $t['name'], 'file' => $t['file'], 'time' => $dt, 'error' => $err];
			if ($err === null) {
				if (!$quiet) {
					printf("PASS %s (%.1fms)\n", $t['name'], $dt * 1000);
				}
			} else {
				$kind = $err instanceof AssertionFailed ? 'FAIL' : 'ERROR';
				printf("%s %s: %s\n", $kind, $t['name'], $err->getMessage());
				if ($stop) {
					break;
				}
			}
		}
		$elapsed = microtime(true) - $t_all;

		if ($pcov) {
			\pcov\stop();
			$src = isset($opts['coverage-src'])
				? array_map(static fn(string $p): string => self::absolutize($p, $root), explode(',', (string)$opts['coverage-src'][0]))
				: [];
			$cov = self::collectCoverage($root, $src);
			if (isset($opts['coverage-clover'])) {
				self::writeClover($cov, self::absolutize((string)$opts['coverage-clover'][0], $root));
			}
			if (isset($opts['coverage-text'])) {
				self::printCoverageText($cov);
			}
		}

		if (isset($opts['junit'])) {
			self::writeJunit($results, self::absolutize((string)$opts['junit'][0], $root), $elapsed);
		}

		printf("\n%d test(s), %d failure(s), %.2fs\n", count($results), $failures, $elapsed);
		return $failures === 0 ? 0 : 1;
	}

	// ---------------------------------------------------------------- options --

	/**
	 * Parse `--key=value` / `--flag` args; everything else is positional. A
	 * repeated `--key` accumulates (so `--dir=a --dir=b` both count).
	 *
	 * @param list<string> $args
	 * @return array{0:array<string,list<string>|bool>,1:list<string>}
	 */
	private static function parseOptions(array $args): array {
		$opts = [];
		$positional = [];
		foreach ($args as $arg) {
			if (!str_starts_with($arg, '--')) {
				$positional[] = $arg;
				continue;
			}
			$body = substr($arg, 2);
			if (str_contains($body, '=')) {
				[$k, $v] = explode('=', $body, 2);
				$opts[$k][] = $v;
			} else {
				$opts[$body] = true;
			}
		}
		return [$opts, $positional];
	}

	private static function absolutize(string $path, string $root): string {
		if ($path === '') {
			return $root;
		}
		return $path[0] === '/' ? $path : rtrim($root, '/') . '/' . $path;
	}

	// -------------------------------------------------------------- discovery --

	/**
	 * @param list<string> $dirs
	 * @return list<string>
	 */
	private static function discover(array $dirs): array {
		$files = [];
		foreach ($dirs as $dir) {
			if (is_file($dir)) {
				$files[$dir] = true;
				continue;
			}
			if (!is_dir($dir)) {
				continue;
			}
			$it = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
			);
			foreach ($it as $file) {
				$name = $file->getFilename();
				if (str_ends_with($name, 'Test.php')) {
					$files[$file->getPathname()] = true;
				}
			}
		}
		$out = array_keys($files);
		sort($out);
		return $out;
	}

	/**
	 * @param list<array{name:string,fn:Closure,file:string}> $tests
	 * @return list<array{name:string,fn:Closure,file:string}>
	 */
	private static function filter(array $tests, string $filter): array {
		if ($filter === '') {
			return $tests;
		}
		$is_regex = strlen($filter) > 1 && $filter[0] === '/' && str_ends_with($filter, '/');
		return array_values(array_filter($tests, static function (array $t) use ($filter, $is_regex): bool {
			return $is_regex
				? preg_match($filter, $t['name']) === 1
				: stripos($t['name'], $filter) !== false;
		}));
	}

	// --------------------------------------------------------------- coverage --

	/**
	 * @param list<string> $srcPrefixes  when empty, include everything outside vendor/ + tests/
	 * @return array<string,array<int,int>>  file => [line => hits]
	 */
	private static function collectCoverage(string $root, array $srcPrefixes): array {
		/** @var array<string,array<int,int>> $raw */
		$raw = \pcov\collect();
		$out = [];
		foreach ($raw as $file => $lines) {
			if ($srcPrefixes !== []) {
				$keep = false;
				foreach ($srcPrefixes as $p) {
					if (str_starts_with($file, $p)) {
						$keep = true;
						break;
					}
				}
				if (!$keep) {
					continue;
				}
			} elseif (str_contains($file, '/vendor/') || str_contains($file, '/tests/')) {
				continue;
			}
			$out[$file] = $lines;
		}
		ksort($out);
		return $out;
	}

	/** @param array<string,array<int,int>> $cov */
	private static function writeClover(array $cov, string $path): void {
		$ts = (string)($_SERVER['REQUEST_TIME'] ?? 0);
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= "<coverage generated=\"{$ts}\"><project timestamp=\"{$ts}\">\n";
		$total = 0;
		$covered = 0;
		foreach ($cov as $file => $lines) {
			$fstmt = count($lines);
			$fcov = 0;
			$xml .= '  <file name="' . htmlspecialchars($file, ENT_XML1) . "\">\n";
			foreach ($lines as $num => $hits) {
				$c = $hits > 0 ? 1 : 0;
				$fcov += $c;
				$xml .= "    <line num=\"{$num}\" type=\"stmt\" count=\"{$c}\"/>\n";
			}
			$xml .= "    <metrics loc=\"{$fstmt}\" ncloc=\"{$fstmt}\" statements=\"{$fstmt}\" coveredstatements=\"{$fcov}\"/>\n";
			$xml .= "  </file>\n";
			$total += $fstmt;
			$covered += $fcov;
		}
		$xml .= "  <metrics files=\"" . count($cov) . "\" loc=\"{$total}\" ncloc=\"{$total}\" statements=\"{$total}\" coveredstatements=\"{$covered}\"/>\n";
		$xml .= "</project></coverage>\n";
		file_put_contents($path, $xml);
		fwrite(STDERR, "coverage (clover) written: {$path}\n");
	}

	/** @param array<string,array<int,int>> $cov */
	private static function printCoverageText(array $cov): void {
		$total = 0;
		$covered = 0;
		foreach ($cov as $lines) {
			$total += count($lines);
			foreach ($lines as $hits) {
				if ($hits > 0) {
					$covered++;
				}
			}
		}
		$pct = $total > 0 ? $covered / $total * 100 : 0.0;
		printf("coverage: %d/%d lines (%.1f%%) across %d files\n", $covered, $total, $pct, count($cov));
	}

	// ------------------------------------------------------------------ junit --

	/** @param list<array{name:string,file:string,time:float,error:?Throwable}> $results */
	private static function writeJunit(array $results, string $path, float $elapsed): void {
		$failures = 0;
		foreach ($results as $r) {
			if ($r['error'] !== null) {
				$failures++;
			}
		}
		$xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$xml .= '<testsuites><testsuite name="kisscore" tests="' . count($results)
			. "\" failures=\"{$failures}\" time=\"" . sprintf('%.4f', $elapsed) . "\">\n";
		foreach ($results as $r) {
			$name = htmlspecialchars($r['name'], ENT_XML1);
			$class = htmlspecialchars(basename($r['file'], '.php'), ENT_XML1);
			$time = sprintf('%.4f', $r['time']);
			if ($r['error'] === null) {
				$xml .= "  <testcase classname=\"{$class}\" name=\"{$name}\" time=\"{$time}\"/>\n";
			} else {
				$err = $r['error'];
				$type = $err instanceof AssertionFailed ? 'failure' : 'error';
				$msg = htmlspecialchars($err->getMessage(), ENT_XML1);
				$xml .= "  <testcase classname=\"{$class}\" name=\"{$name}\" time=\"{$time}\">\n";
				$xml .= "    <{$type} message=\"{$msg}\">" . htmlspecialchars($err::class . ': ' . $err->getMessage(), ENT_XML1) . "</{$type}>\n";
				$xml .= "  </testcase>\n";
			}
		}
		$xml .= "</testsuite></testsuites>\n";
		file_put_contents($path, $xml);
		fwrite(STDERR, "junit written: {$path}\n");
	}
}
