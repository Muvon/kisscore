<?php
/**
 * Трейт реализует управление сообщениями внутри объекта
 */
trait TMessage {
  /**
   * @property array $messages
   *   Список сообщений с ошибками и уведомлениями
   */
  protected $messages = [];

  /**
   * Сохранение сообщения об ошибке или успехе
   *
   * @param string $text строка текста, которая должна быть сохраннеа
   * @param string $mode сообщения является ошибкой или простым уведомлением
   * @return $this
   */
  protected function addMessage($text, $mode = 'notices') {
    $this->messages[$mode][] = $text;
    return $this;
  }

  /**
   * @see self::addMessage( )
   */
  protected function addError($text) {
    $this->setState(self::STATE_FAIL); // Holly shit its hack for Model
    return $this->addMessage('e_' . strtolower(get_called_class()) . '_' . $text, 'errors');
  }

  /**
   * @see self::addMessage( )
   */
  protected function addNotice($text) {
    return $this->addMessage('n_' . strtolower(get_called_class()) . '_' . $text, 'notices');
  }

  /**
   * @see self::addMessage( )
   */
  protected function addInternal($text) {
    return $this->addMessage('i_' . strtolower(get_called_class()) . '_' . $text, 'internal');
  }

  /**
   * Получение Всех ранее добавленных сообщений
   *
   * @access public
   * @return array
   */
  public function getMessages( ) {
    return $this->messages;
  }

  /**
   * Очистка всех ранее добавленных сообщений
   *
   * @access protected
   * @return $this
   */
  protected function flushMessages( ) {
    $this->messages = [];
    return $this;
  }

  /**
   * Получение возникших ошибок
   *
   * @return array
   */
  public function getErrors() {
    return $this->hasErrors() ? $this->messages['errors'] : [];
  }

  /**
   * Получение возникших сообщений об успехе
   *
   * @return array
   */
  public function getNotices() {
    return $this->hasNotices() ? $this->messages['notices'] : [];
  }

  /**
   * Возникали ли какие-то ошибки?
   *
   * @return bool
   */
  public function hasErrors( ) {
    return isset($this->messages['errors']);
  }

  /**
   * Имеются ли сообщения об успехе?
   *
   * @return bool
   */
  public function hasNotices( ) {
    return isset($this->messages['notices']);
  }
}