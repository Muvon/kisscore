<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase {
	public function testGetPhpFilesRecursesAndFiltersByExtension(): void {
		$dir = __DIR__ . '/fixtures/phpfiles';
		$method = new ReflectionMethod(Env::class, 'getPHPFiles');
		/** @var array<string> $files */
		$files = $method->invoke(null, $dir);

		$names = array_map('basename', $files);
		$this->assertContains('a.php', $names, 'top-level php file');
		$this->assertContains('b.php', $names, 'nested php file (recursion)');
		$this->assertNotContains('notphp.txt', $names, 'non-php excluded');

		foreach ($files as $file) {
			$this->assertStringStartsWith($dir, $file, 'returns full paths');
		}
	}
}
