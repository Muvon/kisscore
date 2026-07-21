<?php declare(strict_types=1);

namespace KC\Test;

/**
 * One-line facade an app's test bootstrap calls to pull in the global DSL
 * (`test()` + `assert_*()`) without knowing the vendor path. Idempotent.
 */
final class Kit {
	private static bool $loaded = false;

	public static function load(): void {
		if (self::$loaded) {
			return;
		}
		require __DIR__ . '/functions.php';
		self::$loaded = true;
	}
}
