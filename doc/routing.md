# Swoole Routing System

KissCore now uses a powerful annotation-based routing system optimized for Swoole. This system automatically parses route annotations from action files and provides fast URL matching and parameter extraction.

## How It Works

### 1. Route Annotations

Actions are single PHP files with route annotations in comments:

```php
<?php declare(strict_types=1);

/**
 * @route task/get-by-slug/([a-z0-9A-Z\-]+): slug
 * @var string $slug
 * @zone www
 */

// Action code here
$task = Task::getBySlug($slug);
return ['task' => $task];
```

### 2. Route Components

- **@route pattern**: URL pattern with optional regex groups
- **@route pattern: param1, param2**: Parameters extracted from URL groups
- **@zone zone_name**: Zone/subdomain (optional, defaults to first zone in config)
- **@var type $param**: Variable type hints for IDE support

### 3. Route Examples

```php
/**
 * @route home
 */
// Matches: / or /home

/**
 * @route api/users/(\d+): id
 * @var int $id
 */
// Matches: /api/users/123 → $id = "123"

/**
 * @route blog/([^/]+)/(\d+): category, post_id
 * @var string $category
 * @var int $post_id
 */
// Matches: /blog/tech/456 → $category = "tech", $post_id = "456"

/**
 * @route admin/dashboard
 * @zone admin
 */
// Matches: /admin/dashboard on admin.example.com
```

## Route Generation

Routes are automatically generated when you run:

```bash
./bin/init
```

This scans all files in `app/actions/` and creates a route cache file.

## URL Matching Process

1. **Request comes in**: Swoole receives HTTP request
2. **URL extraction**: Clean URL path is extracted from request URI
3. **Zone detection**: Zone is determined from request host
4. **Pattern matching**: URL is matched against compiled route patterns
5. **Parameter extraction**: URL parameters are extracted and set in Input
6. **Action execution**: Matched action file is included with parameters available

## Router API

### Router::match(string $url, string $host): ?array

Match URL against route patterns:

```php
$result = Router::match('/task/get-by-slug/my-task', 'example.com');
// Returns:
// [
//     'action' => 'task-detail',
//     'params' => ['slug' => 'my-task'],
//     'route' => 'task/get-by-slug/([a-z0-9A-Z\-]+)',
//     'zone' => 'www'
// ]
```

### Router::url(string $action, array $params, string $zone): ?string

Generate URL for action:

```php
$url = Router::url('task-detail', ['slug' => 'my-task'], 'www');
// Returns: "/task/get-by-slug/my-task"
```

### Router::getRoutes(): array

Get all compiled routes for debugging.

## Zone Configuration

Zones are configured in your app config:

```yaml
# app/config/app.yml
common:
  domain: example.com
  zones: [www, api, admin]
```

This creates:
- `example.com` → www zone
- `api.example.com` → api zone
- `admin.example.com` → admin zone

## Parameter Access in Actions

Parameters are automatically available in action files:

```php
<?php declare(strict_types=1);

/**
 * @route user/profile/(\d+): user_id
 * @var int $user_id
 */

// $user_id is automatically available
$user = User::find($user_id);

if (!$user) {
    Response::current()->status(404);
    return "User not found";
}

return ['user' => $user];
```

## Performance Features

- **Route Compilation**: Patterns are compiled to regex once and cached
- **Smart Sorting**: Routes are sorted by specificity for optimal matching
- **Memory Resident**: Routes stay in memory between requests (Swoole benefit)
- **Fast Matching**: Regex matching is much faster than string operations

## Migration from Nginx

The old nginx rewrite system is no longer needed:

**Before (nginx):**
```nginx
rewrite '^/task/get-by-slug/([a-z0-9A-Z\-]+)/?$' '/?ROUTE=task/get-by-slug/([a-z0-9A-Z\-]+)&ACTION=task-detail&slug=$1';
```

**Now (Swoole):**
```php
/**
 * @route task/get-by-slug/([a-z0-9A-Z\-]+): slug
 */
```

## Debugging Routes

To see all compiled routes:

```php
$routes = Router::getRoutes();
var_dump($routes);
```

To test route matching:

```php
$result = Router::match('/your/test/url', 'your-host.com');
var_dump($result);
```

## Error Handling

- **No route found**: Returns null, falls back to default action
- **Missing route file**: Auto-generates if Env class available
- **Invalid patterns**: Logged as warnings during compilation
- **Zone mismatch**: Route skipped if zone doesn't match request host

## Best Practices

1. **Use specific patterns**: More specific routes are matched first
2. **Name parameters clearly**: Use descriptive parameter names
3. **Group related routes**: Keep related actions in same zone
4. **Test your patterns**: Use Router::match() to test URL patterns
5. **Document complex routes**: Add comments for complex regex patterns

## Example Action Structure

```
app/actions/
├── home.php              # @route home
├── user/
│   ├── profile.php       # @route user/profile/(\d+): user_id
│   └── settings.php      # @route user/settings
├── api/
│   ├── users.php         # @route api/users, @zone api
│   └── posts.php         # @route api/posts/(\d+): post_id, @zone api
└── admin/
    ├── dashboard.php     # @route admin/dashboard, @zone admin
    └── users.php         # @route admin/users, @zone admin
```

This routing system provides the flexibility of nginx rewrites with the performance benefits of Swoole's memory-resident architecture.
