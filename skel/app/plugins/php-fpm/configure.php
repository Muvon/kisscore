<?php
App::configure(__DIR__);

$cmd = App::exec('which php-fpm');
App::exec('task add "' . $cmd . ' -c $CONFIG_DIR/php.ini -y $CONFIG_DIR/php-fpm.conf -F #php-fpm"');