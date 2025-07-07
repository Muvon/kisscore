<?php declare(strict_types=1);

namespace Plugin\Data;

use ArrayAccess;
use Error;
use InvalidArgumentException;
use JsonSerializable;
use Result;

/**
 * @template TArray of array
 * @template TId as int|string
 * @implements ArrayAccess<key-of<TArray>,value-of<TArray>>
 */
abstract class Model implements ArrayAccess, JsonSerializable {
	use DatabaseTrait;
	use ArrayTrait;
	use OptionTrait;
	protected string $label = '';
	protected bool $exists = false;
	/** @var array<string,bool> */
	protected array $errors = [];
	protected bool $is_cacheable = false;

  /**
	 * @var array<key-of<TArray>,value-of<TArray>>
   */
	protected array $data   = [];

  /**
   * @var array<string,static> $map Карта всех моделей
   */
	protected static array $map = [];

  // Offsets
	protected ?Pagination $Pagination = null;

	final public function __construct() {
		$this->is_cacheable = false; //!App::$debug;
	}

	/**
	 * @param Pagination $Pagination
	 * @return static
	 */
	public function setPagination(Pagination $Pagination): static {
		$this->Pagination = $Pagination;
		return $this;
	}

	/**
	 * @param TArray &$row
	 * @return void
	 */
	// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
	protected static function expand(array &$row): void {
	}

  /**
   * Правила валидации для необходимых полей
   * Валидирующая функция должна возвращать
   *
   * @access protected
   * @return array<string,callable>
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

	public static function new(): static {
		return new static;
	}

	/**
	 * Helper to save new task to simply chain call
	 * @param array<key-of<TArray>,value-of<TArray>> $data
	 * @return Result<static>
	 */
	public static function create(array $data): Result {
		return static::new()->save($data);
	}

  /**
	 * Update counters in database accept keyss with change as a value
   *
   * @param array<key-of<TArray>,int|numeric-string> $counters
   * @param array<TId> $ids
	 * @return static
   */
	public function increment(array $counters, array $ids = []): static {
		if ($this->is_cacheable) {
			Cache::remove(
				array_map(
					function ($item) {
						return static::class . ':' . $item;
					},
					$ids ?: [$this->getId()]
				)
			);
		}
		$this->dbUpdateByIds($counters, $ids ?: [$this->getId()], true);
		return $this;
	}

	/**
	 * @param TArray $data
	 * @return static
	 */
	public function update(array $data): static {
		$data = $this->appendDates($data);
		$this->data = array_replace($this->data, $data);
		return $this;
	}

  /**
	 * Store data into the model
   * @param array<key-of<TArray>,value-of<TArray>> $data
   * @return Result<static>
   */
	public function save(array $data): Result {
		$data = array_intersect_key($this->appendDates($data), array_flip(static::fields()));
		$this->data = array_merge($this->data, $data);

		if (!$this->data) {
			return err('e_data_to_update_missing');
		}

		$data = array_intersect_key($this->data, $data);
		$errors = $this->validate($data);
		if ($errors) {
			return err_list($errors);
		}

		static::transform($data);

		if ($this->exists) {
			$this->updateExistingRecord($data);
		} else {
			$this->insertNewRecord($data);
		}

		$this->exists = true;
		static::expand($this->data);
		return ok($this);
	}

	/**
	 * @param TArray $data
	 * @return bool
	 */
	private function updateExistingRecord(array $data): bool {
		if (isset($this->data[static::$id_field]) && $this->getId() === $this->data[static::$id_field]) {
			unset($this->data[static::$id_field]);
		}

		$saved = $this->dbUpdateByIds($data, [$this->getId()]);
		$this->data[static::$id_field] = $this->getId();

		if ($this->is_cacheable) {
			Cache::remove(static::class . ':' . $this->getId());
		}

		return !!$saved;
	}

	/**
	 * @param TArray $data
	 * @return bool
	 */
	private function insertNewRecord(array $data): bool {
		$this->prepareId($data);
		$data[static::$id_field] = $this->getId();
		$saved = $this->dbInsert($data);

		if (!$this->getId()) {
			$this->setId($this->dbInsertId());
			$data[static::$id_field] = $this->getId();
		}

		static::transform($data, true);
		$this->data = array_merge(static::getDefault(), $data);

		return $saved;
	}

	/**
	 * @param TArray $data
	 * @return void
	 */
	private function prepareId(array $data): void {
		if (isset($data[static::$id_field])) {
			$this->setId($data[static::$id_field]);
		}
		if ($this->getId()) {
			return;
		}

		$shard_key = static::getShardKey();
		$this->setId(static::generateId($shard_key ? static::dbShardId($data[$shard_key]) : ''));
	}

  /**
   * @param TArray $data
   * @return TArray
   */
	protected function appendDates(array $data) {
		if (!isset($data['updated_at'])) {
			$data['updated_at'] = time();
		}

		if (!$this->exists && !isset($data['created_at'])) {
			$data['created_at'] = $data['updated_at'];
		}

		return $data;
	}

	/**
	 * @return bool
	 */
	public function exists(): bool {
		return $this->exists;
	}

	/**
	 * @param bool $exists
	 * @return static
	 */
	public function setExists(bool $exists): static {
		$this->exists = $exists;
		return $this;
	}

	/**
	 * @return TArray
	 */
	public function getData(): array {
		return $this->data;
	}

  /**
   * @access public
   * @param TId $id
   * @return static
   */
	public static function get(int|string $id, bool $cache = false): static {
		$key = (string)$id;
		if (isset(static::$map[$key])) {
			return static::$map[$key];
		}

		$Obj = static::new()->load($id);
		if ($cache) {
			static::$map[$key] = $Obj;
		}

		return $Obj;
	}

  /**
   * That method performs direct query to database for update purspose without any caching mechanisms
   *
   * @param TId $id
   * @return static
   */
	public static function getForUpdate(int|string $id): static {
		if (!DB::inTransaction()) {
			throw new Error('You must be in transaction to use getForUpdate');
		}
		/** @var array<TArray> */
		$rows = static::dbQuery(
			'SELECT * FROM ' . static::table()
				. ' WHERE ' . static::$id_field . ' = :' . static::$id_field
				. ' FOR UPDATE',
			[static::$id_field => $id]
		);

		if (!$rows || !isset($rows[0])) {
			throw new Error('Cant find row with requested id in database for update');
		}

		$Obj = (new static)->loadByData($rows[0]);
		static::transform($Obj->data, true);
		static::expand($Obj->data);

		// Update cached map if set
		$key = (string)$id;
		if (isset(static::$map[$key])) {
			static::$map[$key] = $Obj;
		}

		return $Obj;
	}

	/**
	 * Get default values for current model
	 * @return TArray
	 */
	public static function getDefault(): array {
		return array_map(fn ($v) => $v['default'], static::fields(true));
	}

	/**
	 * Получение нескольких записей по ID
	 *
	 * @param array<TId> $ids
	 * @return array<TArray>
	 */
	public static function getByIds(array $ids): array {
		$ids = array_unique($ids);

		$Obj = new static;
		$data = [];

		if ($Obj->is_cacheable) {
			$key_ptrn = static::class . ':%s';
			foreach ((array)Cache::get(
				array_map(
					function ($item) use ($key_ptrn) {
						return sprintf($key_ptrn, $item);
					}, $ids
				)
			) as $idx => &$val) {
				$data[$ids[$idx]] = $val;
			}
		}

		// Если есть промахи в кэш
		$cache_size = sizeof($data);
		if ($cache_size !== sizeof($ids)) {
			// Вычисляем разницу для подгрузки
			$missed = array_values(
				$cache_size
				? array_diff(array_values($ids), array_keys($data))
				: $ids
			);

			$result = [];
			$diff   = $missed ? $Obj->dbGetByIds(static::fields(), $missed) : [];
			foreach ($ids as $id) {
				if ($Obj->is_cacheable && isset($diff[$id])) {
					Cache::set(sprintf($key_ptrn, $id), $diff[$id]);
				}

				$result[$id] = $diff[$id] ?? ($data[$id] ?? null);
			}
			$data = &$result;
		}
		$data = array_filter($data);
		array_walk($data, fn (&$row) => static::transform($row, true));
		array_map($Obj::expand(...), $data);
		return $data;
	}

	/**
	 * Helper to simplify process of writing new code for the fetcher by fields
	 * @param array<string,mixed> $fields
	 * @param array<mixed> $oroder
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function getByFields(array $fields, array $order = []): static {
		$Self = new static;
		/** @var TArray $row */
		$row = $Self->dbGet(static::fields(), $fields, $order);
		if ($row) {
			static::transform($row, true);
			$Self->loadByData($row);
		}

		return $Self;
	}

	/**
	 * Загрузка из базы данных в текущий инстанс объекта
	 *
	 * @param TId $id
	 * @return static
	 */
	public function load(int|string $id): static {
		$rows = static::getByIds([$id]);
		if (isset($rows[$id])) {
			$this->loadByData($rows[$id]);
		}
		return $this;
	}

	/**
	 * @param TArray $data
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public function loadByData(array $data): static {
		if (!isset($data[static::$id_field])) {
			throw new InvalidArgumentException('There is no id field in data array');
		}
		$this->setId($data[static::$id_field]);
		$this->data = $this->appendDates(array_replace(static::getDefault(), $data));
		$this->exists = true;
		static::expand($this->data);
		return $this;
	}

	/**
	 * @param TArray $data
	 * @param bool $cache
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function fromData(array $data, bool $cache = false): static {
		$id = $data[static::$id_field] ?? null;
		$key = "$id";
		if (isset($id) && isset(static::$map[$key])) {
			return static::$map[$key];
		}
		$Obj = (new static)->loadByData($data);
		if ($cache) {
			static::$map[$key] = $Obj;
		}
		return $Obj;
	}

	// This method used to load data and run prepare func (same as we get from db)
	/**
	 * @param TArray $data
	 * @param bool $cache
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function fromRawData(array $data, bool $cache = false): static {
		$id = $data[static::$id_field] ?? null;
		$key = "$id";
		if (isset($id) && isset(static::$map[$key])) {
			return static::$map[$key];
		}

		// This helps to prevent sisegv
		$Obj = new static;
		static::transform($data, true);
		$Obj->loadByData($data);

		if ($cache) {
			static::$map[$key] = $Obj;
		}
		return $Obj;
	}


	/**
	 * Функция валидации данных
	 *
	 * @access protected
	 * @param TArray $data
	 * @return array<string>
	 * </code>
	 */
	protected function validate(array $data): array {
		$errors = [];
		foreach ($this->rules() as $field => $rule) {
			if (!$this->exists) { // Если новая запись
				// Еще нет такого поля? Пишем туда нуль и валидируем
				if (!isset($data[$field])) {
					$data[$field] = null;
				}
			} else { // Идет обновление
				// Не указано поле? Просто пропускаем правило
				if (!array_key_exists($field, $data)) {
					continue;
				}
			}
			$Res = $rule($data[$field]);
			if (!$Res->err) {
				continue;
			}
			$errors[] = $field . '_' . $Res->err;
		}
		return $errors;
	}

	/**
	 * @return array{class-string,TId}
	 */
	public function ref(): array {
		return [static::class, $this->getId()];
	}

	/**
	 * Transform the single data row according to our transformers returned by getTransformers
	 * @param TArray &$row
	 * @param bool $is_decode If we should decode, default false, encode
	 * @return void
	 */
	protected static function transform(array &$row, bool $is_decode = false): void {
		foreach (static::getTransformers() as $field => [$encode, $decode]) {
			if (!isset($row[$field])) {
				continue;
			}

			$fn = $is_decode ? $decode : $encode;
			$row[$field] = $fn($row[$field]);
		}
	}

	/**
	 * Return list of current transformers for fields
	 * This is overloadable methods
	 * @return array<string,array{0:callable,1:callable}>
	 */
	protected static function getTransformers(): array {
		return [];
	}

	/**
	 * Implements JSON serialize
	 * @return TArray
	 */
	public function jsonSerialize(): array {
		return $this->getData();
	}

	/** @return TId  */
	abstract public function getId(): int|string;

	/**
	 * @param TId $id
	 * @return static
	 */
	abstract public function setId(int|string $id): static;

	/**
	 * @param string $value
	 * @return TId
	 */
	abstract protected static function generateId(string $value = ''): int|string;

	/**
	 * @param string $value
	 * @return TId
	 */
	abstract protected static function dbShardId(string $value): int|string;

	/** @return string  */
	abstract protected static function getShardKey(): string;
}
