# Laravel MCP Server - Advanced Customization Guide

This guide covers advanced customization options that give you maximum flexibility with minimal package enforcement.

## Philosophy

Laravel MCP Server follows the principle of **"Capabilities, Not Enforcement"**:

- ✅ **Provides capabilities** - Tools, authentication, permissions, etc.
- ❌ **Avoids enforcement** - No required structures, naming conventions, or behaviors
- 🔧 **Maximum configurability** - Override, disable, or replace any component
- 📦 **Minimal footprint** - Disable unused features to reduce bloat

## Core Component Customization

### Disabling Core Components

Reduce package footprint by disabling unused features:

```env
# Disable specific core tools/resources
MCP_ENABLE_ECHO_TOOL=false
MCP_ENABLE_STATUS_RESOURCE=false

# Disable all core component auto-registration
MCP_AUTO_REGISTER_CORE=false
```

### Custom Core Components

Replace default tools and resources with your own:

```php
// config/mcp.php
'package' => [
    'core_tools' => [
        \App\Services\MCP\Tools\CustomEchoTool::class,
        \App\Services\MCP\Tools\DatabaseTool::class,
    ],

    'core_resources' => [
        \App\Services\MCP\Resources\CustomStatusResource::class,
        \App\Services\MCP\Resources\SystemMetricsResource::class,
    ],
],
```

### Service Container Overrides

Replace core services with custom implementations:

```php
// config/mcp.php
'services' => [
    'registry_class' => \App\Services\MCP\CustomRegistry::class,
    'context_class' => \App\Services\MCP\CustomContext::class,
    'permission_manager_class' => \App\Services\MCP\CustomPermissionManager::class,

    // Control service binding behavior
    'singleton_services' => false, // Use 'bind' instead of 'singleton'
    'lazy_load_services' => true,  // Defer service loading
],
```

## Validation & Enforcement Removal

### Disable Configuration Validation

Remove package validation requirements:

```env
# Disable all configuration validation
MCP_STRICT_CONFIG_VALIDATION=false

# Disable specific validations
MCP_REQUIRE_SERVER_INFO=false
MCP_VALIDATE_BOUNCER_SETUP=false
```

### Example: Minimal Configuration

Run MCP with absolute minimal requirements:

```env
# .env - Minimal setup
MCP_STRICT_CONFIG_VALIDATION=false
MCP_AUTO_REGISTER_CORE=false
MCP_ROUTES_ENABLED=false
```

```php
// config/mcp.php - Override everything
return [
    'validation' => [
        'strict_config_validation' => false,
        'require_server_info' => false,
        'validate_bouncer_setup' => false,
    ],

    'package' => [
        'auto_register_core_components' => false,
        'core_tools' => [],
        'core_resources' => [],
    ],

    'routes' => ['enabled' => false],

    'custom' => [
        'tools' => [\App\MCP\MyCustomTool::class],
        'resources' => [\App\MCP\MyCustomResource::class],
    ],
];
```

## Advanced Service Customization

### Custom Registry Implementation

Create your own registry with custom behavior:

```php
<?php

namespace App\Services\MCP;

use ChaoticIngenuity\LaravelMCP\Core\Registry;
use ChaoticIngenuity\LaravelMCP\Contracts\{ToolInterface, ResourceInterface};

class CustomRegistry extends Registry
{
    // Override registration logic
    public function registerTool(ToolInterface $tool): void
    {
        // Add custom validation, logging, etc.
        logger('Registering tool: ' . $tool->getName());

        parent::registerTool($tool);
    }

    // Add custom methods
    public function getToolsByCategory(string $category): Collection
    {
        return $this->tools->filter(function ($tool) use ($category) {
            return $tool->getCategory() === $category;
        });
    }
}
```

### Custom Permission Manager

Implement your own permission logic:

```php
<?php

namespace App\Services\MCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;

class CustomPermissionManager implements PermissionManagerInterface
{
    public function userHasAbility($user, string $ability): bool
    {
        // Your custom permission logic
        return $user->customPermissionCheck($ability);
    }

    public function getUserAbilities($user): array
    {
        // Your custom ability resolution
        return $user->getCustomAbilities();
    }

    public function cacheUserAbilities($user): void
    {
        // Your custom caching logic
    }
}
```

Then register it:

```php
// config/mcp.php
'services' => [
    'permission_manager_class' => \App\Services\MCP\Auth\CustomPermissionManager::class,
],
```

### Custom Authenticators

Add your own authentication methods:

```php
<?php

namespace App\Services\MCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;
use ChaoticIngenuity\LaravelMCP\Http\AuthenticationResult;

class DatabaseAuthenticator implements AuthenticatorInterface
{
    public function authenticate(Request $request): AuthenticationResult
    {
        $token = $request->header('X-Custom-Token');

        $client = ClientModel::where('token', $token)->first();

        if (!$client) {
            return AuthenticationResult::failure('Invalid token');
        }

        return AuthenticationResult::success($client->user, [
            'permissions' => $client->permissions,
            'client_id' => $client->id,
        ]);
    }
}
```

Register in config:

```php
// config/mcp.php
'auth' => [
    'custom_authenticators' => [
        \App\Services\MCP\Auth\DatabaseAuthenticator::class,
    ],
],
```

## Route & Middleware Customization

### Custom Routes

Disable default routes and register your own:

```php
// config/mcp.php
'routes' => ['enabled' => false],
```

```php
// routes/web.php
Route::group(['prefix' => 'custom-mcp'], function () {
    Route::post('/tools/{tool}', [MyMCPController::class, 'executeTool']);
    Route::get('/resources/{resource}', [MyMCPController::class, 'getResource']);
});
```

### Custom Middleware Stack

Override default middleware completely:

```php
// config/mcp.php
'routes' => [
    'middleware' => [
        'custom-auth',
        'custom-logging',
        \App\Http\Middleware\CustomMCPMiddleware::class,
    ],
],
```

## Performance Customization

### Selective Feature Loading

Control what gets loaded and when:

```php
// config/mcp.php
'performance' => [
    'track_permissions' => false,           // Disable permission tracking
    'cache_permission_results' => false,   // Disable permission caching
    'permission_cache_ttl' => 0,           // Disable TTL caching
],

'logging' => [
    'log_requests' => false,               // Disable request logging
    'log_responses' => false,              // Disable response logging
    'log_performance' => false,            // Disable performance logging
],
```

### Custom Service Bindings

Control how services are bound in the container:

```php
// AppServiceProvider
public function register()
{
    // Override MCP service bindings
    $this->app->bind(\ChaoticIngenuity\LaravelMCP\Core\Registry::class, function ($app) {
        return new \App\Services\MCP\HighPerformanceRegistry();
    });
}
```

## Security Customization

### Bypass Security Middleware

Remove all security enforcement:

```env
# .env
MCP_REQUIRE_HTTPS=false
MCP_ALLOWED_IPS=
MCP_BLOCK_SUSPICIOUS_UA=false
```

### Custom Security Implementation

Implement your own security layer:

```php
<?php

namespace App\Http\Middleware;

class CustomMCPSecurityMiddleware
{
    public function handle($request, Closure $next)
    {
        // Your custom security logic
        if (!$this->isRequestSecure($request)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        return $next($request);
    }
}
```

## Testing Customizations

### Test Configuration Overrides

Use different configurations for testing:

```php
// tests/TestCase.php
protected function getEnvironmentSetUp($app)
{
    $app['config']->set('mcp.validation.strict_config_validation', false);
    $app['config']->set('mcp.package.auto_register_core_components', false);
    $app['config']->set('mcp.services.singleton_services', false);
}
```

### Mock Custom Services

Test with custom service implementations:

```php
// tests/Feature/CustomMCPTest.php
public function setUp(): void
{
    parent::setUp();

    $this->app->bind(Registry::class, function () {
        return new MockRegistry();
    });
}
```

## Configuration Examples

### Enterprise Setup

Maximum security and control:

```php
// config/mcp.php
return [
    'validation' => [
        'strict_config_validation' => true,
        'require_server_info' => true,
    ],

    'security' => [
        'require_https' => true,
        'allowed_ips' => ['10.0.0.0/8'],
        'block_suspicious_user_agents' => true,
    ],

    'services' => [
        'permission_manager_class' => \App\Services\EnterprisePermissionManager::class,
        'registry_class' => \App\Services\AuditableRegistry::class,
    ],

    'auth' => [
        'custom_authenticators' => [
            \App\Services\LDAPAuthenticator::class,
            \App\Services\SAMLAuthenticator::class,
        ],
    ],
];
```

### Development Setup

Maximum flexibility and debugging:

```php
// config/mcp.php
return [
    'validation' => [
        'strict_config_validation' => false,
        'require_server_info' => false,
        'validate_bouncer_setup' => false,
    ],

    'debug' => [
        'expose_system_info' => true,
        'detailed_error_messages' => true,
        'log_all_requests' => true,
    ],

    'services' => [
        'singleton_services' => false,
        'lazy_load_services' => false,
    ],
];
```

### Microservice Setup

Minimal footprint for microservices:

```php
// config/mcp.php
return [
    'package' => [
        'auto_register_core_components' => false,
        'enable_echo_tool' => false,
        'enable_status_resource' => false,
    ],

    'routes' => ['enabled' => false],

    'logging' => [
        'log_requests' => false,
        'log_responses' => false,
        'log_performance' => false,
    ],

    'custom' => [
        'tools' => [\App\MicroServices\SpecificTool::class],
        'resources' => [],
    ],
];
```

## Migration Guide

### From Enforced to Configurable

If upgrading from a more restrictive setup:

1. **Identify Current Assumptions**: What does your app currently assume?
2. **Add Configuration**: Use new config options to maintain current behavior
3. **Gradually Customize**: Slowly override components as needed
4. **Remove Package Dependencies**: Reduce reliance on package-specific patterns

### Migration: Individual Flags to Core Arrays

**Current Version (v1.x)** supports both methods for backward compatibility:

```php
// OLD WAY (still works, but deprecated)
'package' => [
    'enable_echo_tool' => false,           // ❌ Will be removed in v2.0
    'enable_status_resource' => false,     // ❌ Will be removed in v2.0
],

// NEW WAY (preferred)
'package' => [
    'core_tools' => [
        // Remove EchoTool::class to disable
        // \ChaoticIngenuity\LaravelMCP\Tools\EchoTool::class,
    ],
    'core_resources' => [
        // Remove StatusResource::class to disable
        // \ChaoticIngenuity\LaravelMCP\Resources\StatusResource::class,
    ],
],
```

**Migration Benefits**:
- ✅ **More Flexible**: Add custom tools/resources alongside core ones
- ✅ **Override Capable**: Replace core components with custom implementations
- ✅ **Single Method**: One configuration pattern instead of multiple flags
- ✅ **Future-Proof**: Will be the only method in v2.0

**Migration Timeline**:
- **v1.x**: Both methods work (individual flags + core arrays)
- **v2.0**: Only core arrays will work (individual flags removed)

### Example Migration

Before (enforced):
```php
// Had to use specific naming and structure
class MyMCPTool extends EchoTool { /* forced inheritance */ }
```

After (configurable):
```php
// Use any structure you want
class MyBusinessLogicTool implements ToolInterface {
    // Your own design patterns
}
```

## Best Practices

### 1. **Gradual Customization**
Start with defaults, customize incrementally

### 2. **Configuration Over Code**
Use config files rather than code changes when possible

### 3. **Interface Compliance**
When creating custom implementations, stick to package interfaces

### 4. **Documentation**
Document your customizations for team members

### 5. **Testing**
Test custom implementations thoroughly

## Troubleshooting

### Common Issues

**Issue**: Service not found after customization
```php
// Solution: Ensure custom class implements required interface
class CustomRegistry extends Registry implements RegistryInterface
```

**Issue**: Configuration not loading
```php
// Solution: Clear config cache
php artisan config:clear
```

**Issue**: Custom authenticator not called
```php
// Solution: Check registration order in config
'custom_authenticators' => [
    \App\Auth\HighPriorityAuth::class, // Called first
    \App\Auth\FallbackAuth::class,     // Called second
],
```

This advanced customization guide shows how Laravel MCP Server provides maximum flexibility while enforcing minimal structure, allowing you to adapt it to any architecture or requirement.