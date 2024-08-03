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
		$shard_id = static::dbShardId($value);
		$epoch = config('common.epoch') * 1000;
		$now = (int)(microtime(true) * 1000);
		$seq = (++$seq % 2048);
		// Combine milliseconds, shard_id, sequence, and nanoseconds
		return (($now - $epoch) << 24) # 40 bit for timestamp in ms
		| ($shard_id << 11) # 13 bit for shard
		| $seq;
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
		typify($id, static::$id_type);
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
