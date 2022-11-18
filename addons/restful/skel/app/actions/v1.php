<?php declare(strict_types=1);

/**
 * @route v1/(user)(/([a-z0-9]+))?: ns, _, id
 * @var string $ns
 * @var string $id
 */

$component = ucfirst($ns);
$class_name = "App\\Component\\{$component}\\{$component}Service";
return $class_name::execute(Request::$method);
