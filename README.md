# KissCore

> Swoole-based PHP framework for rapid API development — async, high-performance, zero bloat.

[![CI](https://github.com/Muvon/kisscore/actions/workflows/ci.yml/badge.svg?branch=master)](https://github.com/Muvon/kisscore/actions/workflows/ci.yml)
[![PHP](https://img.shields.io/badge/PHP-8.4%2B-8892BF)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

**Contents:** [Why](#why-kisscore) · [Quick Start](#quick-start) · [Requirements](#requirements) · [Structure](#project-structure) · [Core Concepts](#core-concepts) · [Coroutine Safety](#coroutine-safety) · [API Reference](#api-reference) · [CLI Tools](#cli-tools) · [Swoole](#swoole-integration) · [Clients](#api-clients) · [Docs](#documentation) · [Docker](#docker) · [Contributing](#contributing)

## Why KissCore?

Most PHP frameworks bootstrap themselves on every request. KissCore runs as a
memory-resident [Swoole](https://www.swoole.co.uk/) server: config and route maps are compiled
to plain PHP arrays at init, files load once at startup, and requests are served
without per-request framework overhead.

- **Zero bloat** — no production Composer dependencies at all (`require` is `php` only)
- **`[err, data]` response protocol** — every action returns `ok(...)`/`err(...)`;
  typed client libraries (TypeScript, Python, PHP) consume the protocol out of the box
- **File-based actions** — one action = one PHP file, routed via `@route` annotations;
  no controllers, no routing config to maintain
- **Coroutine-safe by design** — per-request state (`Input`, `Cookie`, `Session`,
  `Response`) and DB connections are coroutine-local under Swoole
- **JSON and MessagePack** — request/response encoding negotiated automatically

## Quick Start

```bash
# Install
composer require muvon/kisscore

# Scaffold project (copies app skeleton, creates .env, makes bin/ executable)
./vendor/bin/kisscore-init init

# Compile config and route maps
bin/init

# Start server
php app/main.php
```

The default `home` action is already there:

```bash
curl http://localhost/
# [null,{"status":"running"}]
```

The server listens on port 80 by default — set `server.port` in
`app/config/app.yml.tpl` and re-run `bin/init` to change it.

## Requirements

| Requirement | Used for |
|-------------|----------|
| PHP 8.4+ | everything |
| [Swoole](https://www.swoole.co.uk/) extension | HTTP server runtime |
| `yaml` extension | config compilation (`bin/init`) |
| `msgpack` extension | MessagePack request/response protocol |
| `bcmath` extension | numeric helpers |
| `mysqli` / `memcached` extensions | `DB` / `Cache` plugins (only if used) |

## Project Structure

After scaffolding, your project looks like this:

```
app/
  actions/       Action handlers with @route annotations
  triggers/      Event handlers with @event annotations
  config/        Configuration (app.yml.tpl) + compiled config and route maps
  plugin/        Project-local plugins
  src/           Application classes (App\ namespace)
  scripts/       Build/utility scripts
  static/        Static files served by Swoole
  tests/         App tests (run with bin/test)
  main.php       Swoole HTTP server entry point
  start.php      Startup hooks (runs once on App::start)
  stop.php       Shutdown hooks
bin/             CLI tools
env/
  log/           Application logs
  tmp/           Temp files
  var/           Variable data
```

## Core Concepts

### Actions (Request Handlers)

Each action is a PHP file in `app/actions/` with route annotations:

```php
<?php declare(strict_types=1);
/**
 * @route user/(\d+): user_id
 * @var int $user_id
 * @zone api
 */

$user = User::get($user_id);
if (!$user->exists()) {
    Response::current()->status(404);
    return err('e_not_found', ['user_id' => $user_id]);
}

return ok($user->getData());
```

**Return value determines response type:**
- `Result` — unwrapped to `[err, data]` JSON
- `array`/`object` — encoded directly (JSON or MessagePack based on `Accept` header)
- `string` — plain text

### Routing

Routes are defined via `@route` annotations and compiled to a map on `bin/init`.

```php
@route home                              // GET /
@route api/users                         // GET /api/users
@route api/users/(\d+): id              // GET /api/users/123 -> $id=123
@route blog/([^/]+)/(\d+): slug, id    // GET /blog/hello/5 -> $slug='hello', $id=5

@method POST                             // restrict to HTTP methods (any if absent) —
                                         // mismatched methods get 405 with an Allow header
@zone api                                // match api.example.com
```

**Zones** map to subdomains: `@zone api` matches `api.example.com`. Unmatched URLs
fall back to the `default.action` from config.

See [doc/routing.md](doc/routing.md) for the full routing reference.

### Result Type (Error Handling)

Rust-inspired Result type — no exceptions for expected errors:

```php
// Creating results
$ok = ok($data);
$fail = err('e_not_found', ['id' => 123]);

// Consuming results
$value = $result->unwrap();           // throws ResultError if error
$value = $result->unwrapOr($default); // returns default if error

// In actions — return directly as JSON response
return ok(['users' => $users]);       // [null, {"users": [...]}]
return err('e_invalid_input');        // ["e_invalid_input", null]

// Checking
if ($result->err) {
    return $result; // propagate error
}
```

### Configuration

`app/config/app.yml.tpl` with `{{PLACEHOLDER}}` substitution from env vars:

```yaml
common:
  domain: '{{PROJECT}}.com'
  zones: ['www', 'api']
  proto: 'https'

server:
  port: 80

session:
  name: 'KISS'
  save_handler: 'files'
```

Access with dot notation: `config('common.domain')`, `config('server.port')`.

Environment-specific overrides: a `common:production:` block in the template replaces
`common` keys when `APP_ENV=production` (same for any environment). `bin/init`
recompiles the config after edits.

### Events / Triggers

Fire events from anywhere, handle in `app/triggers/`:

```php
// Fire
trigger_event('user.registered', ['user_id' => $id, 'email' => $email]);

// Handle: app/triggers/send_welcome.php
/**
 * @event user.registered
 * @var int $user_id
 * @var string $email
 */
// send welcome email...
```

## Coroutine Safety

KissCore runs on Swoole with coroutine handling enabled per worker. Anything
per-request is stored via the `Coro` helper (coroutine-local state), so concurrent
requests inside one worker can't clobber each other:

- `Input`, `Cookie`, `Session`, `Response` — all coroutine-local, reset per request
- `Plugin\Data\DB` — one connection per coroutine, pooled per shard for reuse

## API Reference

### Core Classes (Global Namespace)

| Class | Purpose |
|-------|---------|
| `App` | Lifecycle, logging, request processing |
| `Request` | HTTP request data (method, headers, IP, URL) |
| `Response` | HTTP response (status, headers, body, redirect) |
| `Router` | URL matching against compiled route map |
| `Input` | Request parameter parsing and typification |
| `Cookie` | Cookie get/set with Swoole support |
| `Session` | Per-request session store |
| `Env` | Environment detection, config compilation |
| `Coro` | Coroutine-local state (safe per-request state under Swoole) |
| `Fetch` | HTTP client (single + multi/parallel requests) |
| `Result` | Ok/Err result type for error handling |
| `Secret` | AES-256-GCM encryption/decryption |
| `Cli` | CLI output utilities |
| `Autoload` | PSR-4 autoloader for app classes |

### Global Functions

| Function | Description |
|----------|-------------|
| `config(string $key): mixed` | Get config by dot notation |
| `ok(mixed $res): Result` | Create success result |
| `err(string $code, mixed $data): Result` | Create error result |
| `err_list(array $errs): Result` | Create multi-error result |
| `typify(mixed $var, string $type): mixed` | Cast to int/uint/float/bool/string/array |
| `trigger_event(string $event, array $payload): void` | Fire event |
| `container(string $name, mixed $value): mixed` | Dependency container (set once, get many) |
| `defer(?SplStack &$ctx, callable $cb): void` | Deferred cleanup callback |
| `bench(?string $label): ?array` | Simple benchmarking |

### Plugins

**`Plugin\Data\DB`** — MySQL with per-coroutine connection pooling, parameter
binding, transactions, and configurable shards.

**`Plugin\Data\Model`** — Abstract ORM: CRUD, validation, caching, field
transformers. ID strategies via `NumericIdTrait` / `StringIdTrait`.

**`Plugin\List\Fetcher`** — Entity batch loading with pagination.

**`Plugin\List\Pagination`** — Pagination calculation and metadata.

### Libraries (`Lib\*`)

| Class | Purpose |
|-------|---------|
| `Lib\AlphaId` | Base-N encoding/decoding for compact IDs |
| `Lib\Image` | Image upload and processing |
| `Lib\IPInfo` | IP geolocation |
| `Lib\LLM` | LLM API integration |
| `Lib\Muvon` | Muvon API (email, payments) |
| `Lib\Queue` | Beanstalk job queue |
| `Lib\R2` | Cloudflare R2 / S3 storage |
| `Lib\Replicate` | Replicate AI API |
| `Lib\Secret` | Libsodium encryption |

## CLI Tools

```bash
bin/init                   # Compile config + generate route/param/trigger maps
bin/php-exec "code"        # Execute PHP in app context
bin/php-exec script.php    # Execute PHP file in app context
bin/php-exec-one script    # Same but with file locking (single instance)
bin/cron script [timeout]  # Run script in loop with optional sleep between runs
bin/watcher                # Watch files, rebuild maps, reload Swoole workers
bin/test                   # Built-in test runner (KC\Test) for app tests
bin/codestyle-check        # PHPCS code style check
bin/codestyle-fix          # PHPCS auto-fix
bin/codestyle-analyze      # PHPStan level 9 analysis
```

## Swoole Integration

KissCore runs as a memory-resident Swoole HTTP server. Files are loaded once at
startup — no per-request overhead.

**Per-request state reset** is handled automatically in `main.php`:
- `Response::current(true)` — fresh response
- `Input::setParser(...)` — fresh input from Swoole request
- `Cookie::setParser(...)` — fresh cookies
- `Request::current(fn)` — fresh request metadata

**Hot reload** during development: `bin/watcher` watches for file changes, rebuilds
maps, and sends `USR1` to Swoole to reload workers.

**Static files** are served directly by Swoole's static handler from `app/static/`.

## API Clients

Client libraries for the `[err, data]` response protocol live in
[clients/](clients/README.md): TypeScript (`@muvon/kisscore-client`), Python
(`kisscore-client`), and PHP (`muvon/kisscore-client`). All have zero external
dependencies and return native `[err, data]` tuples.

## Documentation

- [doc/routing.md](doc/routing.md) — routing reference
- [doc/database.md](doc/database.md) — `Plugin\Data\DB`
- [doc/model.md](doc/model.md) — `Plugin\Data\Model`
- [doc/http-client.md](doc/http-client.md) — `Fetch` HTTP client

## Docker

The skeleton includes Docker setup in `docker/`:

```bash
# Build
docker build -f docker/images/Dockerfile-php -t myapp .

# Run — the entrypoint runs bin/init, then php app/main.php
docker run myapp
```

The image is based on PHP 8.5 with Swoole, msgpack and yaml extensions preinstalled.

## Contributing

```bash
composer install        # dev dependencies
composer test           # PHPUnit test suite
composer analyze        # PHPStan level 9
composer codestyle      # PHPCS check (auto-fix: bin/codestyle-fix)
```

CI runs all three on PHP 8.4 and 8.5 — keep them green. See
[INSTRUCTIONS.md](INSTRUCTIONS.md) for the code style guide.

## License

MIT — Muvon Un Limited <hello@muvon.io>. See [LICENSE](LICENSE).
