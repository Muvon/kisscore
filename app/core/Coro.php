<?php declare(strict_types=1);

/**
 * Coroutine-local state helper.
 *
 * Swoole wraps every request in its own coroutine and may switch coroutines on
 * any hooked I/O, Co::sleep, deferred callback or realtime push. Per-request
 * state kept in plain static properties would therefore be shared across the
 * concurrent requests running inside one worker and corrupt under load. This
 * helper keeps such state in a per-coroutine bag instead, mirroring the
 * per-coroutine connection model in Plugin\Data\DB.
 *
 * Outside a coroutine (CLI, the cron loop, FPM) there is no concurrency, so a
 * single process-wide bag is used under coroutine id -1. Swoole is detected at
 * runtime — there is no compile-time dependency on the extension, so the same
 * code runs unchanged without it.
 */
final class Coro {
	private const NO_CO = -1;

	/**
	 * Per-coroutine bags, namespaced: [namespace][cid] => state.
	 * @var array<string,array<int,mixed>>
	 */
	private static array $bags = [];

	/**
	 * Current Swoole coroutine id, or -1 outside a coroutine.
	 * @return int
	 */
	public static function id(): int {
		if (class_exists('\Swoole\Coroutine', false)) {
			$cid = \Swoole\Coroutine::getCid();
			if (is_int($cid) && $cid >= 0) {
				return $cid;
			}
		}
		return self::NO_CO;
	}

	/**
	 * Whether execution is currently inside a Swoole coroutine.
	 * @return bool
	 */
	public static function active(): bool {
		return self::id() !== self::NO_CO;
	}

	/**
	 * Reference to the current coroutine's bag under $ns, created from $factory
	 * on first access and dropped automatically when the coroutine ends. The
	 * returned reference lets callers mutate nested state in place.
	 *
	 * @param string $ns
	 * @param callable():mixed $factory
	 * @return mixed reference to the bag
	 */
	public static function &bag(string $ns, callable $factory) {
		$cid = self::id();
		if (!isset(self::$bags[$ns][$cid])) {
			self::$bags[$ns][$cid] = $factory();
			if ($cid !== self::NO_CO && class_exists('\Swoole\Coroutine', false)) {
				\Swoole\Coroutine::defer(
					static function () use ($ns, $cid): void {
						unset(self::$bags[$ns][$cid]);
					}
				);
			}
		}
		return self::$bags[$ns][$cid];
	}

	/**
	 * Drop the current coroutine's bag under $ns. Used by reset()/destroy()
	 * helpers that re-initialize per-request state explicitly.
	 * @param string $ns
	 * @return void
	 */
	public static function clear(string $ns): void {
		unset(self::$bags[$ns][self::id()]);
	}
}
