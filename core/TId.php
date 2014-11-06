<?php
/**
 * Трейт для генерации и работы с разными видами идентификаторов
 */
trait TId {

  /**
   * Генерация ид с жизнью до 35 лет для signed и 70 unsigned
   *
   * @param int $id
   *   Идентификатор, по которому идет парцирование данных, если не указан,
   *   то выбирается случайное число
   * @return int
   */
  protected function generateId($id = 0) {
    $this->setState(self::STATE_FAIL);

    // Пытаемся получить метку и сгенерировать ид
    if ($epoch = config('common.epoch')) {
      $this->setState(self::STATE_OK);
      return (floor(microtime(true) * 1000 - $epoch) << 23) + (mt_rand(0, 4095) << 13) + (($id ? $id : lcg_value() * 10000000) % 1024);
    }
    return 0;
  }

  /**
   * Получение альфа ид по идентификатору
   *
   * @param int $id
   * @return $this
   */
  public function getByAlphaId($id) {
    return $this->get($this->decodeId($id));
  }

  /**
   * Gjkextybt Alpha id текущего айтема
   *
   * @return string
   */
  public function getAlphaId() {
    return $this->encodeId($this->getId());
  }

  /**
   * Кодирование ида
   *
   * @param int $id
   * @return string
   */
  protected function encodeId($id) {
    return alpha_encode($id, config('common.alphabet'));
  }

  /**
   * Декодирование ида
   *
   * @param string $id
   * @return int
   */
  protected function decodeId($id) {
    return alpha_decode($id, config('common.alphabet'));
  }
}