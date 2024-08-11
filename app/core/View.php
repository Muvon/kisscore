<?php declare(strict_types=1);

/**
 * Класс реализации представления
 *
 * @final
 *
 * <code>
 * View::create('template')->set(['test_var' => 'test_val'])->render();
 * </code>
 */
final class View {
	const VAR_PTRN = '\!?[a-z\_]{1}[a-z0-9\.\_]*';

	/** @var array<string,mixed> */
	protected array $data = [];
	/** @var string[] $routes */
	protected array $routes = [];
	/** @var string[] $output_filters */
	protected array $output_filters = [];
	/** @var string[] $compilers */
	protected array $compilers = [];

	protected string $body;
	protected string $source_dir;
	protected string $compile_dir;
	protected string $prefix = 'c';

	/**
	 * @static
	 * @var array<string,string>
	 */
	protected static array $filter_funcs = [
		'html' => 'htmlspecialchars',
		'url'  => 'rawurlencode',
		'json' => 'json_encode',
		'upper' => 'strtoupper',
		'lower' => 'strtolower',
		'ucfirst' => 'ucfirst',
		'md5' => 'md5',
		'nl2br' => 'nl2br',
		'count' => 'sizeof',
		'base64' => 'base64_encode',
		'lang' => 'Lang::translate',
		'date' => 'view_filter_date',
		'time' => 'view_filter_time',
		'datetime' => 'view_filter_datetime',
		'timestamp' => 'view_filter_timestamp',
		'raw'  => '',
	];

  /** @var string $template_extension */
	protected string $template_extension = 'tpl';

  /** @var array $block_path */
	protected array $block_path = [];

  /**
   * Финальный приватный конструктор, через него создания вида закрыто
   *
   * @see self::create
   */
	final protected function __construct() {
		$this->routes = [config('default.action')];

	  // Setup default settings
		$this->template_extension = config('view.template_extension');
		$this->source_dir = config('view.source_dir');
		$this->compile_dir = config('view.compile_dir');
	}

  /**
   * Add custom filter function that can be used with template var to modify it
   *
   * @param string $name alias to use in template
   * @param string $func global name of function
   * @return void
   */
	public static function registerFilterFunc(string $name, string $func): void {
		assert(!isset(static::$filter_funcs[$name]));
		if (str_contains($func, '::')) {
			[$class, $method] = explode('::', $func);
			assert(method_exists($class, $method));
		} else {
			assert(function_exists($func));
		}
		static::$filter_funcs[$name] = $func;
	}

	/**
	 * @param array<string,mixed> $config
	 * @return self
	 */
	public function configure(array $config): self {
		foreach ($config as $prop => $val) {
			if (!property_exists($this, $prop)) {
				continue;
			}

			$this->$prop = $val;
		}
		return $this;
	}

  /**
   * @param string $template
   * @return self
   */
	public function prepend(string $template): self {
		array_unshift($this->routes, $template);
		return $this;
	}

  /**
   * @param string $template
   * @return self
   */
	public function append(string $template): self {
		$this->routes[] = $template;
		return $this;
	}

  /**
   * Создание нового объекта вида
   *
   * @static
   * @access public
   * @param string[] ...$routes Список всех роутов в нужной последовательности для сборки
   * @return self
   */
	public static function create(...$routes): self {
		$View = new static;
		$View->routes = $routes;
		return $View;
	}

	/**
	 * @param string $content
	 * @return self
	 */
	public static function fromString(string $content): self {
		$View = new static;
		$View->body = $content;
		return $View;
	}

  /**
   * Получает уже обработанные и готовые данные для вывода функцией self::render()
   *
   * @access public
   * @return string
   */
	public function __toString(): string {
		return $this->getBody();
	}

	/**
	 * @param callable $filter
	 * @return self
	 */
	public function addOutputFilter(callable $filter): self {
		$this->output_filters = $filter;
		return $this;
	}

	/**
	 * @return string
	 */
	protected function getBody(): string {
		$body = $this->body;
		foreach ($this->output_filters as $filter) {
			$body = $filter($body);
		}
		return $body;
	}

  /**
   * Прикрепление массива как разных переменных в шаблон
   *
   * @access public
   * @param array<string,mixed> $data
   * @return self
   */
	public function set(array $data): self {
		$this->data = $data;
		return $this;
	}

	/**
	 * @param string|string[] $key
	 * @param mixed $val
	 * @return self
	 */
	public function assign(string|array $key, mixed $val = null): self {
		if (is_string($key)) {
			$this->data[$key] = $val;
		} elseif (is_array($key)) {
			$this->data = array_merge($this->data, $key);
		}
		return $this;
	}

	/**
	 * @param string $key
	 * @return mixed
	 */
	public function &access(string $key): mixed {
		return $this->data[$key];
	}

  /**
   * Обработчик блочных элементов скомпилированном шаблоне
   *
   * @param string $key
   *   Имя переменной
   * @param mixed $param
   *   Сам параметр, его значение
   * @param mixed $item
   *   Текущий айтем, т.к. возможно блок является вложенным и нужно передать текущий
   *   обходной элемент, если блок не является массивом
   * @param Closure $block
   *   Скомпилированный код, которые отображается внутри блока
   * @return self
   */
	protected function block(string $key, mixed $param, mixed $item, Closure $block): self {
		static $arrays = [];
		$arrays[$key] = is_array($param);
		if ($arrays[$key] && is_int(key($param))) {
			$last = sizeof($param) - 1;
			$i = 0;
			foreach ($param as $value) {
				if (!is_array($value)) {
					$value = ['parent' => $item, 'this' => $value];
				}

				$value['global']     = &$this->data;
				$value['first']      = $i === 0;
				$value['last']       = $i === $last;
				$value['even']       = $i % 2 ? true : false;
				$value['odd']        = !$value['even'];
				$value['iteration']  = ++$i;
				$block($value);
			}
		} elseif ($param) {
			if ($arrays[$key]) {
				$item   = $param + ['global' => &$this->data, 'parent' => $item];
				$block($item);
				$item = $item['parent'];
			} else {
				$block($item);
			}
		}
		return $this;
	}

	/**
	 * @param string $v
	 * @param string $container
	 * @return string
	 */
	protected static function chunkVar(string $v, string $container = '$item'): string {
		$var = '';
		foreach (explode('.', $v) as $p) {
			$var .= ($var ? '' : $container) . '[\'' . $p . '\']';
		}
		return $var;
	}

	/**
	 * @param string $v
	 * @param string $container
	 * @return string
	 */
	protected static function chunkVarExists(string $v, string $container = '$item'): string {
		$parts = explode('.', $v);
		$sz = sizeof($parts);
		$var = '';
		$i = 0;
		foreach ($parts as $p) {
			++$i;
			if ($i === $sz) {
				continue;
			}

			$var .= ($var ? '' : $container) . '[\'' . $p . '\']';
		}
		$array = ($var ?: $container);
		return 'isset(' . $array . ') && array_key_exists(\'' . $parts[array_key_last($parts)] . '\', ' . $array . ')';
	}

	/**
	 * @param string $str
	 * @return string
	 */
	protected static function chunkParseParams(string $str): string {
		$str = trim($str);
		if (!$str) {
			return '';
		}

		$code = '';
		foreach (array_map('trim', explode(' ', $str)) as $item) {
			[$key, $val] = array_map('trim', explode('=', $item));
			$code .= '<?php ' . static::chunkVar($key) . ' = ' . static::chunkVar($val) . '; ?>';
		}
		return $code;
	}

  /**
   * @param string $str
   * @return string
   */
	protected static function chunkTransformVars(string $str): string {
		$filter_ptrn = implode(
			'|',
			array_map(
				function ($v) {
					return '\:' . $v;
				},
				array_keys(static::$filter_funcs)
			)
		);

		return preg_replace_callback(
			'#\{(' . static::VAR_PTRN . ')(' . $filter_ptrn . ')?\}#ium',
			function ($matches) {
				$filter = 'raw';
				if (isset($matches[2])) {
					$filter = substr($matches[2], 1);
				}

				return '<?php if (isset(' . ($v = static::chunkVar($matches[1], '$item')) . ')) {'
				. 'echo ' . static::$filter_funcs[$filter] . '(' . $v . ');'
				. '} ?>';
			},
			$str
		);
	}

  /**
   * Transform one line blocks to closed blocks
   * @param string $str
   * @return string
   */
	protected function chunkCloseBlocks(string $str): string {
		$line_block = '#\{(' . static::VAR_PTRN . ')\:\}(.+)$#ium';

	  // Могут быть вложенные
		while (preg_match($line_block, $str) > 0) {
			$str = preg_replace($line_block, '{$1}' . PHP_EOL . '$2' . PHP_EOL . '{/$1}', $str);
		}

		return $str;
	}

  /**
   * @param string $str
   * @return string
   */
	protected function chunkCompileBlocks(string $str): string {
		return preg_replace_callback(
			'#\{(' . static::VAR_PTRN . ')\}(.+?){\/\\1}#ius',
			function ($m) {
				// Oh Shit so magic :)
				$this->block_path[] = $m[1];
				$compiled  = static::chunkTransformVars($this->chunkCompileBlocks($m[2]));
				array_pop($this->block_path);

				// Если стоит отрицание
				$denial = false;
				$key    = $m[1];

				if (str_starts_with($m[1], '!')) {
					$key = substr($m[1], 1);
				}

				if (strlen($m[1]) !== strlen($key)) {
					$denial = true;
				}

				return
				'<?php $param = ' . static::chunkVarExists($m[1], '$item')
					. ' ? ' . static::chunkVar($m[1], '$item')
					. ' : null;'
				// Блок с тегом отрицанием (no_ | not_) только если не существует переменной как таковой
				. ($denial ? ' if (!isset($param)) $param = !( ' . static::chunkVarExists($key, '$item')
					. ' ? ' . static::chunkVar($key, '$item')
					. ' : null);' : '')
				. '$this->block(\'' . $key . '\', $param, $item, function ($item) { ?>'
				. $compiled
				. '<?php }); ?>';
			},
			$str
		);
	}

  /**
   * Optimize output of compiled chunk if needed
   * @param string $str
   * @return string
   */
	protected function chunkMinify(string $str): string {
	  // Remove tabs and merge into single line
		if (config('view.merge_lines')) {
			$str = preg_replace(['#^\s+#ium', "|\>\s*\r?\n\<|ius", "|\s*\r?\n|ius"], ['', '><', ' '], $str);
		}

	  // Remove comments
		if (config('view.strip_comments')) {
			$str = preg_replace('/<!\-\-.+?\-\->/is', '', $str);
		}

		return $str;
	}

  /**
   * Компиляция примитивов шаблона
   *
   * @param string $route
   *   Роут шаблона для компиляции
   * @return string
   *   Имя скомпилированного файла
   */
	protected function compileChunk(string $route): string {
		$source_file = $this->getSourceFile($route);
		$file_c = $this->getCompiledFile([$route]);
		if (!App::$debug && is_file($file_c)) {
			return $file_c;
		}

		$str = file_get_contents($source_file);
	  // Do precompile by custom compiler to make it possible to change vars after
		$compilers = array_merge($this->compilers[$route] ?? [], $this->compilers['*'] ?? []);
		if ($compilers) {
			foreach ($compilers as $compiler) {
				$str = $compiler($str, $route);
			}
		}

		$str = $this->chunkCloseBlocks($str);

	  // Компиляция блоков
		$str = $this->chunkCompileBlocks($str);

		$str = $this->chunkMinify($str);

	  // Замена подключений файлов
		$str = preg_replace_callback(
			'#\{\>([a-z\_0-9\/]+)(.*?)\}#ium', function ($matches) {
				return static::chunkParseParams($matches[2]) . $this->getChunkContent($matches[1]);
			}, $str
		);

	  // Замена динамичных подключений файлов
		$str = preg_replace_callback(
			'#\{\>\>([a-z\_0-9\.]+)(.*?)\}#ium', function ($matches) {
				$route = static::chunkVar($matches[1], '$item');
				return '<?php '
				. '$this->compileChunk(' . $route . ');'
				.'include $this->getCompiledFile([' . $route . ']);'
				.'?>'
				;
			}, $str
		);

	  // Переменные: {array.index}
		$str = static::chunkTransformVars($str);

		file_put_contents($file_c, $str, LOCK_EX);
		return $file_c;
	}

  /**
   * Компиляция всех чанков и получение результата
   *
   * @return self
   */
	protected function compile(): self {
		$file_c = $this->getCompiledFile();
		if (App::$debug || !is_file($file_c)) {
		  // Init global context
			$content = '<?php $item = &$this->data; ?>';
			foreach ($this->routes as $template) {
				$content .= $this->getChunkContent($template);
			}

			file_put_contents($file_c, $content, LOCK_EX);
		}
		include $file_c;
		return $this;
	}

  // This methods initialize and configure language if its required by config
	/**
	 * @return self
	 */
	protected function initLanguage(): self {
		if (Lang::isEnabled()) {
			$lang = Lang::current();
			$this->configure(
				[
					'compile_dir' => config('view.compile_dir') . '/' . $lang,
				]
			)
			->addCompiler(Lang::getViewCompiler($lang))
			->assign('LANGUAGE_LIST', Lang::getList($lang))
			->assign('CURRENT_LANGUAGE', Lang::getInfo($lang))
			->assign('LANG', $lang);
		}

		return $this;
	}

	/**
	 * @param string $template
	 * @return string
	 */
	protected function getChunkContent(string $template): string {
		return file_get_contents($this->compileChunk($template));
	}

	/**
	 * @param callable $compiler
	 * @param string $template
	 * @return self
	 */
	public function addCompiler(callable $compiler, string $template = '*'): self {
		$this->compilers[$template][] = $compiler;
		return $this;
	}

	/**
	 * @param string $route
	 * @return string
	 */
	protected function getSourceFile(string $route): string {
		assert(is_dir($this->source_dir));
		assert(isset($this->template_extension[0]));

		return $this->source_dir . '/' . $route . '.' . $this->template_extension;
	}

	/**
	 * @param string[] $routes
	 * @return string
	 */
	protected function getCompiledFile(array $routes = []): string {
		assert(is_dir($this->compile_dir) && is_writable($this->compile_dir));
		return $this->compile_dir
		. '/view-' . $this->prefix . '-'
		. md5($this->source_dir . ':' . implode(':', $routes ?: $this->routes))
		. '.tplc'
		;
	}

  /**
   * Рендеринг и подготовка данных шаблона на вывод
   *
   * @access public
   * @param bool $quiet Quiet mode render empty string if no template found
   * @return self
   *   Записывает результат во внутреннюю переменную $body
   *   и возвращает ссылку на объект
   */
	public function render(bool $quiet = false): self {
		$this->initLanguage();

		if (isset($this->body)) {
			return $this;
		}

		try {
			ob_start();
			$this->compile();
			$this->body = ob_get_clean();
		} catch (Throwable $e) {
			if (!$quiet) {
				throw $e;
			}

			$this->body = '';
		}
		return $this;
	}

	/**
	 * @return void
	 */
	public static function flush(): void {
		$dir = escapeshellarg(config('view.compile_dir'));
		system('for file in `find ' . $dir . ' -name \'view-*\'`; do rm -f $file; done');
	}
}
