<?php declare(strict_types=1);

class Secret {
	/**
	 *
	 * @param string $key
	 * @return void
	 */
	public function __construct(protected string $key) {
		$this->key = hex2bin($key);
	}

	/**
	 * Static helper to initialize with request key
	 * @param mixed $key
	 * @return static
	 */
	public static function with(string $key): static {
		return new static($key);
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
		return sodium_crypto_aead_aes256gcm_decrypt($encrypted, '', $nonce, $this->key);
	}
}
