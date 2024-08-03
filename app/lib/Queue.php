<?php declare(strict_types=1);

namespace Lib;

use Beanstalk\Client;

/** @package Lib */
final class Queue {
	const RELEASE_DELAY = 5;

	protected Client $Client;

	private function __construct() {
	}

	/**
	 * @param string $host
	 * @param int $port
	 * @return Queue
	 */
	public static function new(string $host, int $port): self {
		$Self = new self;
		$Self->Client = new Client([
			'host' => $host,
			'port' => $port,
		]);
		$Self->Client->connect();
		return $Self;
	}

	/**
	 *
	 * @param string $ns
	 * @param mixed $job
	 * @param int $delay
	 * @param int $ttr
	 * @return bool
	 */
	public function add(string $ns, mixed $job, int $delay = 0, int $ttr = 300): bool {
		$func = function () use ($ns, $job, $delay, $ttr) {
			if (!$this->Client->connected) {
				return false;
			}

			$this->Client->useTube($ns);
			$this->Client->put(0, $delay, $ttr, base64_encode(msgpack_pack($job)));
			return true;
		};

		if (function_exists('fastcgi_finish_request')) {
			register_shutdown_function(
				function () use ($func) {
					$func();
					fastcgi_finish_request();
				}
			);
			return true;
		}

		return $func();
	}

	/**
	 *
	 * @param string $ns
	 * @param callable $func
	 * @return bool
	 */
	public function process(string $ns, callable $func): bool {
		declare(ticks=1);
		// Install the signal handler
		pcntl_signal(SIGTERM, [static::class, 'sigHandler']);
		pcntl_signal(SIGHUP, [static::class, 'sigHandler']);

		if (!$this->Client->connected) {
			return false;
		}
		$this->Client->watch($ns);

		while (true) {
			if (false === $this->fetch($func)) {
				return false;
			}
			// Check if a signal has been received
			pcntl_signal_dispatch();

			usleep(200000);
		}
	}

	/**
	 *
	 * @param callable $func
	 * @return bool
	 */
	public function fetch(callable $func): bool {
		$job = $this->Client->reserve();
		if ($job === false) {
			return false;
		}
		$payload = msgpack_unpack(base64_decode($job['body']));
		$result = $func($payload);

		if (false === $result) {
			$this->Client->release($job['id'], 0, static::RELEASE_DELAY);
		} else {
			$this->Client->delete($job['id']);
		}

		return true;
	}

	/**
	 *
	 * @param int $signo
	 * @return void
	 */
	public static function sigHandler(int $signo): void {
		switch ($signo) {
			case SIGTERM:
			case SIGHUP:
			default:
				exit;
		}
	}

	/**
	 *
	 * @return void
	 */
	public function __destruct() {
		$this->Client->disconnect();
	}
}
