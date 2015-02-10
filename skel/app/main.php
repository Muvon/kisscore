<?php
include getenv('KISS_CORE');
App::start(['debug' => substr(getenv('HTTP_HOST'), -3) === '.lo']);
$Response = Response::create(200)->header('Content-type', 'text/html;charset=utf-8');
$View = App::process(Request::create(), $Response)
  ->addHead('_head')
  ->addFoot('_foot')
;
$Response->send((string) $View->render());
App::stop();