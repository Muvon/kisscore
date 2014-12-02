<?php
/**
 * Трейт реализует доступ к SQL-базе данных через функцию обертку db() (Mysql)
 */
trait TDatabase {
  /**
   * @property int $shard_id Текущий идентификатор шарда, использующийся для коннекта
   * @property string $table статическая переменная с таблицей
   * @property array $fields Все поля для данной таблицы
   */
  protected
    $shard_id     = 0,
    $table        = '',
    $fields       = [];

  /**
   * Инициализация и подключение к серверу базы данных
   *
   * @uses Database
   * @uses Config
   */
  final public function initDatabase( ) {
    $cc = get_class($this);

    // Если не установлена таблица
    if (!$this->table)
      $this->table = strtolower(str_replace(chr(92), '_', $cc));

    // Инициализация таблицы
    if ($this->table[0] !== '`')
      $this->table = '`' . $this->table . '`';

    // Если не установлены поля
    if (!$this->fields)
      $this->fields = $this->dbGetFields();

    // Кэш ключ для значения по ид self::getByIds()
    $this->cache_keys['item']   = $cc . ':%d';

    // Кэш ключ для self::getCustomCache()
    $this->cache_keys['custom'] = $cc . ':%s';

    return $this;
  }

  /**
   * Получение строки для sql-строки с параметрами, поддерживает как
   * ассоциативный, так и обычный индексный массив
   *
   * @access protected
   * @param array $params список параметров с данными
   * @param string $sep разделитель при объединении параметров
   * @param bool $incremental
   * @return string подготовленная строка для передачи в запрос
   */
  protected static function dbGetSqlStringByParams(array $params, $sep = ',', $incremental = false) {
    $data = []; // массив данных для объединения
    foreach ($params as $param => $value) {
      if (is_string($param)) {
        $data[] = '`' . $param . '` = ' . ($incremental ? '`' . $param . '` + ' : '' ) . ' :' . $param;
      } else {
        $data[] = '`' . $value . '`';
      }
    }

    return implode(' ' . $sep . ' ', $data);
  }

  /**
   * Выполнение запросов к базе данных
   *
   * @access protected
   * @param string $query
   * @param array $params
   * @return mixed
   * @throws Exception
   */
  protected function dbQuery($query, array $params = []) {
    return db($query, $params, $this->dbShardId($params));
  }

  /**
   * Логика определния идентификатора шарда по передаваемым параметрам в запрос
   *
   * @param array $params
   * @return int
   */
  protected function dbShardId(array $params = null) {
    return 0;
  }

  /**
   * Получение полей с их типами из базы данных
   *
   * @access protected
   * @return array
   * @throws Exception
   */
  protected function dbGetFields( ) { 
    return $this->getCacheable('db:scheme:' . $this->table, function () {
      $fields = [];
      if ($data = $this->dbQuery('DESCRIBE ' . $this->table)) {
        $fields = array_values_by_key($data, 'Field');
      }
      return $fields;
    });
  }

  /**
   * Выполнение запроса вставки в базу данных
   *
   * @uses self::dbGetSqlStringByParams()
   * @uses Database::execute()
   *
   * @access protected
   * @param array $params список параметров для передачи в запрос
   * @return bool
   */
  protected function dbInsert(array $params) {
    $q = 'INSERT INTO ' . $this->table
      . ' SET ' . self::dbGetSqlStringByParams($params, ',');
    return $this->dbQuery($q, $params);
  }

  /**
   * Формирование условия WHERE по передаваемым параметрам
   *
   * @param array &$conditions
   * @return array
   */
  protected function dbGetWhere(array &$conditions) {
    $where = [];
    if ($conditions) {
      $params = $conditions;
      foreach ($params as $k => $c) {
        if (is_array($c)) {
          if ($c) {
            // Собриаем параметры с идентификаторами
            $id_params = [];
            $i = 0;
            foreach ($c as $v) {
              $id_params[] = sprintf('ID%d', ++$i);
            }
            
            $conditions = array_merge($conditions, array_combine($id_params, $c));

            $where[] = ' `' . $k . '` IN (:' . implode(', :', $id_params) . ') ';
            
          } else {
            $where[] = ' `'. $k . '` = NULL ';
          }
          unset($conditions[$k]);
        } else {
          $where[] = ' `' . $k . '` = :' . $k . ' ';
        }
      }
    }
    
    return $where;
  }

  /**
   * Выполнение SELECT-запроса из базы данных
   *
   * @uses self::dbGetSqlStringByParams()
   * @uses Database::query()
   *
   * @access protected
   * @param array $fields список полей для выборки
   * @param array $conditions список условий
   * @param array $order условия сортировки
   * @param int $offset офсет для лимитной выборки
   * @param int $limit лимит на выборку, если нужне, 0 - если безлимитная выборка
   * @return Database::query()
   */
  protected function dbSelect(array $fields, array $conditions = null, array $order = null, $offset = null, $limit = null) {
    // Если нужно формировать строку сортировки
    $order_string = '';

    if ($order) {
      foreach ($order as $field => $sort) {
        $order_string .= ', `' . $field . '` ' . strtoupper($sort);
      }
      $order_string = trim($order_string, ', ');
    }

    // Строка условия - special logic :)
    $where = $conditions ? $this->dbGetWhere($conditions) : null;
    /*
    // Данные трейта TPagination, исключаем, чтобы избавиться от зависимости
    if (!isset($offset) || !isset($limit)) {
      $limit  = $this->limit;
      $offset = $this->offset;
    }
    */
    $q = 'SELECT ' . self::dbGetSqlStringByParams($fields)
      . ' FROM ' . $this->table
      . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
      . ($order_string ? ' ORDER BY ' . $order_string : '')
      . ($limit ? ' LIMIT ' . (int) $offset . ', ' . (int) $limit : '');

    return self::dbQuery($q, $conditions);
  }

  /**
   * Получение количества элементов, согласно выборке с условием
   *
   * @access protected
   * @param array $conditions
   * @return int
   */
  protected function dbCount(array $conditions = []) {
    $where = $conditions ? $this->dbGetWhere($conditions) : null;
    $q = 'SELECT COUNT(*) AS `count` FROM ' . $this->table
      . ($where ? ' WHERE ' . implode('AND', $where) : '')
      . ' LIMIT 1';
    $rows = $this->dbQuery($q, $conditions);
    $count = 0;
    if (isset($rows[0])) {
      $count = (int) $rows[0]['count'];
    }
    return $count;
  }

  /**
   * Получение одной строки (LIMIT 1)
   *
   * @see self::dbSelect
   */
  protected function dbGet(array $fields, array $conditions = null, array $order = null) {
    $rows = $this->dbSelect($fields, $conditions, $order, 'AND', 0, 1);
    return isset($rows[0]) ? $rows[0] : [];
  }

  /**
   * Выполнение UPDATE-запроса в базу данных
   *
   * @uses self::dbGetSqlStringByParams()
   * @uses Database::execute()
   *
   * @access protected
   * @param array $params список параметров для установки в запросе
   * @param array $conditions список условий
   * @return Database::execute()
   */
  protected function dbUpdate(array $params, array $conditions, $incremental = false) {
    $q = 'UPDATE ' . $this->table
      . ' SET ' . self::dbGetSqlStringByParams($params, ',', $incremental)
      . ' WHERE ' . self::dbGetSqlStringByParams($conditions, ' AND ');
    return $this->dbQuery($q, array_merge($params, $conditions));
  }

  /**
   * @access protected
   * @param array $params
   * @param array $ids
   * @param bool $incremental
   * @return Database::execute()
   */
  protected function dbUpdateByIds(array $params, array $ids, $incremental = false) {
    // Собриаем параметры с идентификаторами
    $id_params = [];
    $i = 0;
    foreach ($ids as $id) {
      $id_params[] = sprintf('ID%d', ++$i);
    }
    $q = 'UPDATE ' . $this->table
      . ' SET ' . self::dbGetSqlStringByParams($params, ',', $incremental)
      . ' WHERE `id` IN (:' . implode(', :', $id_params) . ')';

    return $this->dbQuery($q, array_merge($params, array_combine($id_params, $ids)));
  }

  /**
   * Выполнение DELETE-запроса в базе данных
   *
   * @uses self::dbGetSqlStringByParams()
   * @uses Database::execute()
   *
   * @param array $conditions список условий
   * @return Database::execute()
   */
  protected function dbDelete(array $conditions) {
    $q = 'DELETE FROM ' . $this->table
      . ' WHERE ' . self::dbGetSqlStringByParams($conditions, ' AND ');
    return $this->dbQuery($q, $conditions);
  }

  /**
   * Удаление по праймери
   *
   * @param array $ids
   * @return Database::execute()
   */
  protected function dbDeleteByIds(array $ids) {
    return $this->dbDeleteByRowValues('id', $ids);
  }

  /**
   * @param string $row
   * @param array $values
   * @return Database::execute()
   */
  protected function dbDeleteByRowValues($row, array $values) {
    $q = 'DELETE FROM ' . $this->table
      . ' WHERE `' . $row . '` IN (' . trim(str_repeat('?,', sizeof($values)), ',') . ')';
    return $this->dbQuery($q, $values);
  }

  /**
   * @param string $row
   * @param mixed $value
   * @return self::dbDeleteByRowValues()
   */
  protected function dbDeleteByRowValue($row, $value) {
    return $this->dbDeleteByRowValues($row, [$value]);
  }

  /**
   * Получение элементов по всем указанным ID и кэширование при необходимости
   *
   * @uses self::dbGetSqlStringByParams()
   * @uses Database::query()
   *
   * @param array $fields список полей для выборки из таблицы
   * @param array $ids список все необходимых ID (primary key)
   * @return Database::query() результат выборки
   */
  protected function dbGetByIds(array $fields, array $ids) {

    return $this->dbGetByFields($fields, 'id', $ids);
  }

  /**
   * Получение данных из таблиы по одному ид
   *
   * @uses self::getByIds()
   *
   * @param array $fields поля для выборки из таблицы
   * @param int $id
   * @return array данные выборки
   */
  protected function dbGetById(array $fields, $id) {
    return $this->dbGetByField($fields, 'id', $id);
  }

  /**
   * @param array $fields
   * @param string $row
   * @param array $values
   * @return array [id => data, ...]
   */
  protected function dbGetByFields(array $fields, $row, array $values) {
    assert('sizeof($values) > 0');
    
    $q = 'SELECT ' . self::dbGetSqlStringByParams($fields)
      . ' FROM ' . $this->table
      . ' WHERE `' . $row . '` IN (' . trim(str_repeat('?, ', sizeof($values)), ', ') . ')';
      ;
    return ($data = self::dbQuery($q, $values))
      ? array_combine(array_values_by_key($data, 'id'), $data)
      : $data;
  }


  /**
   * @see self::dbGetByFields()
   */
  protected function dbGetByField(array $fields, $row, $value) {
    $rows = $this->dbGetByFields($fields, $row, [$value]);
    return array_shift($rows);
  }


  /**
   * Переключение состояния у поля в базе
   *
   * @param string $field
   * @param int $id
   * @param int $prev_value Предыдущее значение поля
   * @return int
   */
  protected function dbToggleField($field, $id, $prev_value = null) {
    $q = 'UPDATE ' . $this->table
      . ' SET `' . $field . '` = IF (`' . $field . '` = 1, 0, 1)'
      . ' WHERE `id` = :id'
      . (isset($prev_value) ? ' AND `' . $field . '` = :prev_value' : '');

    $params = ['id' => $id];

    if (isset($prev_value))
      $params['prev_value'] = $prev_value;

    return $this->dbQuery($q, $params);
  }

  /**
   * Получение всего списка с данными или списка по условию
   *
   * @see self::getList( );
   */
  public function getList(array $conditions = [], array $order = []) {
    // Если было установлено
    if (!isset($this->total))
      $this->setTotal($this->dbCount($conditions));

    return $this->listResult(
      $this->total ? $this->dbSelect($this->fields, $conditions, $order, $this->offset, $this->limit) : []
    );
  }
}
