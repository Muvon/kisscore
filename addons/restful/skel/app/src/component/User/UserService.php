<?php declare(strict_types=1);

namespace App\Component\User;

use App\Component\Base\BaseService;

final class UserService extends BaseService {
	/**
	 * @param string $id
	 * @param array<string,mixed> $item
	 * @return UserItem
	 * @see parent::save()
	 */
	public static function save(string $id, array $item): UserItem {
		$item['id'] = $id;
		return UserItem::create($item);
	}

	/**
	 * @return UserItem
	 * @see parent::delete()
	 */
	public static function delete(string $id): UserItem {
		return UserItem::create(['id' => $id]);
	}

	/**
	 * @return UserItem
	 * @see parent::get()
	 */
	public static function get(string $id): UserItem {
		return UserItem::create(['id' => $id]);
	}

	/**
	 * @return array<UserItem>
	 * @see parent::list()
	 */
	public static function list(): array {
		return [
			UserItem::create(['id' => uniqid()]),
			UserItem::create(['id' => uniqid()]),
		];
	}
}
