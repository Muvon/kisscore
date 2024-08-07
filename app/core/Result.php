<?php declare(strict_types=1);

/**
 * @template T
 * @phpstan-type string E
 */
final class Result {
	/**
	 * @param string $err
	 * @param T $res
	 */
	public function __construct(public ?string $err, public mixed $res) {
	}

	/**
	 * @param T $res
	 * @return static<string,T>
	 */
	public static function ok(mixed $res): static {
		return new static(null, $res);
	}

	/**
	 * @param T $res
	 * @return static<string,T>
	 */
	public static function err(string $err, mixed $res = null): static {
		return new static($err, $res);
	}

	/**
	 * Unwrap or throw exception if error
	 * @return T
	 * @throws ResultError<string>
	 */
	public function unwrap(): mixed {
		if ($this->err) {
			throw new ResultError($this->err);
		}

		return $this->res;
	}

	/**
	 * The function combines two or multiple results and return ok if all ok
	 * or first error from first result that failed
	 * @param Result<T> ...$Results
	 * @return Result<T>
	 */
	public static function unwrapAll(Result ...$Results): Result {
		$oks = [];
		foreach ($Results as $Result) {
			if ($Result->err) {
				return $Result;
			}
			$oks[] = $Result->unwrap();
		}
		return ok($oks);
	}

	/**
	 * @return array{string,T}
	 */
	public function toArray(): array {
		return [$this->err, $this->res];
	}
}

