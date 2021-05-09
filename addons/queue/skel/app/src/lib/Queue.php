<?php
namespace App\Lib;

use Beanstalk\Client;

class Queue {
  const RELEASE_DELAY = 5;

  protected static function client() {
    static $Client;

    if (!$Client) {
      $Client = new Client(config('queue'));
      $Client->connect();
    }

    return $Client;
  }

  public static function add($ns, $job) {
    $func = function () use ($ns, $job) {
      $Client = static::client();
      if (!$Client->connected) {
        return false;
      }

      $Client->useTube($ns);
      $Client->put(0, 0, 300, base64_encode(msgpack_pack($job)));
    };

    if (function_exists('fastcgi_finish_request')) {
      register_shutdown_function(function() use($func) {
        $func();
        fastcgi_finish_request();
      });
    } else {
      $func();
    }
  }

  public static function process($ns, Callable $func) {
    if (!static::client()->connected) {
      return false;
    }
    static::client()->watch($ns);

    while (true) {
      if (false === static::fetch($func)) {
        return false;
      }
      usleep(200000);
    }
  }

  public static function fetch(Callable $func) {
    $Client = static::client();
    $job = $Client->reserve();
    if ($job === false) {
      return false;
    }
    $payload = msgpack_unpack(base64_decode($job['body']));
    $result = $func($payload);

    if (false === $result) {
      $Client->release($job['id'], 0, static::RELEASE_DELAY);
    } else {
      $Client->delete($job['id']);
    }
  }

  public function __destruct() {
    $this->Client->disconnect();
  }
}
