<?php
include getenv('KISS_CORE');
App::start();

$server = new Swoole\Server("0.0.0.0", config('server.port'), SWOOLE_BASE);

$server->set([
  // Process
  'daemonize' => 0,
  // 'user' => 'www-data',
  // 'group' => 'www-data',
  // 'chroot' => '/data/server/',
  // 'open_cpu_affinity' => true,
  // 'cpu_affinity_ignore' => [0, 1],
  // 'pid_file' => __DIR__.'/server.pid',

  // Server
  'reactor_num' => (int)(swoole_cpu_num() * 1.4), // No more than cpu * 2
  'worker_num' => (int)(swoole_cpu_num() * 2.5), // Can stick to cpu * 2 or cpu * 4
  // 'message_queue_key' => 'mq1',
  'dispatch_mode' => 1, // 1 for async and 3 for blocking (for stateless)
  // 'discard_timeout_request' => true,
  // 'dispatch_func' => 'my_dispatch_function',

  // Worker
  'max_request' => 1024,
  'max_request_grace' => 512, // max_request / 2

  // HTTP Server max execution time, since v4.8.0
  'max_request_execution_time' => 15, // 30s

  // Task worker
  // 'task_ipc_mode' => 1,
  // 'task_max_request' => 100,
  // 'task_tmpdir' => '/tmp',
  // 'task_worker_num' => 8,
  // 'task_enable_coroutine' => true,
  // 'task_use_object' => true,

  // Logging
  // 'log_level' => 1,
  // 'log_file' => '/data/swoole.log',
  // 'log_rotation' => SWOOLE_LOG_ROTATION_DAILY,
  // 'log_date_format' => '%Y-%m-%d %H:%M:%S',
  // 'log_date_with_microseconds' => false,
  // 'request_slowlog_file' => false,

  // Enable trace logs
  // 'trace_flags' => SWOOLE_TRACE_ALL,

  // TCP
  // 'input_buffer_size' => 2097152,
  // 'buffer_output_size' => 32 * 1024*1024, // byte in unit
  // 'tcp_fastopen' => true,
  // 'max_conn' => 1000,
  // 'tcp_defer_accept' => 5,
  // 'open_tcp_keepalive' => true,
  // 'open_tcp_nodelay' => true,
  // 'pipe_buffer_size' => 32 * 1024*1024,
  // 'socket_buffer_size' => 128 * 1024*1024,

  // Kernel
  'backlog' => 4096,
  // 'kernel_socket_send_buffer_size' => 65535,
  // 'kernel_socket_recv_buffer_size' => 65535,

  // TCP Parser
  // 'open_eof_check' => true,
  // 'open_eof_split' => true,
  // 'package_eof' => '\r\n',
  // 'open_length_check' => true,
  // 'package_length_type' => 'N',
  // 'package_body_offset' => 8,
  // 'package_length_offset' => 8,
  // 'package_max_length' => 2 * 1024 * 1024, // 2MB
  // 'package_length_func' => 'my_package_length_func',

  // Coroutine
  'enable_coroutine' => true,
  // 'max_coroutine' => 3000,
  // 'send_yield' => false,

  // tcp server
  // 'heartbeat_idle_time' => 600,
  // 'heartbeat_check_interval' => 60,
  // 'enable_delay_receive' => true,
  // 'enable_reuse_port' => true,
  // 'enable_unsafe_event' => true,

  // Protocol
  'open_http_protocol' => true,
  'open_http2_protocol' => false,
  'open_websocket_protocol' => false,
  'open_mqtt_protocol' => false,

  // HTTP2
  // 'http2_header_table_size' => 4095,
  // 'http2_initial_window_size' => 65534,
  // 'http2_max_concurrent_streams' => 1281,
  // 'http2_max_frame_size' => 16383,
  // 'http2_max_header_list_size' => 4095,

  // SSL
  // 'ssl_cert_file' => __DIR__ . '/config/ssl.cert',
  // 'ssl_key_file' => __DIR__ . '/config/ssl.key',
  // 'ssl_ciphers' => 'ALL:!ADH:!EXPORT56:RC4+RSA:+HIGH:+MEDIUM:+LOW:+SSLv2:+EXP',
  // 'ssl_method' => SWOOLE_SSLv3_CLIENT_METHOD, // removed from v4.5.4
  // 'ssl_protocols' => 0, // added from v4.5.4
  // 'ssl_verify_peer' => false,
  // 'ssl_sni_certs' => [
  //     "cs.php.net" => [
  //         'ssl_cert_file' => __DIR__ . "/config/sni_server_cs_cert.pem",
  //         'ssl_key_file' => __DIR__ . "/config/sni_server_cs_key.pem"
  //     ],
  //     "uk.php.net" => [
  //         'ssl_cert_file' => __DIR__ . "/config/sni_server_uk_cert.pem",
  //         'ssl_key_file' => __DIR__ . "/config/sni_server_uk_key.pem"
  //     ],
  //     "us.php.net" => [
  //         'ssl_cert_file' => __DIR__ . "/config/sni_server_us_cert.pem",
  //         'ssl_key_file' =>  __DIR__ . "/config/sni_server_us_key.pem",
  //     ],
  // ],

  // Static Files
  'document_root' => getenv('STATIC_DIR'),
  'enable_static_handler' => true,
  // 'static_handler_locations' => ['/static', '/app/images'],
  // 'http_index_files' => ['index.html', 'index.txt'],

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


  // Websocket
  // 'websocket_compression' => true,
  // 'open_websocket_close_frame' => false,
  // 'open_websocket_ping_frame' => false, // added from v4.5.4
  // 'open_websocket_pong_frame' => false, // added from v4.5.4

  // TCP User Timeout
  // 'tcp_user_timeout' => 0,

  // DNS Server
  // 'dns_server' => '8.8.8.8:53',
  // 'dns_cache_refresh_time' => 60,
  // 'enable_preemptive_scheduler' => 0,

  // 'open_fastcgi_protocol' => 0,
  // 'open_redis_protocol' => 0,

  // 'stats_file' => './stats_file.txt', // removed from v4.9.0

  // 'enable_object' => true,
]);


$server->on('connect', function ($server, $fd) {
  Cli::print("New connection established: #{$fd}.", Cli::LEVEL_DEBUG);
});

$server->on('receive', function(Swoole\Server $server, int $fd, int $reactorId, string $data) {
  $server->send($fd, "Echo to #{$fd}: \n".$data);
  $server->close($fd);
});

$server->on('request', function (Swoole\Http\Request $Request, Swoole\Http\Response $Response) {
  // TODO: Find proper way to organize fpm + swoole support
  // But till that time this is just fast migration from FPM to not break most things
  Input::setParser(function() use ($Request) {
    if (Input::isJson()) {
      Input::$params = (array) $Request->get + (array) json_decode(file_get_contents('php://input'), true);
    } elseif (Input::isMsgpack()) {
      Input::$params = (array) $Request->get + (array) msgpack_unpack(file_get_contents('php://input'));
    } else {
      Input::$params = (array) $Request->get + (array) $Request->post;
    };
  });

  Cookie::setParser(fn() => $Request->cookie);

  Request::current(function() use ($Request) {
    Request::$time = $Request->server['request_time'];
    Request::$time_float = $Request->server['request_time_float'];

    Request::$protocol = $Request->server['server_protocol'];

    Request::$is_ajax = !!($Request->header['x-requested-with'] ?? false);
    Request::$referer = $Request->header['referer'] ?? '';
    Request::$xff = $Request->header['x-forwarded-for'] ?? '';

    // Эти переменные всегда определены в HTTP-запросе
    Request::$method = $Request->server['request_method'];
    Request::$user_agent = $Request->header['user-agent'] ?? '';
    Request::$ip = $Request->server['remote_addr'];

    Request::$request_uri = $Request->server['request_uri'];
    Request::$content_type = $Request->header['content-type'] ?? '';

    Request::$accept_lang = $Request->header['accept-language'] ?? '';
  });

  // Process action and get view template if have
  $View = App::process()
    ->prepend('_head')
    ->append('_foot')
  ;

  Response::current()->sendHeaders($Response->header(...), fn($name, $value, $options) => $Response->cookie(
    key: $name,
    value: $value,
    expire: $options['expires'],
    path: $options['path'],
    domain: $options['domain'],
    secure: $options['secure'],
    httponly: $options['httponly']
  ));
  $Response->end((string) $View->render());
});

// This solves issue with worker exit timeout ERRNO 9012
// @see https://bytepursuits.com/swoole-solve-warning-worker_reactor_try_to_exit-errno-9012-worker-exit-timeout-forced-termination
$server->on('workerExit', function (Swoole\Server $server, int $worker_id) {
  \Swoole\Timer::clearAll();
  \Swoole\Event::exit();
});

$server->on('close', function ($server, $fd) {
  Cli::print("Connection closed: #{$fd}.", Cli::LEVEL_DEBUG);
});

$server->start();
// TODO: This is useless fire on exit process but now its just empty
App::stop();
