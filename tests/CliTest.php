<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase {
	protected function tearDown(): void {
		Cli::prefix('');
	}

	public function testPrintIncludesActivePrefix(): void {
		Cli::prefix('ETHUSDT');

		ob_start();
		Cli::print('hello');
		$line = (string)ob_get_clean();

		$this->assertStringContainsString('[ETHUSDT]  hello', $line);
	}

	public function testPrintWithoutPrefixKeepsPlainLine(): void {
		ob_start();
		Cli::print('hello');
		$line = (string)ob_get_clean();

		$this->assertStringNotContainsString(' [', $line);
		$this->assertStringContainsString('hello', $line);
	}

	public function testPrintfFormatsArguments(): void {
		Cli::prefix('X');

		ob_start();
		Cli::printf('a=%d b=%s', 1, 'two');
		$line = (string)ob_get_clean();

		$this->assertStringContainsString('a=1 b=two', $line);
	}
}
