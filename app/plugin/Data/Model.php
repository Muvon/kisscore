<?php declare(strict_types=1);

namespace Plugin\Data;

use ArrayAccess;
use Error;
use InvalidArgumentException;
use JsonSerializable;
use Plugin\List\Pagination;
use Result;

/**
 * @template TRow of array<string,mixed> The table row as each model declares
 *   it (`@extends Model<array{col: type, ...}>`): column types for
 *   `$Model['col']`, `getData()`, `getList()` and `getByIds()` come from it.
 * @implements ArrayAccess<key-of<TRow>,value-of<TRow>>
 */
abstract class Model implements ArrayAccess, JsonSerializable {
	use DatabaseTrait {
		getList as protected traitGetList;
	}
	use ArrayTrait {
		offsetGet as protected traitOffsetGet;
	}
	use OptionTrait;
	protected string $label = '';
	protected bool $exists = false;
	/** @var array<string,bool> */
	protected array $errors = [];
	protected bool $is_cacheable = false;

  /**
	 * @var array<string,mixed>
   */
	protected array $data   = [];

  /**
   * @var array<string,static> $map Map of all models
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
	 * @param array<string,mixed> &$row
	 * @return void
	 */
	// phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter
	protected static function expand(array &$row): void {
	}

  /**
   * Validation rules for required fields
   * Validator function must return a Result
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
		/** @var static $Obj */
		$Obj = new static;
		return $Obj;
	}

	/**
	 * Helper to save new task to simply chain call
	 * @param array<string,mixed> $data
	 * @return Result<static>
	 */
	public static function create(array $data): Result {
		return static::new()->save($data);
	}

  /**
	 * Update counters in database accept keys with change as a value
   *
   * @param array<string,int|numeric-string> $counters
   * @param array<int|string> $ids
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
	 * @param array<string,mixed> $data
	 * @return static
	 */
	public function update(array $data): static {
		$data = $this->appendDates($data);
		$this->data = array_replace($this->data, $data);
		return $this;
	}

  /**
	 * Store data into the model
   * @param array<string,mixed> $data
   * @return Result<static>
   */
	public function save(array $data): Result {
		$data = array_intersect_key($this->appendDates($data), array_flip(static::fields()));
		$this->data = array_merge($this->data, $data);

		if (!$this->data) {
			/** @var Result<static> */
			return err('e_data_to_update_missing');
		}

		$data = array_intersect_key($this->data, $data);
		$errors = $this->validate($data);
		if ($errors) {
			/** @var Result<static> */
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
		/** @var Result<static> */
		return ok($this);
	}

	/**
	 * @param array<string,mixed> $data
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
	 * @param array<string,mixed> $data
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
	 * @param array<string,mixed> $data
	 * @return void
	 */
	private function prepareId(array $data): void {
		if (isset($data[static::$id_field])) {
			/** @var int|string $id_value */
			$id_value = $data[static::$id_field];
			$this->setId($id_value);
		}
		if ($this->getId()) {
			return;
		}

		$shard_key = static::getShardKey();
		/** @var string $shard_value */
		$shard_value = $shard_key ? ($data[$shard_key] ?? '') : '';
		$this->setId(static::generateId($shard_value));
	}

  /**
   * @param array<string,mixed> $data
   * @return array<string,mixed>
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
	 * The loaded row: every declared column, typed as the model declares it.
	 *
	 * @return TRow
	 */
	public function getData(): array {
		/** @var TRow $row */
		$row = $this->data;
		return $row;
	}

	/**
	 * One column, typed as the model declares it.
	 *
	 * @template TKey of key-of<TRow>
	 * @param TKey $k
	 * @return TRow[TKey]
	 */
	public function offsetGet(mixed $k): mixed {
		/** @var TRow[TKey] $value */
		$value = $this->traitOffsetGet($k);
		return $value;
	}

	/**
	 * Get full list or filtered list by conditions
	 *
	 * @param array<string,mixed> $conditions
	 * @param array<string,string> $order
	 * @return array<int,TRow>
	 */
	public static function getList(array $conditions = [], array $order = [], int $offset = 0, int $limit = 10): array {
		/** @var array<int,TRow> $rows */
		$rows = static::traitGetList($conditions, $order, $offset, $limit);
		return $rows;
	}

  /**
   * @access public
   * @param int|string $id
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
   * @param int|string $id
   * @return static
   */
	public static function getForUpdate(int|string $id): static {
		if (!DB::inTransaction()) {
			throw new Error('You must be in transaction to use getForUpdate');
		}
		/** @var array<int,array<string,mixed>> $rows */
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
	 * Get default values for current model. DESCRIBE reports every default as a
	 * string, while a row read back from the database carries native scalars —
	 * cast by column type so a freshly created model and a reloaded one agree.
	 *
	 * @return array<string,mixed>
	 */
	public static function getDefault(): array {
		return array_map(
			static fn (array $v): int|float|string|null => static::castDefault($v['type'], $v['default']),
			static::fields(true)
		);
	}

	protected static function castDefault(string $type, ?string $default): int|float|string|null {
		if ($default === null) {
			return null;
		}
		return match ($type) {
			'tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'year' => (int)$default,
			'float', 'double' => (float)$default,
			default => $default,
		};
	}

	/**
	 * Get multiple records by IDs
	 *
	 * @param array<int|string> $ids
	 * @return array<int|string,TRow>
	 */
	public static function getByIds(array $ids): array {
		$ids = array_unique($ids);

		$Obj = new static;
		$data = [];
		$key_ptrn = static::class . ':%s';

		if ($Obj->is_cacheable) {
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

		// Handle cache misses
		$cache_size = sizeof($data);
		if ($cache_size !== sizeof($ids)) {
			// Calculate diff to load from DB
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
		/** @var array<int|string,TRow> $rows */
		$rows = array_filter($data);
		array_walk($rows, fn (&$row) => static::transform($row, true));
		array_map($Obj::expand(...), $rows);
		return $rows;
	}

	/**
	 * Helper to simplify process of writing new code for the fetcher by fields
	 * @param array<string,mixed> $fields
	 * @param array<string,string> $order
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function getByFields(array $fields, array $order = []): static {
		/** @var static $Self */
		$Self = new static;
		/** @var array<string,mixed> $row */
		$row = $Self->dbGet(static::fields(), $fields, $order);
		if ($row) {
			static::transform($row, true);
			$Self->loadByData($row);
		}

		return $Self;
	}

	/**
	 * Load from database into current object instance
	 *
	 * @param int|string $id
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
	 * @param array<string,mixed> $data
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public function loadByData(array $data): static {
		if (!isset($data[static::$id_field])) {
			throw new InvalidArgumentException('There is no id field in data array');
		}
		/** @var int|string $data_id */
		$data_id = $data[static::$id_field];
		$this->setId($data_id);
		$this->data = $this->appendDates(array_replace(static::getDefault(), $data));
		$this->exists = true;
		static::expand($this->data);
		return $this;
	}

	/**
	 * @param array<string,mixed> $data
	 * @param bool $cache
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function fromData(array $data, bool $cache = false): static {
		/** @var int|string|null $id */
		$id = $data[static::$id_field] ?? null;
		$key = (string)$id;
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
	 * @param array<string,mixed> $data
	 * @param bool $cache
	 * @return static
	 * @throws InvalidArgumentException
	 */
	public static function fromRawData(array $data, bool $cache = false): static {
		/** @var int|string|null $id */
		$id = $data[static::$id_field] ?? null;
		$key = (string)$id;
		if (isset($id) && isset(static::$map[$key])) {
			return static::$map[$key];
		}

		// This helps to prevent sisegv
		/** @var static $Obj */
		$Obj = new static;
		static::transform($data, true);
		$Obj->loadByData($data);

		if ($cache) {
			static::$map[$key] = $Obj;
		}
		return $Obj;
	}


	/**
	 * Validate data against rules
	 *
	 * @access protected
	 * @param array<string,mixed> $data
	 * @return array<string>
	 * </code>
	 */
	protected function validate(array $data): array {
		$errors = [];
		foreach ($this->rules() as $field => $rule) {
			if (!$this->exists) { // New record
				// Field missing? Set null and validate
				if (!isset($data[$field])) {
					$data[$field] = null;
				}
			} else { // Updating
				// Field not specified? Skip rule
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
	 * @return array{class-string,int|string}
	 */
	public function ref(): array {
		return [static::class, $this->getId()];
	}

	/**
	 * Transform the single data row according to our transformers returned by getTransformers
	 *
	 * A transformer key is one or more comma-separated fields:
	 *   'name'            => [$encode, $decode]   single field: encode/decode fn($value): mixed
	 *   'amount,currency' => [$encode, $decode]   multi field
	 *
	 * Multi-field transformers are asymmetric and use the first field as the single storage column:
	 *   encode: fn($amount, $currency): mixed  fields fold into one value stored in the first field
	 *   decode: fn($stored): array{mixed,...}  the stored value spreads positionally back into all fields
	 *
	 * Encode skips unless all fields are present; decode skips unless the storage field is present.
	 *
	 * @param array<string,mixed> &$row
	 * @param bool $is_decode If we should decode, default false, encode
	 * @return void
	 */
	protected static function transform(array &$row, bool $is_decode = false): void {
		foreach (static::getTransformers() as $key => [$encode, $decode]) {
			$fn = $is_decode ? $decode : $encode;
			if (str_contains($key, ',')) {
				self::transformMulti($row, $key, $fn, $is_decode);
				continue;
			}
			if (!isset($row[$key])) {
				continue;
			}
			$row[$key] = $fn($row[$key]);
		}
	}

	/**
	 * Apply one multi-field transformer (see transform() for the contract).
	 * The first field is the storage column; encode folds all fields into it,
	 * decode spreads it positionally back. Encode skips unless every field is
	 * present; decode skips unless the storage field is present.
	 *
	 * @param array<string,mixed> &$row
	 * @param string $key Comma-separated field list
	 * @param callable $fn Encode or decode transformer for this pass
	 * @param bool $is_decode
	 * @return void
	 */
	private static function transformMulti(array &$row, string $key, callable $fn, bool $is_decode): void {
		$fields = array_map('trim', explode(',', $key));
		$storage = $fields[0];

		if ($is_decode) {
			if (!isset($row[$storage])) {
				return;
			}
			$values = $fn($row[$storage]);
			foreach ($fields as $i => $field) {
				$row[$field] = $values[$i];
			}
			return;
		}

		$args = [];
		foreach ($fields as $field) {
			if (!isset($row[$field])) {
				return;
			}
			$args[] = $row[$field];
		}

		$row[$storage] = $fn(...$args);
		foreach (array_slice($fields, 1) as $field) {
			unset($row[$field]);
		}
	}

	/**
	 * Return list of current transformers for fields
	 * This is overloadable methods
	 * Key is a single field or comma-separated fields mapped positionally (see transform)
	 * @return array<string,array{0:callable,1:callable}>
	 */
	protected static function getTransformers(): array {
		return [];
	}

	/**
	 * Implements JSON serialize
	 * @return array<string,mixed>
	 */
	public function jsonSerialize(): array {
		return $this->getData();
	}

	/** @return int|string  */
	abstract public function getId(): int|string;

	/**
	 * @param int|string $id
	 * @return static
	 */
	abstract public function setId(int|string $id): static;

	/**
	 * @param string $value
	 * @return int|string
	 */
	abstract protected static function generateId(string $value = ''): int|string;

	/**
	 * @param string $value
	 * @return int|string
	 */
	abstract protected static function dbShardId(string $value): int|string;

	/** @return string  */
	abstract protected static function getShardKey(): string;

	/**
	 * (Re)build the field-schema cache for every concrete model under the App
	 * namespace (App\<Domain>\<Name>Model). DB-layer bootstrap step: it only
	 * needs config + a DB connection, so it runs on the lightweight Env::init
	 * path (no App::start) — which is what lets it create the cache that the
	 * model reader fields() depends on without a cold-boot deadlock.
	 *
	 * @return int Number of models cached
	 */
	public static function generateSchemaCache(): int {
		$count = 0;
		foreach (glob(getenv('APP_DIR') . '/src/*/*Model.php') ?: [] as $file) {
			$class = 'App\\' . basename(dirname($file)) . '\\' . basename($file, '.php');
			if (!class_exists($class) || (new \ReflectionClass($class))->isAbstract()) {
				continue;
			}
			if (!is_a($class, self::class, true)) {
				continue;
			}
			$class::generateFieldsCache();
			$count++;
		}
		return $count;
	}
}
