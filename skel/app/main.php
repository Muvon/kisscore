<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
App::start(['root' => dirname(__DIR__)]);

$port = (int)config('server.port');
$Server = new Swoole\HTTP\Server("0.0.0.0", $port, SWOOLE_BASE);

$cpu_num = swoole_cpu_num();
$Server->set([
	// Process
	'daemonize' => 0,

	// Server
	'reactor_num' => $cpu_num * 4,
	'worker_num' => (int)($cpu_num / 2),
	'dispatch_mode' => 3, // 1 for async and 3 for blocking (for stateless)

	// Worker
	'max_request' => 0,
	'discard_timeout_request' => 20,

	// TCP
	'input_buffer_size' => 2097152,
	'buffer_output_size' => 32 * 1024*1024, // byte in unit
	'tcp_fastopen' => true,
	'max_conn' => 8192,
	'tcp_defer_accept' => 3,
	'open_tcp_keepalive' => true,
	'open_tcp_nodelay' => true,
	'socket_buffer_size' => 128 * 1024*1024,

	// Kernel
	'backlog' => 8192,

	// TCP Parser
	'package_max_length' => 8192,

	// Coroutine
	'enable_coroutine' => true,

	// tcp server
	'enable_reuse_port' => true,

	// Protocol
	'open_http_protocol' => true,
	'open_http2_protocol' => false,
	'open_websocket_protocol' => false,
	'open_mqtt_protocol' => false,

	// Static Files
	'document_root' => getenv('STATIC_DIR'),
	'enable_static_handler' => true,

	// Source File Reloading
	'reload_async' => false,
	'max_wait_time' => 5,

	// HTTP Server
	'http_parse_post' => true,
	'http_parse_cookie' => true,
	'upload_tmp_dir' => '/tmp',

	// Compression
	'http_compression' => true,
	'http_compression_level' => 5, // 1 - 9
	'compression_min_length' => 20,
]);

$Server->on('request', function (Swoole\Http\Request $Request, Swoole\Http\Response $Response) {
	try {
		// Reset per-request state
		Response::current(true);

		Input::setParser(function() use ($Request) {
			if (Input::isJson()) {
				$params = (array) $Request->get + (array) json_decode($Request->getContent() ?: '{}', true);
			} elseif (Input::isMsgpack()) {
				$params = (array) $Request->get + (array) msgpack_unpack($Request->getContent());
			} else {
				$params = (array) $Request->get + (array) $Request->post;
			};
			return $params;
		});

		Cookie::setParser(fn() => $Request->cookie);

		Request::current(function() use ($Request) {
			Request::$time = $Request->server['request_time'];
			Request::$time_float = $Request->server['request_time_float'];

			Request::$protocol = $Request->server['server_protocol'];
			Request::$headers = (array)$Request->header;
			Request::$host = $Request->header['host'] ?? '';

			Request::$is_ajax = !!($Request->header['x-requested-with'] ?? false);
			Request::$referer = $Request->header['referer'] ?? '';
			Request::$xff = $Request->header['x-forwarded-for'] ?? '';

			Request::$method = $Request->server['request_method'];
			Request::$user_agent = $Request->header['user-agent'] ?? '';
			Request::$ip = $Request->server['remote_addr'];

			Request::$request_uri = $Request->server['request_uri'];
			Request::$content_type = $Request->header['content-type'] ?? '';
		});

		$response = App::process();

		$Resp = Response::current();
		$Resp->sendHeaders(
			$Response->header(...),
			fn($name, $value, $options) =>
				$Response->cookie($name, $value, ...$options)
		);
		$Response->status($Resp->getStatus());
	} catch (Throwable $T) {
		App::logException($T);
		Response::current()
			->status(400)
			->header('Content-type', 'application/json; charset=utf-8');
		Response::current()->sendHeaders(
			$Response->header(...),
			fn($name, $value, $options) =>
				$Response->cookie($name, $value, ...$options)
		);
		$Response->status(Response::current()->getStatus());
		$response = (string)json_encode([
			$T instanceof ResultError ? $T->getMessage() : 'e_error',
			App::$debug ? $T->getMessage() . PHP_EOL . $T->getTraceAsString() : null,
		]);
	}

	$Response->end($response);
});

// This solves issue with worker exit timeout ERRNO 9012
// @see https://bytepursuits.com/swoole-solve-warning-worker_reactor_try_to_exit-errno-9012-worker-exit-timeout-forced-termination
$Server->on('workerExit', static function (Swoole\Server $Server, int $worker_id) {
	Swoole\Timer::clearAll();
	Swoole\Event::exit();
});

$Server->start();
App::stop();
