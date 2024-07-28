<?php declare(strict_types=1);

/**
 * @final
 * @package Core
 * @subpackage Item
 *
 * <code>
 * Item::fetcher('Photo', 1, $photo, array(
 *   Item::fetch('Album', 'album_id'),
 *   Item::fetch('User', 'user_id'),
 * ))->dispatch();
 *
 * </code>
 *
 * <code>
 * Item::fetcher('Photo', array(1, 2, 3), $list, array(
 *   Item::fetch('Album'),
 *   Item::fetch('User'),
 * ))->dispatch();
 * </code>
 *
 * <code>
 * Item::fetcher('Photo::specialMethodCall', array('here', 'is', 'parameters'), $result, array(
 *   Item::fetch('User'),
 *   Item::fetch('Album'),
 * ))->dispatch( );
 * </code>
 */

class Item {
	protected int $id = 0;
	/** @var array<Fetcher> */
	protected array $batch = [];
	/** @var array<mixed> */
	protected array $result = [];

  /**
   * Make it private to not call directly with new Item
   */
	private function __construct() {
	}

  /**
   * @param string|array{0:string,1:string} $mapper
   * @param mixed $args
   * @param array &$result
   * @param array $batch
   * @return ItemFetcher::create()
   * @see ItemFetcher::create()
   */
	public static function fetcher(string|array $mapper, mixed $args, array &$result, array $batch = []): Fetcher {
		$result = [];
		return Fetcher::create($mapper, 'id', is_array($args) ? $args : [$args], $result, $batch);
	}

  /**
   * @param string|array{0:string,1:string} $mapper
   * @param string $src_key Если не указано, автогенерация из $mapper
   * @param string $root_key Ключ родителя, из которого берется src_key
   * @return ItemFetcher::create()
   */
	public static function fetch(string|array $mapper, string $src_key = '', string $root_key = ''): Fetcher {
		return Fetcher::create($mapper, $src_key ?: strtolower($mapper) . '_id')->setRootKey($root_key);
	}
}
