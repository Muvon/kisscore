<?php
$start_ts = microtime(true);
include getenv('KISS_CORE');
App::start();
$Response = Response::create(200);
$View = App::process(Request::create(), $Response)
  ->prepend('_head')
  ->append('_foot')
;
$Response
  ->header('Referrer-Policy', 'origin-when-cross-origin')
  ->header('X-Frame-Options', 'DENY')
  ->header('X-XSS-Protection', '1; mode=block')
  ->header('X-Content-Type-Options', 'nosniff')
  ->header('Content-Security-Policy', "frame-ancestors 'none'")
  ->header('X-Response-Time', bcmul(microtime(true) - $start_ts, 1000, 0))
  ->send((string) $View->render())
;
App::stop();
