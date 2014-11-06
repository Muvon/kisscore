<?php
/**
 * Трейт реализуер основые механизмы для постраничных выборок
 */
trait TPagination {
  /*
  * @property int $page
  *   Текущая страница, при включенной постраничной выборки
  * @property int $max_page
  *   Максимальная страница
  * @property int $offset
  *   Начальная позиция при использовании постраничного вывода
  * @property int $limit
  *   Лимит при постраничном выводе
  * @property int $total
  *   Всего записей для текущей инициализации
  */
  protected
    $page     = 1,
    $max_page = 1,
    $offset   = 0,
    $limit    = 0,
    $total    = null;

  /**
   * Всего элементов найдено в списке
   *
   * @param int $total
   * @return $this
   */
  public function setTotal($total) {
    return isset($total) ? $this->initPagination($this->total = $total) : $this;
  }

  /**
   * Лимит отображения в один запрос при постраничной навигации
   *
   * @access public
   * @param int $limit
   * @return $this
   */
  public function setLimit($limit) {
    if ($limit > 0)
      $this->limit = (int) $limit;

    return $this;
  }

  /**
   * Текущая страница при постраничной выборки
   *
   * @access public
   * @param int $page
   * @return $this
   */
  public function setPage($page) {
    if ($page < 1)
      $page = 1;

    $this->page = (int) $page;
    return $this;
  }

  /**
   * Безлимитных выборок не бывает, поэтому любая выборка - это постраничаная
   * Но если не указаны параметры, то выборка составлят одну страницу
   * На максимальное число элементов
   *
   * @access protected
   * @param int $total Всего записей
   * @return $this
   *
   * <code>
   * $result = Model::create('Model')
   *   ->setLimit($limit)
   *   ->setPage($page)
   *   ->getSomething( ) // { $this->initPagination($total); ... }
   * ;
   * </code>
   */
  protected function initPagination($total) {
    // Если лимита не указано, ставим максимальным
    if (!$this->limit)
      $this->limit = (int) $total;

    $this->max_page = $total ? (int) ceil($total / $this->limit) : 1;

    // Если выходит за границы
    if (($this->page * $this->limit) > $total)
      $this->page = $this->max_page;

    $this->total = (int) $total;
    // Определяем начало для выборки данных
    $this->offset = ($this->page - 1) * $this->limit;
    return $this;
  }

  /**
   * Получение постранично-сформированного списка по данным
   *
   * @param array $result
   * @return array
   */
  protected function getPaginatedList(array $result) {
    // Ничего нет? Ну и к черту :D
    if (!$result)
      return [];

    $list['items']    = array_map([$this, 'prepareResult'], array_values($result));
    $list['total']    = $this->total;
    // Добавляем дополнительные данные
    $list['offset']   = $this->offset;
    $list['limit']    = $this->limit;
    $list['page']     = $this->page;
    $list['max_page'] = $this->max_page;
    //$list['has_items']    = !!$list['items'];
    //$list['has_no_items'] = !$list['items'];
    // Постраничная навигация: kick it to view
    $list['pagination'] = Pagination::instance()
      ->set([
        'route'     => Request::instance( )->getRoute(),
        'params'    => Request::instance( )->param(),
        'page_name' => 'p',
        'per_page'  => $list['limit'],
        'total'     => $list['total'],
      ])
      ->getArray();

    return $list;
  }
}