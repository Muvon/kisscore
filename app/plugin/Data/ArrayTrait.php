<?php declare(strict_types=1);

namespace Plugin\Data;

trait ArrayTrait {
	/**
	 * @param mixed $k
	 * @param mixed $v
	 * @return void
	 */
	public function offsetSet(mixed $k, mixed $v): void {
		$this->data[$k] = $v;
	}

	/**
	 * @param mixed $k
	 * @return mixed
	 */
	public function offsetGet(mixed $k): mixed {
		return $this->data[$k] ?? null;
	}

	/**
	 * @param mixed $k
	 * @return bool
	 */
	public function offsetExists(mixed $k): bool {
		return isset($this->data[$k]);
	}

	/**
	 * @param mixed $k
	 * @return void
	 */
	public function offsetUnset(mixed $k): void {
		$this->data[$k] = null;
	}
}
