<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Request metadata accessors: init() fills a coroutine-local bag read by
 * method()/ip()/header()/…. Isolation test proves two concurrent coroutines
 * with different metadata never see each other — fails under the old
 * shared-static model.
 */
final class RequestMetaTest extends TestCase {
	protected function setUp(): void {
		Request::reset();
	}

	protected function tearDown(): void {
		Request::reset();
	}

	public function testInitPopulatesAccessors(): void {
		Request::init(
			time: 1750000000,
			time_float: 1750000000.25,
			request_uri: '/api/users?page=2',
			content_type: 'application/json',
			method: 'POST',
			protocol: 'HTTP/1.1',
			referer: 'https://ref.example.com',
			ip: '10.0.0.1',
			xff: '',
			host: 'api.example.com',
			user_agent: 'test-agent',
			headers: ['accept' => 'application/msgpack'],
			is_ajax: true,
		);

		self::assertSame(1750000000, Request::time());
		self::assertSame(1750000000.25, Request::timeFloat());
		self::assertSame('/api/users?page=2', Request::uri());
		self::assertSame('application/json', Request::contentType());
		self::assertSame('POST', Request::method());
		self::assertSame('HTTP/1.1', Request::protocol());
		self::assertSame('https://ref.example.com', Request::referer());
		self::assertSame('10.0.0.1', Request::ip());
		self::assertSame('10.0.0.1', Request::realIp());
		self::assertSame('api.example.com', Request::host());
		self::assertSame('test-agent', Request::userAgent());
		self::assertSame(['accept' => 'application/msgpack'], Request::headers());
		self::assertTrue(Request::isAjax());
	}

	public function testInitAppliesDefaults(): void {
		Request::init(method: 'PUT');

		self::assertSame('PUT', Request::method());
		self::assertSame('0.0.0.0', Request::ip());
		self::assertSame('HTTP', Request::protocol());
		self::assertSame([], Request::headers());
		self::assertFalse(Request::isAjax());
	}

	public function testRealIpDerivedFromForwardedFor(): void {
		Request::init(ip: '10.0.0.1', xff: '203.0.113.7, 10.0.0.1');

		self::assertSame('10.0.0.1', Request::ip());
		self::assertSame('203.0.113.7', Request::realIp());
	}

	public function testExplicitRealIpOverridesForwardedForDerivation(): void {
		// A deployment behind a trusted proxy resolves the client itself (right-
		// walking the chain, honouring CF-Connecting-IP, failing closed on an
		// untrusted peer). Its value must win over the forgeable first token.
		Request::init(ip: '10.0.0.1', xff: '1.2.3.4, 198.51.100.9', real_ip: '198.51.100.9');

		self::assertSame('10.0.0.1', Request::ip());
		self::assertSame('198.51.100.9', Request::realIp());
	}

	public function testExplicitRealIpWinsWhenNoForwardedForPresent(): void {
		Request::init(ip: '10.0.0.1', real_ip: '203.0.113.50');

		self::assertSame('203.0.113.50', Request::realIp());
	}

	public function testHeaderLookupIsCaseInsensitiveWithDefault(): void {
		Request::init(headers: ['x-api-key' => 'secret']);

		self::assertSame('secret', Request::header('X-Api-Key'));
		self::assertSame('', Request::header('missing'));
		self::assertSame('fallback', Request::header('missing', 'fallback'));
	}

	public function testContentTypeDrivesInputDetection(): void {
		Request::init(content_type: 'application/msgpack');

		self::assertTrue(Input::isMsgpack());
		self::assertFalse(Input::isJson());
	}

	public function testResetRestoresDefaults(): void {
		Request::init(method: 'POST', ip: '10.0.0.1');
		Request::reset();

		self::assertSame('GET', Request::method());
		self::assertSame('0.0.0.0', Request::ip());
	}

	public function testMetadataIsolatedAcrossConcurrentCoroutines(): void {
		if (!extension_loaded('swoole')) {
			$this->markTestSkipped('Swoole extension not loaded');
		}

		$results = [];
		\Swoole\Coroutine\run(
			static function () use (&$results): void {
				$worker = static function (string $tag, string $ip) use (&$results): void {
					Request::init(
						method: $tag === 'A' ? 'GET' : 'POST',
						ip: $ip,
						headers: ['x-tag' => $tag],
					);

					// Yield mid-request so the sibling coroutine runs in between.
					\Swoole\Coroutine::sleep(0.02);

					$results[$tag] = [
						'method' => Request::method(),
						'ip' => Request::ip(),
						'tag' => Request::header('x-tag'),
					];
				};
				\Swoole\Coroutine::create($worker, 'A', '10.0.0.1');
				\Swoole\Coroutine::create($worker, 'B', '10.0.0.2');
			}
		);

		self::assertSame(['method' => 'GET', 'ip' => '10.0.0.1', 'tag' => 'A'], $results['A'] ?? null);
		self::assertSame(['method' => 'POST', 'ip' => '10.0.0.2', 'tag' => 'B'], $results['B'] ?? null);
	}
}
