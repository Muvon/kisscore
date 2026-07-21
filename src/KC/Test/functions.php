<?php declare(strict_types=1);

/**
 * The global test DSL — `test()` + `assert_*()`. Deliberately global (not a
 * base class) so a test file reads as plain top-to-bottom code with zero
 * boilerplate, exactly the hand-rolled style KISScore apps already use. Loaded
 * once by `KC\Test\Kit::load()`; every definition is `function_exists`-guarded
 * so an app that already ships its own copies keeps working unchanged.
 *
 * A failed assertion throws; the Runner catches it and records the test as
 * failed. That is the entire contract.
 */

use KC\Test\AssertionFailed;
use KC\Test\Registry;

if (!function_exists('test')) {
	/** Register a named test. The closure runs once; a throw = failure. */
	function test(string $name, Closure $fn): void {
		Registry::add($name, $fn);
	}
}

if (!function_exists('assert_true')) {
	function assert_true(bool $condition, string $message = 'expected true'): void {
		if (!$condition) {
			throw new AssertionFailed($message);
		}
	}
}

if (!function_exists('assert_false')) {
	function assert_false(bool $condition, string $message = 'expected false'): void {
		if ($condition) {
			throw new AssertionFailed($message);
		}
	}
}

if (!function_exists('assert_null')) {
	function assert_null(mixed $value, string $message = 'expected null'): void {
		if ($value !== null) {
			throw new AssertionFailed($message . ', got ' . var_export($value, true));
		}
	}
}

if (!function_exists('assert_same')) {
	/** Strict (===) equality. */
	function assert_same(mixed $expected, mixed $actual, string $message = ''): void {
		if ($expected !== $actual) {
			$detail = $message !== '' ? $message . ': ' : '';
			throw new AssertionFailed(
				$detail . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true)
			);
		}
	}
}

if (!function_exists('assert_equals')) {
	/** Loose (==) equality — use only when a type-coercing compare is intended. */
	function assert_equals(mixed $expected, mixed $actual, string $message = ''): void {
		if ($expected != $actual) {
			$detail = $message !== '' ? $message . ': ' : '';
			throw new AssertionFailed(
				$detail . 'expected == ' . var_export($expected, true) . ', got ' . var_export($actual, true)
			);
		}
	}
}

if (!function_exists('assert_contains')) {
	/** Substring-in-string, or value-in-array. */
	function assert_contains(mixed $needle, mixed $haystack, string $message = ''): void {
		$ok = is_array($haystack)
			? in_array($needle, $haystack, true)
			: (is_string($haystack) && is_string($needle) && str_contains($haystack, $needle));
		if (!$ok) {
			$detail = $message !== '' ? $message . ': ' : '';
			throw new AssertionFailed(
				$detail . var_export($needle, true) . ' not found in ' . var_export($haystack, true)
			);
		}
	}
}

if (!function_exists('assert_count')) {
	/** @param \Countable|array<mixed> $countable */
	function assert_count(int $expected, \Countable|array $countable, string $message = ''): void {
		$actual = count($countable);
		if ($actual !== $expected) {
			$detail = $message !== '' ? $message . ': ' : '';
			throw new AssertionFailed($detail . "expected count {$expected}, got {$actual}");
		}
	}
}

if (!function_exists('assert_throws')) {
	/**
	 * Assert `$work` throws `$exception`. When `$message` is given it must equal
	 * the thrown exception's message exactly.
	 *
	 * @param class-string<Throwable> $exception
	 */
	function assert_throws(Closure $work, string $exception = Throwable::class, string $message = ''): void {
		try {
			$work();
		} catch (Throwable $e) {
			if (!$e instanceof $exception) {
				throw new AssertionFailed('expected ' . $exception . ', got ' . $e::class . ' (' . $e->getMessage() . ')');
			}
			if ($message !== '' && $e->getMessage() !== $message) {
				throw new AssertionFailed(
					'expected exception message ' . var_export($message, true) . ', got ' . var_export($e->getMessage(), true)
				);
			}
			return;
		}
		throw new AssertionFailed('expected ' . $exception . ' to be thrown, nothing was');
	}
}
