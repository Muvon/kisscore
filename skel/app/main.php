<?php
include getenv('KISS_CORE');
App::start();
$Request = Request::create();
$Response = Response::create(200);

// Process action and get view template if have
$View = App::process($Request, $Response)
  ->prepend('_head')
  ->append('_foot')
;

$Response->send((string) $View->render());
App::stop();
