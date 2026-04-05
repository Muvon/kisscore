<?php declare(strict_types=1);

namespace Plugin\Data;

use App;
use Memcached;

final class Cache {
	final protected function __construct() {
	}

  /**
   * Connect to memcached server
   * @return Memcached
   */
	protected static function connect(): Memcached {
		static $Connection;
		if (!$Connection) {
			$Connection = new Memcached;
			$Connection->setOption(Memcached::OPT_BINARY_PROTOCOL, config('memcache.binary_protocol'));
			$Connection->setOption(Memcached::OPT_COMPRESSION, config('memcache.compression'));
			$Connection->setOption(Memcached::OPT_CONNECT_TIMEOUT, config('memcache.connect_timeout'));
			$Connection->setOption(Memcached::OPT_RETRY_TIMEOUT, config('memcache.retry_timeout'));
			$Connection->setOption(Memcached::OPT_SEND_TIMEOUT, config('memcache.send_timeout'));
			$Connection->setOption(Memcached::OPT_RECV_TIMEOUT, config('memcache.recv_timeout'));
			$Connection->setOption(Memcached::OPT_POLL_TIMEOUT, config('memcache.poll_timeout'));
			$Connection->setOption(Memcached::OPT_PREFIX_KEY, config('memcache.key_prefix'));

			/** @var string */
			$host = config('memcache.host');
			/** @var int */
			$port = config('memcache.port');
			if (!$Connection->addServer($host, $port)) {
				App::error('Error while connecting to memcache in memory');
			}
		}

		return $Connection;
	}

  /**
   * Get data from cache by key
   *
   * @param array<string>|string $key
   * @param mixed $default Closure | mixed; if callable, result is cached
   * @param int $ttl Optional TTL for expires
   * @return mixed cached value
   */
	public static function get(array|string $key, mixed $default = null, int $ttl = 0): mixed {
		$items = is_string($key) ? static::connect()->get($key) : static::connect()->getMulti($key);
		if (is_array($key)) {
			if (!is_array($items)) {
				$items = [];
			} else {
				$map = array_flip($key);
				/** @var array<int,mixed> $result */
				$result = [];
				foreach ($items as $k => $item) {
					$result[$map[$k]] = $item;
				}
				unset($items);
				$items = null;
				$items = &$result;
			}
		}

		if (false === $items) {
			$items = $default;
			if (is_string($key) && is_callable($default)) {
				static::set($key, $items = $default(), $ttl);
			}
		}
		return $items;
	}

	/**
	 * @param string $key
	 * @return int
	 */
	public static function getCas(string $key): int {
		/** @var array{cas:int}|false $info */
		$info = static::connect()->get($key, null, Memcached::GET_EXTENDED);
		return is_array($info) ? $info['cas'] : 0;
	}

	/**
	 * @param float $token
	 * @param string $key
	 * @param mixed $val
	 * @param int $ttl
	 * @return bool
	 */
	public static function setWithCas(float $token, string $key, mixed $val, int $ttl = 0): bool {
		return static::connect()->cas($token, $key, $val, $ttl);
	}

  /**
   * Set data for key, overwrite if exists
   *
   * @param string|array<string,mixed> $key Array or string
   * @param mixed $val
   * @param int $ttl
   * @return bool
   */
	public static function set(string|array $key, mixed $val, int $ttl = 0): bool {
		if (is_string($key)) {
			return static::connect()->set($key, $val, $ttl);
		}
		/** @var int $ttl_val */
		$ttl_val = $val;
		return static::connect()->setMulti($key, $ttl_val); // $val as $ttl
	}

  /**
   * Add data to cache only if key does not exist
   *
   * @param string $key
   * @param mixed $val data to add to cache
   *  @param int $ttl cache entry TTL
   * @return bool
   */
	public static function add(string $key, mixed $val, int $ttl = 0): bool {
		return static::connect()->add($key, $val, $ttl);
	}

  /**
  * Append text to the end of an existing string value
  *
  * @param string $key
  * @param string $val
  * @return bool
  */
	public static function append(string $key, string $val): bool {
		return static::connect()->append($key, $val);
	}

  /**
   * Prepend text to the beginning of an existing string value
   *
   * @param string $key
   * @param string $val
   * @return bool
   */
	public static function prepend(string $key, string $val): bool {
		return static::connect()->prepend($key, $val);
	}

	/**
	 * @param string|array<string> $key
	 * @return bool
	 */
	public static function remove(string|array $key): bool {
		if (is_string($key)) {
			return static::connect()->delete($key);
		}
		/** @var bool */
		return (bool)static::connect()->deleteMulti($key);
	}

	/**
	 * @param string|array<string> $key
	 * @return bool
	 */
	public static function delete(string|array $key): bool {
		return static::remove($key);
	}

	/**
	 * @param string $key
	 * @param int $ttl
	 * @return bool
	 */
	public static function touch(string $key, int $ttl = 0): bool {
		return static::connect()->touch($key, $ttl);
	}

  /**
   * Increment counter by n; creates key if missing
   *
   * @param string $key
   * @param int $count
   * @param int $ttl
   * @return int
   */
	public static function increment(string $key, int $count = 1, int $ttl = 0): int {
		$result = static::connect()->increment($key, $count);
		if (false === $result) {
			static::set($key, $count, $ttl);
			return $count;
		}
		return $result;
	}

	/**
	 * @param string $key
	 * @param int $count
	 * @param int $ttl
	 * @return int
	 */
	public static function decrement(string $key, int $count = 1, int $ttl = 0): int {
		return static::increment($key, -$count, $ttl);
	}

  /**
   * Flush entire cache pool
   * @return bool
   */
	public static function flush(): bool {
		return static::connect()->flush();
	}
}
