# Routing

KissCore uses annotation-based routing. Routes are defined in action files and compiled to a fast lookup map.

## Route Annotations

Actions are PHP files in `app/actions/` with route annotations in PHPDoc comments:

```php
<?php declare(strict_types=1);
/**
 * @route user/profile/(\d+): user_id
 * @var int $user_id
 * @zone www
 */

$user = User::get($user_id);
return ['user' => $user->getData()];
```

### Annotation Reference

| Annotation | Purpose | Example |
|-----------|---------|---------|
| `@route pattern: params` | URL pattern with regex groups mapped to named params | `@route api/users/(\d+): id` |
| `@zone name` | Subdomain zone (defaults to first zone in config) | `@zone api` |
| `@var type $name` | Parameter type hint (auto-typified) | `@var int $user_id` |
| `@param type $name default` | Same as @var, with optional default value | `@param string $format json` |

## Pattern Examples

```php
// Static route
@route home                    // matches: /

// Single parameter
@route user/(\d+): id         // matches: /user/123 -> $id = 123

// Multiple parameters
@route blog/([^/]+)/(\d+): slug, post_id
// matches: /blog/hello-world/42 -> $slug = "hello-world", $post_id = 42

// Complex regex
@route task/([a-z0-9\-]+): slug
// matches: /task/my-first-task -> $slug = "my-first-task"
```

## Zones

Zones map to subdomains. Configured in `app/config/app.yml.tpl`:

```yaml
common:
  domain: example.com
  zones: [www, api, admin]
```

This creates:
- `example.com` / `www.example.com` -> `www` zone
- `api.example.com` -> `api` zone
- `admin.example.com` -> `admin` zone

Use `@zone` in actions to restrict to a zone:

```php
/**
 * @route users
 * @zone api
 */
// Only matches requests to api.example.com/users
```

Without `@zone`, the action uses the first configured zone (typically `www`).

## Route Compilation

Routes are compiled to a PHP array on `bin/init`:

```bash
bin/init    # Scans app/actions/, generates env/etc/uri_request_map.php
```

The Router loads this map once at Swoole startup and keeps it in memory.

## URL Matching Flow

1. Swoole receives HTTP request
2. `Router::match($url, $host)` is called
3. Zone is determined from host header
4. URL is matched against compiled regex patterns (filtered by zone)
5. Captured groups are mapped to named parameters
6. Parameters are set in `Input` for the action to use

## Router API

```php
// Match URL — returns match info or null
$result = Router::match('/user/123', 'api.example.com');
// [
//   'action' => 'user/profile',
//   'params' => ['id' => '123'],
//   'route'  => 'user/(\d+)',
//   'zone'   => 'api'
// ]

// Clear cached routes (useful in tests)
Router::clearCache();
```

## Parameter Typification

Parameters from routes are strings. Use `@var` / `@param` annotations to auto-cast:

```php
/**
 * @route order/(\d+): order_id
 * @var int $order_id
 * @var string $format json
 */
// $order_id is int, $format defaults to "json"
```

Supported types: `int`, `uint`, `float`, `ufloat`, `bool`, `string`, `array`.

## Action Return Values

The return value of an action file determines the response:

| Return | Response |
|--------|----------|
| `array` or `object` | JSON (or MessagePack based on Accept header) |
| `Result` instance | Unwrapped to `[err, data]` JSON |
| `string` | Plain text |

## File Organization

```
app/actions/
  home.php              # @route home
  user/
    profile.php         # @route user/profile/(\d+): id
    settings.php        # @route user/settings
  api/
    users.php           # @route api/users, @zone api
    orders.php          # @route api/orders/(\d+): id, @zone api
```

The action name is derived from the file path relative to `app/actions/` (minus `.php`).

## Route Priority

Routes are sorted by specificity:
1. Routes with fewer regex groups are matched first
2. Longer patterns take priority over shorter ones
3. Exact matches beat wildcard patterns

## Error Handling

- **No route match**: Falls back to `default.action` from config (typically `home`)
- **Missing action file**: Throws error (action_map references nonexistent file)
- **Zone mismatch**: Route is skipped, matching continues
