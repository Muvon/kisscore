<?php declare(strict_types=1);

namespace Plugin\List;

use Error;

/**
 * Загрузчик сущностей, доступ через объект Entity
 *
 * @final
 * @package Core
 * @subpackage Fetcher
 */
class Fetcher {
	protected string $model = '';
	protected string $method = '';
	protected array $ids = [];
	protected array $batch = [];
	protected ?array $data = null;

	/**
	 * @property string $src_key
	 * @property string $root_key
	 * @property string $dst_key
	 */
	protected $src_key  = '';
	protected $root_key = '';
	protected $dst_key  = '';
	protected $args = null;

	protected $Pagination = null;

	/**
	 * Создание загрузчика данных и постановка первого задания
	 *
	 * @access public
	 * @static
	 * @param string|array{0:string,1:string} $mapper
	 *   Имя маппера, которые обязауется подгружать данные
	 * @param string $src_key
	 *   Индекс идентификатора
	 * @param mixed $args
	 *   Массив или строка/число - однозначный идентификатор
	 * @param array $data
	 *   Массив с результатами (если была уже агрегированная выборка)
	 * @param array $batch
	 *   Массив оппераций, которые будут выполнены в параллели
	 * @return Fetcher
	 */
	public static function create(
		string|array $mapper,
		string $src_key,
		array $args = null,
		array &$data = [],
		array $batch = []
	) {
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

		$Self->src_key = strtok($src_key, ':');
		$Self->dst_key = $Self->getDstKey($src_key);
		$Self->args = $args;
		$Self->batch = $batch;
		$Self->data = &$data;
		return $Self;
	}

	/**
	 * Установка корневого ключа, откуда будет браться src_key
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
	 * Инициализцаия постраничной выборки
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
	 * Выполнение в последовательном режиме
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

		$Obj = new $this->model;
		$args = $this->args;
		// If we have pagination and need to use dynamic count detection
		if ($this->Pagination && $this->method !== 'get' && $this->method !== 'getByIds') {
			$total = $this->Pagination ? $this->Pagination->getTotal() : 0;
			if (!$total) {
				$total = $Obj->getCount(...$args);
			}
			$this->Pagination->setTotal($total);
			$args = [...$args, ...[
				'offset' => $this->Pagination->getOffset(),
				'limit' => $this->Pagination->getLimit(),
			]];
		}

		$result = call_user_func_array([$Obj, $this->method], $args);
		if ($this->method === 'get') {
			$this->data = $result->getData();
		} else {
			$this->data = $result;
		}

		if (!$this->Pagination) {
			return;
		}

		$this->data = $this->Pagination->listResult($this->data);
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

		$Obj = new $Fetcher->model;

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
			$this->processSingleData($data, $Obj, $rk, $sk, $dk);
		}
	}

	/**
	 * @param bool $is_list
	 * @return array
	 */
	private function &getDataReference(bool &$is_list): array {
		if (isset($this->data['items']) && is_array($this->data['items'])) {
			$is_list = true;
			return $this->data['items'];
		}
		return $this->data;
	}

	/**
	 * @param array $data
	 * @param array $rk
	 * @return array
	 */
	private function &traverseRootKey(array &$data, array $rk): array {
		foreach ($rk as $key) {
			if (array_key_exists($key, $data)) {
				continue;
			}
			$data = &$data[$key];
		}
		return $data;
	}

	/**
	 * @param array $data
	 * @param object $Obj
	 * @param array $rk
	 * @param string $sk
	 * @param string $dk
	 * @return void
	 */
	private function processListData(array &$data, object $Obj, array $rk, string $sk, string $dk): void {
		$ids = $this->getIdsFromListData($data, $rk, $sk);
		$items = $Obj::getByIds($ids);

		foreach ($data as &$item) {
			[$row, $keys] = $this->getRowDest($item, $rk, $sk, $dk);
			if (!isset($row)) {
				continue;
			}
			$this->setDestination($item, $keys, $row, $items);
		}
	}

	/**
	 * @param array $data
	 * @param array $rk
	 * @param string $sk
	 * @return array
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
			$ids = call_user_func_array('array_merge', $ids);
		}

		return $ids;
	}

	/**
	 * @param array $data
	 * @param object $Obj
	 * @param array $rk
	 * @param string $sk
	 * @param string $dk
	 * @return void
	 */
	private function processSingleData(array &$data, object $Obj, array $rk, string $sk, string $dk): void {
		[$row, $keys] = $this->getRowDest($data, $rk, $sk, $dk);
		if (!isset($row)) {
			return;
		}
		$dest = &array_value_ref($this->data, $keys);
		$dest = is_array($row) ? array_values($Obj::getByIds($row)) : $Obj::get($row)->getData();
	}

	/**
	 * @param array &$item
	 * @param array $keys
	 * @param array|int|string $row
	 * @param array $items
	 * @return void
	 */
	private function setDestination(array &$item, array $keys, array|int|string $row, array $items): void {
		$dest = &array_value_ref($item, $keys);
		$dest = is_array($row)
		? array_values(array_intersect_key($items ?: [], array_flip($row)))
		: ($items[$row] ?? null);
	}

	/**
	 * @param array $container
	 * @param array $rk
	 * @param string $sk
	 * @param string $dk
	 * @return array
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
