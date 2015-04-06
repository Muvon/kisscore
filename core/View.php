<?php
/**
 * Класс реализации представления
 *
 * @final
 * @package Core
 * @subpackage View
 *
 * <code>
 * View::create('template')->set(['test_var' => 'test_val'])->render();
 * </code>
 */
class View {
  const VAR_PTRN = '\!?[a-z\_]{1}[a-z0-9\.\_]*';

  /**
   * @property array $data массив переменных, которые использует подключаемый шаблон
   * @property string $body обработанные и готовые данные для отдачи их клиенту
   */
  protected $data = [];
  protected $route = '';
  protected $body = '';
  protected $source_dir = null;
  protected $compile_dir = null;

  protected static $filter_funcs = [
    'html' => 'htmlspecialchars',
    'raw'  => '',
  ];

  /** @var string $template_extension */
  protected $template_extension = 'tpl';

  /** @var array $block_path */
  protected $block_path = [];

  /**
   * @var array $head
   * @var array $foot
   */
  protected $head = [], $foot = [];

  /**
   * Финальный приватный конструктор, через него создания вида закрыто
   *
   * @see self::create
   */
  final protected function __construct() {
    $this->route = config('default.action');

    // Setup default settings
    $this->template_extension = config('view.template_extension');
    $this->source_dir = config('view.source_dir');
    $this->compile_dir = config('view.compile_dir');
  }

  public function configure(array $config) {
    foreach ($config as $prop => $val) {
      if (property_exists($this, $prop)) {
        $this->$prop = $val;
      }
    }
    return $this;
  }

  /**
   * @param string $template
   * @return View
   */
  public function addHead($template) {
    $this->head[] = $template;
    return $this;
  }

  /**
   * @return View
   */
  public function resetHead() {
    $this->head = [];
    return $this;
  }

  /**
   * @param string $template
   * @return View
   */
  public function addFoot($template) {
    $this->foot[] = $template;
    return $this;
  }

  /**
   * @return View
   */
  public function resetFoot() {
    $this->foot = [];
    return $this;
  }

  /**
   * Создание нового объекта вида
   *
   * @static
   * @access public
   * @param string $route строка вида модуль/шаблон, имя шаблона обычно приравнивается выполняемому действию
   * @return View
   */
  public static function create($route) {
    $View = new static;
    $View->route = $route;
    return $View;
  }

  public static function fromString($content) {
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
  public function __toString( ) {
    return $this->body;
  }

  /**
   * Прикрепление массива как разных переменных в шаблон
   *
   * @access public
   * @param array $data
   * @return View
   */
  public function set(array $data) {
    $this->data = $data;
    return $this;
  }

  public function assign($key, $val = null) {
    assert('in_array(gettype($key), ["string", "array"])');
    if (is_string($key)) {
      $this->data[$key] = $val;
    } elseif (is_array($key)) {
      $this->data = array_merge($this->data, $key);
    }
    return $this;
  }

  public function &access($key) {
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
   * @return View
   */
  protected function block($key, $param, $item, Closure $block) {
    assert("is_string(\$key)");

    static $arrays = [];
    $arrays[$key] = is_array($param);
    if ($arrays[$key] && is_int(key($param))) {
      $last = sizeof($param) - 1;
      $i = 0;
      foreach ($param as $k => $value) {
        if (!is_array($value)) {
          $value = ['parent' => $item, 'this' => $value];
        }

        $value['global']     = &$this->data;
        $value['first']      = $i === 0;
        $value['last']       = $i === $last;
        $value['even']       = $i % 2 ?  true : false;
        $value['odd']        = !$value['even'];
        $value['iteration']  = ++$i;
        $block($value);
      }
    } elseif ($param) {
      if ($arrays[$key]) {
        $item   = $param + ['global' => &$this->data, 'parent' => $item];
        $block($item);
        $item = $item['parent'];
      } else $block($item);

    }
    return $this;
  }


  protected static function chunkVar($v, $container = '$item') {
    $ex = explode('.', $v);
    $var = '';
    foreach ($ex as $p) {
      $var .= ($var ? '' : $container) . '[\'' . $p . '\']';
    }
    return $var;
  }


  protected static function chunkVarExists($v, $container = '$item') {
    $ex = explode('.', $v);
    $sz = sizeof($ex);
    $var = '';
    $i = 0;
    foreach ($ex as $p) {
      ++$i;
      if ($i !== $sz) {
        $var .= ($var ? '' : $container) . '[\'' . $p . '\']';
      }
    }
    $array = ($var ?: $container);
    return 'isset(' . $array . ') && array_key_exists(\'' . $p . '\', ' . $array . ')';
  }

  protected static function chunkParseParams($str) {
    $str = trim($str);
    if (!$str)
      return '';

    $ex = array_map('trim', explode(' ', $str));
    $code = '';
    foreach ($ex as $item) {
      list($key, $val) = array_map('trim', explode('=', $item));
      $code .= '<?php ' . static::chunkVar($key) . ' = ' . static::chunkVar($val) . '; ?>';
    }
    return $code;
  }

  protected static function chunkTransformVars($str) {
    $filter_ptrn = implode(
      '|' ,
      array_map(
        function($v) {
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

  protected function chunkCompileBlocks($str) {
    return preg_replace_callback(
      '#\{(' . static::VAR_PTRN . ')\}(.+?){\/\\1}#ius',
      function ($m) {
        // Oh Shit so magic :)
        $this->block_path[] = $m[1];
        $compiled  = static::chunkTransformVars(static::chunkCompileBlocks($m[2]));
        array_pop($this->block_path);

        // Если стоит отрицание
        $denial = false;
        $key    = $m[1];

        if (0 === strpos($m[1], '!'))
          $key = substr($m[1], 1);

        if (strlen($m[1]) !== strlen($key))
          $denial = true;

        return
          '<?php $param = ' . static::chunkVarExists($m[1], '$item') . ' ? ' . static::chunkVar($m[1], '$item') . ' : null;'
        . ($denial ? ' if (!isset($param)) $param = !( ' . static::chunkVarExists($key, '$item') . ' ? ' . static::chunkVar($key, '$item') . ' : null);' : '') // Блок с тегом отрицанием (no_ | not_) только если не существует переменной как таковой
        . '$this->block(\'' . $key . '\', $param, $item, function ($item) { ?>'
          . $compiled
        . '<?php }); ?>';
      },
      $str
    );
  }

  /**
   * Компиляция примитивов шаблона
   *
   * @param string $route
   *   Роут шаблона для компиляции
   * @return string
   *   Имя скомпилированного файла
   */
  protected function compileChunk($route) {
    $file_c = $this->compile_dir . '/view-' . md5($route) . '.chunk';
    if (!App::$debug && is_file($file_c))
      return $file_c;

    $source_file = $this->source_dir . '/' . strtok($route, '/') . '.' . $this->template_extension;

    $str = file_get_contents($source_file);

    // Закрываем строчные блоки
    $line_block = '#\{(' . static::VAR_PTRN . ')\:\}(.+)$#ium';

    // Могут быть вложенные
    while (preg_match($line_block, $str) > 0)
      $str = preg_replace($line_block, '{$1}' . PHP_EOL . '$2' . PHP_EOL . '{/$1}', $str);

    // Компиляция блоков
    $str = $this->chunkCompileBlocks($str);

    // Remove tabs and merge into single line
    $str = preg_replace(['#^\s+#ium', "|\s*\r?\n|ius"], '', $str);

    // Замена подключений файлов
    $str = preg_replace_callback('#\{\>([a-z\_0-9\/]+)(.*?)\}#ium', function ($matches) {
      return static::chunkParseParams($matches[2]) . file_get_contents($this->compileChunk($matches[1]));
    }, $str);

    // Переменные: {array.index}
    $str = static::chunkTransformVars($str);

    file_put_contents($file_c, $str);
    return $file_c;
  }

  /**
   * Компиляция всех чанков и получение результата
   *
   * @return View
   */
  protected function compile() {
    assert("is_string(\$this->source_dir) && is_dir(\$this->source_dir)");
    assert("is_string(\$this->compile_dir) && is_dir(\$this->compile_dir) && is_writable(\$this->compile_dir)");
    assert("is_string(\$this->route)");
    assert("is_string(\$this->template_extension) && isset(\$this->template_extension[0])");

    $file_c =  $this->compile_dir . '/view-' . md5(implode(',', $this->head) . ':' . $this->route . ':' . implode(',', $this->foot)) . '.page';
    if (App::$debug || !is_file($file_c)) {
      $content = [];
      foreach ($this->head as $template) {
        $content[] = file_get_contents($this->compileChunk($template));
      }
      $content[] = file_get_contents($this->compileChunk($this->route));
      foreach ($this->foot as $template) {
        $content[] = file_get_contents($this->compileChunk($template));
      }

      // Init global context
      array_unshift($content, '<?php $item = &$this->data; ?>');
      file_put_contents($file_c, implode($content));
    }
    include $file_c;
    return $this;
  }

  /**
   * Рендеринг и подготовка данных шаблона на вывод
   *
   * @access public
   * @return View
   *   Записывает результат во внутреннюю переменную $body
   *   и возвращает ссылку на объект
   */
  public function render() {
    if ($this->body) {
      return $this;
    }

    ob_start();
    $this->compile();
    $this->body = ob_get_clean();

    return $this;
  }
}
