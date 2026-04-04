# KissCore - Composer Package

Muvon KISScore is a blazing fast framework built for **Swoole** - designed for rapid development with high performance async capabilities.

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

### 3. Available Classes (Global Namespace)

All core classes are available **without namespace prefix**:

- `App` - Application utilities and lifecycle management
- `Input` - Request input handling and parsing
- `Result` - Result type for error handling
- `Cli` - Command line interface utilities
- `View` - Template rendering and view management
- `Request` - HTTP request handling
- `Response` - HTTP response management
- `Cookie` - Cookie management
- `Session` - Session handling
- `Env` - Environment configuration
- `Router` - URL routing engine
- `Secret` - Secret management
- `Fetch` - HTTP client utilities
- `Autoload` - PSR-4 autoloader for app classes

### 4. Available Namespaced Classes

- `Plugin\Data\DB` - Database operations with connection pooling
- `Plugin\Data\Model` - ORM base class with validation and caching
- `Plugin\List\Fetcher` - Data fetching with pagination
- `Lib\*` - Utility libraries (AlphaId, Image, IPInfo, Queue, R2, etc.)

### 5. Binary Tools

After creating a project, these tools are available in `bin/`:

- `init` - Project initialization and config compilation
- `php-exec` - Execute PHP code in app context
- `php-exec-one` - Single-instance PHP execution with locking
- `cron` - Cron job runner with signal handling
- `watcher` - File watcher for development (rebuilds maps + reloads Swoole)
- `codestyle-analyze` - PHPStan static analysis
- `codestyle-check` - PHPCS code style checking
- `codestyle-fix` - PHPCS auto-fix

## Project Structure (After Initialization)

```
your-project/
├── app/
│   ├── actions/          # Action handlers (@route annotations)
│   ├── config/           # Configuration files
│   ├── triggers/         # Event trigger handlers (@event annotations)
│   ├── scripts/          # Custom scripts
│   ├── src/              # Application classes (App\ namespace)
│   ├── static/           # Static assets
│   ├── main.php          # Swoole HTTP Server entry point
│   ├── start.php         # Application startup hooks
│   └── stop.php          # Application cleanup hooks
├── bin/                  # Executable scripts
├── env/                  # Runtime directories
│   ├── etc/              # Compiled configuration
│   ├── log/              # Application logs
│   ├── tmp/              # Temporary files
│   └── var/              # Variable data
├── vendor/               # Composer dependencies
├── .env                  # Environment configuration
└── composer.json
```

## Configuration

Configure your application by editing `app/config/app.yml.tpl`. The config is compiled to PHP on `bin/init` for fast loading. Access values via `config('section.key')` dot notation.

## Running

```bash
php app/main.php
```

## Requirements

- **PHP 8.4+**
- **Swoole Extension**

## License

MIT License - see LICENSE file for details.

## Authors

- Muvon Un Limited <hello@muvon.io>
