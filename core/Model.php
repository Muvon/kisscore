<?php

/**
 * Абстрактный класс реализации модели
 *
 * @abstract
 * @package Core
 * @subpackage Model
 *
 * <code>
 * $Model  = Model::factory('chat/room');
 * $result = $Model->callToSomeMethod( );
 * </code>
 *
 * <code>
 * $errors = Model::create('UserProfile')
 *   ->setId(1)
 *   ->set(['name' => 'Dmitry'])
 *   ->save( )
 *   ->getErrors( )
 * ;
 * </code>
 *
 * <code>
 * $errors = Model::create('UserProfile')
 *   ->setId(1)
 *   ->save($data)
 * ;
 * </code>
 *
 * <code>
 * $url = '/';
 * Model::create('UserProfile')
 *   ->save($data)
 *   ->done([
 *     'ok'  => function () use ($url) {
 *       redirect($url);
 *     },
 *     'fail'  => function () {
 *       echo 'Shit happens :)';
 *     }
 *   ]);
 * </code>
 */
abstract class Model {
  use TMessage, TDatabase, TCache, TTimeout, TPagination, TId;

  /**
   * @const STATE_OK
   *   Успешное завершение операции
   * @const STATE_ERROR
   *   Ошибочное завершение операции
   * @const STATE_DENIED
   *   Если недостаточно прав для совершения действия
   * @const STATE_DISABLED
   *   Если раздел временно недоступен, отключен, и т.п. (не реализовано)
   */
  const
  STATE_OK        = 1,
  STATE_FAIL      = 2,
  STATE_DENIED    = 3,
  STATE_DISABLED  = 4;
  
  /**
   * @property bool $is_new
   *   Флаг, обозначающий является ли текущее данное в модели новым
   * @property array $ids
   *   Список последних полученных ид
   */
  protected
  $is_new   = true,
  $ids      = [];
  
  /**
   * @access protected
   * @property mixed $id
   *   Текущий идентификатор сущности
   * @property array $data
   *   Данные текущей выборки
   * @property int $state
   *   Состояние текущей модели данных
   */
  protected
  $id     = 0,
  $data   = [],
  $state  = self::STATE_OK
  ;
  
  /**
   * @property array $map Карта всех моделей
   */
  protected static $map = [];

  /**
   * Инициализация и подключение к серверу базы данных
   *
   * @uses Database
   * @uses Config
   */
  final public function __construct( ) {
    $this
      ->initCache()
      ->initDatabase()
      ->init();
  }

  /**
   * @return $this
   */
  protected function init() {
    return $this;
  }

  /**
   * Метод
   *
   * @param array $states
   *    Массив с содержимым ключа состояния и замыкания дял выполенния
   *   Допустимые значения ключей: ok, fail, denied, disabled
   * @return $this
   */
  public function done(array $states) {
    $cur = $this->getState( );
    foreach ($states as $state => $action) {
      if ($cur === constant('self::STATE_' . strtoupper($state))) {
        $action();
      }
    }
    return $this;
  }

  /**
   * Подготовка и постобработка результатов выборки
   *
   * @param array $item
   *   Массив с данными одного элемента
   */
  protected function prepareResult(array &$item) {}
  
  /**
   * Проверка возвращаемых данных на предмет унификации
   * Добавление необходимых переменных лимитов и постраничного вывода
   *
   * @access protected
   * @param array $result
   *   Набор данных, которые нужно трансформировать в список
   * @return array
   */
  protected function listResult(array $result) {
    // Ничего нет? Ну и к черту :D
    if (!$result)
      return [];

    $list['items']    = array_values($result);
    $list['total']    = $this->total;
    // Добавляем дополнительные данные
    $list['offset']   = $this->offset;
    $list['limit']    = $this->limit;
    $list['page']     = $this->page;
    $list['max_page'] = $this->max_page;
    $list['has_items']    = !!$list['items'];
    $list['has_no_items'] = !$list['items'];
    // Постраничная навигация: kick it to view
    $list['pagination'] = Pagination::instance( )
      ->set([
        'route'     => Request::instance( )->getRoute(),
        'params'    => Request::instance( )->param(),
        'page_name' => 'p',
        'per_page'  => $list['limit'],
        'total'     => $list['total'],
      ])
      ->getArray();

    // Пост-обработка
    array_walk($list['items'], [$this, 'prepareResult']);
    return $list;
  }
  
  /**
   * Необходимая обработка и возврат плоского списка
   *
   * @access protected
   * @param array $flat
   * @return array
   */
  protected function flatResult(array $flat) {
    $flat = array_filter($flat);
    array_walk($flat, [$this, 'prepareResult']);
    return $flat;
  }

  /**
   * Создание нового объекта модели
   *
   * @access public
   * @param string|null $name создание объекта модели
   * @return Model ссылка на объект
   */
  final public static function create($name = null) {
    if (is_null($name)) {
      $name = static::class;
    }

    return new $name;
  }

  /**
   * Правила валидации для необходимых полей
   * Валидирующая функция должна возвращать
   *
   * @access protected
   * @return array
   *
   * <code>
   * return array(
   *   'field1'  => function ($v) {
   *     if ($v === null) return 'ERROR';
   *     return true;
   *   },
   *   'field2' => …
   * );
   * </code>
   */
  protected function rules() {
    return [];
  }
  
  /**
   * Функция для формирования Closures, которые выполняются до
   * каких-то манипуляций с данными
   *
   * @return array
   *
   * <code>
   * return ['save' => function () { echo 'I\'m happy taurent!'; }];
   * </code>
   */
  protected function before() {
    return [];
  }
  
  /**
   * Функция для формирования Closures, которые выполняются
   * после успешной манипуляци с данными
   *
   * @return array
   */
  protected function after() {
    return [];
  }
  
  /**
   * Выполнение обработки до/после манипуляций с данными
   * согласно определенными правилам self::before() self::after()
   *
   * @param string $type
   * @param string $key
   * @return $this
   */
  final protected function processing($type, $key) {
    $funcs = $this->{$type}();
    if (isset($funcs[$key])) {
      $funcs[$key]();
    }
    return $this;
  }
  
  /**
   * Функция валидации данных
   *
   * @access protected
   * @param array $data
   * @return $this
   *
   * <code>
   * $msgs = Model::create('Photo')->save($form)->getMessages();
   * </code>
   */
  protected function validate($data) {
    foreach ($this->rules( ) as $field => $rule) {
      if ($this->is_new) { // Если новая запись
        // Еще нет такого поля? Пишем туда нуль и валидируем
        if (!isset($data[$field]))
          $data[$field] = null;
      } else { // Идет обновление
        // Не указано поле? Просто пропускаем правило
        if (!array_key_exists($field, $data))
          continue;
      }
      //Closure::bind($rule, $this, get_class($this));
      $res = $rule($data[$field]);

      // Не изменилось поле? удаляем
      if ($data[$field] === null)
        unset($data[$field]);

      // Если результат не TRUE, то там ошибка
      if (isset($res) && true !== $res) {
        $this->addError($field . '_' . $res);
      }
    }
    return $this;
  }


  /**
   * Нужно добавить запись или обновить?
   *
   * @param bool $is_new
   * @return $this
   */
  public function setNew($is_new = true) {
    $this->is_new = $is_new;
    return $this;
  }

  /**
   * Метод для обновления каких-то счетчиков в базе
   *
   * @param array $counters
   *   Показатели для обновления + или -, например ['counter' => -1]
   * @param array $ids
   * @return $this
   */
  public function increment(array $counters, array $ids = []) {
    $this->dbUpdateByIds($counters, $ids ? $ids : [$this->getId()], true);
    return $this;
  }
  
  /**
   * Получение нескольких записей по ID
   *
   * @param array $ids
   * @return array
   */
  protected function getByIds(array $ids) {
    $ids = array_unique($ids);

    // Избавляемся от нуль-ид
    if (false !== $key = array_search(0, $ids, true))
      unset($ids[$key]);

    $data = $this->isCacheable()
      ? $this->cacheGetByIds($ids)
      : [];

    // Если есть промахи в кэш
    if (($cache_size = sizeof($data)) !== sizeof($ids)) {
      // Вычисляем разницу для подгрузки
      $missed = array_values(
        $cache_size
          ? array_diff(array_values($ids), array_keys($data))
          : $ids
      );

      // Подгружаем только не найденные данные,
      // попутно сортируя в порядке ID
      $result = [];
      $diff   = $this->dbGetByIds($this->fields, $missed);
      
      foreach ($ids as $id) {
        if (isset($diff[$id]))
          $this->cacheSet($this->getCacheKey('item', $id), $diff[$id]);

        $result[$id] = isset($diff[$id])
          ? $diff[$id]
          : (isset($data[$id]) ? $data[$id] : null);
      }
      $data = &$result;

      unset($diff, $missed);  
    }

    return $this->flatResult($data);
  }

  /**
   * Сохранение записи
   *
   * @param array $data
   * @return $this
   *
   * <code>
   * $result = Model::create('Name')
   *   ->save($form)
   *   ->isOk( )
   * ;
   * </code>
   *
   * <code>
   * $result = Model::create('Name')
   *   ->setId($id)
   *   ->save($form)
   *   ->isOk( )
   * ;
   * </code>
   */
  public function save(array $data = []) {
    // Очищаем прошлые сообщения
    // $this->flushMessages( );
    // Не пропускаем к базе возможно установленные левые ключи
    $data = array_intersect_key($data, array_flip($this->fields));
    $this->data = array_merge($this->data, $data);

    // Ничего на обновление нет?
    if (!$this->data)
      return $this;

    // Обрабатываем данные
    $this->processing('before', 'save');
    // intersect потому что обработка переменных идет на $this->data, посылам только нужные запросы на сервер
    $data = array_intersect_key($this->data, $data);
    
    // Если что-то не так
    if (!$this->validate($data)->isOk())
      return $this;

    // Валидация прошла успешно, обновляем или вставляем новую запись
    if (!$this->is_new) {
      // Если не нужно обновлять главный ключ
      if (isset($this->data['id']) && $this->id === (int) $this->data['id'])
        unset($this->data['id']);

      // Антифлуд
      if ($this->getTimeout('update'))
        return $this->addError('update_timeout');

      //$this->update($data);   // ? TT
      $affected = $this->dbUpdateByIds($data, [$this->id]);
      $this->setState($affected ? self::STATE_OK : self:: STATE_FAIL);

      $this->data['id'] = $this->id;

      // Обновим кэш завершающим этапом
      // В кэш обработанные данные через prepareResult не попадают
      $this->cacheDelete($this->getCacheKey('item', $this->getId()));
      //$this->cacheSet($this->getCacheKey('item', $this->getId()), $data);
    } else {
      // Антифлуд защита, если активна
      if ($this->getTimeout('add'))
        return $this->addError('add_timeout');;

      // Set via direct field becauze of is_new flag reset on method call
      if (!$this->id)
        $this->id = $this->generateId();

      $data['id'] = $this->id;
      $this->dbInsert($data);

      // Дополняем нулл значениями
      $this->data = array_merge(array_fill_keys($this->fields, null), $data);
    }

    // Все ок? вызываем функцию после сохранения
    if ($this->isOk( )) {
      $this->addNotice('saved');

      list($this->data) = $this->flatResult([$this->data]);

      // Постпроцессинг
      $this->processing('after', 'save');
    }

    return $this;
  }

  /**
   * Удаление текущей редактируемой записи или записей по ID
   *
   * @param array $ids
   * @return int
   *   Число удаленных строк 0/1
   */
  public function delete(array $ids = []) {
    if ($this->getTimeout('delete'))
      return $this->addError('delete_timeout');

    if (!$ids && $this->getId()) {
      $this->get();
      $this->processing('before', 'delete');
    }
    $deleted = $this->dbDeleteByIds($ids ? $ids : [$this->id]);
    $this->setState($deleted ? self::STATE_OK : self::STATE_FAIL);

    if ($this->isOk() && $this->getId())
      $this->processing('after', 'delete');

    // Если было успешно удалено
    if ($deleted && !$ids)
      $this->data = [];

    return $this;
  }

  /**
   * Удаление по ряду условий (AND)
   *
   * @param array $cond
   * @return int
   */
  public function deleteBy(array $cond = []) {
    return $this->setState($deleted = $this->dbDelete($cond, 'AND')
        ? self::STATE_OK
        : self::STATE_FAIL
    );
  }

  /**
   * Установка состояния модели
   *
   * @access protected
   * @param int $state
   * @return $this
   */
  protected function setState($state) {
    if (in_array($state, [self::STATE_OK, self::STATE_FAIL, self::STATE_DENIED], true)) {
      $this->state = $state;
    }
    return $this;
  }
  
  /**
   * Получение текущего состояния модели
   *
   * @access protected
   * @return int
   */
  protected function getState( ) {
    return $this->state;
  }

  /**
   * Если все хорошо и проблем нет (по умолчанию)
   *
   * @access public
   * @return bool
   */
  public function isOk( ) {
    return $this->state === self::STATE_OK;
  }

  /**
   * Установка текущей ID сущности
   *
   * @access public
   * @param int $id
   * @return $this
   */
  public function setId($id) {
    $this->id = (string) $id;

    // Новая запись?
    $this->is_new = !$this->id;

    // Инвалидируем данные
    $this->data = [];
    return $this;
  }
  
  /**
   * Получение текущей ID сущности
   *
   * @access public
   * @return int
   */
  public function getId( ) {
    return $this->id;
  }
  
  /**
   * Получение какого-то отдельного поля
   *
   * @access public
   * @param string $k
   * @return mixed
   */
  public function getField($k) {
    return isset($this->data[$k]) ? $this->data[$k] : null;
  }

  /**
   * Установка новых данных для какого-то поля
   *
   * @access public
   * @param string $k Ключ элемента
   * @param string $v Значение
   * @return $this
   */
  public function setField($k, $v) {
    $this->data[$k] = $v;
    return $this;
  }
  
  /**
   * Получение текущих установленных данных
   *
   * @access public
   * @param int|array $id
   * @return array
   */
  public function get($id = null) {
    $id = isset($id) ? $id : $this->id;

    // Nothing to get?
    if (!$id)
      return false;
    
    if (is_array($id)) { // Нужно получить по идам?
      return $this->getByIds($id);
    } elseif (!$this->getId()) { // Если данных не было установлено, пытаемся найти их
      if ($rows = $this->getByIds([$id])) {
        $this->setId($id)->set(array_shift($rows));
      } 
    }

    return $this->data;
  }
	
  /**
   * Получение текущих установленных данных и возвращение ссылки на объект
   *
   * @access public
   * @param int|array $id
   * @return $this
   */
  public function fetch($id = null) {
    $this->get($id);
    return $this;
  }	
  
  /**
   * Установка данных сущности
   *
   * @access public
   * @param array $data
   * @return $this
   */
  public function set(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * @param array $ids
   * @return string
   */
  protected function packIds(array $ids) {
    return implode(', ', $ids);
  }

  /**
   * @param string $id_string
   * @return array
   */
  protected function unpackIds($id_string) {
    assert("is_string(\$id_string)");
    return $id_string ? array_map('trim', explode(',', $id_string)) : [];
  }
}