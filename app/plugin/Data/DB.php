<?php declare(strict_types=1);

namespace Plugin\Data;

use App;
use Result;
use Throwable;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

final class DB {
	protected static bool $reconnect = true;
	protected static array $pool = [];
	protected static array $try = [];
	protected static ?array $shards = null;

  // TODO: think about shards logic
  // Return false in callbable to revert transaction
	/**
	 * @template V
	 * @param callable $func
	 * @param null|callable $rollback
	 * @return Result<V>
	 * @throws Throwable
	 */
	public static function transaction(callable $func, ?callable $rollback = null): Result {
	  /** @var \mysqli $DB */
		$DB = static::$pool[0];
		$DB->autocommit(false);
		$DB->begin_transaction();
		static::$reconnect = false;
		try {
			/** @var Result<V> $result */
			$result = $func();
			assert($result instanceof Result);
			if ($result->err) {
				$DB->rollback();
				if ($rollback) {
					$rollback();
				}
			} else {
				$DB->commit();
			}
			$DB->autocommit(true);
			static::$reconnect = true;
			return $result;
		} catch (Throwable $e) {
			$DB->rollback();
			$DB->autocommit(true);
			static::$reconnect = true;
			if ($rollback) {
				$rollback();
			}
			throw $e;
		}
	}

  /**
   * Выполнение запроса к базе данных, выполняет коннект на запросе
   *
   * @param string $query
   * @param array $params
   * @param int $shard_id
	 * @return mixed
   * @throws Exception
   */
	public static function query(string $query, array $params = [], $shard_id = 0): mixed {
		assert($shard_id >= 0 && $shard_id < 2048);

		$query = trim($query);
		$type = strtolower(strtok($query, ' '));

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

		static::$shards = config('mysql.use_env')
		? [
			'mysql:host=' . getenv('DB_IP')
				. ';port=' . getenv('DB_PORT')
				. ';dbname=' . getenv('DB_DATABASE')
				. ';user=' . getenv('DB_USER')
				. ';password=' . getenv('DB_PASSWORD'),
		]
		: config('mysql.shard');
	}

	/**
	 * @param int $shard_id
	 * @return void
	 */
	private static function validateShard(int $shard_id): void {
		if (isset(static::$shards[$shard_id])) {
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
			$dsn = static::$shards[$shard_id];
			$DB = static::createConnection($dsn);
			static::$pool[$shard_id] = $DB;
			static::$try[$shard_id] = 1;
		}
		return static::$pool[$shard_id];
	}

	/**
	 * @param string $dsn
	 * @return mysqli
	 */
	private static function createConnection(string $dsn): mysqli {
		$dsn_key = function ($key) use ($dsn) {
			preg_match("|$key=([^;]+)|", $dsn, $m);
			return $m ? $m[1] : null;
		};

		$DB = mysqli_init();
		$DB->options(MYSQLI_OPT_CONNECT_TIMEOUT, config('mysql.connect_timeout'));
		$DB->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
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
	 * @param array $params
	 * @param mysqli $DB
	 * @return string
	 */
	private static function prepareQuery(string $query, array $params, mysqli $DB): string {
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
		static::$pool[$shard_id] = null;
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
	 * @throws Throwable
	 */
	public static function ping(?callable $init_fn = null) {
		static::query('SELECT 1');
		if (!$init_fn) {
			return;
		}

		$init_fn();
	}

	/**
	 * @param mysqli $DB
	 * @param mixed $item
	 * @return mixed
	 */
	protected static function prepare(mysqli $DB, mixed $item): mixed {
		return match (gettype($item)) {
			'NULL' => 'NULL',
			'boolean' => $item ? 1 : 0,
			'integer', 'double' => $item,
			default => '"' . $DB->real_escape_string($item) . '"',
		};
	}
}
