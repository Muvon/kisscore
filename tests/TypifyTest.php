<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class TypifyTest extends TestCase {
	public function testInt(): void {
		$this->assertSame(5, typify('5', 'int'));
		$this->assertSame(-3, typify('-3', 'integer'));
	}

	public function testUintClampsNegativeToZero(): void {
		$this->assertSame(0, typify('-3', 'uint'));
		$this->assertSame(7, typify('7', 'uinteger'));
	}

	public function testFloat(): void {
		$this->assertSame(1.5, typify('1.5', 'float'));
		$this->assertSame(2.0, typify('2', 'double'));
	}

	public function testUfloatClampsNegativeToZero(): void {
		$this->assertSame(0.0, typify('-1.2', 'ufloat'));
		$this->assertSame(1.2, typify('1.2', 'udouble'));
	}

	public function testBoolFalseTokens(): void {
		foreach (['no', 'none', 'false', 'off'] as $token) {
			$this->assertFalse(typify($token, 'bool'), "token $token should be false");
		}
	}

	public function testBoolTruthy(): void {
		$this->assertTrue(typify('1', 'bool'));
		$this->assertTrue(typify('yes', 'boolean'));
		$this->assertFalse(typify('0', 'bool'));
		$this->assertFalse(typify('', 'bool'));
	}

	public function testArray(): void {
		$this->assertSame(['a'], typify('a', 'array'));
		$this->assertSame([], typify('', 'array'));
		$this->assertSame(['x' => 1], typify(['x' => 1], 'array'));
	}

	public function testString(): void {
		$this->assertSame('5', typify(5, 'string'));
	}

	public function testUnknownTypePassesThrough(): void {
		$this->assertSame('raw', typify('raw', 'unknown'));
	}
}
