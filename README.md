# KissCore - Composer Package

Muvon KISScore is a blazing fast framework built for **Swoole** - designed for rapid development with high performance async capabilities.

> **⚠️ Important**: FPM support is deprecated. This framework is now **Swoole-only** for maximum performance.

## Installation

Install via Composer:

```bash
composer require muvon/kisscore
```

## Usage

### 1. Basic Usage (Library Mode)

After installation, all KissCore classes are available **without namespace prefixes** in the global namespace:

```php
<?php
require_once 'vendor/autoload.php';

// Use core classes directly (no namespace needed)
$result = App::getJSON('config.json');
$input = Input::get('param');
$response = Response::json(['status' => 'ok']);

// Use Plugin classes with namespace
$data = Plugin\Data\DB::query('SELECT * FROM users');

// Use Lib classes with namespace
$ipInfo = Lib\IPInfo::fetch('127.0.0.1');

// Global functions are available
$config = config('app.name');
$typed = typify('123', 'int');
```

### 2. Create New Swoole Project (Full Framework Mode)

To create a new KissCore project with skeleton files optimized for Swoole:

```bash
# Method 1: Using composer script
composer run-script kisscore-create-project

# Method 2: Using the CLI tool
./vendor/bin/kisscore-init init
```

This will:
- Copy skeleton files from `skel/` to your project root
- Copy binary tools from `bin/` to your project's `bin/` directory
- Create necessary directory structure (`env/backup`, `env/log`, etc.)
- Generate a `.env` file with default configuration
- Set up Swoole-optimized main application file
- Make all scripts executable

### 3. Available Classes (Global Namespace)

All core classes are available **without namespace prefix**:

- `App` - Application utilities and lifecycle management
- `Input` - Request input handling and parsing (Swoole-optimized)
- `Result` - Result type for error handling
- `Cli` - Command line interface utilities
- `View` - Template rendering and view management
- `Request` - HTTP request handling (Swoole-compatible)
- `Response` - HTTP response management (Swoole-compatible)
- `Cookie` - Cookie management
- `Session` - Session handling
- `Env` - Environment configuration
- `Lang` - Internationalization
- `Secret` - Secret management
- `Fetch` - HTTP client utilities

### 4. Available Namespaced Classes

- `Plugin\Data\DB` - Database operations with connection pooling
- `Lib\IPInfo` - IP information fetching utilities

### 5. Global Functions

- `config(string $param)` - Get configuration values
- `typify(mixed $var, string $type)` - Type conversion utilities
- And many more utility functions for rapid development...

### 6. Binary Tools

After creating a project, these tools are available in `bin/`:

- `init` - Project initialization
- `php-exec` - PHP execution wrapper
- `php-exec-one` - Single PHP execution
- `codestyle-analyze` - Code style analysis
- `codestyle-check` - Code style checking
- `codestyle-fix` - Code style fixing
- `cron` - Cron job utilities
- `watcher` - File watching utilities

## Project Structure (After Initialization)

```
your-project/
├── app/
│   ├── actions/          # Action handlers
│   ├── config/           # Configuration files
│   ├── lang/             # Language files
│   ├── scripts/          # Custom scripts
│   ├── static/           # Static assets
│   ├── views/            # Template files
│   ├── main.php          # Swoole HTTP Server entry point
│   ├── start.php         # Application startup
│   └── stop.php          # Application cleanup
├── bin/                  # Executable scripts
├── env/                  # Environment directories
│   ├── backup/
│   ├── log/
│   ├── tmp/
│   └── var/
├── vendor/               # Composer dependencies
├── .env                  # Environment configuration
└── composer.json
```

## Configuration

After project initialization, configure your application by editing:

- `.env` - Environment variables
- `app/config/` - Application configuration files

## Running Your Swoole Application

```bash
# Start the Swoole HTTP server
php app/main.php
```

The default configuration starts a high-performance Swoole HTTP server with:
- Multi-worker processes
- Coroutine support
- Static file serving
- HTTP/2 support (configurable)
- Connection pooling
- Hot reload capabilities

## Swoole Features

KissCore is optimized for Swoole and provides:

- **Async I/O**: Non-blocking database and HTTP operations
- **Connection Pooling**: Efficient database connection management
- **Coroutines**: Built-in coroutine support for concurrent operations
- **High Performance**: Handles thousands of concurrent connections
- **Memory Resident**: Application stays in memory between requests
- **WebSocket Support**: Built-in WebSocket capabilities (configurable)

## Performance

With Swoole, KissCore delivers:
- **10x-100x** faster than traditional PHP-FPM
- **Memory efficient** with persistent connections
- **Low latency** response times
- **High throughput** for concurrent requests

## Development

The package includes development tools:

```bash
# Code style checking
./bin/codestyle-check

# Code style fixing
./bin/codestyle-fix

# Code analysis
./bin/codestyle-analyze

# File watching for development
./bin/watcher
```

## Requirements

- **PHP 8.1+**
- **Swoole Extension** (required)
- Linux/macOS (recommended for production)

## Migration from FPM

If you're migrating from PHP-FPM:
1. Install Swoole extension
2. Update your deployment to use `php app/main.php` instead of web server
3. Review session and global state handling (memory persistent)
4. Test concurrent request handling

## License

MIT License - see LICENSE file for details.

## Authors

- Muvon Un Limited <hello@muvon.io>
