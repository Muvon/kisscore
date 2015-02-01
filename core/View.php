<?php
/**
 * Класс реализации представления
 *
 * @final
 * @package Core
 * @subpackage View
 *
 * <code>
 * $param1 = 'value';
 * $param2 = 2;
 * $view = new View::create('route/path')
 *   ->set(compact(
 *     'param1',
 *     'param2'
 *   ))
 *   ->render( )
 *   ->getBody( )
 * ;
 * </code>
 */
class View {
  protected static $Instance = null;
  /**
   * @property bool $debug
   * @property array $data массив переменных, которые использует подключаемый шаблон
   * @property string $body обработанные и готовые данные для отдачи их клиенту
   */
  protected
  $debug         = false,
  $data          = [],
  $route         = '',
  $body          = '',
  $source_dir    = null,
  $compile_dir   = null;

  protected static $filter_funcs  = [
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
  final protected function __construct( ) {
    $this->route = Request::instance()->getAction();

    // Setup default settings
    $this->debug = App::$debug;
    $this->template_extension = config('view.template_extension');
    $this->source_dir = config('view.source_dir');
    $this->compile_dir = config('view.compile_dir');
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
   * @param string $extension
   * @return View
   */
  public function setTemplateExtension($extension) {
    assert("is_string(\$extension)");
    $this->template_extension = $extension;
    return $this;
  }

  /**
   * @access public
   * @param string $dir
   * @return View
   */
  public function setSourceDir($dir) {
    assert("is_string(\$dir)");
    assert("is_dir(\$dir)");
    $this->source_dir = $dir;
    return $this;
  }

  /**
   * @param $dir
   * @return View
   */
  public function setCompileDir($dir) {
    assert("is_string(\$dir)");
    assert("is_dir(\$dir)");
    $this->compile_dir = $dir;
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
  public static function create($route = '') {
    return new self;
  }

  /**
   * @static
   * @access public
   * return $this
   */
  public static function instance( ) {
    if (!isset(self::$Instance)) {
      self::$Instance = self::create( );
    }
    return self::$Instance;
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
   * Роут
   *
   * @param string $route
   * @return View
   */
  public function setRoute($route) {
    assert("is_string(\$route)");
    $this->route = $route;
    return $this;
  }

  /**
   * Режим отладки
   *
   * @param bool $flag
   * @return View
   */
  public function setDebug($flag) {
    assert("is_bool(\$flag)");
    $this->debug = $flag;
    return $this;
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
  public function block($key, $param, $item, Closure $block) {
    assert("is_string(\$key)");

    static $arrays = [];
    $arrays[$key] = is_array($param);

    if ($arrays[$key] && is_int(key($param))) {
      $last = sizeof($param) - 1;
      foreach ($param as $key => $value) {
        static $i = 0;

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
      } unset($key,  $item, $value, $i, $last);
    } elseif ($param) {
      if ($arrays[$key]) {
        $item   = $param + ['global' => &$this->data, 'parent' => $item];
        $block($item);
        $item = $item['parent'];
      } else $block($item);

    }
    return $this;
  }

  /**
   * Компиляция примитивов шаблона
   *
   * @param string $route
   *   Роут шаблона для компиляции
   * @return string
   *   Имя скомпилированного файла
   */
  public function compileChunk($route) {
    $file_c = $this->compile_dir . '/view-' . md5($route) . '.chunk';
    if (!$this->debug && is_file($file_c))
      return $file_c;

    $source_file = $this->source_dir . '/' . strtok($route, '/') . '.' . $this->template_extension;

    $str = file_get_contents($source_file);

    // Получение переменной из шаблона
    $var = function ($v, $container = '$this->data') {
      $ex = explode('.', $v);
      $var = '';
      foreach ($ex as $p) {
        $var .= ($var ? '' : $container) . '[\'' . $p . '\']';
      }
      return $var;
    };

    $var_exists = function ($v, $container = '$this->data') {
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
      $array = ($var ? $var : $container);
      return 'isset(' . $array . ') && array_key_exists(\'' . $p . '\', ' . $array . ')';
    };

    // Шаблон имени переменной
    $var_ptrn = '[a-z\_]{1}[a-z0-9\.\_]*';

    $parse_params = function ($str) use($var) {
      $str = trim($str);
      if (!$str)
        return '';

      $ex = array_map('trim', explode(' ', $str));
      $code = '';
      foreach ($ex as $item) {
        list($key, $val) = array_map('trim', explode('=', $item));
        $code .= '<?php ' . $var($key) . ' = ' . $var($val) . '; ?>';
      }
      return $code;
    };

    // Transform variables from template
    $transform_vars = function ($str) use($var_ptrn, $var) {
      return preg_replace_callback(
        '#\{(' . $var_ptrn . ')(\:raw|\:html)?\}#ium',
        function ($matches) use ($var) {
          $filter = 'raw';
          if (isset($matches[2])) {
            $filter = substr($matches[2], 1);
          }

          return '<?php if (isset(' . ($v = $var($matches[1], '$item')) . ')) {'
          . 'echo ' . static::$filter_funcs[$filter] . '(' . $v . ');'
          . '} ?>';
        },
        $str
      );
    };

    // Закрываем строчные блоки
    $line_block = '#\{(' . $var_ptrn . ')\:\}(.+)$#ium';

    // Могут быть вложенные
    while (preg_match($line_block, $str) > 0)
      $str = preg_replace($line_block, '{$1}' . PHP_EOL . '$2' . PHP_EOL . '{/$1}', $str);

    // Компиляция блоков
    $compile_blocks = function ($str, $compile_blocks) use($var_ptrn, $transform_vars, $var, $var_exists) {
      return preg_replace_callback(
        '#\{(' . $var_ptrn . ')\}(.+?){\/\\1}#ius',
        function ($m) use($var, $var_exists, $transform_vars, $compile_blocks, $str) {
          $ret = '';
          // Oh Shit so magic :)
          $this->block_path[] = $m[1];
          $block_key = implode('.', $this->block_path);
          $compiled  = $transform_vars($compile_blocks($m[2], $compile_blocks));
          array_pop($this->block_path);

          // Если стоит отрицание
          $denial = false;
          $key    = $m[1];

          if (0 === stripos($m[1], 'no_'))
            $key = substr($m[1], 3);

          if (0 === stripos($m[1], 'not_'))
            $key = substr($m[1], 4);

          if (strlen($m[1]) !== strlen($key))
            $denial = true;

          return
            '<?php $param = ' . $var_exists($m[1], '$item') . ' ? ' . $var($m[1], '$item') . ' : (isset(' . $var($m[1]) . ') ? ' . $var($m[1]) . ' : null);'
          . ($denial ? ' if (!isset($param)) $param = !( ' . $var_exists($key, '$item') . ' ? ' . $var($key, '$item') . ' : (isset(' . $var($key) . ') ? ' . $var($key) . ' : null));' : '') // Блок с тегом отрицанием (no_ | not_) только если не существует переменной как таковой
          . '$this->block(\'' . $key . '\', $param, isset($item) ? $item : null, function ($item = null) { ?>'
            . $compiled
          . '<?php }); ?>';
        },
        $str
      );
    };
    $str = $compile_blocks($str, $compile_blocks);

    // Remove tabs and merge into single line
    $str = preg_replace(['#^\s+#ium', "|\s*\r?\n|ius"], '', $str);

    // Замена подключений файлов
    $str = preg_replace_callback('#\{\>([a-z\_0-9\/]+)(.*?)\}#ium', function ($matches) use ($parse_params) {
      return $parse_params($matches[2]) . file_get_contents($this->compileChunk($matches[1]));
    }, $str);

    // Переменные: {array.index}
    $str = $transform_vars($str, $var_ptrn, $var);

    file_put_contents($file_c, $str);
    return $file_c;
  }

  /**
   * Компиляция всех чанков и получение результата
   *
   * @return View
   */
  protected function compile() {
    assert("is_string(\$this->source_dir)");
    assert("is_dir(\$this->source_dir)");
    assert("is_string(\$this->compile_dir)");
    assert("is_dir(\$this->compile_dir)");
    assert("is_writable(\$this->compile_dir)");
    assert("is_string(\$this->route)");
    assert("is_string(\$this->template_extension)");
    assert("isset(\$this->template_extension[0])");

    $file_c =  $this->compile_dir . '/view-' . md5(implode(',', $this->head) . ':' . $this->route . ':' . implode(',', $this->foot)) . '.page';
    if ($this->debug || !is_file($file_c)) {
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
   * @param string $route
   *   Опциональное указание роута
   * @return View
   *   Записывает результат во внутреннюю переменную $body
   *   и возвращает ссылку на объект
   */
  public function render($route = '') {
    assert("is_string(\$route)");
    ob_start();

    if ($route)
      $this->setRoute($route);

    $this->compile();
    $this->body = ob_get_clean();
    return $this;
  }
}