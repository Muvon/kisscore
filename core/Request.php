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
   * @property bool $is_ajax запрос посылается через ajax
   */

  private
  $Response     = null,
  $params       = [],
  $action  = '',
  $route   = '',
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
  $is_ajax     = false;

  /**
   * @param string|bool $url адрес текущего запроса
   */
  public function __construct($url) {
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
  public static function instance($url = true) {
    assert("in_array(gettype(\$url), ['string', 'boolean'])");

    if (!self::$Instance) {
      if (filter_input(INPUT_SERVER, 'argc')) {
        self::$method   = 'GET';
        self::$protocol = 'CLI';
        self::$ip       = '127.0.0.1';
        self::$host     = 'localhost';
        self::$real_ip  = self::$ip;
      } else {
        if (filter_input(INPUT_SERVER, 'HTTPS')) {
          self::$method = 'HTTPS';
        }
        if (filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH')) {
          self::$is_ajax = true;
        }
        self::$referer = filter_input(INPUT_SERVER, 'HTTP_REFERER');
        self::$xff = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR');

        // Эти переменные всегда определены в HTTP-запросе
        self::$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
        self::$user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') ?: 'undefined';
        self::$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');


        // Real IP
        self::$real_ip = self::$ip;
        if (self::$xff) {
          self::$real_ip = str_replace(self::$ip, '', self::$xff);
          self::$real_ip = trim(self::$real_ip, ' ,');
        }


        if ($url === true) {
          $url = self::detectUrl();
        }
        self::$host = filter_input(INPUT_SERVER, 'HTTP_HOST');
      }
      self::$Instance = new self($url);
      self::$Instance->setRoute(Input::get('ROUTE'));
      self::$Instance->setAction(Input::get('ACTION'));
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
  public static function detectUrl() {
    if ($url = filter_input(INPUT_SERVER, 'REQUEST_URI')) {
      $url = rtrim($url, ';&?');
    }
    return $url ?: '/';
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
   * Посылается запрос с использованием AJAX или нет
   *
   * @access public
   * @return bool
   */
  public function isAjax( ) {
    return self::$is_ajax;
  }
}