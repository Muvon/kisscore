<?php
/**
 * Класс для работы с запросом и переменными запроса
 *
 * @final
 * @package Core
 * @subpackage Request
 *
 * <code>
 * $response = Request::instance( )
 *   ->getResponse()
 *   ->addHeader('Content-type', 'text/html;charset=utf-8')
 *   ->sendHeaders( )
 * ;
 * echo $response;
 * </code>
 */
class Request {
  /**
   * @property Response $Response класс ответа для данного запроса
   * @property array $params все параметры, переданные в текущем запросе
   *
   * @property string $action имя действия, которое должно выполнится в выполняемом запросе
   * @property string $module имя модуля, который вызывается запросом
   * @property string $url адрес обрабатываемого запроса
   * @property array $url_map
   * @property array $route_map
   *
   * @property Request $instance хэндлер объекта текущего запроса
   * @property string $method вызываемый метод на данном запросе (GET | POST)
   * @property string $protocol протокол соединения, например HTTP, CLI и т.п.
   * @property string $referer реферер, если имеется
   * @property string $ip IP-адрес клиента
   * @property string $xff ip адрес при использовании прокси, заголовок: X-Forwarded-For
   * @property string $user_agent строка, содержащая USER AGENT браузера клиента
   * @property string $host Хост, который выполняет запрос
   * @property bool $is_cli является ли запрос CLI или же обычный HTTP
   * @property bool $is_ajax запрос посылается через ajax
   */
  
  private
  $Response     = null,
  $params       = [],
  $action   = '',
  $module   = '',
  $url      = '',
  $url_map    = [],
  $route_map  = [];
  
  private static
  $Instance    = null,
  $method      = 'GET',
  $protocol    = 'HTTP',
  $referer     = '',
  $ip          = '0.0.0.0',
  $real_ip     = '0.0.0.0',
  $xff         = '',
  $host        = '',
  $user_agent  = '',
  $is_cli      = false,
  $is_ajax     = false;
  
  /**
   * @param string|bool $url адрес текущего запроса
   */
  public function __construct($url) {
    assert("in_array(gettype(\$url), ['string', 'boolean'])");

    $this->url  = $url;
    $this
      ->parseParams( )
      ->initFilter( )
    ;
  }
  
  /**
   * Получение ссылки на экземпляр объекта исходного запроса
   *
   * @static
   * @access public
   * @param $url
   * @return Request ссылка на объекта запроса
   */
  public static function instance($url = true) {
    assert("in_array(gettype(\$url), ['string', 'boolean'])");

    if (!self::$Instance) {
      // Режим кли или нет
      if (isset($_SERVER['argc'])) {
        self::$is_cli = true;
      }

      if (self::$is_cli) {
        self::$method   = 'GET';
        self::$protocol = 'CLI';
        self::$ip       = '127.0.0.1';
        self::$host     = 'localhost';
        self::$real_ip  = self::$ip;
      } else {
        if (isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) {
          self::$method = 'HTTPS';
        }
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
          self::$is_ajax = true;
        }
        if (isset($_SERVER['HTTP_REFERER'])) {
          self::$referer = $_SERVER['HTTP_REFERER'];
        }
        
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
          self::$xff = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }

        // Эти переменные всегда определены в HTTP-запросе
        self::$method = $_SERVER['REQUEST_METHOD'];
        self::$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'undefined';
        self::$ip = $_SERVER['REMOTE_ADDR'];


        // Real IP
        self::$real_ip = self::$ip;
        if (self::$xff) {
          self::$real_ip = str_replace(self::$ip, '', self::$xff);
          self::$real_ip = trim(self::$real_ip, ' ,');
        }


        if ($url === true) {
          $url = self::detectUrl( );
        }
        self::$host = $_SERVER['HTTP_HOST'];
      }
      self::$Instance = new self($url);
      self::$Instance->setRoute(self::$Instance->param('route'));
    }
    return self::$Instance;
  }
  
  /**
   * Определение текущего адреса запроса
   *
   * @static
   * @access public
   * @return string
   */
  public static function detectUrl( ) {
    $url = '/';
    if (isset($_SERVER['REQUEST_URI'])) {
      $url = rtrim($_SERVER['REQUEST_URI'], ';&?');
    }
    return $url;
  }
  
  /**
   * Парсит и сохраняет все параметры в переменной self::$params
   *
   * @access protected
   * @return $this
   */
  protected function parseParams( ) {
    if (self::$is_cli) {
      $file = array_shift($_SERVER['argv']);
      $this->params['route'] = array_shift($_SERVER['argv']);
      $this->params += $_SERVER['argv']; unset($_SERVER['argv']);
    } else {
      $this->params = $_POST + $_GET; unset($_POST, $_GET);
    }
    return $this;
  }
  
  /**
   * Вспомогательная функция для разбора роута на составляющие
   *
   * @static
   * @access public
   * @param string $route строка вида module/action
   * @return array [ $module, $action ];
   *
   * <code>
   * list($module, $action) = Request::parseRoute('main/index');
   * </code>
   */
  public static function parseRoute($route = '') {
    $route = trim($route, '/');
    // CLI?
    if (self::$is_cli) {
      $route = 'cli/' . $route;
    }
    if (!$route) {
      $route = config('defaults.route');
    }
    
    if (false === strpos($route, '/')) {
      $route .= '/' . (config('common.use_actions')  ? config('defaults.action') : '');
    }

    return explode('/', $route);
  }
  
  /**
   * Инициализация правил для фильтра
   * Позволяет использовать Lazy-filter технику
   * Фильтр применяется только тогда, когда это нужно,
   * а не при инициализации переданных переменных
   *
   * @access protected
   * @return $this
   */
  protected function initFilter( ) {
    $exclude = array_map('chr', range(0, 31));
    unset($exclude[9], $exclude[10]); // Разрешаем табы и переводы строк
    $this->filter = implode('', $exclude);
    return $this;
  }
  
  /**
   * Фильтрация значения
   *
   * @access public
   * @param mixed $param Может быть строкой или массивом
   * @return $this
   */
  public function filter(&$param) {
    if (is_string($param)) {
      $param = trim(strtr(rawurldecode($param), $this->filter, ''));
    } elseif(is_array($param)) {
      array_map([$this, 'filter'], $param);
    }
    return $this;
  }
  
  /**
   * Получение переменной запроса
   *
   * @access public
   * @param mixed $key имя переменной или индекс (string | int)
   * @param mixed $default значение по умолчанию
   * @return mixed значение переменной, если она существует, или значение по умолчанию
   */
  public function param($key = null, $default = null) {
    static $filtered = [];
    if (!isset($key))
      return $this->params;

    if (isset($this->params[$key])) {
      $ret = $this->params[$key];
    } else $ret = $default;

    // Фильтровали ранее?
    if (!isset($filtered[$key])) {
      $filter[$key] = true;
      $this->filter($ret);
    }

    return $ret;
  }
  
  /**
   * Извлечение параметром из запроса по мнемоническим правилам
   *
   * @access public
   * @param array
   *   набор парамтеров по правилам 'param@int'
   *   'param:type=default' = - присвоить значение по умолчанию
   * @return array
   *
   * @uses typify()
   */
  public function getParams(array $args) {
    $params = [];
    foreach ($args as $arg) {
      preg_match('#^([a-z0-9_]+)(?:\:([a-z]+))?(?:\=(.+))?$#', $arg, $m);
      $params[$m[1]]  = $this->param($m[1], isset($m[3]) ? $m[3] : '');

      // Нужно ли типизировать
      if (isset($m[2])) {
        typify($params[$m[1]], $m[2]);
      }
    }
    return $params;
  }
  
  /**
   * Получение данных куки
   *
   * @access public
   * @param string $name
   * @return string
   */
  public function getCookie($name) {
    return isset($_COOKIE[$name])
      ? $_COOKIE[$name]
      : null
    ;
  }

  /**
   * Получение пути по роуту
   *
   * @static
   * @access public
   * @param string $route
   * @param array &$params
   * @return string
   */
  public static function detectPath($route, array &$params = []) {
    assert("is_string(\$route)");

    // mini cheat :)
    static $def_m, $def_a;
    if (!$def_m)
      list($def_m, $def_a) = self::parseRoute(config('defaults.route'));

    list($module, $action)  = self::parseRoute($route);
    
    $path = '/' . ($module === $def_m
      ? ''
      : ($action === $def_a
        ? $module
        : $module . '/' . $action
      ));
    $Request = self::instance();

    if (isset($Request->route_map[$route]) && isset($params[$Request->route_map[$route][1]])) {
      $map = $Request->route_map[$route];
      $pattern = array_shift($map);

      $i = 0;
      $repl = [];
      foreach ($map as $name) {
        $key = '<' . ((string) $i++) . '>';
        $repl[$key] = isset($params[$name]) ? $params[$name] : null;
        unset($params[$name]);
      }

      $path = trim(strtr($pattern, $repl)); //trim(str_replace(array_keys($repl), array_values($repl), $pattern), '/');
    }
    return '/' . trim($path, '/') . '/';
  }

  /**
  * Получение текущего запрашиваемого адреса
  *
  * @param access public
  * @return string
  */
  public function getUrl( ) {
    return $this->url;
  }
  
  /**
   * Получение пути в урле
   *
   * @access public
   * @return string без слешев в начале и конце
   */
  public function getUrlPath( ) {
    return trim(parse_url($this->getUrl( ), PHP_URL_PATH), '/');
  }

  /**
   * Получение одной из частей урла, части делятся слешем - /
   * @return string
   */
  public function getUrlPathPart($part = 0) {
    return explode('/', $this->getUrlPath())[$part];
  }
  
  /**
   * @access public
   * @return string
   */
  public function getAction( ) {
    return $this->action;
  }
  
  /**
   * @access public
   * @return string
   */
  public function getModule( ) {
    return $this->module;
  }
  
  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string $route
   * @return $this
   */
  public function setRoute($route) {
    list($this->module, $this->action) = Request::parseRoute($route);
    return $this;
  }
  
  /**
   * Получение текущего роута
   *
   * @access public
   * @return string
   */
  public function getRoute( ) {
    return $this->module . ($this->action ? '/' . $this->action : '');
  }
  
  /**
   * Юзер агент посетителя, запрашивающего данный запрос
   *
   * @access public
   * @return string
   */
  public function getUserAgent( ) {
    return self::$user_agent;
  }
  
  /**
   * Запрашиваемый хост в текущем запросе
   *
   * @access public
   * @return string
   */
  public function getHost( ) {
    return self::$host;
  }
  
  /**
   * Получение IP-адреса клиента
   *
   * @access public
   * @return string
   */
  public function getIp( ) {
    return self::$ip;
  }
  
  /**
   * Получение реального адреса клиента (если скрывается под прокси)
   *
   * @access public
   * @return string
   */
  public function getRealIp( ) {
    return self::$real_ip;
  }
  
  /**
   * Получение реферера при посещении текущего роута
   *
   * @access public
   * @return string
   */
  public function getReferer( ) {
    return self::$referer;
  }
  
  /**
   * Метод запроса: POST | GET
   *
   * @access public
   * @return string
   */
  public function getMethod( ) {
    return self::$method;
  }
  
  /**
   * Протокоол взаимодействия с текущим запросом: CLI | HTTP
   *
   * @access public
   * @return string
   */
  public function getProtocol( ) {
    return self::$protocol;
  }
  
  /**
   * CLI запрос из строки шелла или нет
   *
   * @access public
   * @return bool
   */
  public function isCli( ) {
    return self::$is_cli;
  }
  
  /**
   * Посылается запрос с использованием AJAX или нет
   *
   * @access public
   * @return bool
   */
  public function isAjax( ) {
    return self::$is_ajax;
  }
  
  /**
   * Статическая функция для доступа к текущему ответу
   * Если ответ не имеется, он создается
   *
   * @static
   * @access public
   * @return Response
   */
  public static function response( ) {
    $Req = Request::instance( );
    if (!$Req->Response instanceof Response) {
      $Req->Response = Response::create( );
    }
    return $Req->Response;
  }
  
  /**
   * Установка другого ответа для текущего запроса
   *
   * @access public
   * @param Response $Response ссылка на объект ответа
   * @return Response
   */
  public function setResponse(Response $Response = null) {
    //$this->Response = $Response;
    return $this->Response = $Response;
  }
}