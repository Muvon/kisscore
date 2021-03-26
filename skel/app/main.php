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

$Response
  ->header('Referrer-Policy', 'origin-when-cross-origin')
  ->header('X-Frame-Options', 'DENY')
  ->header('X-XSS-Protection', '1; mode=block')
  ->header('X-Content-Type-Options', 'nosniff')
  ->header('Content-Security-Policy', "frame-ancestors 'none'")
  ->header('X-Response-Time', intval((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000))
  ->send((string) $View->render())
;
App::stop();
