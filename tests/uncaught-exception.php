<?php declare(strict_types=1);

$root = sys_get_temp_dir() . '/kisscore-exception-' . bin2hex(random_bytes(8));
foreach (['app/src', 'env/etc', 'env/log'] as $dir) {
	mkdir($root . '/' . $dir, 0700, true);
}
file_put_contents($root . '/app/start.php', '<?php');
file_put_contents($root . '/env/etc/config.php', '<?php return ["common.cli_level" => 0];');

try {
	$code = 'require ' . var_export(dirname(__DIR__) . '/vendor/autoload.php', true) . ';'
		. 'App::start(["root" => ' . var_export($root, true) . ']);'
		. 'throw new RuntimeException("expected uncaught exception");';
	file_put_contents($root . '/run.php', '<?php ' . $code);
	$Process = proc_open(
		[PHP_BINARY, '-d', 'opcache.enable_cli=0', $root . '/run.php'],
		[1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
		$pipes
	);
	if ($Process === false) {
		throw new RuntimeException('Could not launch exception test');
	}
	$output = stream_get_contents($pipes[1]);
	$errors = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);
	$status = proc_close($Process);
	if ($status !== 1 || !str_contains($output, 'expected uncaught exception')) {
		throw new RuntimeException("Expected exit 1 and exception output; got $status: $output $errors");
	}
	echo 'Uncaught exception: diagnostic preserved and process exits 1' . PHP_EOL;
} finally {
	$files = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($files as $file) {
		if ($file->isDir()) {
			rmdir($file->getPathname());
		} else {
			unlink($file->getPathname());
		}
	}
	rmdir($root);
}
