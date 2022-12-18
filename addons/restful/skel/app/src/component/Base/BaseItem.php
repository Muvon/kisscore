<?php declare(strict_types=1);

namespace App\Component\Base;

abstract class BaseItem {
	/**
	 * Create current structure from the array
	 *
	 * @param array<string,mixed> $item
	 * @return static
	 */
	abstract public static function create(array $item): static;

	/**
	 * This method converts current structure to the array
	 *
	 * @return array<string,mixed>
	 */
	public function asArray(): array {
		return get_object_vars($this);
	}
}
