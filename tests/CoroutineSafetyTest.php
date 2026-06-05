<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Proves the per-request state lifecycle is coroutine-local: two concurrent
 * coroutines that interleave (via a mid-request yield) never read each other's
 * Input/Session/Response. With the old shared-static model both coroutines
 * would observe whichever ran last and these assertions would fail.
 */
final class CoroutineSafetyTest extends TestCase {
	public function testIdAndActiveOutsideCoroutine(): void {
		$this->assertFalse(Coro::active());
		$this->assertSame(-1, Coro::id());
	}

	public function testProcessLocalBagPersistsOutsideCoroutine(): void {
		Coro::clear('test_bag');
		$bag = &Coro::bag('test_bag', static fn(): array => ['n' => 0]);
		$bag['n'] = 5;
		unset($bag);

		$again = &Coro::bag('test_bag', static fn(): array => ['n' => 0]);
		$this->assertSame(5, $again['n']);
		Coro::clear('test_bag');
	}

	public function testRequestStateIsolatedAcrossConcurrentCoroutines(): void {
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension not loaded');
		}

		$results = [];
		\Swoole\Coroutine\run(
			static function () use (&$results): void {
				$worker = static function (string $tag) use (&$results): void {
					Input::setParser(static fn(): array => ['who' => $tag]);
					Input::set('extra', $tag);
					Session::set('sid', $tag);
					Response::current(true)->status($tag === 'A' ? 201 : 202);

					// Yield mid-request so the sibling coroutine runs in between.
					\Swoole\Coroutine::sleep(0.02);

					$results[$tag] = [
						'who' => Input::get('who'),
						'extra' => Input::get('extra'),
						'sid' => Session::get('sid'),
						'status' => Response::current()->getStatus(),
					];
				};
				\Swoole\Coroutine::create($worker, 'A');
				\Swoole\Coroutine::create($worker, 'B');
			}
		);

		$this->assertSame(
			['who' => 'A', 'extra' => 'A', 'sid' => 'A', 'status' => 201],
			$results['A'] ?? null
		);
		$this->assertSame(
			['who' => 'B', 'extra' => 'B', 'sid' => 'B', 'status' => 202],
			$results['B'] ?? null
		);
	}
}
