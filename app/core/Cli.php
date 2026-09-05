<?php declare(strict_types=1);

final class Cli {
	const LEVEL_DEBUG = 0;
	const LEVEL_WARNING = 1;
	const LEVEL_INFO = 2;

	protected static string $prefix = '';

	/**
	 * This function reads hidden input (password) from stdin
	 *
	 * @param string|null $prompt
	 * @return string
	 */
	public static function readSecret(?string $prompt = null): string {
		if ($prompt) {
			echo $prompt;
		}

		system('stty -echo');
		$secret = trim((string)fgets(STDIN));
		system('stty echo');

		return $secret;
	}

	/**
	 * Set the prefix printed before every line, e.g. the symbol a worker
	 * is currently processing; empty string disables it
	 *
	 * @param string $prefix
	 * @return void
	 */
	public static function prefix(string $prefix): void {
		static::$prefix = $prefix;
	}

	/**
	 * @param string|string[] $lines
	 * @param int $level
	 * @return void
	 */
	public static function print(string|array $lines, int $level = 2): void {
		if (isset(App::$log_level) && App::$log_level > $level) {
			return;
		}

		if (is_string($lines)) {
			$lines = [$lines];
		}
		$date = gmdate('[Y-m-d H:i:s T]');
		foreach ($lines as $line) {
			$prefix = $date . (static::$prefix ? ' [' . static::$prefix . '] ' : '');
			echo $prefix . ' ' . rtrim($line) . PHP_EOL;
		}
	}

	/**
	 * Print a sprintf-formatted line
	 *
	 * @param string $line
	 * @param bool|float|int|string|null ...$args
	 * @return void
	 */
	public static function printf(string $line, bool|float|int|string|null ...$args): void {
		static::print(sprintf($line, ...$args));
	}

	/**
	 * @param mixed $var
	 * @return void
	 */
	public static function dump(mixed $var): void {
		$date = gmdate('[Y-m-d H:i:s T]');
		echo $date . ' ' . var_export($var, true) . PHP_EOL;
	}

	/**
	 * @param string $line
	 * @param int $error_code
	 * @return void
	 */
	public static function error(string $line, int $error_code = 1): void {
		static::print($line);
		exit($error_code);
	}
}
