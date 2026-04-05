# KissCore

Swoole-based PHP framework for rapid API development. Async, high-performance, zero bloat.

**PHP 8.4+ | Swoole required**

## Quick Start

```bash
# Install
composer require muvon/kisscore

# Scaffold project
./vendor/bin/kisscore-init init

# Compile config and route maps
bin/init

# Start server
php app/main.php
```

## Project Structure

```
app/
  actions/       Action handlers with @route annotations
  triggers/      Event handlers with @event annotations
  config/        Configuration (app.yml.tpl)
  src/           Application classes (App\ namespace)
  scripts/       Build/utility scripts
  static/        Static files served by Swoole
  main.php       Swoole HTTP server entry point
  start.php      Startup hooks (runs once on App::start)
  stop.php       Shutdown hooks
bin/             CLI tools
env/
  etc/           Compiled config + route maps
  log/           Application logs
  tmp/           Temp files + compiled views
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
    return err('not_found');
}

return ok($user->getData());
```

**Return value determines response type:**
- `array`/`object` -> JSON (or MessagePack based on Accept header)
- `Result` -> unwrapped to `[err, data]` JSON
- `string` -> plain text

### Routing

Routes are defined via `@route` annotations and compiled to a map on `bin/init`.

```php
@route home                              // GET /
@route api/users                         // GET /api/users
@route api/users/(\d+): id              // GET /api/users/123 -> $id=123
@route blog/([^/]+)/(\d+): slug, id    // GET /blog/hello/5 -> $slug=hello, $id=5
```

**Zones** map to subdomains: `@zone api` matches `api.example.com`.

See [doc/routing.md](doc/routing.md) for full routing reference.

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
return ok(['users' => $users]);       // [null, {users: [...]}]
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

Environment-specific overrides: `common:production:`, `common:test:`.

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

### View Templates

Simple template engine with `.tpl` files:

```
{variable}
{nested.property}
{value|html}              // filter: html, url, json, upper, lower, date, time, raw...

{items}                   // loop over array
  {iteration}. {name}     // context vars: first, last, odd, even, iteration
{/items}

{!empty_var}              // negation — render when falsy
  No items found
{/empty_var}

{>partial_template}       // include
{>>dynamic_template_var}  // dynamic include
```

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

**`Plugin\Data\DB`** — MySQL with connection pooling, parameter binding, transactions, sharding.

**`Plugin\Data\Model`** — Abstract ORM: CRUD, validation, caching, field transformers.

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
bin/codestyle-check        # PHPCS code style check
bin/codestyle-fix          # PHPCS auto-fix
bin/codestyle-analyze      # PHPStan level 9 analysis
```

## Swoole Integration

KissCore runs as a memory-resident Swoole HTTP server. Files are loaded once at startup — no per-request overhead.

**Per-request state reset** is handled automatically in `main.php`:
- `Response::current(true)` — fresh response
- `Input::setParser(...)` — fresh input from Swoole request
- `Cookie::setParser(...)` — fresh cookies
- `Request::current(fn)` — fresh request metadata

**Hot reload** during development: `bin/watcher` watches for file changes, rebuilds maps, and sends `USR1` to Swoole to reload workers.

## Docker

Skeleton includes Docker setup in `docker/`:

```bash
# Build
docker build -f docker/images/Dockerfile-php -t myapp .

# The entrypoint runs bin/init then php app/main.php
```

## License

MIT - Muvon Un Limited <hello@muvon.io>
