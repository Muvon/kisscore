<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase {
	public function testContainerSetAndGet(): void {
		container('fn_test_k1', 'value');
		$this->assertSame('value', container('fn_test_k1'));
	}

	public function testContainerMissingKeyThrows(): void {
		$this->expectException(Error::class);
		container('fn_test_missing_key');
	}

	public function testContainerResolvesAndMemoizesLazyValue(): void {
		container('fn_test_lazy', static fn() => 42);
		$this->assertSame(42, container('fn_test_lazy'));
		// Second read returns the already-resolved value.
		$this->assertSame(42, container('fn_test_lazy'));
	}

	public function testGetClassNameReturnsShortName(): void {
		$this->assertSame('Model', get_class_name(\Plugin\Data\Model::class));
		$this->assertSame('Result', get_class_name(Result::class));
	}

	public function testContainerDoesNotInvokeNonClosureCallable(): void {
		// 'strlen' is callable, but a stored plain value must not be invoked —
		// only Closures are resolved lazily.
		container('fn_test_callable_string', 'strlen');
		$this->assertSame('strlen', container('fn_test_callable_string'));
	}
}
