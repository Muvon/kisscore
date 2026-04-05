<?php declare(strict_types=1);

/**
 * Session management (Swoole-compatible in-memory store)
 *
 * For API backends, prefer token-based auth over sessions.
 * This class provides per-request session state — not shared across workers.
 */
final class Session {
	/** @var array<string,mixed> */
	protected static array $container = [];

	protected static bool $started = false;

	/**
	 * Start session for current request
	 */
	public static function start(): void {
		static::$container = [];
		static::$started = true;
	}

	/**
	 * @return bool
	 */
	public static function isStarted(): bool {
		return static::$started;
	}

	/**
	 * Reset session state (call at start of each Swoole request)
	 */
	public static function reset(): void {
		static::$container = [];
		static::$started = false;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function has(string $key): bool {
		return isset(static::$container[$key]);
	}

	/**
	 * Add value only if key doesn't exist
	 * @param string $key
	 * @param mixed $value Callable will be invoked and result stored
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
	 */
	public static function set(string $key, mixed $value): void {
		static::$container[$key] = $value;
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function remove(string $key): bool {
		if (!isset(static::$container[$key])) {
			return false;
		}

		unset(static::$container[$key]);
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
		if (!static::has($key) && $default !== null && is_callable($default)) {
			$default = $default();
			static::set($key, $default);
		}
		return static::$container[$key] ?? $default;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function all(): array {
		return static::$container;
	}

	/**
	 * Destroy all session data
	 */
	public static function destroy(): void {
		static::$container = [];
		static::$started = false;
	}
}
