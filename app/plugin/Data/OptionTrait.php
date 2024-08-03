<?php declare(strict_types=1);

namespace Plugin\Data;

trait OptionTrait {
	/**
	 * @param int|string $id
	 * @param array<string,mixed> $cond
	 * @return array<mixed>
	 */
	public static function getOptionList(int|string $id, array $cond = []): array {
		$list = static::new()->getList($cond);

		$ids = [];
		if (is_array($id)) {
			$ids = $id;
		} else {
			$ids = [$id];
		}


		foreach ($list as &$item) {
			$item['selected'] = in_array($item['id'], $ids);
		}

		return $list;
	}
}
