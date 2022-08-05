<?php
/**
 * Класс для работы с запросом и переменными запроса
 *
 * @final
 * @package Core
 * @subpackage Request
 */
final class Request {
  /**
   * @property string $route имя действия, которое должно выполнится в выполняемом запросе
   * @property string $url адрес обрабатываемого запроса
   *
   * @property string $method вызываемый метод на данном запросе (GET | POST)
   * @property string $referer реферер, если имеется
   * @property string $ip IP-адрес клиента
   * @property string $xff ip адрес при использовании прокси, заголовок: X-Forwarded-For
   * @property string $user_agent строка, содержащая USER AGENT браузера клиента
   * @property string $host Хост, который выполняет запрос
   * @property bool $is_ajax запрос посылается через ajax
   */

  protected string
    $action  = '',
    $route   = ''
  ;

  public static int $time = 0;
  public static float $time_float = 0;

  public static string
  $protocol    = '',
  $method      = 'GET',
  $referer     = '',
  $ip          = '0.0.0.0',
  $real_ip     = '0.0.0.0',
  $xff         = '',
  $host        = '',
  $request_uri = '',
  $content_type = '',
  $accept_lang = '',
  $user_agent  = '';

  public static array
  $languages   = [];

  public static bool
  $is_ajax     = false;

  /**
   * @param string|bool $url адрес текущего запроса
   */
  final protected function __construct(protected string $url) {}

  /**
   * Получение ссылки на экземпляр объекта исходного запроса
   *
   * @static
   * @return Request ссылка на объекта запроса
   */
  final protected static function create(): self {
    if (self::$accept_lang) {
      preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', self::$accept_lang, $lang);
      if ($lang && sizeof($lang[1]) > 0) {
        $langs = array_combine($lang[1], $lang[4]);

        foreach ($langs as $k => $v) {
          if ($v === '') {
            $langs[$k] = 1;
          }
        }
        arsort($langs, SORT_NUMERIC);
        static::$languages = $langs;
      }
    }

    $url = rtrim(static::$request_uri, ';&?') ?: '/';
    $Request = (new static($url))
      ->setRoute(Input::get('ROUTE'))
      ->setAction(Input::get('ACTION'))
    ;

    // Init language
    Lang::init($Request);

    return $Request;
  }

  /**
   * Return current instance or initialize and parse
   */
  public static function current(?Closure $init_fn = null): self {
    static $instance;
    if (!isset($instance) || isset($init_fn)) {
      $init_fn ??= static::init(...);
      $init_fn();
      static::parseRealIp();
      $instance = static::create();
    }

    return $instance;
  }

  protected static function init(): void {
    self::$time = $_SERVER['REQUEST_TIME'];
    self::$time_float = $_SERVER['REQUEST_TIME_FLOAT'];
    self::$protocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL') ?? 'HTTP/1.1';
    self::$is_ajax = !!filter_input(INPUT_SERVER, 'HTTP_X_REQUESTED_WITH');
    self::$referer = filter_input(INPUT_SERVER, 'HTTP_REFERER') ?? '';
    self::$xff = filter_input(INPUT_SERVER, 'HTTP_X_FORWARDED_FOR') ?? '';

    // Эти переменные всегда определены в HTTP-запросе
    self::$method = filter_input(INPUT_SERVER, 'REQUEST_METHOD');
    self::$user_agent = filter_input(INPUT_SERVER, 'HTTP_USER_AGENT') ?: 'undefined';
    self::$ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');

    self::$request_uri = filter_input(INPUT_SERVER, 'REQUEST_URI') ?? '';
    self::$content_type = filter_input(INPUT_SERVER, 'CONTENT_TYPE') ?? '';

    self::$accept_lang = filter_input(INPUT_SERVER, 'HTTP_ACCEPT_LANGUAGE') ?? '';
  }

  /**
   * Parse IPS to prepare request
   * @return void
   */
  protected static function parseRealIp(): void {
    self::$real_ip = self::$ip;
    if (self::$xff && self::$xff !== self::$ip) {
      self::$real_ip = trim(strtok(self::$xff, ','));
    }
  }

  /**
   * Get current handled url for this request
   * @return string
   */
  public function getUrl(): string {
    return $this->url;
  }

  /**
   * Get part of url as path. /some/path for url /some/path?fuck=yea
   * @param string
   */
  public function getUrlPath(): string {
    return parse_url($this->url, PHP_URL_PATH);
  }

  /**
   * Get url query
   * @return string
   */
  public function getUrlQuery(): string {
    return parse_url($this->url, PHP_URL_QUERY) ?? '';
  }


  /**
   * Get requested header
   * @param string $header
   * @return string
   */
  public function getHeader(string $header): string {
    return filter_input(INPUT_SERVER, 'HTTP_' . strtoupper(str_replace('-', '_', $header))) ?? '';
  }

  /**
   * Установка текущего роута с последующим парсингом его в действие и модуль
   *
   * @access public
   * @param string|null $route
   * @return $this
   */
  public function setRoute(?string $route): self {
    $this->route = $route ?? '/home';
    return $this;
  }

  /**
   * Current route
   * @access public
   * @return string
   */
  public function getRoute(): string {
    return $this->route ?? '';
  }

  /**
   * Set action that's processing now
   * @access public
   * @param string|null$route
   * @return $this
   */
  public function setAction(?string $action): self {
    $this->action = $action
      ? trim(preg_replace('|[^a-z0-9\_\-\/]+|is', '', $action), '/')
      : 'home'
    ;
    return $this;
  }

  /**
   * Get current action
   * @access public
   * @return string
   */
  public function getAction(): string {
    return $this->action ?? config('default.action');
  }
}
