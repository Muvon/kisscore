<?php declare(strict_types=1);

/**
 * Lightweight Rust-style Result wrapper. `T` is the success payload — covariant
 * so a `Result<Specific>` flows naturally into a caller declaring `Result<Wider>`
 * (e.g. composing `Result<User>` into a method that returns `Result<mixed>`).
 * The class is final, mutation-free past construction, and only ever exposes
 * `T` via read accessors (`->res`, `unwrap()`, `unwrapOr()`), so covariance is
 * sound here — there is no consumer position that would observe `T`.
 *
 * @template-covariant T
 */
final class Result {
	/**
	 * @param ?string $err
	 * @param T $res
	 */
	public function __construct(public ?string $err, public mixed $res) {
	}

	/**
	 * @param T $res
	 * @return self<T>
	 */
	public static function ok(mixed $res): self {
		return new self(null, $res);
	}

	/**
	 * @param T $res
	 * @return self<T>
	 */
	public static function err(string $err, mixed $res = null): self {
		return new self($err, $res);
	}

	/**
	 * Unwrap or throw exception if error
	 * @return T
	 */
	public function unwrap(): mixed {
		if ($this->err) {
			$message = $this->err;
			if ($this->res) {
				$message .= ': ' . json_encode($this->res);
			}
			throw new ResultError($message);
		}

		return $this->res;
	}

	/**
	 * In case of error return default value
	 * @param T $default
	 * @return T
	 */
	public function unwrapOr(mixed $default): mixed {
		if ($this->err) {
			return $default;
		}

		return $this->res;
	}


	/**
	 * The function combines two or multiple results and return ok if all ok
	 * or first error from first result that failed
	 * @param Result<T> ...$Results
	 * @return array<T>
	 */
	public static function unwrapAll(Result ...$Results): array {
		return array_map(fn($Result) => $Result->unwrap(), $Results);
	}

	/**
	 * @return array{?string,T}
	 */
	public function toArray(): array {
		return [$this->err, $this->res];
	}
}
