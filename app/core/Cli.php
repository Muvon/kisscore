<?php declare(strict_types=1);

final class Cli {
	const LEVEL_DEBUG = 0;
	const LEVEL_WARNING = 1;
	const LEVEL_INFO = 2;

  /**
   * This function reads hidden input (password) from stdin
   *
   * @param string|null $promt
   * @return string
   */
	public static function readSecret(?string $promt = null): string {
		if ($promt) {
			echo $promt;
		}

		system('stty -echo');
		$secret = trim(fgets(STDIN));
		system('stty echo');

		return $secret;
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
			echo $date . ' ' . rtrim($line) . PHP_EOL;
		}
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
