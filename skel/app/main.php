<?php
include getenv('KISS_CORE');
App::start();
$Response = Response::create(200);
$View = App::process(Request::create(), $Response)
  ->prepend('_head')
  ->append('_foot')
;
$Response->send((string) $View->render());
App::stop();
