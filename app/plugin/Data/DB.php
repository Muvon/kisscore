<?php declare(strict_types=1);

namespace Plugin\Data;

use App;
use Result;
use Throwable;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

final class DB {
	protected static bool $in_transaction = false;
	protected static bool $reconnect = true;
	/** @var array<int,mysqli> */
	protected static array $pool = [];
	/** @var array<int,int> */
	protected static array $try = [];
	/** @var array<int,string>|null */
	protected static ?array $shards = null;

  // TODO: think about shards logic
  // Return false in callbable to revert transaction
	/**
	 * @template V
	 * @param callable():Result<V> $func
	 * @param null|callable $rollback
	 * @return Result<V>
	 * @throws Throwable
	 */
	public static function transaction(callable $func, ?callable $rollback = null): Result {
		// If we are already in transaction just call func
		// Cuz anyway it all goes to the single big transaction
		if (static::$in_transaction) {
			// Rewrap it just to make sure that we will throw exception
			// and process rollback
			/** @var Result<V> */
			return ok($func()->unwrap());
		}

	  /** @var \mysqli $DB */
		$DB = static::$pool[0];
		$DB->autocommit(false);
		$DB->begin_transaction();
		static::$in_transaction = true;
		static::$reconnect = false;
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
			static::$reconnect = true;
			static::$in_transaction = false;
		}
	}

  /**
   * Выполнение запроса к базе данных, выполняет коннект на запросе
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

		$query = static::prepareQuery($query, $params, $DB);

		try {
			$Result = $DB->query($query, MYSQLI_USE_RESULT);
		} catch (mysqli_sql_exception $e) {
			return static::handleQueryException($e, $query, $shard_id);
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
	 * @param int $shard_id
	 * @return mysqli
	 */
	private static function getConnection(int $shard_id): mysqli {
		if (!isset(static::$pool[$shard_id])) {
			assert(static::$shards !== null);
			$dsn = static::$shards[$shard_id];
			$DB = static::createConnection($dsn);
			static::$pool[$shard_id] = $DB;
			static::$try[$shard_id] = 1;
		}
		/** @var mysqli */
		return static::$pool[$shard_id];
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
	 * @param Throwable $e
	 * @param string $query
	 * @param int $shard_id
	 * @return mixed
	 * @throws Throwable
	 */
	private static function handleQueryException(Throwable $e, string $query, int $shard_id): mixed {
		if ($e->getCode() !== 2006 || !static::$reconnect || ++static::$try[$shard_id] > 2) {
			App::log($e->getMessage(), ['query' => $query, 'trace' => $e->getTraceAsString()], 'db');
			throw $e;
		}
		unset(static::$pool[$shard_id]);
		return static::query($query, [], $shard_id);
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
		return static::$in_transaction;
	}
}
