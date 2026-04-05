# Model (Plugin\Data\Model)

Abstract ORM base class for database entities with validation, caching, and field transformers.

## Defining a Model

```php
<?php declare(strict_types=1);

namespace App\User;

use Plugin\Data\Model;
use Plugin\Data\NumericIdTrait;

final class UserModel extends Model {
    use NumericIdTrait;

    protected static function table(): string {
        return 'users';
    }

    protected static function fields(bool $with_defaults = false): array {
        return [
            'user_id' => $with_defaults ? 0 : null,
            'name' => $with_defaults ? '' : null,
            'email' => $with_defaults ? '' : null,
            'status' => $with_defaults ? 'active' : null,
            'created_at' => $with_defaults ? 0 : null,
        ];
    }

    protected function rules(): array {
        return [
            'name' => fn($v) => $v !== '' ? ok() : err('e_name_required'),
            'email' => fn($v) => str_contains($v, '@') ? ok() : err('e_invalid_email'),
        ];
    }
}
```

## ID Traits

Use one of the provided traits for ID generation:

- **`NumericIdTrait`** — Auto-generated numeric IDs (snowflake-style)
- **`StringIdTrait`** — String-based IDs

Each trait implements `getId()`, `setId()`, `generateId()`, `dbShardId()`, `getShardKey()`.

## CRUD Operations

```php
use App\User\UserModel;

// Create
$result = UserModel::create([
    'name' => 'John',
    'email' => 'john@example.com',
]);
$user = $result->unwrap(); // throws ResultError if validation fails

// Read
$user = UserModel::get(123);
$user = UserModel::get(123, cache: true); // with in-memory cache

// Read multiple
$users = UserModel::getByIds([1, 2, 3]);
// Returns: [1 => ['user_id' => 1, ...], 2 => [...], ...]

// Read by fields
$user = UserModel::getByFields(['email' => 'john@example.com']);

// Update
$user->save(['name' => 'Jane'])->unwrap();

// Increment counters
$user->increment(['login_count' => 1]);

// Check existence
if ($user->exists()) { ... }
```

## Transactions with Locking

```php
use Plugin\Data\DB;

DB::transaction(function () {
    $user = UserModel::getForUpdate(123); // SELECT ... FOR UPDATE
    $user->save(['balance' => $user['balance'] - 100])->unwrap();
    return ok();
})->unwrap();
```

## Validation

Rules return `Result`. Validation runs on `save()` / `create()`:

```php
protected function rules(): array {
    return [
        'email' => function ($v) {
            if (!$v) return err('e_required');
            if (!str_contains($v, '@')) return err('e_invalid');
            return ok();
        },
        'age' => fn($v) => $v >= 18 ? ok() : err('e_too_young'),
    ];
}

// Usage
$result = UserModel::create(['email' => 'bad']);
// $result->err === 'e_invalid'
```

## Field Transformers

Encode data on save, decode on load:

```php
protected static function getTransformers(): array {
    return [
        'settings' => [
            fn($v) => json_encode($v),   // encode (save)
            fn($v) => json_decode($v, true), // decode (load)
        ],
    ];
}
```

## Data Access

Models implement `ArrayAccess`:

```php
$user = UserModel::get(123);
$name = $user['name'];        // ArrayAccess
$data = $user->getData();      // Full data array
$ref = &$user->access('name'); // Reference access
```

## JSON Serialization

Models implement `JsonSerializable`:

```php
return ok($user); // auto-serializes to JSON via toArray()
```

## Pagination

```php
use Plugin\List\Pagination;

$pagination = Pagination::create(['page' => 1, 'limit' => 20, 'total' => 100]);
$user = UserModel::new()->setPagination($pagination);
// Subsequent queries use LIMIT/OFFSET from pagination
```
