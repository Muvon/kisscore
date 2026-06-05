<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ResultTest extends TestCase {
	public function testOkCarriesValueAndUnwraps(): void {
		$Result = ok(['id' => 1]);
		$this->assertNull($Result->err);
		$this->assertSame(['id' => 1], $Result->res);
		$this->assertSame(['id' => 1], $Result->unwrap());
		$this->assertSame(['id' => 1], $Result->unwrapOr('fallback'));
	}

	public function testErrHasNoValueAndUnwrapThrows(): void {
		$Result = err('e_not_found');
		$this->assertSame('e_not_found', $Result->err);
		$this->assertSame('default', $Result->unwrapOr('default'));

		$this->expectException(ResultError::class);
		$Result->unwrap();
	}

	public function testUnwrapMessageIncludesContext(): void {
		$Result = err('e_bad', ['id' => 7]);
		try {
			$Result->unwrap();
			$this->fail('expected ResultError');
		} catch (ResultError $e) {
			$this->assertStringContainsString('e_bad', $e->getMessage());
			$this->assertStringContainsString('"id":7', $e->getMessage());
		}
	}

	public function testToArrayShape(): void {
		$this->assertSame([null, 'v'], ok('v')->toArray());
		$this->assertSame(['e_x', null], err('e_x')->toArray());
	}

	public function testUnwrapAll(): void {
		$this->assertSame([1, 2, 3], Result::unwrapAll(ok(1), ok(2), ok(3)));
	}

	public function testUnwrapAllPropagatesFirstError(): void {
		$this->expectException(ResultError::class);
		Result::unwrapAll(ok(1), err('e_mid'), ok(3));
	}

	public function testErrListWrapsErrors(): void {
		$Result = err_list(['field_required', 'name_taken']);
		$this->assertSame('e_error_list', $Result->err);
		$this->assertSame(['field_required', 'name_taken'], $Result->res);
	}
}
