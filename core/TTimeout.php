<?php
/**
 * Трейт реализует работу таймаутов на те или иные операции
 * Зависит от трейта TCache и использует его
 */
trait TTimeout {
  /**
   * @property array $timeouts
   *   Таймауты при различной записи
   */
  protected $timeouts = ['add' => 0, 'update' => 0, 'delete' => 0];

  /**
   * @property array $timeout_identify_params
   *   Параметры для идентификации уникальности посетителя при определении таймаутов
   *   По умолчанию определяются на основе HTTP_USER_AGENT и IP посетителя
   */
  private $timeout_identify_params = [];

  /**
   * Установка функции генерации ключа идентификации создателя таймаута
   *
   * @param array $params
   * @return $this
   */
  public function setTimeoutIdentifyParams(array $params) {
    $this->timeout_identify_params = $params;
    return $this;
  }

  /**
   * Получение идентификационного ключа для хранения значения таймаута
   *
   * @param string $type
   * @return string
   */
  protected function getTimeoutKey($type) {
    if (!$this->timeout_identify_params)
      $this->timeout_identify_params = [Request::instance()->getUserAgent(), Request::instance()->getIp()];

    return $type
    . '-protection-'
    . get_called_class()
    . md5(implode(':', $this->timeout_identify_params));
  }

  /**
   * Разрешена ли запись/обновление
   *
   * @param string $type
   *   add, update
   * @return bool
   */
  protected function getTimeout($type) {
    return (bool) $this->Cache->get($this->getTimeoutKey($type));
  }

  /**
   * Установка таймаута
   *
   * @param string $type
   *   add, update
   * @return bool
   */
  protected function setTimeout($type) {
    $t = isset($this->timeouts[$type])
      ? $this->timeouts[$type]
      : 0;

    // Нет таймаута или ноль, всегда будет ок
    if (!$t)
      return false;

    // Устанавливаем таймаут
    $this->Cache->set($this->getTimeoutKey($type), 1, $t);
    return true;
  }
}