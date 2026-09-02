<?php declare(strict_types=1);

namespace Plugin\List;

use Error;
use Plugin\Data\Model;

/**
 * Entity data loader
 *
 * @final
 * @package Core
 * @subpackage Fetcher
 */
final class Fetcher {
	protected string $model = '';
	protected string $method = '';
	/** @var array<mixed> */
	protected array $ids = [];
	/** @var array<Fetcher> */
	protected array $batch = [];
	/** @var array<mixed>|null */
	protected ?array $data = null;

	protected string $src_key  = '';
	protected string $root_key = '';
	protected string $dst_key  = '';
	/** @var array<mixed>|null */
	protected ?array $args = null;

	protected ?Pagination $Pagination = null;

	/**
	 * Create data loader and queue first task
	 *
	 * @access public
	 * @static
	 * @param string|array{0:string,1:string} $mapper
	 *   Mapper name for loading data
	 * @param string $src_key
	 *   Identifier key
	 * @param array<mixed>|null $args
	 *   Identifier (array, string, or number)
	 * @param array<mixed> $data
	 *   Result array (if already aggregated)
	 * @param array<Fetcher> $batch
	 *   Operations to execute in parallel
	 * @return Fetcher
	 */
	public static function create(
		string|array $mapper,
		string $src_key,
		?array $args = null,
		array &$data = [],
		array $batch = []
	): self {
		$Self = new self;
		if (is_string($mapper)) {
			$model  = $mapper;
			$method = $args ? 'get' : 'getList';
		} elseif (is_array($mapper)) {
			[$model, $method] = $mapper;
		} else {
			throw new Error('Mapper can be string or array with 2 elements');
		}

		$Self->model = $model;
		$Self->method = $method;

		$Self->src_key = (string)strtok($src_key, ':');
		$Self->dst_key = $Self->getDstKey($src_key);
		$Self->args = $args;
		$Self->batch = $batch;
		$Self->data = &$data;
		return $Self;
	}

	/**
	 * Set root key from which src_key is resolved
	 *
	 * @param string $root_key
	 * @return static
	 */
	public function setRootKey(string $root_key): static {
		$this->root_key = $root_key;
		return $this;
	}

	/**
	 * @access protected
	 * @param string $key
	 * @return string
	 */
	protected function getDstKey(string $key): string {
		if (false !== strpos($key, ':')) {
			return explode(':', $key)[1];
		}

		return substr($key, 0, strrpos($key, '_') ?: 0);
	}

	/**
	 * Initialize paginated fetching
	 *
	 * @access public
	 * @param int $page
	 * @param int $limit
	 * @param int $total
	 * @return $this
	 */
	public function paginate(int $page, int $limit, int $total = 0): static {
		$this->Pagination = Pagination::create(
			[
				'page' => $page,
				'limit' => $limit,
				'total' => $total,
			]
		);
		return $this;
	}

	/**
	 * Execute sequentially
	 *
	 * @access public
	 * @return static
	 */
	public function dispatch(): static {
		$this->loadDataIfNeeded();
		$this->processBatchIfNeeded();
		return $this;
	}

	/**
	 * Load data if it's not already loaded
	 * @return void
	 */
	private function loadDataIfNeeded(): void {
		if ($this->data) {
			return;
		}

		/** @var class-string<Model<array<string,mixed>>> $model */
		$model = $this->model;
		$Obj = $model::new();
		$args = $this->args ?? [];
		// If we have pagination and need to use dynamic count detection
		if ($this->Pagination && $this->method !== 'get' && $this->method !== 'getByIds') {
			$total = $this->Pagination->getTotal();
			if (!$total) {
				/** @var array<string,mixed> $first_arg */
				$first_arg = $args[0] ?? [];
				$total = $Obj->getCount($first_arg);
			}
			$this->Pagination->setTotal($total);
			$args = [...$args, ...[
				'offset' => $this->Pagination->getOffset(),
				'limit' => $this->Pagination->getLimit(),
			]];
		}

		$result = $Obj->{$this->method}(...$args);
		if ($this->method === 'get') {
			$this->data = $result->getData();
		} else {
			$this->data = $result;
		}

		if (!$this->Pagination) {
			return;
		}

		$this->data = $this->Pagination->listResult($this->data ?? []);
	}

	/**
	 * Process batch if it's not empty
	 * @return void
	 */
	private function processBatchIfNeeded(): void {
		if (!$this->data || !$this->batch) {
			return;
		}

		$prev = null;
		foreach ($this->batch as $Fetcher) {
			$this->processFetcher($Fetcher, $prev);
			$prev = $Fetcher->src_key;
		}
	}

	/**
	 * @param Fetcher $Fetcher
	 * @param string|null $prev
	 * @return void
	 */
	private function processFetcher(Fetcher $Fetcher, string|null $prev): void {
		$dk = $Fetcher->dst_key;
		$sk = $Fetcher->src_key;
		$rk = $Fetcher->root_key ? explode('.', $Fetcher->root_key) : [];

		/** @var class-string<Model<array<string,mixed>>> $model_class */
		$model_class = $Fetcher->model;
		$Obj = $model_class::new();

		$is_list = $this->method === 'getByIds';
		$data = &$this->getDataReference($is_list);

		if ($prev && $rk) {
			$data = &$this->traverseRootKey($data, $rk);
		}

		if (!$is_list && array_is_list($data)) {
			$is_list = true;
		}

		if ($is_list) {
			$this->processListData($data, $Obj, $rk, $sk, $dk);
		} else {
			assert($this->data !== null);
			$this->processSingleData($this->data, $Obj, $rk, $sk, $dk);
		}
	}

	/**
	 * @param bool $is_list
	 * @return array<mixed>
	 */
	private function &getDataReference(bool &$is_list): array {
		if (isset($this->data['items']) && is_array($this->data['items'])) {
			$is_list = true;
			return $this->data['items'];
		}
		/** @var array<mixed> */
		return $this->data;
	}

	/**
	 * @param array<mixed> $data
	 * @param array<string> $rk
	 * @return array<mixed>
	 */
	private function &traverseRootKey(array &$data, array $rk): array {
		$current = &$data;
		foreach ($rk as $key) {
			if (!is_array($current) || !array_key_exists($key, $current)) {
				continue;
			}
			$current = &$current[$key];
		}
		/** @var array<mixed> $current */
		return $current;
	}

	/**
	 * @param array<mixed> $data
	 * @param Model<array<string,mixed>> $Obj
	 * @param array<string> $rk
	 * @param string $sk
	 * @param string $dk
	 * @return void
	 */
	private function processListData(array &$data, Model $Obj, array $rk, string $sk, string $dk): void {
		/** @var array<int|string> $ids */
		$ids = $this->getIdsFromListData($data, $rk, $sk);
		$items = $Obj::getByIds($ids);

		foreach ($data as &$item) {
			[$row, $keys] = $this->getRowDest($item, $rk, $sk, $dk);
			if (!isset($row) || !isset($keys)) {
				continue;
			}
			/** @var array<mixed>|int|string $row */
			$this->setDestination($item, $keys, $row, $items);
		}
	}

	/**
	 * @param array<mixed> $data
	 * @param array<string> $rk
	 * @param string $sk
	 * @return array<mixed>
	 */
	private function getIdsFromListData(array $data, array $rk, string $sk): array {
		$array = $data;
		if ($rk) {
			foreach ($rk as $key) {
				$array = array_column($array, $key);
			}
		}

		$ids = array_column($array, $sk);
		if (isset($ids[0]) && is_array($ids[0])) {
			$ids = array_merge(...$ids);
		}

		return $ids;
	}

	/**
	 * @param array<mixed> $data
	 * @param Model<array<string,mixed>> $Obj
	 * @param array<string> $rk
	 * @param string $sk
	 * @param string $dk
	 * @return void
	 */
	private function processSingleData(array &$data, Model $Obj, array $rk, string $sk, string $dk): void {
		[$row, $keys] = $this->getRowDest($data, $rk, $sk, $dk);
		if (!isset($row) || !isset($keys)) {
			return;
		}
		$dest = &array_value_ref($this->data, $keys);
		if (is_array($row)) {
			/** @var array<int|string> $row */
			$dest = array_values($Obj::getByIds($row));
		} else {
			/** @var int|string $row */
			$dest = $Obj::get($row)->getData();
		}
	}

	/**
	 * @param array<mixed> &$item
	 * @param array<string> $keys
	 * @param array<mixed>|int|string $row
	 * @param array<mixed> $items
	 * @return void
	 */
	private function setDestination(array &$item, array $keys, array|int|string $row, array $items): void {
		$dest = &array_value_ref($item, $keys);
		if (is_array($row)) {
			/** @var array<int|string> $row */
			$dest = array_values(array_intersect_key($items ?: [], array_flip($row)));
		} else {
			$dest = $items[$row] ?? null;
		}
	}

	/**
	 * @param array<mixed> $container
	 * @param array<string> $rk
	 * @param string $sk
	 * @param string $dk
	 * @return array{0:mixed,1:array<string>|null}
	 */
	protected function getRowDest(array &$container, array $rk, string $sk, string $dk): array {
		if ($rk) {
			$root = &array_value_ref($container, $rk);
			if (!isset($root)) {
				return [null, null];
			}
			unset($root);
			$row = &array_value_ref($container, [...$rk, $sk]);
			$keys = array_merge($rk, [$dk]);
		} else {
			$row = $container[$sk];
			$keys = [$dk];
		}

		return [$row, $keys];
	}
}
