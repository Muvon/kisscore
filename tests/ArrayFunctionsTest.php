<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ArrayFunctionsTest extends TestCase {
	public function testArrayOrderByAscending(): void {
		$data = [
			['name' => 'a', 'age' => 30],
			['name' => 'b', 'age' => 20],
			['name' => 'c', 'age' => 25],
		];
		$sorted = array_order_by($data, 'age', SORT_ASC, SORT_NUMERIC);
		$this->assertSame([20, 25, 30], array_column($sorted, 'age'));
	}

	public function testArrayOrderByDescending(): void {
		$data = [
			['name' => 'a', 'age' => 30],
			['name' => 'b', 'age' => 20],
		];
		$sorted = array_order_by($data, 'age', SORT_DESC, SORT_NUMERIC);
		$this->assertSame(['a', 'b'], array_column($sorted, 'name'));
	}

	public function testArrayCartesian(): void {
		$result = array_cartesian(['x' => [1, 2], 'y' => ['a']]);
		$this->assertSame(
			[
				['x' => 1, 'y' => 'a'],
				['x' => 2, 'y' => 'a'],
			],
			$result
		);
	}

	public function testBcHexDecRoundTrip(): void {
		$this->assertSame('255', bchexdec('ff'));
		$this->assertSame('ff', bcdechex('255'));
		$this->assertSame('deadbeef', bcdechex(bchexdec('deadbeef')));
	}

	public function testArrayValueRefMutatesExisting(): void {
		$container = ['a' => ['b' => 1]];
		$ref = &array_value_ref($container, 'a.b');
		$ref = 99;
		$this->assertSame(99, $container['a']['b']);
	}

	public function testArrayValueRefCreatesMissingPath(): void {
		$container = [];
		$ref = &array_value_ref($container, 'x.y');
		$ref = 'z';
		$this->assertSame(['x' => ['y' => 'z']], $container);
	}

	public function testAsArray(): void {
		$this->assertSame(['a' => 1, 'b' => 2], as_array((object)['a' => 1, 'b' => 2]));
	}
}
