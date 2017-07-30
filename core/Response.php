<?php
/**
 * Класс для формирования ответа клиенту
 *
 * @final
 *  @package Core
 * @subpackage Config
 */

class Response {
  /**
   * @property array $headers Список заголовков, которые отправляются клиенту
   * @property string $body ответ клиенту, содержаший необходимый контент на выдачу
   * @property int $status код HTTP-статуса
   *
   * @property array $messages возможные статусы и сообщения HTTP-ответов
   */
  protected
  $headers  = [],
  $body     = '',
  $status   = 200;

  protected static
  $messages = [
    200 => 'OK',
    201 => 'Created',
    202 => 'Accepted',
    203 => 'Non-Authoritative Information',
    204 => 'No Content',
    205 => 'Reset Content',
    206 => 'Partial Content',

    301 => 'Moved Permanently',
    302 => 'Found',
    303 => 'See Other',
    304 => 'Not Modified',
    305 => 'Use Proxy',
    307 => 'Temporary Redirect',

    400 => 'Bad Request',
    401 => 'Unauthorized',
    402 => 'Payment Required',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    406 => 'Not Acceptable',
    407 => 'Proxy Authentication Required',
    408 => 'Request Timeout',
    409 => 'Conflict',
    410 => 'Gone',
    411 => 'Length Required',
    412 => 'Precondition Failed',
    413 => 'Request Entity Too Large',
    414 => 'Request-URI Too Long',
    415 => 'Unsupported Media Type',
    416 => 'Requested Range Not Satisfiable',
    417 => 'Expectation Failed',

    500 => 'Internal Server Error',
    501 => 'Not Implemented',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
    504 => 'Gateway Timeout',
    505 => 'HTTP Version Not Supported',

  ];

  /**
   * Init of new response
   * @param int $status HTTP Status of response
   * @return void
   */
  final protected function __construct($status = 200) {
    assert("is_int(\$status)", 'Status must be integer');
    $this->status($status);
  }
  /**
   * Create new response
   * @param int $status HTTP status of response
   * @return $this
   */
  public static function create($status = 200) {
    return new static($status);
  }

  /**
   * Change HTTP status of response
   * @param int $status New HTTP status to be set
   * @return $this
   */
  public function status($status) {
    assert(in_array($status, array_keys(self::$messages)));
    if (isset(self::$messages[$status])) {
      $this->status = $status;
    }
    return $this;
  }

  /**
  * Get response body
  * @access public
  * @return string данные ответа клиенту
  */
  public function __toString( ) {
    return (string) $this->body;
  }

  /**
   * Send body to output
   * @return $this
   */
  public function sendBody() {
    echo (string) $this;
    return $this;
  }

  /**
   * Send all staff to output: headers, body and so on
   * @return $this
   */
  public function send($content = '') {
    return $this->sendHeaders()->setBody($content)->sendBody();
  }

  /**
  * Relocate user to url
  * @param string $url полный HTTP-адрес страницы для редиректа
  * @param int $code код редиректа (301 | 302)
  * @return void
  */
  public static function redirect($url, $code = 302) {
    assert(is_string($url));
    assert(in_array($code, [301, 302]));

    if ($url[0] === '/')
      $url = (filter_input(INPUT_SERVER, 'HTTPS') ? 'https': 'http') . '://' . getenv('HTTP_HOST') . $url;

    static::create($code)
      ->header('Content-type', '')
      ->header('Location', $url)
      ->sendHeaders()
    ;
    exit;
  }

  /**
  * Reset headers stack
  * @return Response
  */
  public function flushHeaders( ) {
    $this->headers = [];
    return $this;
  }

  /**
  * Push header to stack to be sent
  * @param string $header
  * @param string $value
  * @return Response
  */
  public function header($header, $value) {
    assert(is_string($header));
    assert(is_string($value));

    $this->headers[$header] = $value;
    return $this;
  }

  /**
   * Send stacked headers to output
   * @return Response
   */
  protected function sendHeaders() {
    Cookie::send(); // This is not good but fuck it :D
    if (headers_sent()) {
      return $this;
    }
    $protocol = filter_input(INPUT_SERVER, 'SERVER_PROTOCOL') ?: 'HTTP/1.1';

    // HTTP-строка статуса
    header($protocol . ' ' . $this->status . ' ' . self::$messages[$this->status], true);

    foreach ($this->headers as $header=>$value) {
      header($header . ': ' . $value, true);
    }
    return $this;
  }

  /**
  * Set boy data to response
  * @access public
  * @param string $body
  * @return $this
  */
  public function setBody($body) {
    assert(is_string($body));

    $this->body = $body;
    return $this;
  }
}
