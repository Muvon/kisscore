<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Router matching against a fixture route map + config (no app bootstrap).
 * config() memoizes per-process; this is the only suite that exercises it, so
 * pointing CONFIG_DIR at the fixtures before the first call is sufficient.
 */
final class RouterTest extends TestCase {
	public static function setUpBeforeClass(): void {
		putenv('CONFIG_DIR=' . __DIR__ . '/fixtures/router');
	}

	protected function setUp(): void {
		Router::clearCache();
	}

	public function testMatchExactHome(): void {
		$match = Router::match('home', '');
		$this->assertNotNull($match);
		$this->assertSame('home', $match['action']);
		$this->assertSame('www', $match['zone']);
	}

	public function testEmptyUrlDefaultsToHome(): void {
		$match = Router::match('/', '');
		$this->assertNotNull($match);
		$this->assertSame('home', $match['action']);
	}

	public function testMatchWithNumericParam(): void {
		$match = Router::match('users/42', 'api.example.com');
		$this->assertNotNull($match);
		$this->assertSame('users/get', $match['action']);
		$this->assertSame('api', $match['zone']);
		$this->assertSame(['id' => '42'], $match['params']);
	}

	public function testZoneSelectsDifferentAction(): void {
		$match = Router::match('users', 'api.example.com');
		$this->assertNotNull($match);
		$this->assertSame('users/list', $match['action']);
	}

	public function testMultiParamRoute(): void {
		$match = Router::match('blog/hello/5', '');
		$this->assertNotNull($match);
		$this->assertSame('blog/post', $match['action']);
		$this->assertSame(['slug' => 'hello', 'id' => '5'], $match['params']);
	}

	public function testUnknownRouteReturnsNull(): void {
		$this->assertNull(Router::match('does/not/exist', ''));
	}

	public function testRouteMatchesOnlyWithinItsZone(): void {
		// "users/(\d+)" lives in the api zone; the www zone must not match it.
		$this->assertNull(Router::match('users/42', ''));
	}

	public function testMatchCarriesRouteMethod(): void {
		$match = Router::match('users/42', 'api.example.com');
		$this->assertNotNull($match);
		$this->assertSame('POST', $match['method'], 'method annotation flows through to the match');
	}

	public function testMatchDefaultsToAnyMethodWhenUnset(): void {
		$match = Router::match('home', '');
		$this->assertNotNull($match);
		$this->assertSame('', $match['method'], "no @method => '' (any method allowed)");
	}
}
