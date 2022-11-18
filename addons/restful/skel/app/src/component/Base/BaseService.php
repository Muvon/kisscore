<?php declare(strict_types=1);

namespace App\Component\Base;

use Input;
use Request;

abstract class BaseService {
	/**
	 * @param string $id
	 * @param array<string,mixed> $item
	 * @return BaseItem
	 */
	abstract public static function save(string $id, array $item): BaseItem;

	/**
	 * @param string $id
	 * @return BaseItem
	 */
	abstract public static function get(string $id): BaseItem;

	/**
	 * @param string $id
	 * @return BaseItem
	 */
	abstract public static function delete(string $id): BaseItem;

	/**
	 * @return array<static>
	 */
	abstract public static function list(): array;

	/**
	 * This is main entry point that will process the request to the endpoint
	 *
	 * @param string $method
	 *  HTTP method to process
	 * @return array<string,mixed>
	 *  Response that will be returned to the client
	 */
	public static function execute(string $method): array {
		$request = Request::current();
		$content_type = $request->getHeader('content-type');
		if ($method !== 'GET' && !str_starts_with($content_type, 'application/json')) {
			return [BaseError::InvalidHttpContentType, null];
		}

		$id = Input::get('id');
		if ($method === 'POST') {
			$id = uniqid(); // TODO: generate new id here
		}

		if ($method === 'GET' && !$id) {
			$method = 'LIST';
		}

		$fn = match ($method) {
			'GET' => fn ($id) => static::get($id),
			'POST', 'PUT' => fn ($id) => static::save($id, Input::get()),
			'DELETE' => fn ($id) => static::delete($id),
			'LIST' => fn () => static::list(),
			default => null,
		};

		if (!$fn) {
			// TODO: rethink error implementation
			return [BaseError::InvalidHttpMethod, null];
		}

		return [null, (array)$fn($id)];
	}
}
