<?php
include getenv('KISS_CORE');
App::start(['debug' => substr(getenv('HTTP_HOST'), -3) === '.lo']);
Request::response()->addHeader('Content-type', 'text/html;charset=utf-8');
$View = App::process(Request::instance())
  ->addHead('_head')
  ->addFoot('_foot')
;
Request::response()
  ->setBody((string) $View->render())
  ->send()
;
App::stop();