# Security

Botovis has a multi-layered security model that integrates with your existing Laravel authentication and authorization system.

## Overview

```
Request → Auth Guard → Role Resolution → Permission Check → Schema Filtering → Tool Execution
```

1. **Authentication** — Uses your Laravel auth guard
2. **Role resolution** — Determines the user's role (attribute, method, Spatie, callback)
3. **Permission check** — Role-based table+action permissions
4. **Schema filtering** — Users only see tables they can access
5. **Tool execution** — Write operations require confirmation

## Authentication

```php
'security' => [
    'guard'        => 'web',        // Laravel auth guard
    'require_auth' => true,         // Reject unauthenticated users
],
```

When `require_auth` is `true`, unauthenticated users receive a 401 response. The guard matches your `config/auth.php` configuration.

> **Guest mode:** Set `require_auth` to `false` to allow unauthenticated access. Guest users get the permissions defined under the `'*'` role.

## Role Resolution

Botovis needs to know the current user's role to check permissions. Four resolvers are supported:

### Attribute (default)

```php
'role_resolver' => 'attribute',
'role_attribute' => 'role',     // reads $user->role
```

Works when your `users` table has a `role` column.

### Method

```php
'role_resolver' => 'method',
'role_method' => 'getRole',     // calls $user->getRole()
```

Use when role determination requires logic (e.g., checking multiple conditions).

### Spatie Permission

```php
'role_resolver' => 'spatie',
```

Uses `$user->getRoleNames()->first()` from the [Spatie Permission](https://spatie.be/docs/laravel-permission) package. No additional configuration needed.

### Custom Callback

```php
'role_resolver' => 'callback',
'role_callback' => fn($user) => $user->department === 'IT' ? 'admin' : 'user',
```

Maximum flexibility — resolve the role however you like.

## Role-Based Permissions

```php
'roles' => [
    'admin' => [
        '*' => ['create', 'read', 'update', 'delete'],
    ],
    'manager' => [
        'products'  => ['read', 'update'],
        'orders'    => ['read'],
        'customers' => ['read', 'update'],
    ],
    'user' => [
        '*' => ['read'],
    ],
    // Default for all authenticated users (fallback)
    '*' => [
        '*' => ['read'],
    ],
],
```

### How Resolution Works

1. Look up the user's role in the `roles` array
2. If found, use those table→actions mappings
3. If not found, fall back to `'*'` (default permissions)
4. Within a role, `'*'` as table key means "all tables"
5. Permissions merge: role-specific + default (`'*'`)

### Examples

**Admin sees everything, can do everything:**
```php
'admin' => [
    '*' => ['create', 'read', 'update', 'delete'],
],
```

**Sales team can only view orders and customers:**
```php
'sales' => [
    'orders'    => ['read'],
    'customers' => ['read'],
],
```

**HR can manage employees but only read positions:**
```php
'hr' => [
    'employees' => ['create', 'read', 'update'],
    'positions' => ['read'],
],
```

## Laravel Gates

```php
'use_gates' => true,
```

When enabled, Botovis checks Gates before allowing access:

```php
// Gate format: botovis.{table}.{action}
Gate::define('botovis.products.read', function ($user) {
    return $user->hasPermission('view-products');
});

Gate::define('botovis.products.update', function ($user) {
    return $user->isAdmin();
});
```

Gates take priority over role-based permissions when both are configured.

## Write Confirmation

```php
'require_confirmation' => ['create', 'update', 'delete'],
```

Actions listed here always show a confirmation dialog to the user before execution. The AI describes what will happen, and the user must explicitly confirm or reject.

This is a safety net that **cannot be bypassed** by the AI — the agent loop pauses and waits for user input.

## Schema Filtering

Users only see tables they have permission to access. When the widget fetches the schema, Botovis:

1. Starts with the whitelisted models from config
2. Filters to only tables the user's role can access
3. Annotates each table with the user's allowed actions

The AI never knows about tables the user can't access.

## Security Context

Botovis builds a `SecurityContext` object for each request containing:

```php
SecurityContext {
    $userId         // Current user ID
    $userRole       // Resolved role name
    $allowedTables  // Array of accessible table names
    $permissions    // Table → actions mapping
    $metadata       // Extra data (user name, etc.)
}
```

This context is:
- Included in the AI system prompt (so the AI knows what it can and cannot do)
- Checked before every tool execution
- Used to filter schema responses

## Best Practices

1. **Always require auth in production** — Set `require_auth: true`
2. **Least privilege** — Give roles only the permissions they need
3. **Sensitive tables** — Don't whitelist models with sensitive data (passwords, tokens, etc.)
4. **Use Spatie** — If you already use Spatie Permission, set `role_resolver: 'spatie'`
5. **Test with discover** — Run `php artisan botovis:discover` to see exactly what each role sees
6. **Write confirmation** — Keep `require_confirmation` for all write actions in production

---

Next: [Tools](tools.md) · Previous: [Configuration](configuration.md)
