<?php declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader, which wires up the framework's PSR-4 maps
 * (global core classes from app/core, Plugin\ from app/plugin, …) and the
 * app/core/functions.php helpers. Tests target pure logic and coroutine-local
 * state, so no compiled config or env is required.
 */
require_once __DIR__ . '/../vendor/autoload.php';
