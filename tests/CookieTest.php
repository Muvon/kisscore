<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CookieTest extends TestCase {
	protected function setUp(): void {
		Coro::clear('cookie');
	}

	public function testGetReturnsParsedCookieOrDefault(): void {
		Cookie::setParser(static fn() => ['session' => 'abc']);
		$this->assertSame('abc', Cookie::get('session'));
		$this->assertSame('def', Cookie::get('missing', 'def'));
	}

	public function testSetThenAllReturnsJar(): void {
		Cookie::setParser(static fn() => ['a' => '1']);
		Cookie::set('b', '2');
		$this->assertSame('1', Cookie::get('a'));
		$this->assertSame('2', Cookie::get('b'));
		$this->assertEqualsCanonicalizing(['a' => '1', 'b' => '2'], Cookie::all());
	}

	public function testAddDoesNotOverwriteExisting(): void {
		Cookie::setParser(static fn() => ['x' => 'orig']);
		Cookie::add('x', 'new');
		$this->assertSame('orig', Cookie::get('x'));
	}

	public function testAddCreatesWhenAbsent(): void {
		Cookie::setParser(static fn() => []);
		Cookie::add('y', 'created');
		$this->assertSame('created', Cookie::get('y'));
	}

	public function testSetOverridesIncomingCookieBeforeFirstRead(): void {
		// set() before any read must win over the request's original cookie of
		// the same name (regression: a later lazy parse used to clobber it).
		Cookie::setParser(static fn() => ['b' => 'incoming']);
		Cookie::set('b', 'updated');
		$this->assertSame('updated', Cookie::get('b'));
	}
}
