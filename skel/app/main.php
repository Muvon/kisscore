<?php
include getenv('KISS_CORE');
App::start(['debug' => substr(getenv('HTTP_HOST'), -3) === '.lo']);

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

$file_path = getenv('APP_DIR') . '/actions/' . strtok(Request::instance()->getRoute(), '/') . '.php';
if (is_file($file_path)) {
  $response = include $file_path;
} else $response = ret('not_found');

if ($response === 1) {
  $response = View::instance();
}

// Если в ответе объекта вида
if ($response instanceof View) {
  unset($GLOBALS['_GET'], $GLOBALS['_POST'], $GLOBALS['_COOKIE'], $GLOBALS['_SESSION'], $GLOBALS['_FILES'], $GLOBALS['_SERVER']);
  $response->set($GLOBALS)->render();
}

// Обращаемся через метод, чтобы избежать возможной перезаписи переменной в экшене
Request::response()->setBody((string) $response)->send();
App::stop();