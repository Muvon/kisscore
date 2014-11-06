<?php
/**
* Класс реализации методов для кэширования объектов в памяти
* Представляет из себя wrapper класса Memcached
*
* @uses \Memcached
* @link http://www.php.net/manual/en/class.memcache.php
*
* @final
* @package Core
* @subpackage Cache
*/
class Cache {
  /**
   * @property array $instances Пул коннектов
   * @property string $host
   * @property int $port
   * @property bool $persistent
   * @property object $Memcache ссылка на объект для работы с Memcache
   * @property bool $is_connected флаг состояния подключения с сервисом кэширования
   * @property array $stack локальный стек для хранения получаемых данных из кэша
   */
  protected static $instances = [];
  protected
  $host          = '',
  $port          = '',
  $persistent    = true,
  $Memcache      = null,
  $is_connected  = false,
  $stack         = [];

  /**
   * Инициализация настроек для последующего соединения
   *
   * @param string $host хост подключения
   * @param int $port порт подключения
   * @param bool $persistent флаг постоянно подключения
   */
  public function __construct($host = 'localhost', $port = 11211, $persistent = false) {
    $this->host       = $host;
    $this->port       = $port;
    $this->persistent = $persistent;
  }

  /**
   * Функция для получения едиснтвенного соединения по переданным параметрам
   *
   * @see self::__construct()
   *
   * @static
   * @access public
   * @param string $host хост подключения
   * @param int $port порт подключения
   * @param bool $is_persistent флаг постоянно подключения
   * @return Cache
   *
   * <code>
   * // Коннект будет установлен при первом запросе query|execute
   * $Db = Database::getConnection('localhost', 'database');
   * // Только тут начинается коннект
   * $Db->execute(...);
   * </code>
   */
  public static function getConnection($host = 'localhost', $port = 11211, $persistent = false) {
    $Instance = &self::$instances[$host . ':' . $port];
    if (!$Instance) {
      $Instance = new self($host, $port, $persistent);
    }
    return $Instance;
  }
  
  /**
   * Подключение к серверну мемкэша
   */
  public function connect( ) {
    $func = $this->persistent
      ? 'pconnect'
      : 'connect';

    $this->Memcache = new Memcached;
    if (!$this->Memcache->addServer($this->host, $this->port)) {
      throw new HttpException('Ошибка при попытке подключения к серверу кэша в оперативной памяти.', 500);
    }
    $this->is_connected = true;
  }
  
  /**
   * Получение данных из кэша по ключу
   *
   * @param mixed $key
   * @return mixed кэшированное данное
   */
  public function get($key) {
    $items = $this->doCommand(is_string($key) ? 'get' : 'getMulti', [$key]);

    // Если массив, то нужно выполнить преобразования для возвращаемых данных
    if (is_array($key)) {
      // Если возникла ошибка или же просто нет данных, то возвращаем массив
      // Т.к. было запрошен кэш по нескольким ключам
      if (!$items) {
        $items = [];
      } else {
        $map = array_flip($key); unset($key);
        //$result = new SplFixedArray(sizeof($items));
        foreach ($items as $k => $item) {
          $result[$map[$k]] = $item;
        }
        unset($items);
        $items = &$result;
      }
    }

    return $items;
  }
  
  /**
   * Установка данные для ключа, перезапись в случае нахождения
   *
   * @param mixed $key Массив или строка
   * @param mixed $val
   * @param int $ttl
   * @return mixed Булевый тип или же массив с булевыми значениями для всех ключей
   */
  public function set($key, $val, $ttl = 0) {
    assert("is_string(\$key) || is_array(\$key)");
    assert("is_int(\$ttl)");

    $ret = false;
    // Если нужно выполнить multiset
    if (is_array($key)) {
      $args = func_get_args();
      $ttl = $args[1];
      $ret = [];
      foreach ($args as $key => $val) {
        $ret[] = $this->doCommand('setMulti', [
          $key,
          $val // Выступает в качестве $ttl
        ]);
      }
    } else {
      $ret = $this->doCommand('set', [
        $key,
        $val,
        $ttl
      ]);
    }
    return $ret;
  }
  
  /**
   * Добавление данных в кэш, если их там нет
   *
   * @param string $key
   * @param mixed $val данные для добавления в кэш
   *  @param int $ttl время жизни кэшируемого объекта
   * @return bool
   */
  public function add($key, $val, $ttl = 0) {
    return $this->doCommand('add', [
      $key,
      $val,
      $ttl
    ]);
  }
  
  /**
  * Добавление какого-то текста к данному в конец строки
  *
  * @param string $key
  * @param string $val
  * @return bool
  */
  function append($key, $val) {
    //return $this->Memcache->append($key, $val);
    return $this->doCommand('append', [$key, $val]);
  }
  
  /**
   * Добавление какого-то текста к данному в начало строки
   *
   * @param string $key
   * @param string $val
   * @return bool
   */
  function prepend($key, $val) {
    return $this->doCommand('prepend', [$key, $val]);
  }
  
  /**
   * Удаление данного по ключу из кэша
   *
   * @param string $key
   * @return bool
   */
  public function delete($key) {
    return $this->doCommand('delete', [$key]);
  }
  
  /**
   * Алиас для функции удаления
   *
   * @see self::delete()
   */
  public function remove($key) {
    return $this->delete($key);
  }
  
  /**
   * Увеличения счетчика на n число раз
   * Если ключа нет, он создается
   *
   * @param string $key
   * @param int $count количество, на которое необходимо увеличить счетчик
   * @return mixed Новое значение с учетом увеличения или FALSE
   */
  public function increment($key, $count = 1) {
    if (false === $result = $this->doCommand('increment', [$key, $count])) {
      $this->set($key, $count);
      return $count;
    }
    return $result;
  }
  
  /**
   * Уменьшение счетчика на n число раз
   *
   * @see self::increment()
   */
  public function decrement($key, $count = 1) {
    return $this->increment($key, -$count);
  }
  
  /**
   * Выполнение комманды к серверу
   *
   * @param string $command
   * @param array $data
   * @return mixed
   */
  private function doCommand($command, array $data = []) {
    // Установлен ли коннект
    if (!$this->is_connected)
      $this->connect( );

    return call_user_func_array([$this->Memcache, $command], $data);
  }
  
  /**
   * Очистка всего пула кэша
   * @return bool
   */
  public function flush( ) {
    return $this->doCommand('flush');
  }
  
  /**
   * Закрытие соединение с кэширующим механизмом
   *
   * @return void
   */
  public function __destruct( ) {
    if ($this->is_connected) {
      $this->is_connected = false;
      unset($this->Memcache);
    }
  }
}