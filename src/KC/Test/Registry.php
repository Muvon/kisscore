<?php declare(strict_types=1);

namespace KC\Test;

use Closure;

/**
 * The flat registry every `test()` call appends to. One process, one array —
 * this is the whole "state" of the runner. `$current_file` is stamped by the
 * Runner around each discovered file so a test remembers where it came from
 * (used for per-file filtering and the JUnit/report grouping).
 */
final class Registry {
	/** @var list<array{name:string,fn:Closure,file:string}> */
	public static array $tests = [];

	public static string $current_file = '';

	public static function add(string $name, Closure $fn): void {
		self::$tests[] = ['name' => $name, 'fn' => $fn, 'file' => self::$current_file];
	}

	public static function reset(): void {
		self::$tests = [];
		self::$current_file = '';
	}
}
