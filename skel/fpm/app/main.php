<?php declare(strict_types=1);
App::start();

// Process action and get view template if have
$View = App::process()
  ->prepend('_head')
  ->append('_foot');

Response::current()->send((string)$View->render());
App::stop();
