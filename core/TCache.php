<?php
/**
 * Трейт реализует основные механизмы кэшируемого объекта и удобные методы доступа к ним
 *
 * Активация кэширования выполняется через объявление свойства is_cacheable в классе,
 * который использует трейт. По умолчанию — отключен.
 */
trait TCache {
  /*
   * @property Cache $Cache
   *   Ссылка на объект кэширующего механизма
   */
  protected $Cache = null;

  /**
   * @property array $cache_keys
   *   Массив с шаблонками для ключей кэшера
   */
  protected $cache_keys = [];

  /**
   * Инициализация трейта
   *
   * @return $this
   */
  public function initCache() {
    // создаем кэширующий объект
    $conf = config('memcache');
    $this->Cache = Cache::getConnection($conf['host'], $conf['port'], (bool) $conf['persistent']);

    $cc = get_class($this);

    // Кэш ключ для значения по ид self::getByIds()
    $this->cache_keys['item']   = $cc . ':%s';

    // Кэш ключ для self::getCustomCache()
    $this->cache_keys['custom'] = $cc . ':%s';

    return $this;
  }

  /**
   * Проверка, разрешено ли кэширование вообще, а также установка флага на текущий объект
   *
   * @param bool|null $flag
   * @return bool
   */
  public function isCacheable($flag = null) {
    static $is_cacheable = null;

    // Инициализация из фалаг класса?
    if (!isset($is_cacheable))
      $is_cacheable = property_exists($this, 'is_cacheable') ? $this->is_cacheable : false;

    // изменение?
    if (isset($flag))
      $is_cacheable = (bool) $flag;

    return $is_cacheable;
  }

  /**
   * Функция для получения пользовательского кэша
   * Если в кэше ничего нет или кэширование отключено
   * Возвращается результат функции
   *
   * @param string $id
   *   Идентификационная строка
   * @param Callable $fetcher
   *   Функция, которая служит получением данных, если в кэше нет
   * @return mixed
   *
   * <code>
   * $data = $this->getCacheable('caching_example', function () {
   *   return $this->getSomethingFromDb();
   * });
   * </code>
   */
  protected function getCacheable($id, Callable $fetcher) {
    // Кэш отключен?
    if (!$this->isCacheable())
      return $fetcher();

    // Кэш включен, проверяем
    $key = $this->getCacheKey('custom', $id);
    if (false === $data = $this->Cache->get($key))
      $this->cacheSet($key, $data = $fetcher());

    return $data;
  }


  protected function cacheSet($key, $value) {
    $this->Cache->set($key, $value);
    return $this;
  }

  protected function cacheDelete($key) {
    $this->Cache->delete($key);
    return $this;
  }

  protected function cacheGet($key) {
    return $this->Cache->get($key);
  }

  /**
   * Получение данных из кэша по ид
   * Функция обращается к кэшу и подготавливает вывод
   * в виде массива с ключами номеров (ids)
   *
   * @param array $ids
   * @return array
   */
  protected function cacheGetByIds(array $ids) {
    $result = [];
    foreach ($this->Cache->get($this->getCacheKeys('item', $ids)) as $item) {
      $result[$item['id']] = $item;
    }
    return $result;
  }


  /**
   * Получение ключ для кэша из шаблонов
   *
   * @access protected
   * @param string $key
   * @param mixed Данные для передачи в шаблон
   * @return string
   */
  protected function getCacheKey($key) {
    $args = func_get_args( );
    array_shift($args);

    $params = [];
    if (is_array($args[0]))
      $params = &$args[0];
    else $params = &$args;

    $pattern = Request::instance()->getHost() . ':' . $this->cache_keys[$key];

    return $params
      ? vsprintf($pattern, $params)
      : $pattern
      ;
  }

  /**
   * Получение нескольких ключей для кэша из шаблонов
   *
   * @uses self::getCacheKey()
   *
   * @access protected
   * @param string $key
   * @param array $args
   * @return array
   */
  protected function getCacheKeys($key, array $args = []) {
    $ret = [];
    foreach ($args as $k => $arg) {
      $ret[$k] = $this->getCacheKey($key, $arg);
    }
    return $ret;
  }
}