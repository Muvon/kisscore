<?php
$action_map = App::getJSON(config('common.action_map_file'));
$setups = [
  ['prepend' => '_head_ajax', 'append' => '_foot_ajax'],
  ['prepend' => '_head', 'append' => '_head']
];

foreach ($setups as $setup) {
  foreach (config('common.locale') as $language => $domain) {
    foreach ($action_map as $action => $file) {
      View::create($action)
        ->configure(['source_dir' => config('view.source_dir') . '/' . $language])
        ->prepend($setup['prepend'])
        ->append($setup['append'])
        ->render(true)
      ;
    }
  }
}

