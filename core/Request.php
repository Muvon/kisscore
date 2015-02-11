<?php
/**
 * Класс для работы с запросом и переменными запроса
 *
 * @final
 * @package Core
 * @subpackage Request
 */
class Request {
  /**
   * @property Response $Response класс ответа для данного запроса
   * @property array $params все параметры, переданные в текущем запросе
   *
   * @property string $route имя действия, которое должно выполнится в выполняемом запросе
   * @property string $url адрес обрабатываемого запроса
   *
   * @property Request $instance хэндлер объекта текущего запроса
   * @property string $method вызываемый метод на данном запросе (GET | POST)
   * @property string $protocol протокол соединения, например HTTP, CLI и т.п.
   * @property string $referer реферер, если имеется
   * @property string $ip IP-адрес клиента
   * @property string $xff ip адрес при использовании прокси, заголовок: X-Forwarded-For
   * @property string $user_agent строка, содержащая USER AGENT браузера клиента
   * @property string $host Хост, который выполняет запрос
   * @property bool $is_ajax запрос посылается через ajax
   */

  private
  $Response     = null,
  $params       = [],
  $action  = '',
  $route   = '',
  $url     = '';

  private static
  $Instance    = null;

  public static
  $time        = 0,
  $method      = 'GET',
  $protocol    = 'HTTP',
  $referer     = '',
  $ip          = '0.0.0.0',
  $real_ip     = '0.0.0.0',
  $xff         = '',
  $host        = '',
  $user_agent  = '',
  $is_ajax     = false;

  /**
   * @param string|bool $url адрес текущего запроса
   */
  final protected function __construct($url) {
    assert("in_array(gettype(\$url), ['string', 'boolean'])");

    $this->url  = $url;
  }

  /**
   * Получение ссылки на экземпляр объекта исходного запроса
   *
   * @static
   * @access public
   * @param $url
   * @return Request ссылка на объекта запроса
   */
  public static function create($url = true) {
    assert("in_array(gettype(\$url), ['string', 'boolean'])");

    self::$time = time();
    if (filter_input(INPUT_SERVER, 'argc')) {
      self::$protocol = 'CLI';
    } else {
      self::$protocol = filter_input(INPUT_SERVER, 'HTTPS') ?: 'HTTP';
      self::$is_ajax = !!filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH');
      self::$referer = filter_input(INPUT_SERVER, 'HTTP_REFERER');
      self::$xff = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR');

      // Эти переменные всегда определены в HTTP-запросе
      self::$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
      self::$user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') ?: 'undefined';
      self::$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');

      static::parseRealIp();

      if ($url === true && $url = filter_input(INPUT_SERVER, 'REQUEST_URI')) {
        $url = rtrim($url, ';&?') ?: '/';
      }
    }

    return (new static($url))
      ->setRoute(Input::get('ROUTE'))
      ->setAction(Input::get('ACTION'))
    ;
  }

  protected static function parseRealIp() {
    self::$real_ip = self::$ip;
    if (self::$xff) {
      self::$real_ip = str_replace(self::$ip, '', self::$xff);
      self::$real_ip = trim(self::$real_ip, ' ,');
    }
  }

  public function getUrl() {
    return $this->url;
  }


  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string $route
   * @return $this
   */
  public function setRoute($route) {
    $this->route = $route;
    return $this;
  }

  /**
   * Получение текущего роута
   *
   * @access public
   * @return string
   */
  public function getRoute() {
    return $this->route ? $this->route : '';
  }

  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string $route
   * @return $this
   */
  public function setAction($action) {
    $this->action = preg_replace('|[^a-z0-9\_\-]+|is', '', $action);
    return $this;
  }

  /**
   * Получение текущего роута
   *
   * @access public
   * @return string
   */
  public function getAction() {
    return $this->action ? $this->action : config('default.action');
  }
}