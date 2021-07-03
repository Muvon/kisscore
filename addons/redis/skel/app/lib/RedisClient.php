<?php
namespace App\Lib;

use Redis;

abstract class RedisClient {
  protected Redis $Storage;
  protected Redis $Multi;

  protected int $db = 0;

  protected array $subscribers = [];

  protected function __construct(string $host, int $port) {
    $this->Client = new Redis();
    $this->Client->connect($host, $port);
    $this->Client->select($this->db);

    $this->Multi = new Redis();
    $this->Multi->connect($host, $port);
    $this->Multi->select($this->db);
    $this->Multi->multi();
  }

  public static function create(): static {
    return new static(getenv('REDIS_HOST'), getenv('REDIS_PORT'));
  }

  public function commit(): bool {
    $res = $this->Multi->exec();
    $this->Multi->multi();

    if (is_array($res) && in_array(false, $res, true)) {
      return false;
    }
    return true;
  }

  public function subscribe(string $channel, Callable $func): static {
    $this->subscribers[$channel] = $func;
    return $this;
  }

  public function listen(): static {
    $this->Client->subscribe(array_keys($this->subscribers), [$this, 'listenProcessor']);
    return $this;
  }

  protected function listenProcessor(Redis $Client, string $channel, string $message) {
    return $this->subscribers[$channel](json_decode($message, true));
  }

  public function publish(string $channel, mixed $data): static {
    $this->Client->publish($channel, json_encode($data));
    return $this;
  }


}
