<?php
/**
 * Класс для работы с системой постраничной навигации
 *
 * @final
 * @package Core
 * @subpackage Pagination
 *
 * <code>
 * Pagination::instance( )
 *   ->setPerPage(10)
 *   ->setTotal(100)
 *   ->setPageName('page')
 *   ->setRoute('user/list')
 *   ->setParams(['filter' => 1])
 *   ->getArray( )
 * ;
 * </code>
 */
class Pagination {
  /**
   * @property int $per_page Количество элементов на страницу
   * @property int $total Количество всех элементов в списке
   * @property string $page_name Имя переменной параметра запроса, содержащего страницу
   * @property string $route Роут для формирования урл листания страниц
   * @property string $params Параметры для создания урл
   */
  private
  $per_page     = 15,
  $total        = 15,
  $page_name    = 'p',
  $default_page = 1,
  $route        = '',
  $params       = [];
  
  private static $Instance = null;

  /**
   * @static
   * @access public
   * @return Pagination
   */
  public static function instance( ) {
    if (!self::$Instance) {
      self::$Instance = new self;
    }
    return self::$Instance;
  }
  
  /**
   * Установка необходимых данных для генерации
   *
   * @param array $conf
   *   [per_page, params, total, page_name, default_page, route]
   * @return $this
   */
  public function set(array $conf = []) {
    foreach ($conf as $k => $v) {
      $this->$k = $v;
    }
    return $this;
  }

  /**
   * Получение установленного роута
   *
   * @return string
   */
  public function getRoute( ) {
    return $this->route;
  }
  
  /**
   * Получение текущей страницы
   *
   * @return int
   */
  public function getCurrentPage( ) {
    $page = (int) Request::instance( )
      ->param($this->page_name, $this->default_page)
    ;
    if ($page < 1) {
      $page = 1;
    }
    return $page > ($last_page = $this->getLastPage( )) ? $last_page : $page;
  }
  
  /**
   * Получение номера последней страницы
   *
   * @return int
   */
  public function getLastPage( ) {
    return ($this->total && $this->per_page)
      ? (int) ceil($this->total / $this->per_page)
      : 1;
  }

  /**
   * Получение итогового массива страниц для отображения
   *
   * @return array [param_name, current, last, next_url, prev_url, pages[[page, url]]
   */
  public function getArray( ) {
    $data = [];
    // Если не установлен роут, используем текущий
    if (!$route = $this->getRoute( )) {
      $route = Request::instance( )->getRoute( );
    }
    $cur_page   = $this->getCurrentPage( );
    $last_page  = $this->getLastPage( );

    $data['has_pages']  = $last_page > 1;
    $data['param_name'] = $this->page_name;
    $data['current']    = $cur_page;
    $data['last']       = $last_page;

    // Some magic :( Till I create somethink for good mapping without any load on php
    $url    = parse_url(Request::instance()->getUrl());
    $path   = isset($url['path']) ? $url['path'] : '/';
    $params = [];
    if (isset($url['query']))
      parse_str($url['query'], $params);

    $url = function ($page) use($path, $params) {
      $params[$this->page_name] = $page;
      return $path . '?' . http_build_query($params);
    };
    // Magic stopped ;P
    
    $data['prev_url'] = '';
    if ($cur_page > 1) {
      //$data['prev_url'] = $url($cur_page - 1);

      $data['prev_url'] = $url($cur_page - 1);
    }

    $data['next_url'] = '';
    if ($cur_page !== $last_page) {
      //$data['next_url'] = $url($cur_page + 1);

      $data['next_url'] = $url($cur_page + 1);
    }

    // @TODO: сформировать страницы
    for ($i = 1; $i <= $last_page; $i++) {
      $data['pages'][] = ['page' => $i, 'url' => $url($i), 'current' => $i === $cur_page];
    }
    return $data;
  }

}