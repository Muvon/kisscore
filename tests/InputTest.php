<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase {
	protected function setUp(): void {
		// Fresh coroutine-local (here: process-local) input state per test.
		Coro::clear('input');
	}

	public function testExtractTypifiedAppliesTypes(): void {
		$out = Input::extractTypified(
			['id:int', 'name', 'active:bool'],
			static fn($k, $d) => ['id' => '42', 'name' => 'bob', 'active' => 'off'][$k] ?? $d
		);
		$this->assertSame(['id' => 42, 'name' => 'bob', 'active' => false], $out);
	}

	public function testExtractTypifiedUsesDefault(): void {
		$out = Input::extractTypified(['n:int=7'], static fn($k, $d) => $d);
		$this->assertSame(['n' => 7], $out);
	}

	public function testGetReturnsParsedParams(): void {
		Input::setParser(static fn() => ['a' => 1, 'b' => 'x']);
		$this->assertSame(1, Input::get('a'));
		$this->assertSame('x', Input::get('b'));
		$this->assertNull(Input::get('missing'));
		$this->assertSame('def', Input::get('missing', 'def'));
	}

	public function testSetAddsParam(): void {
		Input::setParser(static fn() => ['a' => 1]);
		Input::set('c', 3);
		$this->assertSame(3, Input::get('c'));
		$this->assertSame(['a' => 1, 'c' => 3], Input::get());
	}

	public function testGetWithTypedArrayArg(): void {
		Input::setParser(static fn() => ['id' => '10']);
		$this->assertSame(['id' => 10], Input::get(['id:int']));
	}

	public function testCliDefaultParserExposesActionAndPositionalArgs(): void {
		// No parser set → default CLI parser reads $argv (PHP_SAPI is 'cli' here).
		$orig = $GLOBALS['argv'] ?? null;
		$GLOBALS['argv'] = ['script.php', 'myaction', 'pos1', 'pos2'];
		Coro::clear('input');
		try {
			$this->assertSame('myaction', Input::get('ACTION'));
			$all = Input::get();
			$this->assertSame('pos1', $all[0] ?? null);
			$this->assertSame('pos2', $all[1] ?? null);
		} finally {
			if ($orig === null) {
				unset($GLOBALS['argv']);
			} else {
				$GLOBALS['argv'] = $orig;
			}
			Coro::clear('input');
		}
	}
}
