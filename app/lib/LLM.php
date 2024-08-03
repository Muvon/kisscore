<?php declare(strict_types=1);

namespace Lib;

use Orhanerday\OpenAi\OpenAi;
use Result;

/** @package Lib */
final class LLM {
	/**
	 * Translate the original prompt to English and return the translated text
	 * @param string $prompt
	 * @return Result<string>
	 */
	protected static function translate(string $prompt): Result {
		$prompt = 'Translate the original input to the English and return the translated text'
		. ' without adding anything else to it. Input: ' . $prompt;
		$Client = new OpenAi(getenv('OPENAI_API_TOKEN'));
		$Client->setTimeout(10);
		$opts = [
			'model' => 'gpt-3.5-turbo',
			'messages' => [
				[
					'role' => 'system',
					'content' => '',
				],
				[
					'role' => 'user',
					'content' => $prompt,
				],
			],
			'temperature' => 1,
			'max_tokens' => 500,
			'frequency_penalty' => 0,
			'presence_penalty' => 0.6,
			'stream' => false,
		];

		$result = $Client->chat($opts);
		if (!is_string($result)) {
			return err('e_translate_failed', $result);
		}
		return ok($result);
	}
}
