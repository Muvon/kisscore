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
   * @property array $cookies Массив с куками, необходимый для отправки в ответе
   * @property array $headers Список заголовков, которые отправляются клиенту
   * @property string $body ответ клиенту, содержаший необходимый контент на выдачу
   * @property int $status код HTTP-статуса
   *
   * @property array $messages возможные статусы и сообщения HTTP-ответов
   */
  protected
  $cookies  = [],
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
   * Инициализация объекта ответа по HTTP коду-возврата в случае HTTP запроса
   *
   * @final
   * @access protected
   * @param int $status статус ответа при необходимости
   */
  final protected function __construct($status = 200) {
    assert("is_int(\$status)", 'Status must be integer');
    $this->setStatus($status);
  }
  /**
   * Создание нового ответа
   *
   * @static
   * @access public
   * @param int $status статус ответа при необходимости
   * @return Response
   */
  public static function create($status = 200) {
    return new self($status);
  }

  /**
   * @access public
   * @param int $status новый статус ответа
   * @return Response
   */
  public function setStatus($status) {
    assert('in_array($status, array_keys(self::$messages))');
    if (isset(self::$messages[$status])) {
      $this->status = $status;
    }
    return $this;
  }

  /**
  * Получение данных для вывода клиенту
  *
  * @access public
  * @return string данные ответа клиенту
  */
  public function __toString( ) {
    return (string) $this->body;
  }

  /**
   * Добавление кук для отправки
   *
   * @access public
   * @param string $name
   * @param string $value
   * @param int $time
   * @param string $path
   * @return $this
   */
  public function addCookie($name, $value, $time, $path = '/') {
    assert('is_string($name)');

    $this->cookies[] = [
      'name'  => $name,
      'value' => $value,
      'time'  => $time,
      'path'  => $path,
    ];
    return $this;
  }

  /**
   * Отправка кук, если установлены
   *
   * @access public
   * @return $this
   */
  public function sendCookies( ) {
    foreach ($this->cookies as $cookie) {
      setcookie($cookie['name'], $cookie['value'], $cookie['time'], $cookie['path']);
    }
    return $this;
  }

  /**
   * Отправка тела ответа
   *
   * @access public
   * @return $this
   */
  public function sendBody() {
    echo (string) $this;
    return $this;
  }

  /**
   * Метод отпрваки заголовков и тела ответа
   *
   * @access public
   * @return $this
   */
  public function send() {
    $this
      ->sendCookies()
      ->sendHeaders()
      ->sendBody()
    ;
    return $this;
  }

  /**
  * Выполнение редиректа на определенный URL
  *
  * @uses self::flushHeaders()
  * @uses self::addHeader()
  * @uses self::sendHeaders()
  *
  * @access public
  * @param string $url полный HTTP-адрес страницы для редиректа
  * @param int $code код редиректа (301 | 302)
  * @return void
  */
  public function redirect($url, $code = 302) {
    assert('is_string($url)');
    assert('in_array($code, [301, 302])');

    $this->status = $code;
    $this
      ->flushHeaders()
      ->addHeader('Content-type', '')
      ->addHeader('Location', $url)
      ->sendCookies()
      ->sendHeaders()
    ;
    exit;
  }

  /**
  * Очистка всех уже ранее добавленных заголовков в массив
  *
  * @access public
  * @return Response
  */
  public function flushHeaders( ) {
    $this->headers = [];
    return $this;
  }

  /**
  * Добавление заголовка в пул заголовков для вывода
  *
  * @access public
  * @param string $header
  *   имя заголовка, например, Location
  * @param string $value
  *   значение заголовка, для Location это просто адрес редиректа
  * @return Response
  */
  public function addHeader($header, $value) {
    assert('is_string($header)');
    assert('is_string($value)');

    $this->headers[$header] = $value;
    return $this;
  }

  /**
   * Отправка подготовленных заголовков клиенут
   *
   * @access public
   * @return Response
   */
  public function sendHeaders() {
    if (headers_sent( )) {
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
  * Установка содержимого для ответа
  *
  * @access public
  * @param string $body
  *   данные для передачи в виде ответа клиенту
  * @return $this
  */
  public function setBody($body) {
    assert('is_string($body)');

    $this->body = $body;
    return $this;
  }
}