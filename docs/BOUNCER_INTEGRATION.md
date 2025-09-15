# Laravel MCP Server - Bouncer Integration Guide

This guide covers advanced role and permission management using [Laravel Bouncer](https://github.com/JosephSilber/bouncer) with Laravel MCP Server.

## Overview

Laravel MCP Server integrates seamlessly with Bouncer to provide:

- **Role-based permissions** for MCP tools and resources
- **Wildcard permission matching** (e.g., `mcp.tools.*` grants access to all tools)
- **Field-level access control** for sensitive data
- **Transparent security model** - all permissions stored in Bouncer's database

## Installation & Setup

### 1. Install Bouncer

```bash
composer require silber/bouncer
```

### 2. Setup MCP with Bouncer

```bash
# Run MCP setup with Bouncer integration
php artisan mcp:setup --bouncer

# Publish Bouncer migrations
php artisan vendor:publish --tag=bouncer.migrations

# Run migrations
php artisan migrate
```

### 3. Enable Bouncer Integration

```env
# .env
MCP_BOUNCER_ENABLED=true
MCP_BOUNCER_ABILITY_PREFIX=mcp.
```

### 4. Configure Your User Model

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class User extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;
    use HasRolesAndAbilities;

    protected $fillable = [
        'name', 'email', 'password', 'mcp_enabled'
    ];

    protected $casts = [
        'mcp_enabled' => 'boolean',
    ];
}
```

## Permission Management

### Basic Permission Patterns

All MCP permissions use the `mcp.` prefix by default:

```php
use Silber\Bouncer\BouncerFacade as Bouncer;

// Grant access to specific tools
Bouncer::allow($user)->to('mcp.tools.echo');
Bouncer::allow($user)->to('mcp.resources.status');

// Grant wildcard access to all tools
Bouncer::allow($user)->to('mcp.tools.*');

// Grant full MCP access
Bouncer::allow($user)->to('mcp.*');
```

### Role-Based Permissions

**Recommended Approach**: Define permissions on roles, then assign roles to users.

```php
// Create roles with specific permissions
Bouncer::allow('api-user')->to('mcp.tools.echo');
Bouncer::allow('api-user')->to('mcp.resources.status');

Bouncer::allow('developer')->to('mcp.tools.*');
Bouncer::allow('developer')->to('mcp.resources.read');

Bouncer::allow('admin')->to('mcp.*'); // Full access

// Assign roles to users
Bouncer::assign('developer')->to($user);
```

### Field-Level Access Control

Control access to sensitive fields using field access patterns:

```php
// Grant access to specific user fields
Bouncer::allow($role)->to('access-fields.user.name');
Bouncer::allow($role)->to('access-fields.user.email');

// Grant access to all product fields
Bouncer::allow($role)->to('access-fields.product.*');

// Grant access to specific nested fields
Bouncer::allow($role)->to('view-field.order.billing.address');
```

## Security Best Practices

### ✅ Do: Use Explicit Permissions

```php
// GOOD: Explicit, auditable permissions
Bouncer::allow('admin')->to('mcp.*');
Bouncer::assign('admin')->to($user);
```

### ❌ Don't: Bypass Bouncer's Permission System

The package **does not** provide configuration-based permission shortcuts. This ensures:

- **Transparency**: All permissions visible in Bouncer's database
- **Auditability**: Full permission history and tracking
- **Security**: No hidden backdoors or configuration bypasses
- **Standard Patterns**: Uses Bouncer the way it was designed

### Permission Hierarchy Examples

```php
// Basic user - limited access
Bouncer::allow('user')->to('mcp.tools.echo');
Bouncer::allow('user')->to('mcp.resources.status');

// API user - broader tool access
Bouncer::allow('api-user')->to('mcp.tools.*');
Bouncer::allow('api-user')->to('mcp.resources.read');

// Developer - full tools, limited admin
Bouncer::allow('developer')->to('mcp.tools.*');
Bouncer::allow('developer')->to('mcp.resources.*');

// Administrator - full access
Bouncer::allow('admin')->to('mcp.*');
```

## Wildcard Permission Matching

The package implements intelligent wildcard matching:

```php
// Grant wildcard permission
Bouncer::allow($user)->to('mcp.tools.*');

// These will all return true:
$user->hasMCPPermission('tools.echo');
$user->hasMCPPermission('tools.file.read');
$user->hasMCPPermission('tools.database.query');
```

### Hierarchical Wildcards

```php
// Broad wildcard
Bouncer::allow($user)->to('mcp.*');

// Grants access to:
// - tools.* (all tools)
// - resources.* (all resources)
// - Any future MCP functionality
```

## Configuration Options

```php
// config/mcp.php
'auth' => [
    'bouncer' => [
        'enabled' => env('MCP_BOUNCER_ENABLED', false),
        'cache_abilities' => env('MCP_BOUNCER_CACHE_ABILITIES', true),
        'ability_prefix' => env('MCP_BOUNCER_ABILITY_PREFIX', 'mcp.'),
    ],
],
```

### Custom Prefix Example

```php
// Use custom prefix in .env
MCP_BOUNCER_ABILITY_PREFIX=myapp.mcp.

// Then grant permissions with your prefix
Bouncer::allow($user)->to('myapp.mcp.tools.*');
```

## Testing Your Integration

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Silber\Bouncer\BouncerFacade as Bouncer;

class MCPBouncerTest extends TestCase
{
    public function test_user_with_admin_role_has_full_access()
    {
        $user = User::factory()->create();

        // Grant full MCP access to admin role
        Bouncer::allow('admin')->to('mcp.*');

        // Assign admin role to user
        Bouncer::assign('admin')->to($user);

        // Test permissions
        $this->assertTrue($user->hasMCPPermission('tools.echo'));
        $this->assertTrue($user->hasMCPPermission('resources.status'));
        $this->assertTrue($user->hasMCPPermission('any.permission'));
    }

    public function test_developer_has_limited_access()
    {
        $user = User::factory()->create();

        Bouncer::allow('developer')->to('mcp.tools.*');
        Bouncer::assign('developer')->to($user);

        $this->assertTrue($user->hasMCPPermission('tools.echo'));
        $this->assertFalse($user->hasMCPPermission('admin.functions'));
    }
}
```

## Troubleshooting

### Permission Not Working?

1. **Check Bouncer cache**: Run `Bouncer::refresh()` after granting permissions
2. **Verify prefix**: Ensure abilities use the correct `mcp.` prefix
3. **Check user model**: Confirm `HasRolesAndAbilities` trait is used
4. **Enable debugging**: Check `bouncer_abilities` table in database

### Common Issues

**Issue**: Permissions not updating immediately
```php
// Solution: Refresh Bouncer cache
Bouncer::refresh();
```

**Issue**: Wildcard permissions not matching
```php
// Check that you're using the correct prefix
Bouncer::allow($user)->to('mcp.tools.*'); // ✅ Correct
Bouncer::allow($user)->to('tools.*');     // ❌ Missing prefix
```

**Issue**: User has role but no permissions
```php
// Make sure the role has the permissions
Bouncer::allow('admin')->to('mcp.*');    // Grant to role first
Bouncer::assign('admin')->to($user);     // Then assign role
```

## Advanced Examples

### Multi-Tenant Permissions

```php
// Scope permissions to specific tenants
Bouncer::scope()->to($tenant)->allow($user)->to('mcp.tools.*');
```

### Conditional Permissions

```php
// Grant permissions with conditions
Bouncer::allow($user)->to('mcp.resources.user')->where('user', $user);
```

### Permission Auditing

```php
// Check what permissions a user has
$abilities = $user->getAbilities();

// Check what roles a user has
$roles = $user->getRoles();

// Get all permissions for debugging
$permissions = $user->getMCPPermissions();
```

## Migration from Basic to Bouncer

If you're upgrading from basic MCP permissions to Bouncer:

1. **Install Bouncer** (steps above)
2. **Migrate permissions** from user attributes to Bouncer abilities
3. **Update code** to use role-based assignments
4. **Test thoroughly** with your existing permission checks

Example migration script:

```php
// Migration: Convert user permissions to Bouncer
foreach (User::where('mcp_enabled', true)->get() as $user) {
    if ($user->mcp_permissions) {
        foreach ($user->mcp_permissions as $permission) {
            Bouncer::allow($user)->to("mcp.{$permission}");
        }
    }
}
```