<?php declare(strict_types=1);

namespace Plugin\Data;

use App;
use Result;
use Throwable;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

final class DB {
	/**
	 * Swoole-safe per-coroutine connection model.
	 *
	 * `enable_coroutine` wraps every request in its own coroutine, and any
	 * coroutine switch (hooked I/O, Co::sleep, a realtime push, a deferred
	 * callback) can hand execution to another coroutine mid-flight. A single
	 * shared mysqli session is therefore unsafe: two coroutines would interleave
	 * queries on one connection and corrupt each other's results / transactions
	 * (and a half-read result wedges the very next query).
	 *
	 * So each coroutine gets its OWN connection for its lifetime, taken from a
	 * small per-shard free list (reused — not reconnected every time) and handed
	 * back on coroutine exit. Transaction state is tracked per coroutine too.
	 * Outside a coroutine (CLI, the cron loop, FPM) there is no concurrency, so a
	 * single long-lived connection is used under coroutine id -1.
	 *
	 * Swoole is detected at runtime — there is no compile-time dependency on the
	 * extension, so the same code runs unchanged without it.
	 */
	private const NO_CO = -1;
	/** Idle connections kept per shard for reuse; extras are closed on release. */
	private const MAX_IDLE = 32;

	/**
	 * Connection bound to a coroutine for its lifetime. Keyed [shard_id][cid].
	 * @var array<int,array<int,mysqli>>
	 */
	protected static array $bound = [];
	/**
	 * Idle connections available for reuse. Keyed [shard_id].
	 * @var array<int,list<mysqli>>
	 */
	protected static array $free = [];
	/**
	 * Reconnect-attempt counter. Keyed [shard_id][cid].
	 * @var array<int,array<int,int>>
	 */
	protected static array $try = [];
	/**
	 * Open-transaction flag, per coroutine. Keyed [cid].
	 * @var array<int,bool>
	 */
	protected static array $in_transaction = [];
	/**
	 * Whether a 2006 reconnect is allowed (disabled mid-transaction). Keyed [cid].
	 * @var array<int,bool>
	 */
	protected static array $allow_reconnect = [];
	/** @var array<int,string>|null */
	protected static ?array $shards = null;

	/** Current Swoole coroutine id, or -1 outside a coroutine. Runtime-detected. */
	private static function cid(): int {
		return \Coro::id();
	}

  // TODO: think about shards logic
  // Return false in callable to revert transaction
	/**
	 * @template V
	 * @param callable():Result<V> $func
	 * @param null|callable $rollback
	 * @return Result<V>
	 * @throws Throwable
	 */
	public static function transaction(callable $func, ?callable $rollback = null): Result {
		$cid = self::cid();
		// If we are already in THIS coroutine's transaction just call func — it
		// all folds into the single outer transaction (and rethrows to roll back).
		if (self::$in_transaction[$cid] ?? false) {
			/** @var Result<V> */
			return ok($func()->unwrap());
		}

		/** @var \mysqli $DB */
		$DB = self::getConnection(0);
		$DB->autocommit(false);
		$DB->begin_transaction();
		self::$in_transaction[$cid] = true;
		self::$allow_reconnect[$cid] = false;
		try {
			/** @var Result<V> $result */
			$result = $func();
			assert($result instanceof Result);
			// This throws exception if error
			$result->unwrap();
			$DB->commit();
			return $result;
		} catch (Throwable $e) {
			$DB->rollback();
			if ($rollback) {
				$rollback();
			}

			// Do simple return in case if it's result
			/** @phpstan-ignore-next-line Result may be thrown in legacy code paths */
			if ($e instanceof Result) {
				/** @var Result<V> $e */
				return $e;
			}
			throw $e;
		} finally {
			$DB->autocommit(true);
			self::$allow_reconnect[$cid] = true;
			self::$in_transaction[$cid] = false;
		}
	}

  /**
   * Execute database query, connecting (per coroutine) on first use
   *
   * @param string $query
   * @param array<string,mixed> $params
   * @param int $shard_id
	 * @return mixed
   */
	public static function query(string $query, array $params = [], $shard_id = 0): mixed {
		assert($shard_id >= 0 && $shard_id < 2048);

		$query = trim($query);
		$type = strtolower((string)strtok($query, ' '));

		static::initShards();
		static::validateShard($shard_id);

		$DB = static::getConnection($shard_id);

		$prepared = static::prepareQuery($query, $params, $DB);

		try {
			// STORE_RESULT (buffered): query() pulls the whole result client-side,
			// so the connection is never left mid-result — a half-read USE_RESULT
			// set would wedge the next query on this connection ("commands out of
			// sync"), which is what corrupts the sequential cron loop.
			$Result = $DB->query($prepared, MYSQLI_STORE_RESULT);
		} catch (mysqli_sql_exception $e) {
			return static::handleQueryException($e, $query, $params, $shard_id);
		}

		return static::processResult($Result, $type, $DB);
	}

	/**
	 * @return void
	 */
	private static function initShards(): void {
		if (static::$shards !== null) {
			return;
		}

		/** @var bool */
		$use_env = config('mysql.use_env');
		if ($use_env) {
			static::$shards = [
				'mysql:host=' . getenv('DB_IP')
					. ';port=' . getenv('DB_PORT')
					. ';dbname=' . getenv('DB_DATABASE')
					. ';user=' . getenv('DB_USER')
					. ';password=' . getenv('DB_PASSWORD'),
			];
		} else {
			/** @var array<int,string> $shard_config */
			$shard_config = config('mysql.shard');
			static::$shards = $shard_config;
		}
	}

	/**
	 * @param int $shard_id
	 * @return void
	 */
	private static function validateShard(int $shard_id): void {
		if (static::$shards !== null && isset(static::$shards[$shard_id])) {
			return;
		}

		trigger_error('No shards for mysql server specified');
	}

	/**
	 * Get (or open) the connection bound to the current coroutine for $shard_id.
	 * Reuses an idle connection from the per-shard free list when available.
	 *
	 * @param int $shard_id
	 * @return mysqli
	 */
	private static function getConnection(int $shard_id): mysqli {
		$cid = static::cid();

		if (isset(static::$bound[$shard_id][$cid])) {
			return static::$bound[$shard_id][$cid];
		}

		assert(static::$shards !== null);
		$DB = empty(static::$free[$shard_id])
		? static::createConnection(static::$shards[$shard_id])
		: array_pop(static::$free[$shard_id]);

		static::$bound[$shard_id][$cid] = $DB;
		static::$try[$shard_id][$cid] ??= 1;

		// Hand the connection back to the free list when the coroutine finishes,
		// so it is reused instead of churned. The non-coroutine slot (-1) lives
		// for the whole process and is never released here.
		if ($cid !== self::NO_CO && \class_exists('\Swoole\Coroutine', false)) {
			\Swoole\Coroutine::defer(
				static function () use ($shard_id, $cid): void {
					static::release($shard_id, $cid);
				}
			);
		}

		/** @var mysqli */
		return $DB;
	}

	/**
	 * Return a coroutine's connection to the free list (or drop it if unhealthy)
	 * once the coroutine ends, and clear its per-coroutine state.
	 *
	 * @param int $shard_id
	 * @param int $cid
	 * @return void
	 */
	private static function release(int $shard_id, int $cid): void {
		$DB = static::$bound[$shard_id][$cid] ?? null;
		unset(
			static::$bound[$shard_id][$cid],
			static::$try[$shard_id][$cid],
			static::$in_transaction[$cid],
			static::$allow_reconnect[$cid]
		);
		if (!($DB instanceof mysqli)) {
			return;
		}
		// Reset to a clean session before pooling: roll back anything dangling and
		// restore autocommit. A dead/erroring connection is dropped, not pooled.
		try {
			$DB->rollback();
			$DB->autocommit(true);
		} catch (Throwable) {
			static::closeQuietly($DB);
			return;
		}
		if (sizeof(static::$free[$shard_id] ?? []) >= self::MAX_IDLE) {
			static::closeQuietly($DB);
			return;
		}
		static::$free[$shard_id][] = $DB;
	}

	private static function closeQuietly(mysqli $DB): void {
		try {
			$DB->close();
		} catch (Throwable) {
			// connection already gone — nothing to do
		}
	}

	/**
	 * @param string $dsn
	 * @return mysqli
	 */
	private static function createConnection(string $dsn): mysqli {
		$dsn_key = function (string $key) use ($dsn): ?string {
			preg_match("|$key=([^;]+)|", $dsn, $m);
			return $m ? $m[1] : null;
		};

		$DB = mysqli_init();
		if ($DB === false) {
			throw new \RuntimeException('Failed to initialize mysqli');
		}
		/** @var int $connect_timeout */
		$connect_timeout = config('mysql.connect_timeout');
		$DB->options(MYSQLI_OPT_CONNECT_TIMEOUT, $connect_timeout);
		$DB->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
		$DB->real_connect(
			$dsn_key('host'),
			$dsn_key('user'),
			$dsn_key('password'),
			$dsn_key('dbname'),
			(int)$dsn_key('port'),
			'',
			MYSQLI_CLIENT_COMPRESS
		);
		return $DB;
	}

	/**
	 * @param string $query
	 * @param array<string,mixed> $params
	 * @param mysqli $DB
	 * @return string
	 */
	private static function prepareQuery(string $query, array $params, mysqli $DB): string {
		/** @var array<string,string|int|float> $placeholders */
		$placeholders = [];
		foreach ($params as $key => $value) {
			$placeholders[':' . $key] = is_array($value)
			? implode(
				',', array_map(
					function ($v) use ($DB) {
						return static::prepare($DB, $v);
					}, $value
				)
			)
			: static::prepare($DB, $value);
		}
		return $placeholders ? strtr($query, $placeholders) : $query;
	}

	/**
	 * Reconnect-on-2006 (server gone away). Re-prepares the raw query+params on
	 * the fresh connection (escaping is connection-specific). Bounded retries.
	 *
	 * @param Throwable $e
	 * @param string $query
	 * @param array<string,mixed> $params
	 * @param int $shard_id
	 * @return mixed
	 * @throws Throwable
	 */
	private static function handleQueryException(Throwable $e, string $query, array $params, int $shard_id): mixed {
		$cid = static::cid();
		$can_reconnect = static::$allow_reconnect[$cid] ?? true;
		// Counter survives the retry because we only drop $bound below (not $try),
		// and getConnection preserves an existing $try with `??=`.
		static::$try[$shard_id][$cid] = (static::$try[$shard_id][$cid] ?? 1) + 1;
		if ($e->getCode() !== 2006 || !$can_reconnect || static::$try[$shard_id][$cid] > 2) {
			App::log($e->getMessage(), ['query' => $query, 'trace' => $e->getTraceAsString()], 'db');
			throw $e;
		}
		$dead = static::$bound[$shard_id][$cid] ?? null;
		unset(static::$bound[$shard_id][$cid]);
		if ($dead instanceof mysqli) {
			static::closeQuietly($dead);
		}
		return static::query($query, $params, $shard_id);
	}

	/**
	 * @param mysqli_result|bool $Result
	 * @param string $type
	 * @param mysqli $DB
	 * @return mixed
	 */
	private static function processResult(mysqli_result|bool $Result, string $type, mysqli $DB): mixed {
		switch ($type) {
			case 'insert':
				return $DB->insert_id ?: $DB->affected_rows;
			case 'update':
			case 'delete':
				return $DB->affected_rows;
			case 'with':
			case 'select':
			case 'describe':
			case 'show':
				assert($Result instanceof mysqli_result);
				$result = $Result->fetch_all(MYSQLI_ASSOC);
				$Result->close();
				return $result;
			default:
				// Drain any result a non-standard statement (CALL/REPLACE/…) may
				// have produced, so the connection isn't left mid-result.
				if ($Result instanceof mysqli_result) {
					$Result->close();
				}
				return null;
		}
	}

	/**
	 * Simple ping and reconnect
	 * TODO: use ping and extract connection to separated func
	 * @param null|callable $init_fn
	 * @return void
	 */
	public static function ping(?callable $init_fn = null): void {
		static::query('SELECT 1');
		if (!$init_fn) {
			return;
		}

		$init_fn();
	}

	/**
	 * @param mysqli $DB
	 * @param mixed $item
	 * @return string|int|float
	 */
	protected static function prepare(mysqli $DB, mixed $item): string|int|float {
		if ($item === null) {
			return 'NULL';
		}
		if (is_bool($item)) {
			return $item ? 1 : 0;
		}
		if (is_int($item) || is_float($item)) {
			return $item;
		}
		/** @var string $str_item */
		$str_item = $item;
		return '"' . $DB->real_escape_string($str_item) . '"';
	}

	public static function inTransaction(): bool {
		return static::$in_transaction[static::cid()] ?? false;
	}
}
