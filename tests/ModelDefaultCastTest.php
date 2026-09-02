<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugin\Data\Model;

/** @extends Model<array<string,mixed>> */
final class DefaultCastModel extends Model {
	public static function cast(string $type, ?string $default): int|float|string|null {
		return static::castDefault($type, $default);
	}

	public function getId(): int|string {
		/** @var int|string */
		return $this->data['id'] ?? 0;
	}

	public function setId(int|string $id): static {
		$this->data['id'] = $id;
		return $this;
	}

	protected static function generateId(string $value = ''): int|string {
		return 1;
	}

	protected static function dbShardId(string $value): int|string {
		return 0;
	}

	protected static function getShardKey(): string {
		return '';
	}
}

// DESCRIBE reports defaults as strings; reads use MYSQLI_OPT_INT_AND_FLOAT_NATIVE.
// A freshly created model must carry the same scalar types as a reloaded one.
final class ModelDefaultCastTest extends TestCase {
	public function testIntegerColumnsBecomeNativeInts(): void {
		self::assertSame(0, DefaultCastModel::cast('bigint', '0'));
		self::assertSame(7, DefaultCastModel::cast('tinyint', '7'));
		self::assertSame(-3, DefaultCastModel::cast('int', '-3'));
		self::assertSame(2026, DefaultCastModel::cast('year', '2026'));
	}

	public function testFloatColumnsBecomeNativeFloats(): void {
		self::assertSame(0.0, DefaultCastModel::cast('double', '0'));
		self::assertSame(1.5, DefaultCastModel::cast('float', '1.5'));
	}

	public function testStringLikeColumnsAndNullsAreUntouched(): void {
		self::assertSame('0.00000000', DefaultCastModel::cast('decimal', '0.00000000'));
		self::assertSame('', DefaultCastModel::cast('varchar', ''));
		self::assertSame('pending', DefaultCastModel::cast('enum', 'pending'));
		self::assertSame('CURRENT_TIMESTAMP', DefaultCastModel::cast('timestamp', 'CURRENT_TIMESTAMP'));
		self::assertNull(DefaultCastModel::cast('bigint', null));
		self::assertNull(DefaultCastModel::cast('varchar', null));
	}
}
