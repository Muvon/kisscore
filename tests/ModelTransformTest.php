<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Plugin\Data\Model;

/**
 * Concrete model exposing the protected transform() pipeline for testing both
 * single-field and multi-field (folded-storage) transformers.
 */
final class MultiFieldModel extends Model {
	public static function applyTransform(array &$row, bool $is_decode = false): void {
		static::transform($row, $is_decode);
	}

	/** @return array<string,array{0:callable,1:callable}> */
	protected static function getTransformers(): array {
		return [
			'name' => [
				static fn($v) => strtoupper((string)$v),
				static fn($v) => strtolower((string)$v),
			],
			'amount,currency' => [
				static fn($amount, $currency) => $amount . ':' . $currency,
				static fn($stored) => explode(':', (string)$stored),
			],
		];
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

final class ModelTransformTest extends TestCase {
	public function testSingleFieldEncodeThenDecode(): void {
		$row = ['name' => 'Bob'];
		MultiFieldModel::applyTransform($row);
		$this->assertSame('BOB', $row['name']);

		MultiFieldModel::applyTransform($row, true);
		$this->assertSame('bob', $row['name']);
	}

	public function testMultiFieldEncodeFoldsIntoStorage(): void {
		$row = ['amount' => 10, 'currency' => 'USD'];
		MultiFieldModel::applyTransform($row);
		$this->assertSame('10:USD', $row['amount']);
		$this->assertArrayNotHasKey('currency', $row);
	}

	public function testMultiFieldDecodeSpreadsBack(): void {
		$row = ['amount' => '10:USD'];
		MultiFieldModel::applyTransform($row, true);
		$this->assertSame('10', $row['amount']);
		$this->assertSame('USD', $row['currency']);
	}

	public function testMultiFieldEncodeSkipsWhenAnyFieldMissing(): void {
		$row = ['amount' => 10];
		MultiFieldModel::applyTransform($row);
		$this->assertSame(10, $row['amount']);
		$this->assertArrayNotHasKey('currency', $row);
	}

	public function testMultiFieldDecodeSkipsWhenStorageMissing(): void {
		$row = ['other' => 1];
		MultiFieldModel::applyTransform($row, true);
		$this->assertSame(['other' => 1], $row);
	}
}
