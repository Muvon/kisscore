<?php
/**
 * Абстрактный класс с базовыми возможностями для объекта управления элементами
 *
 * @abstract
 * @package Core
 * @subpackage ItemManager
 */
abstract class ItemManager {
  /**
    * @property string $model
    * @property string $method
    * @property array $calls
    * @property array $states Состояния моделей, получаемых данные
    * @property array $ids
    * @property array $batch Список задач для выполнения, после выполнений текущей (последовательно)
    * @property array $data Данные, которые используются, или куда пишется результат
    */
  protected
  $model    = '',
  $method   = '',
  $calls    = [],
  $states   = [],
  $ids      = [],
  $batch    = [],
  $data     = null;

  /**
   * Финальный конструктор
   */
  final protected function __construct() {}

  /**
   * Добавление вызова к объекту главного fetcher'а перед получением данных
   *
   * @param string $method
   * @param mixed $args аргументы для передачи
   * @return $this
   */
  public function call($method, $args) {
    $this->calls[] = [$method, is_array($args) ? $args : [$args]];
    return $this;
  }
  

  /**
   * Проверка состояния последних выполненных операций всех моделей
   *
   * @return bool
   */
  public function isOk() {
    static $is_ok = null;
    if (!$is_ok) {
      $is_ok = true;
      foreach ($this->states as $state) {
        $is_ok = $is_ok && $state;
      }
    }
    return $is_ok;
  }
}
/**
 * Загрузчик сущностей, доступ через объект Entity
 *
 * @final
 * @package Core
 * @subpackage ItemFetcher
 */
class ItemFetcher extends ItemManager {
  /**
   * @property string $src_key
   * @property string $root_key
   *   Корневой ключ, откуда идет выборка $src_key (должен представлять собой массив)
   * @property string $dst_key
   */
  protected
  $src_key  = '',
  $root_key = '',
  $dst_key  = '';
  
  /**
   * Создание загрузчика данных и постановка первого задания
   *
   * @access public
   * @static
   * @param string $mapper
   *   Имя маппера, которые обязауется подгружать данные
   * @param string $src_key
   *   Индекс идентификатора
   * @param mixed $args
   *   Массив или строка/число - однозначный идентификатор
   * @param array $data
   *   Массив с результатами (если была уже агрегированная выборка)
   * @param array $batch
   *   Массив оппераций, которые будут выполнены в параллели
   * @return ItemFetcher
   */
  public static function create($mapper, $src_key, array $args = null, array &$data = [], array $batch = []) {
    $Self = new self;
    if (false !== strpos($mapper, '::')) {
      list($model, $method) = explode('::', $mapper);
    } else {
      $model  = $mapper;
      $method = 'get';
    }
    
    $Self->model  = $model;
    $Self->method = $method;
    
    $Self->src_key    = $src_key;
    $Self->dst_key    = $Self->getDstKey($src_key);
    $Self->args       = $args;
    $Self->batch      = $batch;
    $Self->data       = &$data;
    return $Self;
  }

  /**
   * Установка корневого ключа, откуда будет браться src_key
   *
   * @param string $root_key
   * @return $this
   */
  public function setRootKey($root_key) {
    $this->root_key = $root_key;
    return $this;
  }
  
  /**
   * Получение ключа назначения (куда будут слиты данные)
   * Обычно передается ключ вида user_id, соответсвенно ключ будет user
   * Допускается передача с явным указанием клча назначения user_id:user (запишет в user)
   *
   * @access protected
   * @param string $key
   * @return string
   */
  protected function getDstKey($key) {
    if (false !== strpos($key, ':'))
      return explode(':', $key)[1];

    return substr($key, 0, strrpos($key, '_'));
  }
  
  /**
   * Инициализцаия постраничной выборки
   *
   * @access public
   * @param int $page
   * @param int $limit
   * @param int $total
   * @return $this
   */
  public function paginate($page, $limit, $total = null) {
    return $this
      ->call('setLimit',  $limit)
      ->call('setPage',   $page)
      ->call('setTotal', $total);
  }
  
  /**
   * Выполнение в последовательном режиме
   *
   * @access public
   * @return $this
   */
  public function dispatch() {
    if (!$this->data) { // Если данных не было передано, подгружаем
      $Obj = Model::create($this->model);
      
      // Если необходимы специфичные вызовы
      if ($this->calls) {
        foreach ($this->calls as $call) {
          call_user_func_array([$Obj, $call[0]], $call[1]);
        }
        unset($this->calls);
      }
      
      // Хапаем основные данные
      $this->data = call_user_func_array(
        [$Obj, $this->method],
        $this->args
      );

      // Состояние операции текущей модели
      $this->states[0] = $Obj->isOk();
      unset($Obj);
    }

    // Если нужно выполнить действия, зависимые от первого
    if ($this->data && $this->batch) {
      $prev = null;
      foreach ($this->batch as $Fetcher) {
        // Подразумевает обход и доступ многократный к свойствам, поэтому читерим ненмого
        $dk = $Fetcher->dst_key;
        $sk = $Fetcher->src_key;
        $rk = $Fetcher->root_key ? explode('.', $Fetcher->root_key) : [];

        $Obj = Model::create($Fetcher->model);

        // Возвращаемые данные списков могут быть просто данными
        // или же специальными постраничными списками, тогда данные находятся
        // в индексе items
        $is_list = false;
        if (isset($this->data['items']) && is_array($this->data['items'])) {
          $data = &$this->data['items'];
          $is_list = true;
        } else {
          $data = &$this->data;
        }

        // Если есть рутовый ключ
        if ($prev && $rk) {
          foreach ($rk as $key) {
            if (array_key_exists($key ,$data)) {
              $data = &$data[$key];
            }
          }
        }

        if (isset($data[0]))
          $is_list = true;

        // Массив выборки или один элемент
        if ($is_list) {
          $array = $data;
          if ($rk) {
            foreach ($rk as $key) {
              $array = array_values_by_key($array, $key);
            }
          }

          $ids = array_values_by_key($array, $sk);
          unset($array);

          if (isset($ids[0]) && is_array($ids[0]))
            $ids = call_user_func_array('array_merge', $ids);

          $items = $Obj->get($ids);
          foreach ($data as &$item) {

            if ($rk) {
              eval('$row = &$item[\'' . implode('\'][\'', $rk) . '\'][\'' . $sk . '\'];');

            } else {
              $row = $item[$sk];
            }
            //$row  = $rk ? $item[$rk][$sk] : $item[$sk];
            
            if ($rk) {
              eval('$dest = &$item[\'' . implode('\'][\'', $rk) . '\'][\'' . $dk . '\'];');
              //$dest = &$item[$rk][$dk];
            } else {
              $dest = &$item[$dk];
            }
            
            $dest = is_array($row)
                  ? array_values(array_intersect_key($items ? $items : [], array_flip($row)))
                  : (isset($items[$row]) ? $items[$row] : null);
            
            //$item['has_' . $dk]   = !!$item[$dk];
            //$item['has_no_' . $dk]= !$item[$dk];
          }
        } else {
          $this->data[$dk] = $Obj->get($this->data[$sk]);
        }
        $this->states[] = $Obj->isOk( );
        unset($Obj);
        $prev = $sk;
      }

    }
    return $this;
  }
}