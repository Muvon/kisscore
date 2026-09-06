<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugin\Data\IdWorker;
use Plugin\Data\NumericIdTrait;
use Plugin\Data\StringIdTrait;

final class NumericIdSubject {
	use NumericIdTrait;
}

final class StringIdSubject {
	use StringIdTrait;
}

/**
 * These pin the property the worker field exists to provide: two workers minting
 * for the same model in the same millisecond must not agree. Without the field
 * they always did — every process's sequence counts from zero in lockstep — and
 * it surfaced as a duplicate-key insert on a primary key.
 */
final class IdGenerationTest extends TestCase {
	protected function tearDown(): void {
		IdWorker::set(0);
	}

	public function testWorkerFieldFitsBesideTheSequence(): void {
		// The two share the 11 bits the id layout reserves below the shard. If
		// that stops holding, the worker field corrupts the shard.
		$this->assertSame(11, IdWorker::BITS + IdWorker::SEQ_BITS);

		// Pin the split itself, not just the total: the header documents this
		// layout, and a silent re-balance would leave that diagram lying.
		$this->assertSame(6, IdWorker::BITS, 'worker width changed — update the layout diagram in IdWorker');
		$this->assertSame(5, IdWorker::SEQ_BITS, 'sequence width changed — update the layout diagram in IdWorker');
		$this->assertSame(64, IdWorker::SLOTS);
		$this->assertSame(32, IdWorker::SEQ_SLOTS);
	}

	public function testAnUnconfiguredProcessIsWorkerZero(): void {
		// A single-process application needs no setup and cannot collide.
		$this->assertSame(0, IdWorker::id());
	}

	public function testAWorkerIdOutsideTheRangeIsRejected(): void {
		// Wrapping it silently would reintroduce the collision, so it throws.
		$this->expectException(RuntimeException::class);
		IdWorker::set(IdWorker::SLOTS);
	}

	public function testOneWorkerNeverRepeatsAnIdWhenTheSequenceIsExhausted(): void {
		// The counter is only SEQ_SLOTS wide, so a caller that mints faster than
		// that within a millisecond used to wrap straight back onto ids it had
		// already handed out. Mint far more than one millisecond's worth as fast
		// as the machine allows and require every one to be distinct.
		IdWorker::set(0);
		$ids = [];
		for ($i = 0; $i < IdWorker::SEQ_SLOTS * 50; $i++) {
			$ids[] = NumericIdSubject::generateId();
		}

		$this->assertCount(count($ids), array_unique($ids), 'the sequence wrapped onto an id it had already minted');
		// And they stay ordered, which is the other half of the contract.
		$sorted = $ids;
		sort($sorted);
		$this->assertSame($sorted, $ids);
	}

	public function testStringIdsAlsoSurviveSequenceExhaustion(): void {
		IdWorker::set(0);
		$ids = [];
		for ($i = 0; $i < IdWorker::SEQ_SLOTS * 50; $i++) {
			$ids[] = StringIdSubject::generateId();
		}
		$this->assertCount(count($ids), array_unique($ids));
	}

	public function testNumericIdsFromDifferentWorkersNeverCollide(): void {
		$mint = static function (int $worker, int $count): array {
			IdWorker::set($worker);
			$ids = [];
			for ($i = 0; $i < $count; $i++) {
				$ids[] = NumericIdSubject::generateId();
			}
			return $ids;
		};

		// A full sequence wrap each, which is the worst a single millisecond can do.
		$a = $mint(0, IdWorker::SEQ_SLOTS);
		$b = $mint(1, IdWorker::SEQ_SLOTS);

		$this->assertSame([], array_intersect($a, $b), 'two workers produced the same id');
		$this->assertCount(IdWorker::SEQ_SLOTS, array_unique($a));
	}

	public function testEveryWorkerTheRangeAllowsIsDistinct(): void {
		$seen = [];
		for ($worker = 0; $worker < IdWorker::SLOTS; $worker++) {
			IdWorker::set($worker);
			$id = NumericIdSubject::generateId();
			$seen[] = ($id >> IdWorker::SEQ_BITS) & (IdWorker::SLOTS - 1);
		}

		$this->assertSame(range(0, IdWorker::SLOTS - 1), $seen);
	}

	public function testTheWorkerFieldDoesNotReachTheShard(): void {
		IdWorker::set(IdWorker::SLOTS - 1);
		$id = NumericIdSubject::generateId();

		// The subject does not shard, so those bits must still read zero.
		$this->assertSame(0, ($id >> 11) & 0x1FFF);
	}

	public function testStringIdsFromDifferentWorkersNeverCollide(): void {
		IdWorker::set(0);
		$a = StringIdSubject::generateId();
		IdWorker::set(1);
		$b = StringIdSubject::generateId();

		$this->assertNotSame($a, $b);
		// The width is part of the contract: callers store these in fixed
		// CHAR columns.
		$this->assertSame(20, strlen($a));
		$this->assertSame(20, strlen($b));
	}
}
