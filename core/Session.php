<?php
/**
 * Class Session
 * Работа с механизмом сессий
 */
class Session implements ArrayAccess {
  /** @var Session $Instance */
  private static $Instance = null;

  /** @var array $container */
  private $container = [];

  /**
   * Инициализация и настройка выполнения сессий
   * @param string $session_name
   */
  public function __construct($session_name = 'session') {
    assert("is_string(\$session_name)");
    session_start($session_name);

    $this->container = &$_SESSION;
  }

  /**
   * Получение текущего инстанса сессииы
   *
   * @return Session
   */
  public static function current() {
    if (!isset(self::$Instance))
      self::$Instance = new Session(config('session.name'));

    return self::$Instance;
  }

  /**
   * Добавление данных в сессию, если ранее добавлено ничего не было
   *
   * @param string $key
   * @param mixed $value
   * @return Session
   */
  public function add($key, $value) {
    assert("!array_key_exists(\$key, \$this->container)");
    return $this->set($key, $value);
  }

  /**
   * Установка переменной в сессию
   *
   * @param string $key
   * @param mixed $value
   * @return Session
   */
  public function set($key, $value) {
    assert("is_string(\$key)");
    $this->container[$key] = $value;
    return $this;
  }

  /**
   * Удаление ключа из сесссии
   *
   * @param string $key
   * @return Session
   */
  public function delete($key) {
    assert("is_string(\$key)");
    if (isset($this->container[$key]))
      unset($this->container[$key]);
    return $this;
  }

  /**
   * Получение переменной из сессии
   *
   * @param string $key
   * @return mixed
   */
  public function get($key) {
    return isset($this->container[$key]) ? $this->container[$key] : null;
  }

  /**
   * @param mixed $offset
   * @param mixed $value
   */
  public function offsetSet($offset, $value) {
    $this->set($offset, $value);
  }

  /**
   * @param mixed $offset
   * @return bool
   */
  public function offsetExists($offset) {
    return isset($this->container[$offset]);
  }

  /**
   * @param mixed $offset
   */
  public function offsetUnset($offset) {
    unset($this->container[$offset]);
  }

  /**
   * @param mixed $offset
   * @return mixed|null
   */
  public function offsetGet($offset) {
    return $this->get($offset);
  }
}