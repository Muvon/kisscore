<?php declare(strict_types=1);

namespace App\Component\User;

use App\Component\Base\BaseItem;

final class UserItem extends BaseItem {
	public string $id;

	/**
	 * @param array{id:string} $item
	 * @see parent::create()
	 */
	public static function create(array $item): static {
		$self = new static;
		$self->id = $item['id'];
		return $self;
	}
}
