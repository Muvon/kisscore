<?php declare(strict_types=1);

namespace Lib;

use Error;

/** @package Lib */
final class AlphaId {

	/** @var string */
	private string $alphabet;

	/** @var int */
	private int $base;

	/** @var array<string,int> */
	private array $flipped = [];

	private function __construct() {
	}

	/**
	 * Create a new instance of the AlphaId class
	 * @param string $alphabet
	 * @return self
	 */
	public static function new(string $alphabet): self {
		$Self = new self();
		$Self->alphabet = $alphabet;
		$Self->flipped = array_flip(str_split($alphabet));
		$Self->base = strlen($alphabet);
		return $Self;
	}

	/**
	 * Encode the integer into the alpha id
	 * @param int|numeric-string $val
	 * @return string
	 */
	public function encode(int|string $val): string {
		if (is_int($val) && $val <= PHP_INT_MAX) {
			$str = '';
			do {
				$remainder = $val % $this->base;
				$str = $this->alphabet[$remainder] . $str;
				$val = ($val - $remainder) / $this->base;
			} while ($val > 0);
			return $str;
		}

		$val = gmp_init($val);
		$str = '';
		$zero = gmp_init(0);
		$base = gmp_init($this->base);

		while (gmp_cmp($val, $zero) > 0) {
			$remainder = gmp_intval(gmp_mod($val, $base));
			$str = $this->alphabet[$remainder] . $str;
			$val = gmp_div_q($val, $base);
		}

		return $str ?: $this->alphabet[0];
	}

	/**
	 * Decode the alpha id into the original integer
	 * @param string $val
	 * @return int
	 */
	public function decode(string $val): int {
		$len = strlen($val);
		$num = 0;

		for ($i = 0; $i < $len; ++$i) {
			if (!isset($this->flipped[$val[$i]])) {
				throw new Error('Cant decode alpha id');
			}
			$num = $num * $this->base + $this->flipped[$val[$i]];
		}
		return $num;
	}
}
