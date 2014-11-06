<?php
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
  protected
  $id     = 0,
  $batch  = [],
  $result = [];
  
  /**
   * Финальный конструктор
   */
  final private function _construct() {}
  
  /**
   * Создание главного загрузчика, после которого выполняются все дополнительные, инициализация
   *
   * @param string $mapper
   * @param mixed $args
   * @param null &$result
   * @param array $batch
   * @return ItemFetcher::create()
   * @see ItemFetcher::create()
   */
  public static function fetcher($mapper, $args, &$result, array $batch = []) {
    $result = [];
    return ItemFetcher::create($mapper, 'id', is_array($args) ? $args : [$args], $result, $batch);
  }
  
  /**
   * Поставнока подзагрузки в очередь, данные зависимы от главного загрузчика
   *
   * @param string $mapper
   * @param string|null $src_key Если не указано, автогенерация из $mapper
   * @param string|null $root_key Ключ родителя, из которого берется src_key
   * @return ItemFetcher::create()
   */
  public static function fetch($mapper, $src_key = null, $root_key = null) {
    return ItemFetcher::create($mapper, $src_key ? $src_key : strtolower($mapper) . '_id')->setRootKey($root_key);
  }
}