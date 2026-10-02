<?php declare(strict_types=1);

namespace Plugin\Data;

trait NumericIdTrait {
  /** @var int|null $id */
	protected ?int $id = null;

	/** @var string */
	protected static string $id_field = 'id';

	/** @var string */
	protected static string $id_type = 'int';

	/** @var array<string,int> */
	protected static array $id_seqs = [];

	/** @var string */
	protected static string $shard_key = '';

	/**
	 * Generates a unique ID with a lifespan of up to 35 years for signed and 70 years for unsigned integers
	 *
	 * @param string $value
	 * @return int
	 */
	public static function generateId(string $value = ''): int {
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
		// Combine milliseconds, shard_id, worker and sequence. `$seq` counts per
		// process, so the worker id beside it is what keeps ids unique across a
		// forking server — see IdWorker.
		return (($last - $epoch) << 24) # 40 bit for timestamp in ms
		| ($shard_id << 11) # 13 bit for shard
		| (IdWorker::id() << IdWorker::SEQ_BITS) # 6 bit for the worker
		| $seq; # 5 bit for the sequence
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
		/** @var int $id */
		$id = typify($id, static::$id_type);
		$this->id = $id;
		return $this;
	}

	/** @return int */
	public function getId(): int {
		return (int)$this->id;
	}

	/** @return string  */
	protected static function getShardKey(): string {
		return static::$shard_key;
	}
}
