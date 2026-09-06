<?php declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Loads the Composer autoloader, which wires up the framework's PSR-4 maps
 * (global core classes from app/core, Plugin\ from app/plugin, …) and the
 * app/core/functions.php helpers.
 *
 * CONFIG_DIR is set HERE, not per-suite. `config()` memoizes on its first call
 * and caches even a failed load, so a suite that reads config before the
 * variable is set poisons every later lookup in the process — which is exactly
 * what happened when id generation (which reads `common.epoch`) started being
 * exercised for real and silently broke the router suite that runs after it.
 * One fixture, set before any test, removes the ordering dependency.
 */
putenv('CONFIG_DIR=' . __DIR__ . '/fixtures/router');

require_once __DIR__ . '/../vendor/autoload.php';
