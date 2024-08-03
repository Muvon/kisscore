<?php declare(strict_types=1);

namespace Plugin\List;

use Error;

/**
 * @final
 * @package Core
 * @subpackage Pagination
 * @phpstan-type PaginationInfo array{current:int,last:int,next_url?:string,prev_url?:string}
 */
final class Pagination {
	private int $limit = 1000;
	private int $total = 0;
	private int $page = 1;
	private int $offset = 0;

  /**
   * @static
   * @access public
   * @return Pagination
   *
   * <code>
   * $list = Pagination::create([
   *  'page'  => 1,
   *  'limit' => 15,
   *  'total' => 10,
   * ])->listResult($items_from_database);
   * </code>
	 * @param array{page?:int,limit?:int,total?:int,offset?:int} $conf
	 * @return static
   */
	public static function create(array $conf = []): static {
		$Obj = new static;
		foreach ($conf as $k => $v) {
			$Obj->$k = $v;
		}

	// Calculate offset if we pass page
		if (isset($conf['page'])) {
			if ($conf['page'] < 1) {
				$conf['page'] = 1;
			}
			$Obj->offset = ($conf['page'] - 1) * $Obj->getLimit();
		} elseif (isset($conf['offset'])) {
			$Obj->page = (int)ceil($conf['offset'] / $Obj->getLimit());
		} else {
			throw new Error('Pagination: no page or offset provided');
		}


		return $Obj;
	}

  /**
   * Получение текущей страницы
   *
   * @return int
   */
	public function getCurrentPage() {
		$page = $this->page;
		if ($page < 1) {
			$page = 1;
		}
	// Hot fix ffs
		return $page > ($last_page = $this->getLastPage()) ? $last_page : $page;
	}

	/**
	 * @return int
	 */
	public function getMaxPage(): int {
		return $this->getTotal() ? (int)ceil($this->getTotal() / $this->limit) : 1;
	}

	/**
	 * @return int
	 */
	public function getOffset(): int {
		return $this->offset;
	}

	/**
	 * @return int
	 */
	public function getLimit(): int {
		return $this->limit ?: $this->total;
	}

	/**
	 * @param int $total
	 * @return static
	 */
	public function setTotal(int $total): static {
		$this->total = $total;
		return $this;
	}

	/**
	 * @return int
	 */
	public function getTotal(): int {
		return $this->total;
	}

  /**
   * Получение номера последней страницы
   *
   * @return int
   */
	public function getLastPage() {
		return ($this->total && $this->limit)
		? (int)ceil($this->total / $this->limit)
		: 1;
	}

  /**
   * Получение итогового массива страниц для отображения
   *
	 * @return PaginationInfo
   */
	public function getArray(): array {
		$data = [];
		$cur_page   = $this->getCurrentPage();
		$last_page  = $this->getLastPage();

		$data['has_pages']  = $last_page > 1;
		$data['current']    = $cur_page;
		$data['last']       = $last_page;

		$data['prev_page'] = null;
		if ($cur_page > 1) {
			$data['prev_page'] = $cur_page - 1;
		}

		$data['next_page'] = null;
		if ($cur_page !== $last_page) {
			$data['next_page'] = $cur_page + 1;
		}

	// // @TODO: сформировать страницы
	// for ($i = 1; $i <= $last_page; $i++) {
	//   $data['pages'][] = ['page' => $i, 'current' => $i === $cur_page];
	// }
		return $data;
	}

  /**
   * @param array<mixed> $list
   * @return array{}|array{items:array<mixed>,total:int,offset:int,limit:int,pagination:PaginationInfo}
   */
	public function listResult(array $list): array {
		if (!$list) {
			return [];
		}

		return [
			'items'   => array_values($list),
			'total'   => $this->getTotal(),
			'offset'  => $this->getOffset(),
			'limit'   => $this->getLimit(),
			'pagination' => $this->getArray(),
		];
	}
}
