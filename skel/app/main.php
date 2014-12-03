<?php
include getenv('KISS_CORE');
App::start(['debug' => substr(getenv('HTTP_HOST'), -3) === '.lo']);

View::instance()
  ->setDebug(App::$debug)
  ->addHead('_head')
  ->addFoot('_foot')
  ->setType(View::HTML)
  ->setTemplateExtension(config('view.template_extension'))
  ->setSourceDir(config('view.source_dir'))
  ->setCompileDir(config('view.compile_dir'))
;

Request::response()->addHeader('Content-type', 'text/html;charset=utf-8');

$file_path = getenv('APP_DIR') . '/actions/' . strtok(Request::instance()->getRoute(), '/') . '.php';
if (is_file($file_path)) {
  $response = include $file_path;
} else {
  Request::response()->setStatus(404);
  $response = 'Action not found';
}

if ($response === 1) {
  $response = View::instance();
}

// Если в ответе объекта вида
if ($response instanceof View) {
  $vars = App::getDefinedVars();
  
  array_walk_recursive($vars, function ($str) {
    if ($str instanceof Closure)
      return $str();

    if (is_string($str)) {
      switch  (View::instance()->getType()) {
        case View::HTML:
          $str = htmlspecialchars($str);
          break;

        case View::XML:
          $str = htmlentities($str);
          break;
      }
    }
    
    return $str;
  });  
  $response->set($vars)->render();
}

// Обращаемся через метод, чтобы избежать возможной перезаписи переменной в экшене
Request::response()->setBody((string) $response)->send();
App::stop();