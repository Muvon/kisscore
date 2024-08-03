<?php declare(strict_types=1);

namespace Plugin\Data;

use App;
use Memcached;

final class Cache {
	final protected function __construct() {
	}

  /**
   * Подключение к серверну мемкэша
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
   * Получение данных из кэша по ключу
   *
   * @param array|string $key
   * @param mixed $default Closure | mixed если это замыкание, то кэш записвыается
   * @param int $ttl Optional TTL for expires
   * @return mixed кэшированное данное
   */
	public static function get(array|string $key, mixed $default = null, int $ttl = 0): mixed {
		$items = is_string($key) ? static::connect()->get($key) : static::connect()->getMulti($key);
		if (is_array($key)) {
			if (!$items) {
				$items = [];
			} else {
				$map = array_flip($key);
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
	 * @throws Exception
	 */
	public static function getCas(string $key): int {
		$info = static::connect()->get($key, null, Memcached::GET_EXTENDED);
		return $info['cas'] ?? 0;
	}

	/**
	 * @param float $token
	 * @param string $key
	 * @param mixed $val
	 * @param int $ttl
	 * @return bool
	 * @throws Exception
	 */
	public static function setWithCas(float $token, string $key, mixed $val, int $ttl = 0): bool {
		return static::connect()->cas($token, $key, $val, $ttl);
	}

  /**
   * Установка данные для ключа, перезапись в случае нахождения
   *
   * @param string|array $key Массив или строка
   * @param mixed $val
   * @param int $ttl
   * @return mixed
   */
	public static function set(string|array $key, mixed $val, int $ttl = 0): mixed {
		assert(is_string($key) || is_array($key));
		assert(is_int($ttl));

		return is_string($key)
		? static::connect()->set($key, $val, $ttl)
		: static::connect()->setMulti($key, $val) // $val as $ttl
		;
	}

  /**
   * Добавление данных в кэш, если их там нет
   *
   * @param string $key
   * @param mixed $val данные для добавления в кэш
   *  @param int $ttl время жизни кэшируемого объекта
   * @return bool
   */
	public static function add(string $key, mixed $val, int $ttl = 0): bool {
		return static::connect()->add($key, $val, $ttl);
	}

  /**
  * Добавление какого-то текста к данному в конец строки
  *
  * @param string $key
  * @param string $val
  * @return bool
  */
	public static function append(string $key, string $val): bool {
		return static::connect()->append($key, $val);
	}

  /**
   * Добавление какого-то текста к данному в начало строки
   *
   * @param string $key
   * @param string $val
   * @return bool
   */
	public static function prepend(string $key, string $val): bool {
		return static::connect()->prepend($key, $val);
	}

	/**
	 * @param string|array $key
	 * @return bool
	 * @throws Exception
	 */
	public static function remove(string|array $key): bool {
		return is_string($key)
		? static::connect()->delete($key)
		: static::connect()->deleteMulti($key);
	}

	/**
	 * @param string|array $key
	 * @return bool
	 * @throws Exception
	 */
	public static function delete(string|array $key): bool {
		return static::remove($key);
	}

	/**
	 * @param string $key
	 * @param int $ttl
	 * @return bool
	 * @throws Exception
	 */
	public static function touch(string $key, int $ttl = 0): bool {
		return static::connect()->touch($key, $ttl);
	}

  /**
   * Увеличения счетчика на n число раз
   * Если ключа нет, он создается
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
	 * @throws Exception
	 */
	public static function decrement(string $key, int $count = 1, int $ttl = 0): int {
		return static::increment($key, -$count, $ttl);
	}

  /**
   * Очистка всего пула кэша
   * @return bool
   */
	public static function flush(): bool {
		return static::connect()->flush();
	}
}
