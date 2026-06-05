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
	 * Static factories can't reference the class template `T` (it's bound per
	 * instance, not at the class), so this declares its own method template and
	 * infers the payload type straight from `$res` — `Result::ok($user)` yields
	 * `Result<User>`, not `Result<mixed>`.
	 * @template TValue
	 * @param TValue $res
	 * @return self<TValue>
	 */
	public static function ok(mixed $res): self {
		return new self(null, $res);
	}

	/**
	 * A failed result carries no success value, so its payload type is the
	 * bottom type `never` — which, with the covariant template, lets it flow
	 * into any `Result<X>` slot. `$res` is optional error *context*, not a
	 * success payload. The constructor binds `T` to the `mixed` context value,
	 * so the `never` narrowing is asserted here rather than inferred.
	 * @return self<never>
	 */
	public static function err(string $err, mixed $res = null): self {
		/** @phpstan-ignore-next-line err() narrows the payload to never; see above */
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
	 * In case of error return default value. `$default` gets its own template so
	 * the covariant `T` never lands in a parameter (contravariant) position, and
	 * the caller gets the precise `T|TDefault` union back.
	 * @template TDefault
	 * @param TDefault $default
	 * @return T|TDefault
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
