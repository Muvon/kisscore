<?php
include getenv('KISS_CORE');
App::start(['debug' => config('common.dev_host') === getenv('HTTP_HOST')]);

View::instance()
  ->setEscapeMode(View::ESCAPE_HTML)
  ->setDebug(App::$debug)
  ->addHead('_head')
  ->addFoot('_foot')
  ->setTemplateExtension(config('view.template_extension'))
  ->setSourceDir(config('view.source_dir'))
  ->setCompileDir(config('view.compile_dir'))
;

Request::response()->addHeader('Content-type', 'text/html;charset=utf-8');

$file_path = getenv('APP_DIR') . '/actions/' . Request::instance()->getModule() . '.php';

if (is_file($file_path)) { // Исполняем запрос
  // Получаем ответ и выполняем запрос
  $response = include $file_path;
  // Были установлены какие-то сообщения?
  // Ипортируем в пространство отображения
  if (isset($MSGS['errors']))
    extract(array_fill_keys($MSGS['errors'], true));

  if (isset($MSGS['notices']))
    extract(array_fill_keys($MSGS['notices'], true));
} else $response = ret('not_found');

if ($response === 1)
  $response = View::instance();

// Если в ответе объекта вида
if ($response instanceof View) {
  unset($GLOBALS['_GET'], $GLOBALS['_POST'], $GLOBALS['_COOKIE'], $GLOBALS['_SESSION'], $GLOBALS['_FILES'], $GLOBALS['_SERVER']);
  $response->set($GLOBALS)->render();
}

// Обращаемся через метод, чтобы избежать возможной перезаписи переменной в экшене
Request::response()->setBody((string) $response)->send();
App::stop();

// Доп. функции для применения в экшенах
/**
* Отдельные коды для возврата из экшенов
*
* @param string $state
* @return mixed
*/
function ret($state) {
  switch ($state) {
    case 'not_found':
        Request::response()->setStatus(404);
        return View::instance()->setRoute('not_found');
      break;
    case 'denied':
        Request::response()->setStatus(403);
        return View::instance()->setRoute('denied');
      break;
    case 'http403':
      Request::response()->setStatus(403);
      return 1;
      break;
    case 'http404':
      Request::response()->setStatus(404);
      return 1;
      break;
    case 'internal_error':
        Request::response()->setStatus(502);
        return View::instance()->setRoute('internal_error');
      break;
  }
  return null;
}
