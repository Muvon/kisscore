<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase {
	protected function setUp(): void {
		Coro::clear('response');
		Coro::clear('cookie');
	}

	public function testDefaultStatusIs200(): void {
		$this->assertSame(200, Response::current(true)->getStatus());
	}

	public function testStatusIsMutable(): void {
		$response = Response::current(true);
		$response->status(404);
		$this->assertSame(404, $response->getStatus());
	}

	public function testCurrentReturnsSameInstanceWithoutReset(): void {
		$first = Response::current(true);
		$this->assertSame($first, Response::current());
	}

	public function testResetReturnsFreshInstance(): void {
		$first = Response::current(true);
		$second = Response::current(true);
		$this->assertNotSame($first, $second);
	}

	public function testBodyRoundTripAndSend(): void {
		$response = Response::current(true)->setBody('hello');
		$this->assertSame('hello', (string)$response);

		ob_start();
		$response->sendBody();
		$this->assertSame('hello', (string)ob_get_clean());
	}

	public function testSendHeadersEmitsCustomAndSecurityHeaders(): void {
		$response = Response::current(true);
		$response->header('X-Test', 'yes');

		$captured = [];
		$response->sendHeaders(
			static function (string $key, string $value) use (&$captured): void {
				$captured[$key] = $value;
			}
		);

		$this->assertSame('yes', $captured['X-Test'] ?? null);
		$this->assertArrayHasKey('X-Frame-Options', $captured);
		$this->assertArrayHasKey('X-Content-Type-Options', $captured);
	}
}
