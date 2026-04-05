# KissCore API Clients

Client libraries for the KissCore `[err, data]` response protocol.

All KissCore API responses follow a consistent format:

```json
[null, {"user": {"id": 1, "name": "John"}}]   // success
["e_not_found", null]                           // error
["e_validation", {"field": "email"}]            // error with details
```

Every response is a tuple: `[error_code | null, data | null]`. Error-first, always.

## Available Clients

### TypeScript

```bash
npm install @muvon/kisscore-client
```

```typescript
import { createClient } from '@muvon/kisscore-client';

const api = createClient('https://api.example.com', {
  headers: { 'Authorization': 'Bearer token' },
  onError: (err, data, status) => console.error(err),
});

const [err, user] = await api.get<User>('/users/123');
if (err) {
  // err is string error code: 'e_not_found', 'e_unauthorized', etc.
  return;
}
// user is typed as User
console.log(user.name);

// POST
const [err, order] = await api.post<Order>('/orders', {
  product_id: 42,
  quantity: 1,
});

// With query params
const [err, users] = await api.get<User[]>('/users', {
  query: { page: 1, limit: 20 },
});
```

### PHP

```bash
composer require muvon/kisscore-client
```

```php
use KissCore\Client;

$api = new Client('https://api.example.com', [
    'headers' => ['Authorization' => 'Bearer token'],
    'timeout' => 10,
]);

[$err, $user] = $api->get('/users/123');
if ($err) {
    // $err is string: 'e_not_found', etc.
    return;
}

// POST
[$err, $order] = $api->post('/orders', [
    'product_id' => 42,
    'quantity' => 1,
]);

// GET with query
[$err, $users] = $api->get('/users', ['page' => 1, 'limit' => 20]);
```

### Python

```bash
pip install kisscore-client
```

```python
from kisscore_client import Client

api = Client('https://api.example.com', headers={
    'Authorization': 'Bearer token',
})

err, user = api.get('/users/123')
if err:
    print(f'Error: {err}')
    # err is string: 'e_not_found', etc.

# POST
err, order = api.post('/orders', {
    'product_id': 42,
    'quantity': 1,
})

# GET with query
err, users = api.get('/users', query={'page': 1, 'limit': 20})
```

## Adding New Clients

Each client should:
1. Accept a base URL and options (headers, timeout, callbacks)
2. Provide `get`, `post`, `put`, `delete` methods
3. Return `[err, data]` tuple matching the KissCore protocol
4. Handle network errors as error codes (e.g., `e_timeout`, `e_network`)
5. Have zero external dependencies
