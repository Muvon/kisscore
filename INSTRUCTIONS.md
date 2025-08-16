# KissCore Code Style Instructions

## PHP Version Requirement

**KissCore requires PHP 8.4.x or higher.**

All code must be compatible with PHP 8.4+ features and syntax. Use modern PHP features when appropriate.

## Naming Conventions

### Functions
Use `snake_case` for function names:
```php
function get_user_data(): array
function process_payment(): bool
function validate_email_address(string $email): bool
```

### Variables (Scalars)
Use `snake_case` for scalar variables:
```php
$user_id = 123;
$email_address = 'user@example.com';
$is_active = true;
$total_amount = 99.99;
```

### Methods
Use `camelCase` for object methods:
```php
class UserService
{
    public function getUserById(int $id): ?User
    public function validateCredentials(string $email, string $password): bool
    public function sendNotification(User $user, string $message): void
}
```

### Classes and Enums
Use `PascalCase` for classes and enums:
```php
class UserRepository
class PaymentProcessor
class DatabaseConnection

enum OrderStatus
enum UserRole
enum PaymentMethod
```

### Objects
Use `$PascalCase` for object variables:
```php
$User = new User();
$PaymentProcessor = new PaymentProcessor();
$DatabaseConnection = DatabaseConnection::getInstance();
```

## Brace Style

Always place opening braces on the same line:
```php
// Functions
function process_data() {
    // code here
}

// Classes
class UserService {
    // properties and methods
}

// Methods
public function getUserData(): array {
    // code here
}

// Control structures
if ($condition) {
    // code here
} elseif ($other_condition) {
    // code here
} else {
    // code here
}

foreach ($items as $item) {
    // code here
}

while ($condition) {
    // code here
}
```

### Important: Use `elseif` not `else if`
Always use `elseif` (one word) instead of `else if` (two words):
```php
// ✅ Correct
if ($condition) {
    // code
} elseif ($other_condition) {
    // code
} else {
    // code
}

// ❌ Wrong - will cause linter errors
if ($condition) {
    // code
} else if ($other_condition) {  // This breaks the linter
    // code
}
```

## Type Declarations

### Prohibited Types
- **NEVER use `mixed`** - always specify concrete types
- Use specific types or union types instead

### Scalar Types
Use built-in PHP scalar types:
```php
function calculate_total(int $quantity, float $price): float
function get_user_name(int $user_id): string
function is_user_active(int $user_id): bool
function get_user_tags(): array
```

### Complex Types
Use PHPStan type annotations for complex types:
```php
/**
 * @param array<string, int> $user_scores
 * @param list<User> $users
 * @return array{name: string, email: string, age: int}
 */
function process_user_data(array $user_scores, array $users): array

/**
 * @param array<int, array{id: int, name: string}> $items
 * @return Generator<int, User>
 */
function get_users_generator(array $items): Generator

/**
 * @return array<string, string|int|bool>
 */
function get_config_data(): array
```

### Union Types
Use PHP 8.0+ union types for multiple possible types:
```php
function format_value(string|int|float $value): string
function get_user_data(int|string $identifier): ?User
function process_result(array|object $data): bool
```

### Intersection Types (PHP 8.1+)
Use intersection types when objects must implement multiple interfaces:
```php
function process_data(Countable&Iterator $data): void
function handle_request(RequestInterface&ValidatedInterface $request): Response
```

### Readonly Properties (PHP 8.1+)
Use readonly properties for immutable data:
```php
class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly DateTime $created_at
    ) {}
}
```

### Enums (PHP 8.1+)
Use enums for fixed sets of values:
```php
enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';

    public function getLabel(): string {
        return match($this) {
            self::ACTIVE => 'Active User',
            self::INACTIVE => 'Inactive User',
            self::SUSPENDED => 'Suspended User',
        };
    }
}
```

### Match Expressions (PHP 8.0+)
Prefer `match` over `switch` for value returns:
```php
function get_status_color(UserStatus $status): string {
    return match($status) {
        UserStatus::ACTIVE => 'green',
        UserStatus::INACTIVE => 'gray',
        UserStatus::SUSPENDED => 'red',
    };
}
```

### Named Arguments (PHP 8.0+)
Use named arguments for clarity in complex function calls:
```php
create_user(
    name: 'John Doe',
    email: 'john@example.com',
    is_active: true,
    role: UserRole::ADMIN
);
```

### Constructor Property Promotion (PHP 8.0+)
Use constructor property promotion to reduce boilerplate:
```php
class DatabaseConfig
{
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $database,
        private readonly string $username,
        private readonly string $password,
    ) {}
}
```

### Nullable Types
Use nullable types when values can be null:
```php
function find_user(int $id): ?User
function get_cached_data(string $key): ?string
function parse_json(string $json): ?array
```

### Type Aliases
Define complex type aliases for reusability:
```php
/**
 * @phpstan-type UserData array{id: int, name: string, email: string, active: bool}
 * @phpstan-type ConfigArray array<string, string|int|bool>
 * @phpstan-type RouteParams array<string, string>
 */

/**
 * @param UserData $user_data
 * @return ConfigArray
 */
function process_user_config(array $user_data): array
```

## Examples

### Complete Class Example (PHP 8.4+)
```php
<?php declare(strict_types=1);

/**
 * @phpstan-type UserData array{id: int, name: string, email: string}
 */
class UserRepository
{
    public function __construct(
        private readonly DatabaseConnection $Connection,
        private readonly LoggerInterface $Logger
    ) {}

    /**
     * @return list<UserData>
     */
    public function getAllUsers(): array {
        $query = "SELECT id, name, email FROM users";
        return $this->Connection->fetchAll($query);
    }

    /**
     * @param UserData $user_data
     */
    public function createUser(array $user_data): int {
        $user_id = $this->insertUserData($user_data);
        $this->logUserCreation($user_id);
        return $user_id;
    }

    public function getUserStatus(int $user_id): UserStatus {
        $status = $this->Connection->fetchValue(
            query: "SELECT status FROM users WHERE id = ?",
            params: [$user_id]
        );

        return match($status) {
            'active' => UserStatus::ACTIVE,
            'inactive' => UserStatus::INACTIVE,
            'suspended' => UserStatus::SUSPENDED,
            default => UserStatus::INACTIVE,
        };
    }

    private function insertUserData(array $user_data): int {
        // implementation using modern PHP features
    }

    private function logUserCreation(int $user_id): void {
        $this->Logger->info('User created', ['user_id' => $user_id]);
    }
}
```

### Function Example
```php
/**
 * @param array<string, string> $route_params
 * @return array{action: string, params: array<string, string>}
 */
function parse_route_data(string $url, array $route_params): array {
    $parsed_url = parse_url($url);
    $path_segments = explode('/', trim($parsed_url['path'], '/'));

    if (empty($path_segments[0])) {
        return ['action' => 'home', 'params' => []];
    }

    return [
        'action' => $path_segments[0],
        'params' => $route_params
    ];
}
```

## Key Rules Summary

1. **PHP Version**: 8.4.x+ required
2. **Functions**: `snake_case`
3. **Variables (scalars)**: `snake_case`
4. **Methods**: `camelCase`
5. **Classes/Enums**: `PascalCase`
6. **Objects**: `$PascalCase`
7. **Braces**: Same line `{`
8. **Control structures**: Use `elseif` not `else if`
9. **Types**: Never `mixed`, use specific types
9. **Complex types**: Use PHPStan annotations
10. **Nullable**: Use `?Type` when needed
11. **Union types**: Use `Type1|Type2` syntax
12. **Modern PHP**: Use enums, match, readonly, constructor promotion

## Code Quality Tools

### ⚠️ CRITICAL: Always Run Linters

**ALL code MUST pass linting checks before commit.** Use these tools to ensure code quality:

### Code Style Checking
```bash
# Check code style compliance
./bin/codestyle-check
```
- Validates naming conventions
- Checks brace placement
- Ensures consistent formatting
- **Must pass before commit**

### Automatic Code Fixing
```bash
# Automatically fix code style issues
./bin/codestyle-fix
```
- Fixes brace placement
- Corrects indentation
- Standardizes spacing
- **Run before manual review**

### Static Analysis
```bash
# Run PHPStan static analysis
./bin/codestyle-analyze
```
- Validates type declarations
- Detects type errors
- Ensures no `mixed` usage
- Validates PHPStan annotations
- **Must pass with zero errors**

### Development Workflow

1. **Write code** following naming conventions
2. **Run `./bin/codestyle-fix`** to auto-fix formatting
3. **Run `./bin/codestyle-check`** to validate style
4. **Run `./bin/codestyle-analyze`** to check types
5. **Fix any errors** reported by linters
6. **Commit only after all checks pass**

### Error Handling

- **Style errors**: Fix manually or use `codestyle-fix`
- **Type errors**: Update type annotations and fix code
- **PHPStan errors**: Never ignore, always resolve
- **Mixed type usage**: Replace with specific types

**Remember: Linting errors are NOT optional - they must be resolved.**
