<?php declare(strict_types=1);

namespace App\Lib;

use Beanstalk\Client;

class Queue {
	const RELEASE_DELAY = 5;

	/**
	 *
	 * @return Client
	 */
	protected static function client(): Client {
		static $Client;

		if (!$Client) {
			$Client = new Client(config('queue'));
			$Client->connect();
		}

		return $Client;
	}

	/**
	 *
	 * @param string $ns
	 * @param mixed $job
	 * @param int $delay
	 * @param int $ttr
	 * @param int $priority
	 * @return bool
	 */
	public static function add(string $ns, mixed $job, int $delay = 0, int $ttr = 300, int $priority = 0): bool {
		$func = function () use ($ns, $job, $delay, $ttr, $priority) {
			$Client = static::client();
			if (!$Client->connected) {
				return false;
			}

			$Client->useTube($ns);
			$Client->put($priority, $delay, $ttr, json_encode($job));
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
	public static function process(string $ns, callable $func): bool {
		declare(ticks=1);
		// Install the signal handler
		pcntl_signal(SIGTERM, [static::class, 'sigHandler']);
		pcntl_signal(SIGHUP, [static::class, 'sigHandler']);

		if (!static::client()->connected) {
			return false;
		}
		static::client()->watch($ns);

		while (true) {
			if (false === static::fetch($func)) {
				return false;
			}
			// Check if a signal has been received
			pcntl_signal_dispatch();

			usleep(200000);
		}
	}

	/**
	 * Get stats of the queue
	 * @param ?string $ns
	 * @return array{queued:int,total:int}
	 */
	public static function stats(?string $ns = null): array {
		$Client = static::client();
		/** @var array */
		$stats = $ns ? $Client->statsTube($ns) : $Client->stats();
		return [
			'queued' => $stats['current-jobs-ready'],
			'total' => $stats['total-jobs'],
		];
	}

	/**
	 *
	 * @param callable $func
	 * @return bool
	 */
	public static function fetch(callable $func): bool {
		$Client = static::client();
		$job = $Client->reserve();
		if ($job === false) {
			return false;
		}
		$payload = json_decode($job['body'], true);
		$result = $func($payload);

		if (false === $result) {
			$Client->release($job['id'], 0, static::RELEASE_DELAY);
		} else {
			$Client->delete($job['id']);
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
				// Stop the script here
				exit;
				break;
			default:
				// Handle all other signals here
				exit;
		}
	}

	/**
	 *
	 * @return void
	 */
	public function __destruct() {
		static::client()->disconnect();
	}
}
