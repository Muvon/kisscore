<?php declare(strict_types=1);

final class Result {
	/**
	 * @param ?string $err
	 * @param mixed $res
	 */
	public function __construct(public ?string $err, public mixed $res) {
	}

	/**
	 * @param mixed $res
	 * @return static
	 */
	public static function ok(mixed $res): static {
		return new static(null, $res);
	}

	/**
	 * @param string $err
	 * @param mixed $res
	 * @return static
	 */
	public static function err(string $err, mixed $res = null): static {
		return new static($err, $res);
	}

	/**
	 * @return array{0:?string,1:mixed}
	 */
	public function toArray(): array {
		return [$this->err, $this->res];
	}
}

