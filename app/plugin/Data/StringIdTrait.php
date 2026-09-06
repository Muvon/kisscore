<?php declare(strict_types=1);

namespace Plugin\Data;

trait StringIdTrait {
  /** @var string|null $id */
	protected ?string $id = null;

	/** @var string */
	protected static string $id_type = 'string';

	/** @var array<string,int> */
	protected static array $id_seqs = [];

	/** @var string */
	protected static string $shard_key = '';

	/**
	 * Generates a unique string ID with embedded shard information
	 *
	 * @param string $value
	 * @return string string with length of 20
	 */
	public static function generateId(string $value = ''): string {
		static $seq = 0;
		static $last = 0;
		$shard_id = static::dbShardId($value);
		$epoch = config('common.epoch') * 1000;
		// Canonical snowflake sequencing: the counter belongs to a millisecond and
		// resets when the clock moves on, so a (timestamp, worker, sequence) triple
		// is only ever used once. Twitter's version busy-waits for the next
		// millisecond when the counter is spent; advancing the timestamp instead
		// gives the same guarantee without blocking a worker that has no coroutine
		// hooks, and it self-corrects as soon as the wall clock catches up. This
		// also covers a clock that steps backwards, which would otherwise re-mint
		// ids already handed out.
		$now = (int)(microtime(true) * 1000);
		if ($now > $last) {
			$last = $now;
			$seq = 0;
		} else {
			$seq = ($seq + 1) % IdWorker::SEQ_SLOTS;
			if ($seq === 0) {
				$last++;
			}
		}

		// Generate components. `$seq` counts per process, so the trailing field
		// pairs it with this process's worker id — without that, two workers
		// minting for the same model in the same millisecond produce the same id.
		$time_component = dechex($last - $epoch);
		$shard_component = dechex($shard_id);
		$seq_component = dechex((IdWorker::id() << IdWorker::SEQ_BITS) | $seq);

		// Combine all components
		return sprintf(
			'%s-%s-%s',
			str_pad($time_component, 11, '0', STR_PAD_LEFT),
			str_pad($shard_component, 4, '0', STR_PAD_LEFT),
			str_pad($seq_component, 3, '0', STR_PAD_LEFT),
		);
	}

	/**
	 * @param string $value
	 * @return int
	 */
	protected static function dbShardId(string $value): int {
		$shard_id = 0;
		if (static::$shard_key) {
			$shard_id = crc32($value) % 8192;
		}
		return $shard_id;
	}

	/**
	 * @param int|string $id
	 * @return static
	 */
	public function setId(int|string $id): static {
		/** @var string $id */
		typify($id, static::$id_type);
		$this->id = $id;
		return $this;
	}

	/** @return string */
	public function getId(): string {
		return (string)$this->id;
	}

	/** @return string  */
	protected static function getShardKey(): string {
		return static::$shard_key;
	}
}
