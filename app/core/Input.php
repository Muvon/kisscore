<?php declare(strict_types=1);

final class Input {
	public static bool $is_parsed = false;

	/** @var array<string,mixed> */
	public static array $params = [];

	protected static Closure $parse_fn;
	/**
	 * @return bool
	 */
	public static function isCli(): bool {
		return !!filter_input(INPUT_SERVER, 'argc');
	}

	/**
	 * @return bool
	 */
	public static function isJson(): bool {
		return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/json');
	}

	/**
	 * @return bool
	 */
	public static function isMsgpack(): bool {
		return str_starts_with(filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '', 'application/msgpack');
	}

	/**
	 * @return bool
	 */
	public static function isRaw(): bool {
		return filter_has_var(INPUT_SERVER, 'REQUEST_URI') && !static::isJson();
	}

  /**
   * Парсит и сохраняет все параметры в переменной self::$params
   *
   * @return void
   */
	protected static function parse(): void {
		if (static::$is_parsed) {
			return;
		}

		if (static::isCli()) {
			$argv = filter_input(INPUT_SERVER, 'argv');
			array_shift($argv); // file
			static::$params['ACTION'] = array_shift($argv);
			$get = $argv;
			$post = [];
		} elseif (static::isJson()) {
			$get = (array)filter_input_array(INPUT_GET);
			$post = (array)json_decode(file_get_contents('php://input'), true);
		} elseif (static::isMsgpack()) {
			$get = (array)filter_input_array(INPUT_GET);
			$post = (array)msgpack_unpack(file_get_contents('php://input'));
		} else {
			$get = (array)filter_input_array(INPUT_GET);
			$post = (array)filter_input_array(INPUT_POST);
		}
		// Clean up system variables that are not supposed to be in post
		unset($post['ROUTE'], $post['ACTION']);
		static::$params = $get + $post;
		static::$is_parsed = true;
	}

	/**
	 * Set parser
	 * @param Callable $fn [description]
	 * @return void
	 */
	public static function setParser(Callable $fn): void {
	  static::$is_parsed = false;
	  static::$parse_fn = $fn;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public static function set(string $key, mixed $value): void {
		static::$is_parsed || static::parse();
		static::$params[$key] = $value;
	}

  /**
   * Получение переменной запроса
   *
   * <code>
   * $test = Input::get('test');
   *
   * $params = Input::get(['test:int=1']);
   * </code>
	 *
	 * @param string|string[] $args
	 * @return mixed
   */
	public static function get(...$args): mixed {
		static::$is_parsed || static::parse();

		if (!isset($args[0])) {
			return static::$params;
		}

	  // String key?
		if (is_string($args[0])) {
			return static::$params[$args[0]] ?? ($args[1] ?? null);
		}

		if (is_array($args[0])) {
			return static::extractTypified(
				$args[0], function ($key, $default = null) {
					return static::get($key, $default);
				}
			);
		}
	  // Exctract typifie var by mnemonic rules as array


		trigger_error('Error while fetch key from input');
	}

  /**
   * Извлекает и типизирует параметры из массива args с помощью функции $fetcher, которая
   * принимает на вход ключ из массива args и значение по умолчанию, если его там нет
   *
   * @param array $args
   * @param Closure $fetcher ($key, $default)
	 * @return array<string,string|int|bool>
   */
	public static function extractTypified(array $args, Closure $fetcher): array {
		$params = [];
		foreach ($args as $arg) {
			preg_match('#^([a-zA-Z0-9_]+)(?::([a-z]+))?(?:=(.+))?$#', $arg, $m);
			$params[$m[1]]  = $fetcher($m[1], $m[3] ?? '');

		  // Нужно ли типизировать
			if (!isset($m[2])) {
				continue;
			}

			$params[$m[1]] = typify($params[$m[1]], $m[2]);
		}
		return $params;
	}
}
