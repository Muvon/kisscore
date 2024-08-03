<?php declare(strict_types=1);

namespace Lib;

use Error;
use Exception;
use SodiumException;

/** @package Lib */
final class Secret {
	protected string $key;

	private function __construct() {
	}

	/**
	 * @param string $key
	 * @return self
	 */
	public function new(string $key): self {
		$bin = hex2bin($key);
		if (!$bin) {
			throw new Error('Cant decode secret key');
		}

		$Self = new self;
		$Self->key = $bin;
		return $Self;
	}

	/**
	 *
	 * @param string $key
	 * @return self
	 */
	public static function with(string $key): self {
		return self::new($key);
	}

	/**
	 *
	 * @param string $payload
	 * @return array{0:string,1:string}
	 * @throws Exception
	 * @throws SodiumException
	 */
	public function encrypt(string $payload): array {
		$nonce = random_bytes(12);
		$encrypted = sodium_crypto_aead_aes256gcm_encrypt($payload, '', $nonce, $this->key);
		return [$encrypted, $nonce];
	}

	/**
	 *
	 * @param string $encrypted
	 * @param string $nonce
	 * @return string
	 * @throws SodiumException
	 */
	public function decrypt(string $encrypted, string $nonce): string {
		$decrypted = sodium_crypto_aead_aes256gcm_decrypt($encrypted, '', $nonce, $this->key);
		if (!$decrypted) {
			throw new Error('Cant decrypt');
		}
		return $decrypted;
	}
}
