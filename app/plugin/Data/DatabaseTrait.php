<?php declare(strict_types=1);

namespace Plugin\Data;

/**
 * @phpstan-type FieldInfo array{type:string,null:bool,default:mixed}
 */
trait DatabaseTrait {
	protected static int $shard_id = 0;
	protected static string $table = '';
	protected static string $id_field = 'id';
	/** @var array<string,array<string>> */
	protected static array $keys = [];
	/** @var array<string,array<string,FieldInfo>> */
	protected static array $fields = [];
	protected static bool $incremental_id = false;

	/**
	 * @access protected
	 * @param array<int|string,mixed> $params список параметров с данными
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
	 * @return string
	 */
	public static function table(): string {
		if (!static::$table) {
			static::$table = strtolower(str_replace(chr(92), '_', get_class_name(static::class)));

			// Инициализация таблицы
			if (static::table()[0] !== '`') {
				static::$table = '`' . static::table() . '`';
			}
		}
		return static::$table;
	}

	/**
	 * @param bool $with_info Include mysql info about each field
	 * @return ($with_info is true ? array<string,FieldInfo> : array<string>)
	 */
	public static function fields(bool $with_info = false): array {
		if (!isset(self::$fields[static::class])) {
			[static::$incremental_id, self::$keys[static::class], self::$fields[static::class]] =
			include_once getenv('ENV_DIR') . '/etc/model/' . str_replace('\\', '_', static::class) . '.php';
		}
		return $with_info ? self::$fields[static::class] : self::$keys[static::class];
	}

	public static function generateFieldsCache(): void {
		$dir = getenv('ENV_DIR') . '/etc/model';
		if (!is_dir($dir)) {
			mkdir($dir);
		}
		$func = function () {
			$incremental_id = false;
			$fields = [];
			/** @var array<int,array{Field:string,Type:string,Null:string,Default:mixed,Extra:string}>|null $data */
			$data = static::dbQuery('DESCRIBE ' . static::table());
			if ($data) {
				for ($i = 0, $max_sz = sizeof($data); $i < $max_sz; $i++) {
					// Find type only for non null default values
					$type = (string)strtok((string)strtok($data[$i]['Type'], ' '), '(');

					$fields[$data[$i]['Field']] = [
						'type' => $type,
						'null' => $data[$i]['Null'],
						'default' => $data[$i]['Default'],
					];
				}
				if ($data[0]['Field'] === static::$id_field && $data[0]['Extra'] === 'auto_increment') {
					$incremental_id = true;
				}
			}
			return [$incremental_id, array_keys($fields), $fields];
		};
		$fields = $func();
		$file_path = $dir . '/' . str_replace('\\', '_', static::class) . '.php';
		$content = '<?php return ' . var_export($fields, true) . ';' . PHP_EOL;
		file_put_contents($file_path, $content);
	}

	/**
	 * @param string $key
	 * @return bool
	 */
	public static function hasField(string $key): bool {
		return isset(static::fields(true)[$key]);
	}

	/**
	 * @return bool
	 */
	protected function isIncrementalId(): bool {
		return static::$incremental_id;
	}


	/**
	 * Выполнение запросов к базе данных
	 *
	 * @access protected
	 * @param string $query
	 * @param array<string,mixed> $params
	 * @return mixed
	 */
	// TODO: implement shard logic
	protected static function dbQuery(string $query, array $params = []): mixed {
		return DB::query($query, static::adaptParams($params));
	}

	/**
	 * @param array<string,mixed> $params
	 * @return array<string,mixed>
	 */
	protected static function adaptParams(array $params): array {
		/** @var array<string> $mapped_keys */
		$mapped_keys = array_map(
			function (string $key): string {
				if (false !== stripos($key, ':')) {
					return (string)strtok($key, ':');
				}
				return $key;
			}, array_keys($params)
		);
		return array_combine($mapped_keys, $params);
	}

	/**
	 * Выполнение запроса вставки в базу данных
	 *
	 * @uses self::dbGetSqlStringByParams()
	 * @uses Database::execute()
	 *
	 * @access protected
	 * @param array<string,mixed> $params список параметров для передачи в запрос
	 * @return bool
	 */
	protected function dbInsert(array $params): bool {
		$q = 'INSERT INTO ' . static::table()
		. ' SET ' . self::dbGetSqlStringByParams($params, ',');
		return !!static::dbQuery($q, $params);
	}

	/**
	 * Get last inserted id in case of auto_increment
	 * @return int|string
	 */
	protected function dbInsertId(): int|string {
		/** @var array<int,array<string,int|string>>|null $result */
		$result = static::dbQuery('SELECT LAST_INSERT_ID() AS `' . static::$id_field . '`');
		if ($result && isset($result[0])) {
			return $result[0][static::$id_field];
		}

		return 0;
	}

	/**
	 * Формирование условия WHERE по передаваемым параметрам
	 *
	 * @param array<string,mixed> &$conditions
	 * @return array<string>
	 */
	protected function dbGetWhere(array &$conditions): array {
		$where = [];
		if (!$conditions) {
			return $where;
		}

		$i = 0;
		foreach ($conditions as $k => $c) {
			$t = '';
			if (false !== stripos($k, ':')) {
				[$k, $t] = explode(':', $k);
			}

			if (is_array($c)) {
				$where[] = $this->handleArrayCondition($k, $c, $t, $conditions, $i);
			} else {
				$where[] = $this->handleScalarCondition($k, $t, $conditions);
			}
		}

		return $where;
	}

	/**
	 * @param string $k
	 * @param array<mixed> $c
	 * @param string $t
	 * @param array<string,mixed> $conditions
	 * @param int $i
	 * @return string
	 */
	private function handleArrayCondition(string $k, array $c, string $t, array &$conditions, int &$i): string {
		if (empty($c)) {
			return " `$k` = NULL ";
		}

		$id_params = array_map(
			function () use (&$i) {
				return 'ID' . ++$i . '$';
			}, $c
		);

		$conditions = array_merge($conditions, array_combine($id_params, $c));
		unset($conditions[$k]);

		if ($t === 'range') {
			return " (`$k` BETWEEN :" . implode(' AND :', $id_params) . ') ';
		}
		return " `$k` IN (:" . implode(', :', $id_params) . ') ';
	}

	/**
	 * @param string $k
	 * @param string $t
	 * @param array<string,mixed> $conditions
	 * @return string
	 */
	private function handleScalarCondition(string $k, string $t, array &$conditions): string {
		$field = $t ? $k . '_' . $t : $k;
		$op = $this->getOperator($t);

		if ($t === '~') {
			$result = " LOWER(`$k`) $op LOWER(:$field) ";
		} else {
			$result = " `$k` $op :$field ";
		}

		if ($field !== $k) {
			$conditions[$k . '_' . $t] = $conditions[$k . ':' . $t];
			unset($conditions[$k . ':' . $t]);
		}

		return $result;
	}

	/**
	 * @param string $t
	 * @return string
	 */
	private function getOperator(string $t): string {
		$operators = [
			'~' => 'LIKE',
			'gt' => '>',
			'ge' => '>=',
			'lt' => '<',
			'le' => '<=',
			'ne' => '!=',
		];

		return $operators[$t] ?? '=';
	}

	/**
	 * Выполнение SELECT-запроса из базы данных
	 *
	 * @uses self::dbGetSqlStringByParams()
	 * @uses Database::query()
	 *
	 * @access protected
	 * @param array<string> $fields
	 * @param array<string,mixed>|null $conditions
	 * @param array<string,string>|null $order
	 * @param int|null $offset
	 * @param int|null $limit
	 * @return array<int,array<string,mixed>>
	 */
	protected function dbSelect(
		array $fields,
		?array $conditions = null,
		?array $order = null,
		$offset = null,
		$limit = null
	): array {
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
		if (!isset($offset) || !isset($limit)) {
		$limit  = $this->limit;
		$offset = $this->offset;
		}
		*/
		$q = 'SELECT ' . self::dbGetSqlStringByParams($fields)
		. ' FROM ' . static::table()
		. ($where ? ' WHERE ' . implode(' AND ', $where) : '')
		. ($order_string ? ' ORDER BY ' . $order_string : '')
		. ($limit ? ' LIMIT ' . (int)$offset . ', ' . (int)$limit : '');

		/** @var array<int,array<string,mixed>> */
		return self::dbQuery($q, $conditions ?? []);
	}

	/**
	 * Get total count by condition passed
	 * @param array<string,mixed> $conditions
	 * @return int
	 */
	public function getCount(array $conditions = []): int {
		$where = $conditions ? $this->dbGetWhere($conditions) : null;
		$q = 'SELECT COUNT(*) AS `count` FROM ' . static::table()
		. ($where ? ' WHERE ' . implode('AND', $where) : '')
		. ' LIMIT 1';
		/** @var array<int,array{count:int}> $rows */
		$rows = static::dbQuery($q, $conditions);
		$count = 0;
		if (isset($rows[0])) {
			$count = (int)$rows[0]['count'];
		}
		return $count;
	}

	/**
	 * Получение одной строки (LIMIT 1)
	 *
	 * @see self::dbSelect
	 * @param array<string> $fields
	 * @param array<string,mixed>|null $conditions
	 * @param array<string,string>|null $order
	 * @return array<string,mixed>
	 */
	protected function dbGet(array $fields, ?array $conditions = null, ?array $order = null): array {
		$rows = $this->dbSelect($fields, $conditions, $order, 0, 1);
		return $rows[0] ?? [];
	}

	/**
	 * Выполнение UPDATE-запроса в базу данных
	 *
	 * @uses self::dbGetSqlStringByParams()
	 * @uses Database::execute()
	 *
	 * @access protected
	 * @param array<string,mixed> $params
	 * @param array<string,mixed> $conditions
	 * @param bool $incremental
	 * @return mixed
	 */
	protected function dbUpdate(array $params, array $conditions, $incremental = false): mixed {
		$q = 'UPDATE ' . static::table()
		. ' SET ' . self::dbGetSqlStringByParams($params, ',', $incremental)
		. ' WHERE ' . self::dbGetSqlStringByParams($conditions, ' AND ');
		return static::dbQuery($q, array_merge($params, $conditions));
	}

	/**
	 * @access protected
	 * @param array<string,mixed> $params
	 * @param array<int|string> $ids
	 * @param bool $incremental
	 * @return mixed
	 */
	protected function dbUpdateByIds(array $params, array $ids, $incremental = false): mixed {
		$i = 0;
		$id_params = array_map(
			fn() => sprintf('ID%d$', ++$i),
			$ids
		);
		$q = 'UPDATE ' . static::table()
		. ' SET ' . self::dbGetSqlStringByParams($params, ',', $incremental)
		. ' WHERE `' . static::$id_field . '` IN (:' . implode(', :', $id_params) . ')';

		return static::dbQuery($q, array_merge($params, array_combine($id_params, $ids)));
	}

	/**
	 * Выполнение DELETE-запроса в базе данных
	 *
	 * @uses self::dbGetSqlStringByParams()
	 * @uses Database::execute()
	 *
	 * @param array<string,mixed> $conditions список условий
	 * @return mixed
	 */
	protected function dbDelete(array $conditions): mixed {
		$q = 'DELETE FROM ' . static::table()
		. ' WHERE ' . self::dbGetSqlStringByParams($conditions, ' AND ');
		return static::dbQuery($q, $conditions);
	}

	/**
	 * Удаление по праймери
	 *
	 * @param array<int|string> $ids
	 * @return int
	 */
	protected function dbDeleteByIds(array $ids): int {
		/** @var array<int|string> $ids */
		return $this->dbDeleteByRowValues(static::$id_field, $ids);
	}

	/**
	 * @param string $row
	 * @param array<int|string> $values
	 * @return int
	 */
	protected function dbDeleteByRowValues(string $row, array $values): int {
		$q = 'DELETE FROM ' . static::table()
		. ' WHERE `' . $row . '` IN (:values)';
		/** @var int */
		return static::dbQuery($q, ['values' => $values]);
	}

	/**
	 * @param string $row
	 * @param mixed $value
	 * @return int
	 */
	protected function dbDeleteByRowValue(string $row, mixed $value): int {
		/** @var array<int|string> $values */
		$values = [$value];
		/** @var int */
		return $this->dbDeleteByRowValues($row, $values);
	}

	/**
	 * @uses self::dbGetSqlStringByParams()
	 * @uses Database::query()
	 *
	 * @param array<string> $fields
	 * @param array<int|string> $ids
	 * @return array<int|string,array<string,mixed>>
	 */
	protected function dbGetByIds(array $fields, array $ids): array {
		return $this->dbGetByFields($fields, static::$id_field, $ids);
	}

	/**
	 * Получение данных из таблиы по одному ид
	 *
	 * @uses self::getByIds()
	 *
	 * @param array<string> $fields поля для выборки из таблицы
	 * @param int $id
	 * @return array<string,mixed>
	 */
	protected function dbGetById(array $fields, int $id): array {
		return $this->dbGetByField($fields, static::$id_field, $id);
	}

	/**
	 * @param array<string> $fields
	 * @param string $row
	 * @param array<mixed> $values
	 * @return array<int|string,array<string,mixed>>
	 */
	protected function dbGetByFields(array $fields, string $row, array $values): array {
		assert(sizeof($values) > 0);

		$q = 'SELECT ' . self::dbGetSqlStringByParams($fields)
		. ' FROM ' . static::table()
		. ' WHERE `' . $row . '` IN (:' . $row . ')';
		;
		/** @var array<int,array<string,mixed>> $data */
		$data = self::dbQuery($q, [$row => $values]);
		if (!$data) {
			return $data;
		}
		/** @var array<int|string> $id_keys */
		$id_keys = array_column($data, static::$id_field);
		/** @var array<int|string,array<string,mixed>> */
		return array_combine($id_keys, $data);
	}

	/**
	 * @param array<string> $fields
	 * @param string $row
	 * @param mixed $value
	 * @return array<string,mixed>
	 */
	protected function dbGetByField(array $fields, string $row, mixed $value): array {
		$rows = $this->dbGetByFields($fields, $row, [$value]);
		return array_shift($rows) ?? [];
	}


	/**
	 * Переключение состояния у поля в базе
	 *
	 * @param string $field
	 * @param int $id
	 * @param null|int $prev_value Предыдущее значение поля
	 * @return int
	 */
	protected function dbToggleField(string $field, int $id, ?int $prev_value = null): int {
		$q = 'UPDATE ' . static::table()
		. ' SET `' . $field . '` = IF (`' . $field . '` = 1, 0, 1)'
		. ' WHERE `' . static::$id_field . '` = :id'
		. (isset($prev_value) ? ' AND `' . $field . '` = :prev_value' : '');

		$params = [static::$id_field => $id];

		if (isset($prev_value)) {
			$params['prev_value'] = $prev_value;
		}

		/** @var int */
		return static::dbQuery($q, $params);
	}

	/**
	 * @param string $query
	 * @param string $select
	 * @param array<string,mixed> $params
	 * @param array<string,string> $order
	 * @param int $offset
	 * @param int $limit
	 * @param int $total
	 * @return array<int,array<string,mixed>>
	 */
	protected function dbGetPaginated(
		string $query,
		string $select = '*',
		array $params = [],
		array $order = [],
		int $offset = 0,
		int $limit = 10,
		int $total = -1
	): array {
		assert(is_string($query));
		$order_string = '';
		if ($order) {
			foreach ($order as $field => $sort) {
				$order_string .= ', `' . $field . '` ' . strtoupper($sort);
			}
			$order_string = trim($order_string, ', ');
		}

		$query_cnt = 'SELECT %s FROM ' . static::table() . ' ' . $query;
		$query = 'SELECT %s FROM ' . static::table() . ' ' . $query
		. ($order_string ? ' ORDER BY ' . $order_string : '')
		. ' LIMIT %d, %d';

		if ($total === -1) {
			/** @var array<int,array{count:int|string}> $rows */
			$rows = self::dbQuery(sprintf($query_cnt, ...['COUNT(*) AS `count`']), $params);
			$total = (int)array_sum(array_column($rows, 'count'));
		}

		/** @var array<int,array<string,mixed>> $result */
		$result = $total > 0 ? self::dbQuery(sprintf($query, ...[$select, $offset, $limit]), $params) : [];
		array_walk($result, fn (&$row) => static::transform($row, true));

		return $result;
	}

	/**
	 * Получение всего списка с данными или списка по условию
	 *
	 * @param array<string,mixed> $conditions
	 * @param array<string,string> $order
	 * @param int $offset
	 * @param int $limit
	 * @return array<int,array<string,mixed>>
	 */
	public static function getList(array $conditions = [], array $order = [], int $offset = 0, int $limit = 10): array {
		$rows = $limit > 0 ? static::new()->dbSelect(static::fields(), $conditions, $order, $offset, $limit) : [];

		array_walk(
			$rows, function (&$row) {
				static::expand($row);
				static::transform($row, true);
			}
		);
		return $rows;
	}
}
