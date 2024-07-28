<?php declare(strict_types=1);

trait ArrayTrait {
	/**
	 * @param key-of<TArray> $k
	 * @param value-of<TArray> $v
	 * @return void
	 */
	public function offsetSet(mixed $k, mixed $v): void {
		$this->data[$k] = $v;
	}

	/**
	 * @param key-of<TArray> $k
	 * @return value-of<TArray>|null
	 */
	public function offsetGet(mixed $k): mixed {
		return $this->data[$k] ?? null;
	}

	/**
	 * @param key-of<TArray> $k
	 * @return bool
	 */
	public function offsetExists(mixed $k): bool {
		return isset($this->data[$k]);
	}

	/**
	 * @param key-of<TArray> $k
	 * @return void
	 */
	public function offsetUnset(mixed $k): void {
		$this->data[$k] = null;
	}
}
