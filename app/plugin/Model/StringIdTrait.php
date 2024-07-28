<?php declare(strict_types=1);

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
	 * @return string
	 */
	public static function generateId(string $value = ''): string {
		static $seq = 0;
		$shard_id = static::dbShardId($value);
		$epoch = config('common.epoch');
		$nano_time = hrtime(true);
		$milliseconds = (int)($nano_time / 1_000_000);
		$nano_seconds = $nano_time % 1_000_000;

		$seq = (++$seq % 1024);

		// Generate components
		$time_component = dechex($milliseconds - $epoch);
		$shard_component = str_pad(dechex($shard_id), 3, '0', STR_PAD_LEFT);
		$seq_component = str_pad(dechex($seq), 3, '0', STR_PAD_LEFT);
		$nano_component = str_pad(dechex($nano_seconds), 5, '0', STR_PAD_LEFT);

		// Generate a random component to ensure uniqueness
		$random_component = bin2hex(random_bytes(4));

		// Combine all components
		return sprintf(
			'%s-%s-%s-%s-%s',
			str_pad($time_component, 12, '0', STR_PAD_LEFT),
			$shard_component . $seq_component,
			$nano_component . substr($random_component, 0, 3),
			substr($random_component, 3, 4),
			bin2hex(random_bytes(6))  // Additional randomness
		);
	}

	/**
	 * @param string $value
	 * @return int
	 */
	protected static function dbShardId(string $value): int {
		$shard_id = 0;
		if (static::$shard_key) {
			$shard_id = crc32($value) % 1024;
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
