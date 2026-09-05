# Database (Plugin\Data\DB)

MySQL database layer with connection pooling, parameter binding, and transaction support.

## Configuration

```yaml
# app/config/app.yml.tpl
mysql:
  shard:
    0: 'mysql:host=127.0.0.1;user=root;password=;dbname=myapp'
```

Multiple shards for horizontal scaling:

```yaml
mysql:
  shard:
    0: 'mysql:host=db1;user=app;password=secret;dbname=myapp'
    1: 'mysql:host=db2;user=app;password=secret;dbname=myapp'
```

## Queries

```php
use Plugin\Data\DB;

// SELECT — returns array of rows
$users = DB::query('SELECT * FROM users WHERE status = :status', [
    'status' => 'active',
]);

// INSERT — returns insert_id
$id = DB::query('INSERT INTO users (name, email) VALUES (:name, :email)', [
    'name' => 'John',
    'email' => 'john@example.com',
]);

// UPDATE — returns affected_rows
$affected = DB::query('UPDATE users SET status = :status WHERE id = :id', [
    'status' => 'inactive',
    'id' => 123,
]);

// DELETE
DB::query('DELETE FROM users WHERE id = :id', ['id' => 123]);
```

## Parameter Binding

Parameters use `:name` placeholders. Array values auto-expand for IN clauses:

```php
// Single value
DB::query('SELECT * FROM users WHERE id = :id', ['id' => 5]);

// Array expands to: WHERE id IN (1, 2, 3)
DB::query('SELECT * FROM users WHERE id IN (:ids)', ['ids' => [1, 2, 3]]);
```

## Transactions

```php
$result = DB::transaction(function () {
    DB::query('UPDATE accounts SET balance = balance - :amount WHERE id = :from', [
        'amount' => 100, 'from' => 1,
    ]);
    DB::query('UPDATE accounts SET balance = balance + :amount WHERE id = :to', [
        'amount' => 100, 'to' => 2,
    ]);
    return ok();
});

$result->unwrap(); // throws if transaction failed
```

Nested `transaction()` calls reuse the parent transaction. Return `err()` or throw to rollback.

## Sharding

Query a specific shard:

```php
DB::query('SELECT * FROM orders WHERE id = :id', ['id' => 42], $shard_id);
```

## Connection Management

Connections are pooled per-shard and auto-reconnect on failure. `DB::ping()` tests the connection.

`DB::disconnect()` closes every connection and drops the pool. Call it before forking — a child that
inherits a live mysqli socket shares it with the parent and corrupts the wire protocol for both:

```php
$rows = SomeModel::getList();  // opens a connection
DB::disconnect();              // close it before we fork
for ($i = 0; $i < $workers; $i++) {
  (new Process(fn() => /* child opens its own connection on first query */))->start();
}
```
