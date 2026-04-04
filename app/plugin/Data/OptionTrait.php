<?php declare(strict_types=1);

namespace Plugin\Data;

trait OptionTrait {
	/**
	 * @param int|string|array<int|string> $id
	 * @param array<string,mixed> $cond
	 * @return array<int,array<string,mixed>>
	 */
	public static function getOptionList(int|string|array $id, array $cond = []): array {
		$list = static::getList($cond);

		$ids = is_array($id) ? $id : [$id];

		foreach ($list as &$item) {
			$item['selected'] = in_array($item['id'], $ids);
		}

		return $list;
	}
}
