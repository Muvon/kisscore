<?php declare(strict_types=1);

class Secret {
	/**
	 *
	 * @param string $key
	 * @return void
	 */
	public function __construct(protected string $key) {
		$decoded = hex2bin($key);
		if ($decoded === false) {
			throw new \InvalidArgumentException('Invalid hex key');
		}
		$this->key = $decoded;
	}

	/**
	 * Static helper to initialize with request key
	 * @param string $key
	 * @return static
	 */
	public static function with(string $key): static {
		/** @phpstan-ignore-next-line Unsafe new static() — class is not final but subclasses must maintain constructor contract */
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
		$result = sodium_crypto_aead_aes256gcm_decrypt($encrypted, '', $nonce, $this->key);
		if ($result === false) {
			throw new \RuntimeException('Decryption failed');
		}
		return $result;
	}
}
