<?php
$start_ts = microtime(true);
include getenv('KISS_CORE');
App::start();
$Request = Request::create();
$Response = Response::create(200);

// Initiazliations
$lang = config('common.lang_type') !== 'none' ? Lang::init($Request) : null;

// Process action and get view template if have
$View = App::process($Request, $Response)
  ->prepend('_head')
  ->append('_foot')
;

// Detect language and add compiler to the View
// Language {
if ($lang) {
  $View->configure([
    'compile_dir' => config('view.compile_dir') . '/' . $lang,
  ])
    ->addCompiler(Lang::getViewCompiler($lang))
    ->assign('LANGUAGE_LIST', Lang::getList($lang))
    ->assign('CURRENT_LANGUAGE', Lang::getInfo($lang))
    ->assign('LANG', $lang)
  ;
}
// } Language

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
