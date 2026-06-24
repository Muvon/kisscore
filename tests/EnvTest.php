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

	public function testParseMethodAnnotationSingle(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		$this->assertSame('POST', $method->invoke(null, "<?php\n/**\n * @route foo\n * @method POST\n */"));
	}

	public function testParseMethodAnnotationMultipleAndNormalises(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		// mixed case + comma/space separators collapse to an uppercase CSV list
		$this->assertSame('GET,POST', $method->invoke(null, "/**\n * @method get, post\n */"));
	}

	public function testParseMethodAnnotationAbsentMeansAny(): void {
		$method = new ReflectionMethod(Env::class, 'parseMethodAnnotation');
		$this->assertSame('', $method->invoke(null, "/**\n * @route foo\n */"));
	}
}
