<?php declare(strict_types=1);

namespace Plugin\Data;

use RuntimeException;

/**
 * The worker id embedded in every generated id.
 *
 * `NumericIdTrait`/`StringIdTrait` mint snowflake-style ids, and a snowflake
 * needs a worker field: its sequence counter is per generator instance, so
 * without one, every process of a forking server counts from zero in lockstep
 * and two of them minting for the same model in the same millisecond produce
 * the SAME id.
 *
 * As in Twitter's original, the worker id is ASSIGNED, not inferred. Uniqueness
 * is a property of the deployment — the server hands each of its workers a
 * distinct number and passes it here — which is what makes collisions
 * impossible rather than merely unlikely. Deriving it at runtime (pid, a lock
 * file, a hash) only ever approximates that, so it is not done.
 *
 * The field is carved out of the sequence bits, so id width, layout and sort
 * order are unchanged:
 *
 *     bits 63..24  milliseconds since the configured epoch
 *     bits 23..11  shard        (13 bits)
 *     bits 10..5   worker       (6 bits — this class, as in snowflake)
 *     bits  4..0   sequence     (5 bits)
 *
 * 64 workers and 32 ids per model per millisecond per worker. The split favours
 * workers because running out of them is a hard deployment failure, while
 * running out of sequence is not: the generator carries the timestamp forward
 * and keeps going (see the id traits).
 *
 * A single-process application needs no setup: it is worker 0 and cannot
 * collide with anyone. A forking or multi-node deployment MUST call `set()`
 * once per process before it mints anything — under Swoole that is the
 * `WorkerStart` handler, which is handed exactly this number (see
 * `skel/app/main.php`). Out-of-range ids throw rather than wrap, because a
 * silently-wrapped worker id is the collision this class exists to prevent.
 */
final class IdWorker {
	/** Width of the worker field, in bits. */
	public const BITS = 6;
	/** Distinct workers a deployment may run. */
	public const SLOTS = 1 << self::BITS;

	/** Width of the sequence field left below the worker field. */
	public const SEQ_BITS = 11 - self::BITS;
	/** Ids one worker can mint for one model within one millisecond. */
	public const SEQ_SLOTS = 1 << self::SEQ_BITS;

	private static int $id = 0;

	/** Assign this process's worker id. */
	public static function set(int $id): void {
		if ($id < 0 || $id >= self::SLOTS) {
			throw new RuntimeException(
				'worker id ' . $id . ' is outside 0..' . (self::SLOTS - 1)
				. ' — ids from workers beyond that range would collide'
			);
		}
		self::$id = $id;
	}

	/** This process's worker id; 0 until assigned. */
	public static function id(): int {
		return self::$id;
	}
}
