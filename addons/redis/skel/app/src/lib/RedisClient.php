<?php
namespace App\Lib;

use Redis;
use Exception;

abstract class RedisClient {
  const CONNECT_TIMEOUT = 1;
  const RECONNECT_TIMEOUT_MS = 100;
  const READ_TIMEOUT = 1;

  protected Redis $Storage;
  protected Redis $Multi;

  protected static $Instance;
  protected int $db = 0;
  protected array $subscribers = [];

  protected function __construct(string $dsn) {
    // First find out we use socket or port
    [$host, $port] = match ($dsn[0]) {
      '/' => [$dsn, 0],
      default => [strtok($dsn, ':'), intval(strtok(':'))],
    };

    $connect_timeout = getenv('REDIS_CONNECT_TIMEOUT') ?: static::CONNECT_TIMEOUT;
    $reconnect_timeout_ms = getenv('REDIS_RECONNECT_TIMEOUT_MS') ?: static::RECONNECT_TIMEOUT_MS;
    $read_timeout = getenv('REDIS_READ_TIMEOUT') ?: static::READ_TIMEOUT;

    $this->Client = new Redis();
    $this->Client->pconnect($host, $port, $connect_timeout, null, $reconnect_timeout_ms);
    $this->Client->select($this->db);
    $this->Client->setOption(Redis::OPT_READ_TIMEOUT, $read_timeout);

    $this->Multi = new Redis();
    $this->Multi->pconnect($host, $port, $connect_timeout, null, $reconnect_timeout_ms);
    $this->Multi->select($this->db);
    $this->Multi->setOption(Redis::OPT_READ_TIMEOUT, $read_timeout);
    $this->Multi->multi();
  }

  public static function create(): static {
    return new static(getenv('REDIS_SOCK') ?: getenv('REDIS_HOST') . ':' . getenv('REDIS_PORT'));
  }

  public static function instance(): static {
    if (!isset(static::$Instance)) {
      static::$Instance = static::create();
    }

    return static::$Instance;
  }

  public function commit(): bool {
    $res = $this->Multi->exec();
    $this->Multi->multi();

    if (is_array($res) && in_array(false, $res, true)) {
      throw new Exception('Error while commiting to redis client');
    }
    return true;
  }

  public function subscribe(string $channel, Callable $func, string $type = 'raw'): static {
    $this->subscribers[$channel] = [$func, $type];
    return $this;
  }

  public function listen(int $timeout = 60): static {
    $this->Client->setOption(Redis::OPT_READ_TIMEOUT, $timeout);
    $this->Client->subscribe(array_keys($this->subscribers), [$this, 'listenProcessor']);
    return $this;
  }

  protected function listenProcessor(Redis $Client, string $channel, string $message) {
    $type = $this->subscribers[$channel][1];
    return $this->subscribers[$channel][0]($type === 'json' ? json_decode($message, true) : $message);
  }

  public function publish(string $channel, mixed $data, string $type = 'raw'): static {
    $this->Client->publish($channel, $type === 'json' ? json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE) : $data);
    return $this;
  }

  public function __call(string $method, array $args = []): mixed {
    return $this->Client->$method(...$args);
  }

}
