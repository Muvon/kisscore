# HTTP Client (Fetch)

cURL-based HTTP client with JSON/MessagePack support and parallel request execution.

## Single Request

```php
$http = Fetch::new(['request_type' => 'json', 'response_type' => 'json']);

$result = $http->request('https://api.example.com/users', [
    'name' => 'John',
], 'POST', ['Authorization: Bearer token123']);

$data = $result->unwrap(); // throws on HTTP error
```

## GET Request

```php
$result = $http->request('https://api.example.com/users', [
    'page' => 1,
    'limit' => 20,
], 'GET');
```

## Parallel Requests

```php
$http = Fetch::new(['request_type' => 'json', 'response_type' => 'json']);

$results = $http->multi()
    ->add('https://api.example.com/users/1')
    ->add('https://api.example.com/users/2')
    ->add('https://api.example.com/users/3')
    ->exec();

foreach ($results as $result) {
    if ($result->err) {
        // handle error
        continue;
    }
    $data = $result->unwrap();
}
```

## Configuration

```php
$http = Fetch::new([
    'request_type' => 'json',       // json, msgpack, binary, raw
    'response_type' => 'json',      // json, msgpack, binary, raw
    'request_timeout' => 30,        // seconds
    'request_connect_timeout' => 5, // seconds
    'request_ssl_verify' => 0,      // 0 = skip, 1 = verify
    'request_keepalive' => 20,      // TCP keepalive
    'request_useragent' => 'MyApp/1.0',
    'request_encoding' => '',       // '' = all, null = none
    'request_proxy' => [            // optional proxy
        'host' => '127.0.0.1',
        'port' => 8080,
        'type' => 'http',          // http, socks4, socks5
        'user' => 'user',          // optional
        'password' => 'pass',      // optional
    ],
]);
```

## Custom Encoder/Decoder

```php
$http = Fetch::new([
    'encoder_fn' => fn($data) => yaml_emit($data),
    'decoder_fn' => fn($response) => yaml_parse($response),
]);
```

## Error Codes

| Code | Meaning |
|------|---------|
| `e_request_refused` | Connection refused |
| `e_request_timedout` | Request timeout |
| `e_request_got_nothing` | Empty response from server |
| `e_request_failed` | General failure |
| `e_response_empty` | Empty response body |
| `e_response_decode_failed` | Failed to decode response |
| `e_http_bad_request` | HTTP 400 |
| `e_http_unauthorized` | HTTP 401 |
| `e_http_forbidden` | HTTP 403 |
| `e_http_not_found` | HTTP 404 |
| `e_http_too_many_request` | HTTP 429 |
| `e_http_server_error` | HTTP 500 |
