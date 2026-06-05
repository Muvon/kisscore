<?php declare(strict_types=1);

/**
 * Session management (Swoole-compatible in-memory store).
 *
 * For API backends, prefer token-based auth over sessions. State is
 * coroutine-local (see Coro): per-request, never shared across concurrent
 * requests or workers.
 */
final class Session {
	/**
	 * Per-coroutine session state.
	 * @return array{started:bool,container:array<string,mixed>}
	 */
	private static function &state(): array {
		/** @var array{started:bool,container:array<string,mixed>} $s */
		$s = &Coro::bag(
			'session',
			static fn(): array => ['started' => false, 'container' => []]
		);
		return $s;
	}

	/**
	 * Start session for current request.
	 * @return void
	 */
	public static function start(): void {
		$s = &static::state();
		$s['container'] = [];
		$s['started'] = true;
	}

	/**
	 * @return bool
	 */
	public static function isStarted(): bool {
		return static::state()['started'];
	}

	/**
	 * Reset session state (per-request; coroutine-local).
	 * @return void
	 */
	public static function reset(): void {
		$s = &static::state();
		$s['container'] = [];
		$s['started'] = false;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function has(string $key): bool {
		return isset(static::state()['container'][$key]);
	}

	/**
	 * Add value only if key doesn't exist.
	 * @param string $key
	 * @param mixed $value Callable will be invoked and result stored
	 * @return void
	 */
	public static function add(string $key, mixed $value): void {
		if (static::has($key)) {
			return;
		}

		static::set($key, is_callable($value) ? $value() : $value);
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function set(string $key, mixed $value): void {
		$s = &static::state();
		$s['container'][$key] = $value;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function remove(string $key): bool {
		$s = &static::state();
		if (!isset($s['container'][$key])) {
			return false;
		}

		unset($s['container'][$key]);
		return true;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function delete(string $key): bool {
		return static::remove($key);
	}

	/**
	 * @param string $key
	 * @param mixed $default Callable will be invoked, result stored and returned
	 * @return mixed
	 */
	public static function get(string $key, mixed $default = null): mixed {
		$s = &static::state();
		if (!isset($s['container'][$key]) && $default !== null && is_callable($default)) {
			$default = $default();
			$s['container'][$key] = $default;
		}
		return $s['container'][$key] ?? $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		return static::state()['container'];
	}

	/**
	 * Destroy all session data.
	 * @return void
	 */
	public static function destroy(): void {
		$s = &static::state();
		$s['container'] = [];
		$s['started'] = false;
	}
}
