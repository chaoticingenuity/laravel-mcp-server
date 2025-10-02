# Laravel MCP Server

A Laravel package that implements the Model Context Protocol (MCP) server specification, enabling AI models to interact with your Laravel application through a standardized protocol.

## Features

- ✅ Full MCP 2024-11-05 protocol implementation
- ✅ Tool and Resource support with JSON-RPC 2.0
- ✅ Fine-grained permission system with field-level access control
- ✅ Template resources with parameter extraction
- ✅ Multiple authentication methods (API keys, Basic Auth, Bearer tokens)
- ✅ Flexible custom authentication system with database integration
- ✅ Comprehensive security middleware stack
- ✅ Rate limiting with per-client controls and burst protection
- ✅ Performance monitoring and detailed logging
- ✅ Extensible architecture for custom tools/resources
- ✅ Laravel 10+, 11+ and 12+ support
- ✅ Comprehensive test suite
- ✅ Auto-discovery of tools and resources
- ✅ **NEW**: Advanced permission resolution system with custom resolvers
- ✅ **NEW**: Enhanced field-level security with data filtering
- ✅ **NEW**: API key audit logging and usage tracking
- ✅ **NEW**: Per-key rate limiting with burst protection
- ✅ **NEW**: Security enhancements (key rotation, scope-based permissions)
- ✅ **NEW**: Performance monitoring and metrics tracking

## Installation

Install the package via Composer:

```bash
composer require chaoticingenuity/laravel-mcp-server
```

## Quick Start

### 1. Publish Configuration

```bash
php artisan vendor:publish --tag=mcp-config
```

### 2. Publish Controllers (Optional)

```bash
php artisan vendor:publish --tag=mcp-controllers
```

**Note**: You only need to publish if you want to customize the MCPController. The middleware classes are used directly from the package namespace.

### 3. Laravel Version-Specific Setup

#### Laravel 11+ (including Laravel 12) Setup (Recommended Method)

For Laravel 11+, use the static helper method to register middleware in `bootstrap/app.php`:

```php
<?php

use ChaoticIngenuity\LaravelMCP\Providers\MCPServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Register MCP middleware aliases using static helper
        $middleware->alias(MCPServiceProvider::middlewareAliases());
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

Add the MCP route to your `routes/api.php`:

```php
<?php

use App\Http\Controllers\MCPController;
use Illuminate\Support\Facades\Route;

Route::post('/mcp', [MCPController::class, 'handle'])
    ->middleware([
        'mcp.security',
        'mcp.auth', 
        'mcp.throttle',
        'mcp.logging'
    ])
    ->name('mcp.handle');
```

#### Laravel 10 Setup

For Laravel 10, middleware is automatically registered by the service provider. Just ensure your `routes/api.php` includes the MCP route (same as above).

### 4. Set Environment Variables

Add to your `.env` file:

```env
# Server Information
MCP_SERVER_NAME="Your MCP Server"
MCP_SERVER_VERSION="1.0.0"

# Authentication - Static Keys (Optional)
MCP_API_KEY_1=mcp_live_sk_1234567890abcdef1234567890abcdef
MCP_CLIENT_1=gpt_client

# User Model Configuration (for database authentication)
MCP_USER_MODEL=App\Models\User
MCP_USER_FOREIGN_KEY=user_id
MCP_USER_OWNER_KEY=id

# Security
MCP_REQUIRE_HTTPS=true
MCP_ALLOWED_IPS=192.168.1.0/24

# Rate Limiting
MCP_RATE_LIMIT=100
MCP_BURST_LIMIT=20

# Logging & Monitoring
MCP_LOG_REQUESTS=true
MCP_LOG_PERFORMANCE=true
MCP_LOG_API_USAGE=false
MCP_TRACK_PERMISSIONS=false

# Performance
MCP_AUTH_CACHE_DURATION=300
MCP_CACHE_PERMISSION_RESULTS=true
MCP_PERMISSION_CACHE_TTL=300
```

### 5. Configure Authentication

Choose your authentication strategy:

#### Option A: Static Configuration (Simple)

Edit `config/mcp.php`:

```php
'clients' => [
    'gpt_client' => [
        'permissions' => [
            'tools.echo',
            'resources.status',
        ],
        'field_access' => [],
        'metadata' => ['tier' => 'standard']
    ],
],
```

#### Option B: Database Authentication (Recommended)

1. **Add MCP support to your User model:**

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;

class User extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;

    protected $fillable = [
        'name', 'email', 'password', 'mcp_enabled', 'mcp_permissions', 'mcp_field_access'
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'mcp_enabled' => 'boolean',
        'mcp_permissions' => 'array',
        'mcp_field_access' => 'array',
        'mcp_tokens' => 'array',
        'mcp_scopes' => 'array', // NEW: Scope-based permissions
    ];

    // Optional: Customize permissions based on user roles
    public function getMCPPermissions(): array
    {
        if ($this->hasRole('admin')) {
            return ['admin'];
        }

        if ($this->hasRole('api_user')) {
            return [
                'tools.*',
                'resources.catalog',
                'products.read'
            ];
        }

        return parent::getMCPPermissions();
    }
}
```

2. **Run migrations:**

```bash
# Option A: For API key-based authentication (includes both api_keys table and users table fields)
php artisan vendor:publish --tag=mcp-migrations-api-keys
php artisan migrate

# Option B: For Bouncer integration (enables MCP for existing Bouncer users)
php artisan vendor:publish --tag=mcp-migrations-bouncer
php artisan migrate

# Option C: Custom implementation (no migrations needed - implement custom storage in your User model)
# See examples in documentation for user table columns, JSON storage, etc.
```

```php
// In the migration file
public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('mcp_enabled')->default(false);
        $table->json('mcp_permissions')->nullable();
        $table->json('mcp_field_access')->nullable();
        $table->json('mcp_tokens')->nullable();
        $table->json('mcp_scopes')->nullable(); // NEW: Scope-based permissions
        
        $table->index('mcp_enabled');
    });
}
```

3. **Enable custom database authenticator:**

```php
// config/mcp.php
'auth' => [
    'custom_authenticators' => [
        \App\Services\Custom\MCP\Auth\DatabaseAuthenticator::class,
    ],
],
```

### 6. Test the Installation

```bash
# Generate an API key for a user (if using database auth)
php artisan tinker
>>> $user = User::find(1);
>>> $apiKey = $user->generateMCPApiKey('test_client', ['tools.*']);
>>> echo $apiKey->key;

# Test the endpoint
curl -X POST http://your-app.com/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: mcp_live_sk_1234567890abcdef1234567890abcdef" \
  -d '{"jsonrpc": "2.0", "method": "tools/list", "id": 1}'
```

### 7. Verification

Check that everything is working:

```bash
# Verify routes are registered
php artisan route:list --name=mcp

# Check middleware is loaded
php artisan route:show mcp.handle

# Clear cache if needed
php artisan optimize:clear
```

## Advanced Features (v1.1.0+)

### Permission Resolution System

The package now includes a flexible permission resolution system that allows custom permission logic:

```php
// Create custom permission resolvers
class OrganizationPermissionResolver implements PermissionResolverInterface
{
    public function resolveUserPermissions($user): array
    {
        // Custom logic based on organization membership
        $orgPermissions = $user->organization->mcp_permissions ?? [];
        $userPermissions = $user->mcp_permissions ?? [];
        
        return array_merge($orgPermissions, $userPermissions);
    }
    
    public function resolveUserFieldAccess($user): array
    {
        // Dynamic field access based on user tier
        return match($user->subscription_tier) {
            'premium' => ['*'],
            'standard' => ['name', 'price', 'description'],
            'basic' => ['name', 'price'],
            default => []
        };
    }
    
    public function canResolve($user): bool
    {
        return $user->organization !== null;
    }
    
    public function getPriority(): int
    {
        return 50; // Medium priority
    }
}

// Register in config/mcp.php
'auth' => [
    'custom_permission_resolvers' => [
        \App\Services\MCP\OrganizationPermissionResolver::class,
    ],
],
```

### Enhanced API Key Management

Advanced API key features with audit logging and per-key rate limiting:

```php
// Generate API key with specific rate limits and scopes
$apiKey = $user->generateMCPApiKey('mobile_app', ['tools.search'], 'Mobile App Key');

// Set per-key rate limits
$apiKey->update([
    'rate_limit_per_minute' => 500,
    'rate_limit_burst' => 50
]);

// Track usage automatically
$keyWithTracking = $user->getMCPApiKeyWithTracking($keyValue);

// Rotate keys securely
$newKey = $user->rotateMCPApiKey($oldKeyValue);

// Get detailed usage analytics
$analytics = $user->getMCPApiKeysSummary();
// Returns: total_keys, active_keys, expired_keys, usage_stats
```

### Scope-Based Permissions

Implement fine-grained access control with scopes:

```php
// Add scopes to users
$user->addMCPScope('read:products');
$user->addMCPScope('write:orders');

// Check scopes in tools/resources
public function isAccessibleTo(ContextInterface $context): bool
{
    return $context->hasScope('read:products') || 
           $context->hasPermission('admin');
}

// Configure scope-based client permissions
'clients' => [
    'mobile_app' => [
        'permissions' => ['tools.*'],
        'scopes' => ['read:products', 'read:categories'],
        'metadata' => ['app_version' => '2.1.0']
    ]
]
```

### Data Filtering & Field Security

Automatically filter response data based on field access permissions:

```php
public function execute(array $arguments, ContextInterface $context): ResultInterface
{
    $products = Product::all()->toArray();
    
    // Automatically filter fields based on user permissions
    $filteredProducts = array_map(function($product) use ($context) {
        return $context->filterFields('product', $product);
    }, $products);
    
    return Result::success([
        'products' => $filteredProducts,
        'accessible_fields' => $context->getAccessibleFields('product')
    ]);
}
```

### Performance Monitoring & Analytics

Built-in performance tracking and usage analytics:

```php
// Enable in .env
MCP_TRACK_PERMISSIONS=true
MCP_LOG_API_USAGE=true

// Access metrics via Cache
$permissionStats = Cache::get('mcp.perms.tools.search_products.granted', 0);
$deniedAttempts = Cache::get('mcp.perms.tools.search_products.denied', 0);

// View API usage logs
tail -f storage/logs/mcp.log | grep "API Key Usage"
```

## Authentication Methods

The package supports multiple authentication strategies that can be used simultaneously:

### Static Configuration

Configure static API keys, basic auth, and bearer tokens:

```php
// config/mcp.php
'auth' => [
    'api_keys' => [
        env('MCP_API_KEY_1'),
        env('MCP_API_KEY_2'),
    ],
    'basic_auth' => [
        env('MCP_BASIC_USER_1') => env('MCP_BASIC_PASS_1'),
    ],
    'bearer_tokens' => [
        env('MCP_BEARER_TOKEN_1'),
    ],
],
```

### Database Authentication

Store API keys in your database with user relationships:

```php
// Generate API keys for users
$user = User::find(1);
$apiKey = $user->generateMCPApiKey('mobile_app', ['tools.search', 'resources.catalog']);

// Check if a key is valid
$isValid = $user->isMCPApiKeyValid($apiKey->key);

// Revoke a key
$user->revokeMCPApiKey($apiKey->key);

// Get key information (without exposing the actual key)
$keyInfo = $user->getMCPApiKeyInfo($apiKey->key);
```

### Custom Authentication

Create your own authentication logic:

```php
// app/Services/Custom/MCP/Auth/CustomAuthenticator.php
class CustomAuthenticator implements AuthenticatorInterface
{
    public function handles(string $type): bool
    {
        return $type === 'custom_token';
    }

    public function authenticate(string $type, array $credentials): AuthenticationResult
    {
        // Your custom authentication logic
        $token = $credentials['token'] ?? '';
        
        if ($this->isValidCustomToken($token)) {
            return AuthenticationResult::success('custom_client_id');
        }
        
        return AuthenticationResult::failure('Invalid custom token');
    }
}

// Register in config/mcp.php
'auth' => [
    'custom_authenticators' => [
        \App\Services\Custom\MCP\Auth\CustomAuthenticator::class,
    ],
],
```

## Flexible Key Storage Patterns

The package supports multiple API key storage strategies:

### Separate Table (Default)

Uses the `ApiKey` model with foreign key relationships:

```php
// Uses the HasMCPAuthentication trait defaults
class User extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;
    
    // No overrides needed - uses ApiKey model
}
```

### User Table Columns

Store hashed keys directly in user table columns:

```php
class User extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;

    protected $fillable = [
        'mcp_api_key_1', 'mcp_api_key_2', 
        'mcp_api_key_1_meta', 'mcp_api_key_2_meta'
    ];

    protected $casts = [
        'mcp_api_key_1_meta' => 'array',
        'mcp_api_key_2_meta' => 'array',
    ];

    protected $hidden = [
        'mcp_api_key_1', 'mcp_api_key_2'
    ];

    public function generateMCPApiKey(string $clientIdentifier, array $scopes = [], ?string $name = null): ApiKey
    {
        $key = 'mcp_' . bin2hex(random_bytes(32));
        $hashedKey = Hash::make($key);
        
        if (empty($this->mcp_api_key_1)) {
            $this->update([
                'mcp_api_key_1' => $hashedKey,
                'mcp_api_key_1_meta' => [
                    'client_identifier' => $clientIdentifier,
                    'scopes' => $scopes,
                    'created_at' => now(),
                ],
            ]);
        }
        // Return mock ApiKey for compatibility
    }

    public function isMCPApiKeyValid(string $key): bool
    {
        return ($this->mcp_api_key_1 && Hash::check($key, $this->mcp_api_key_1)) ||
               ($this->mcp_api_key_2 && Hash::check($key, $this->mcp_api_key_2));
    }
}
```

### JSON Column Storage

Store all keys in a single JSON column:

```php
class User extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;

    protected $casts = [
        'mcp_api_keys' => 'array',
    ];

    public function isMCPApiKeyValid(string $key): bool
    {
        $keys = $this->mcp_api_keys ?? [];
        foreach ($keys as $keyData) {
            if (Hash::check($key, $keyData['hash']) && 
                ($keyData['is_active'] ?? false) &&
                (!isset($keyData['expires_at']) || now()->isBefore($keyData['expires_at']))) {
                return true;
            }
        }
        return false;
    }
}
```

## Client Permissions

Configure fine-grained permissions for each client:

```php
'clients' => [
    'admin_client' => [
        'permissions' => ['admin'], // Full access
        'field_access' => ['*'],
        'metadata' => ['tier' => 'admin']
    ],
    
    'api_client' => [
        'permissions' => [
            'tools.*',           // All tools
            'resources.*',       // All resources
            'products.read',     // Entity-level permission
        ],
        'field_access' => [
            'user' => ['name', 'email'],           // Limited fields
            'product' => ['*'],                    // All product fields
        ],
        'rate_limit' => [
            'requests_per_minute' => 1000,        // Client-specific limits
            'burst_limit' => 50,
        ],
        'metadata' => ['tier' => 'premium']
    ],
    
    'public_client' => [
        'permissions' => [
            'tools.echo',
            'resources.status',
        ],
        'field_access' => [
            'product' => ['name', 'price'],       // Public fields only
        ],
        'metadata' => ['tier' => 'public']
    ]
]
```

## Creating Custom Tools

### 1. Generate Tool Stub

```bash
php artisan vendor:publish --tag=mcp-stubs
```

### 2. Create Your Tool

```php
<?php

namespace App\Services\Custom\MCP\Tools;

use ChaoticIngenuity\LaravelMCP\Contracts\{ToolInterface, ContextInterface, ResultInterface};
use ChaoticIngenuity\LaravelMCP\Core\Result;

class SearchProductsTool implements ToolInterface
{
    public function getName(): string
    {
        return 'search_products';
    }

    public function getDescription(): string
    {
        return 'Search products in the database with advanced filtering';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'filters' => [
                    'type' => 'array',
                    'description' => 'Search filters to apply',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'field' => [
                                'type' => 'string',
                                'description' => 'Field name to filter on'
                            ],
                            'operator' => [
                                'type' => 'string', 
                                'enum' => ['=', '!=', '>', '<', '>=', '<=', 'like', 'in'],
                                'description' => 'Comparison operator'
                            ],
                            'value' => [
                                'oneOf' => [
                                    {'type' => 'string'},
                                    {'type' => 'number'},
                                    {'type' => 'array'}
                                ],
                                'description' => 'Value to compare against'
                            ]
                        ],
                        'required' => ['field', 'operator', 'value']
                    ]
                ],
                'limit' => [
                    'type' => 'integer',
                    'default' => 10,
                    'maximum' => 100,
                    'description' => 'Maximum number of results'
                ],
                'sort' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string'],
                        'direction' => ['type' => 'string', 'enum' => ['asc', 'desc']]
                    ]
                ]
            ],
            'required' => ['filters'],
            'examples' => [
                {
                    'description' => 'Find electronics under $500',
                    'filters' => [
                        {'field' => 'category', 'operator' => '=', 'value' => 'electronics'},
                        {'field' => 'price', 'operator' => '<', 'value' => 500}
                    ],
                    'limit' => 20,
                    'sort' => {'field' => 'name', 'direction' => 'asc'}
                }
            ]
        ];
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        return $context->hasPermission('tools.search_products') || 
               $context->hasPermission('admin');
    }

    public function execute(array $arguments, ContextInterface $context): ResultInterface
    {
        $filters = $arguments['filters'] ?? [];
        $limit = min($arguments['limit'] ?? 10, 100);
        $sort = $arguments['sort'] ?? ['field' => 'created_at', 'direction' => 'desc'];
        
        // Apply field-level access control
        $accessibleFields = $context->getAccessibleFields('product');
        
        $query = Product::query();
        
        // Apply filters with field access checking
        foreach ($filters as $filter) {
            $field = $filter['field'];
            $operator = $filter['operator'];
            $value = $filter['value'];
            
            // Skip inaccessible fields
            if (!$context->hasFieldAccess('product', $field)) {
                continue;
            }
            
            match($operator) {
                '=' => $query->where($field, $value),
                '!=' => $query->where($field, '!=', $value),
                '>', '<', '>=', '<=' => $query->where($field, $operator, $value),
                'like' => $query->where($field, 'like', "%{$value}%"),
                'in' => $query->whereIn($field, (array) $value),
                default => null
            };
        }
        
        // Apply sorting if field is accessible
        if ($context->hasFieldAccess('product', $sort['field'])) {
            $query->orderBy($sort['field'], $sort['direction']);
        }
        
        $products = $query->limit($limit)->get();
        
        // Filter response fields based on permissions
        $filteredProducts = $products->map(function ($product) use ($accessibleFields) {
            if (in_array('*', $accessibleFields)) {
                return $product->toArray();
            }
            return $product->only($accessibleFields);
        });

        return Result::success([
            'products' => $filteredProducts,
            'total' => $filteredProducts->count(),
            'applied_filters' => $filters,
            'accessible_fields' => $accessibleFields
        ], [
            'execution_time_ms' => microtime(true) * 1000,
            'client_id' => $context->getClientId()
        ]);
    }
}
```

### 3. Register Your Tool

Add to `config/mcp.php`:

```php
'custom' => [
    'tools' => [
        \App\Services\Custom\MCP\Tools\SearchProductsTool::class,
    ],
]
```

## Creating Custom Resources

### 1. Static Resources

```php
<?php

namespace App\Services\Custom\MCP\Resources;

use ChaoticIngenuity\LaravelMCP\Contracts\{ResourceInterface, ContextInterface, ResultInterface};
use ChaoticIngenuity\LaravelMCP\Core\Result;

class ProductCatalogResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'catalog://products';
    }

    public function getName(): string
    {
        return 'Product Catalog';
    }

    public function getDescription(): string
    {
        return 'Complete product catalog with categories and metadata';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function isTemplate(): bool
    {
        return false;
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        return $context->hasPermission('resources.catalog') ||
               $context->hasPermission('products.read');
    }

    public function getAccessibleFields(ContextInterface $context): array
    {
        return $context->getAccessibleFields('product');
    }

    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        $accessibleFields = $this->getAccessibleFields($context);
        
        // Cache based on client permissions
        $cacheKey = 'mcp.catalog.products.' . md5(json_encode($accessibleFields));
        
        $data = Cache::remember($cacheKey, 300, function () use ($accessibleFields) {
            $query = Product::with('categories');
            
            if (!in_array('*', $accessibleFields)) {
                $query->select($accessibleFields);
            }
            
            return [
                'products' => $query->get()->toArray(),
                'categories' => Category::all(['id', 'name', 'description'])->toArray(),
            ];
        });

        return Result::success([
            ...$data,
            'metadata' => [
                'total_products' => count($data['products']),
                'total_categories' => count($data['categories']),
                'accessible_fields' => $accessibleFields,
                'last_updated' => now()->toISOString(),
            ]
        ], [
            'cached' => true,
            'cache_key' => $cacheKey
        ]);
    }
}
```

### 2. Template Resources

Resources that accept parameters in the URI:

```php
class ProductResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'product://{product_id}';
    }

    public function isTemplate(): bool
    {
        return true;
    }

    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        // Extract product_id from URI: product://123
        $productId = str_replace('product://', '', $uri);
        
        if (!$context->hasFieldAccess('product', 'id')) {
            return Result::error('Access denied to product data');
        }
        
        $accessibleFields = $context->getAccessibleFields('product');
        $product = Product::find($productId);
        
        if (!$product) {
            return Result::error('Product not found');
        }
        
        $filteredProduct = in_array('*', $accessibleFields) 
            ? $product->toArray() 
            : $product->only($accessibleFields);
        
        return Result::success($filteredProduct, [
            'product_id' => $productId,
            'accessible_fields' => $accessibleFields
        ]);
    }
}
```

### 3. Register Your Resources

```php
// config/mcp.php
'custom' => [
    'resources' => [
        \App\Services\Custom\MCP\Resources\ProductCatalogResource::class,
        \App\Services\Custom\MCP\Resources\ProductResource::class,
    ],
]
```

## Security

### Field-Level Access Control

Control which fields clients can access at a granular level:

```php
'clients' => [
    'public_api' => [
        'field_access' => [
            'user' => ['name', 'avatar'],           // Public fields only
            'product' => ['name', 'price', 'description'], // No internal data
            'order' => [],                          // No access to orders
        ]
    ],
    'partner_api' => [
        'field_access' => [
            'user' => ['name', 'email', 'phone'],   // More fields for partners
            'product' => ['*'],                     // All product data
            'order' => ['id', 'status', 'total'],  // Limited order access
        ]
    ],
    'internal_admin' => [
        'permissions' => ['admin'],              // Full access
        'field_access' => ['*']                  // All fields, all entities
    ]
]
```

### Middleware Stack

The package includes comprehensive security middleware:

1. **MCPSecurityMiddleware**: HTTPS enforcement, IP whitelisting, security headers
2. **MCPAuthMiddleware**: Multi-method authentication with custom authenticators
3. **MCPThrottleMiddleware**: Rate limiting with per-client and burst controls
4. **MCPLoggingMiddleware**: Request/response logging with performance metrics

### Environment-Based Security

```env
# Production Security Settings
MCP_REQUIRE_HTTPS=true
MCP_ALLOWED_IPS=203.0.113.0/24,192.168.1.0/24
MCP_BLOCK_SUSPICIOUS_UA=true

# Never enable these in production
MCP_DEBUG_EXPOSE_SYSTEM_INFO=false
MCP_DEBUG_DETAILED_ERRORS=false
MCP_DEBUG_LOG_REQUESTS=false
```

### IP Whitelisting

Support for both individual IPs and CIDR notation:

```env
# Individual IPs
MCP_ALLOWED_IPS=203.0.113.1,203.0.113.2

# CIDR ranges
MCP_ALLOWED_IPS=192.168.1.0/24,10.0.0.0/8

# Mixed
MCP_ALLOWED_IPS=203.0.113.1,192.168.1.0/24,10.0.0.0/8
```

### Rate Limiting

Configure rate limits globally and per-client:

```env
# Global Defaults
MCP_RATE_LIMIT=60        # Requests per minute
MCP_BURST_LIMIT=10       # Short-term burst allowance

# Per-client limits in config/mcp.php
'clients' => [
    'high_volume_client' => [
        'rate_limit' => [
            'requests_per_minute' => 1000,
            'burst_limit' => 100,
        ],
    ],
]
```

## What's New in v1.0.0 🎉

### Enhanced Permission Management
- **🔗 Optional Bouncer Integration**: Seamlessly integrate with Laravel Bouncer for advanced role-based permissions
- **🔄 Permission Manager Architecture**: Pluggable permission system with automatic fallback
- **⚡ Performance Optimizations**: Registry template matching with compiled pattern caching
- **🛡️ Enhanced Security**: Improved authentication flow and circular dependency prevention

### New Features
- **🚀 MCP Setup Command**: `php artisan mcp:setup --bouncer` for easy configuration
- **📊 Comprehensive Test Suite**: 74 tests covering all major functionality
- **🎛️ Flexible Authentication**: Multiple storage patterns for API keys (database, user columns, JSON)
- **📝 Better Documentation**: Enhanced examples, troubleshooting guides, and migration instructions

### Developer Experience
- **✨ PSR-12 Compliance**: Full code formatting standards with .editorconfig
- **🔧 Auto-Detection**: Automatic Bouncer package detection and configuration
- **📚 Rich Examples**: Comprehensive examples for both basic and Bouncer usage
- **🐛 Improved Error Handling**: Better validation and error messages

## Quick Setup with Bouncer (Optional)

If you want enhanced permission management with Laravel Bouncer:

```bash
# Install Bouncer (optional)
composer require silber/bouncer

# Setup MCP with Bouncer integration
php artisan mcp:setup --bouncer

# Enable Bouncer in your .env
MCP_BOUNCER_ENABLED=true
```

## Testing

### Comprehensive Test Suite (v1.0.0)

The package includes **74 comprehensive tests** covering:

- ✅ **Core MCP Protocol**: Initialize, tools/list, tools/call, resources/*
- ✅ **Authentication**: API keys, Basic auth, Bearer tokens, custom authenticators  
- ✅ **Permission Management**: Default and Bouncer permission managers
- ✅ **Bouncer Integration**: Package detection, fallback behavior, configuration
- ✅ **Registry Performance**: Template matching, caching, memory optimization
- ✅ **Security**: Access control, rate limiting, validation
- ✅ **HTTP Integration**: Middleware, routing, error handling

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit tests/Feature/
vendor/bin/phpunit tests/Unit/

# Run with coverage
composer test-coverage

# Individual test files
vendor/bin/phpunit tests/Feature/MCPServerTest.php
vendor/bin/phpunit tests/Feature/AuthenticationTest.php
vendor/bin/phpunit tests/Unit/PermissionManagerTest.php
vendor/bin/phpunit tests/Feature/BouncerIntegrationTest.php
vendor/bin/phpunit tests/Unit/RegistryTest.php
```

### Performance Benchmarks

The v1.0.0 test suite includes performance validation:

- **Template URI Matching**: 1000 matches complete in <100ms
- **Memory Usage**: 1000 tool/resource registrations use <5MB  
- **Authentication**: Cached permission lookups for optimal performance
- **Registry**: Compiled pattern caching for repeated template matches

### Running Package Tests

```bash
composer test
```

### Testing Your Implementation

```bash
# List available tools
curl -X POST http://localhost/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: your_key" \
  -d '{"jsonrpc": "2.0", "method": "tools/list", "id": 1}'

# Execute a tool with structured query
curl -X POST http://localhost/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: your_key" \
  -d '{
    "jsonrpc": "2.0",
    "method": "tools/call",
    "params": {
      "name": "search_products",
      "arguments": {
        "filters": [
          {"field": "category", "operator": "=", "value": "electronics"},
          {"field": "price", "operator": "<", "value": 500}
        ],
        "limit": 5,
        "sort": {"field": "name", "direction": "asc"}
      }
    },
    "id": 2
  }'

# Read a static resource
curl -X POST http://localhost/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: your_key" \
  -d '{
    "jsonrpc": "2.0",
    "method": "resources/read",
    "params": {"uri": "catalog://products"},
    "id": 3
  }'

# Read a template resource
curl -X POST http://localhost/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-MCP-API-Key: your_key" \
  -d '{
    "jsonrpc": "2.0",
    "method": "resources/read",
    "params": {"uri": "product://123"},
    "id": 4
  }'

# Test with database authentication
curl -X POST http://localhost/api/mcp \
  -H "Content-Type: application/json" \
  -H "X-User-Token: user_token_xyz" \
  -H "X-User-ID: 123" \
  -d '{"jsonrpc": "2.0", "method": "tools/list", "id": 5}'
```

## MCP Protocol Methods

The package implements all standard MCP methods:

| Method | Description | Response |
|--------|-------------|----------|
| `initialize` | Initialize MCP session | Server capabilities and info |
| `tools/list` | List available tools | Array of tool definitions |
| `tools/call` | Execute a tool | Tool execution result |
| `resources/list` | List available static resources | Array of resource definitions |
| `resources/read` | Read resource content | Resource content |
| `resources/templates/list` | List template resources | Array of template definitions |

## Error Handling

The package provides standardized JSON-RPC 2.0 error responses:

| Code | Meaning | When Used |
|------|---------|-----------|
| `-32001` | Authentication required | Invalid/missing credentials |
| `-32002` | Access denied | Insufficient permissions |
| `-32003` | Rate limit exceeded | Too many requests |
| `-32602` | Invalid params | Missing/invalid parameters |
| `-32603` | Internal error | Server-side errors |

### Exception Types

The package uses specific exception types for better error handling:

- **`MCPAuthenticationException`**: Thrown for authentication failures, invalid client IDs, or authorization issues
- **Client ID Validation**: Client IDs must be alphanumeric with dots, hyphens, or underscores only (max 255 chars)

### Input Validation

Client IDs are automatically validated and must meet these requirements:
- Non-empty
- Maximum 255 characters
- Only alphanumeric characters, dots (.), hyphens (-), and underscores (_)
- Invalid characters will throw `MCPAuthenticationException`

Example error response:
```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32002,
    "message": "Access denied",
    "data": {
      "required_permission": "tools.search_products",
      "client_permissions": ["tools.echo", "resources.status"]
    }
  },
  "id": 1
}
```

## Performance Considerations

### Caching Strategies

Implement intelligent caching in your custom tools and resources:

```php
public function getContent(string $uri, ContextInterface $context): ResultInterface
{
    // Cache based on URI and client permissions
    $cacheKey = "mcp.resource.{$uri}." . md5(json_encode([
        $context->getClientId(),
        $context->getAccessibleFields('product')
    ]));
    
    $data = Cache::remember($cacheKey, 300, function() use ($uri, $context) {
        return $this->fetchExpensiveData($uri, $context);
    });
    
    return Result::success($data, ['cached' => true]);
}
```

### Database Optimization

- Use appropriate indexes for your search fields
- Implement field selection based on access permissions
- Consider read replicas for heavy MCP usage
- Use `select()` to limit returned columns

```php
// Optimize queries based on field access
$accessibleFields = $context->getAccessibleFields('product');
$query = Product::query();

if (!in_array('*', $accessibleFields)) {
    $query->select($accessibleFields);
}
```

### Authentication Performance

- Database authenticators use intelligent caching
- Cache duration is configurable per environment
- Failed authentication attempts are rate limited
- Async updates (last_used_at) don't block requests

```php
// config/mcp.php
'auth' => [
    'cache_duration' => 300, // 5 minutes
    'rate_limit_failed_attempts' => 10, // Per IP per hour
],
```

### Monitoring

Use the built-in logging to monitor performance:

```bash
# View MCP request logs
tail -f storage/logs/mcp.log

# Monitor slow requests (>500ms)
grep "duration_ms.*[5-9][0-9][0-9]" storage/logs/mcp.log

# Check authentication patterns
grep "auth_type" storage/logs/mcp.log | sort | uniq -c

# Monitor rate limiting
grep "rate.*limit" storage/logs/mcp.log
```

## User Management

### Creating API Keys for Users

```php
// Generate API keys programmatically
$user = User::find(1);

// Basic API key
$apiKey = $user->generateMCPApiKey('mobile_app_v1');

// API key with specific scopes
$apiKey = $user->generateMCPApiKey('analytics_dashboard', [
    'tools.search_products',
    'resources.catalog',
    'products.read'
], 'Analytics Dashboard Key');

// Check key validity
$isValid = $user->isMCPApiKeyValid($apiKey->key);

// Get key information (safe - no actual key exposed)
$keyInfo = $user->getMCPApiKeyInfo($apiKey->key);

// Get summary of all user's keys
$summary = $user->getMCPApiKeysSummary();
```

### User Permission Management

```php
// Enable MCP access for a user
$user->update(['mcp_enabled' => true]);

// Set user permissions
$user->update([
    'mcp_permissions' => [
        'tools.search_products',
        'tools.get_inventory',
        'resources.catalog'
    ]
]);

// Set field access
$user->update([
    'mcp_field_access' => [
        'product' => ['name', 'price', 'category'],
        'user' => ['name', 'email']
    ]
]);

// Check permissions
$hasAccess = $user->hasMCPPermission('tools.search_products');
$hasFieldAccess = $user->hasMCPFieldAccess('product', 'price');
```

### Bulk Operations

```php
// Revoke all keys for a user
$revokedCount = $user->revokeAllMCPApiKeys();

// Clean up expired keys
$cleanedCount = $user->cleanupExpiredMCPApiKeys();

// Enable MCP for multiple users
User::whereIn('id', [1, 2, 3])->update(['mcp_enabled' => true]);

// Find users with specific permissions
$apiUsers = User::withMCPAccess()
    ->whereJsonContains('mcp_permissions', 'tools.search_products')
    ->get();
```

## Deployment

### Production Checklist

- [ ] Set `MCP_DEBUG_*` variables to `false`
- [ ] Use strong API keys (32+ characters)
- [ ] Configure rate limits appropriate for your infrastructure
- [ ] Enable HTTPS (`MCP_REQUIRE_HTTPS=true`)
- [ ] Set up IP whitelisting if applicable
- [ ] Configure proper logging levels
- [ ] Set up log rotation for MCP logs
- [ ] Test all middleware is properly registered
- [ ] Verify error responses don't expose sensitive information
- [ ] Set up monitoring for MCP endpoints
- [ ] Configure database indexes for API key lookups
- [ ] Test authentication performance under load

### Environment Variables

```env
# Production Settings
APP_ENV=production
MCP_REQUIRE_HTTPS=true
MCP_DEBUG_EXPOSE_SYSTEM_INFO=false
MCP_DEBUG_DETAILED_ERRORS=false

# Security
MCP_ALLOWED_IPS=your.trusted.networks
MCP_BLOCK_SUSPICIOUS_UA=true

# Performance
MCP_RATE_LIMIT=1000
MCP_BURST_LIMIT=50
MCP_LOG_PERFORMANCE=true
MCP_AUTH_CACHE_DURATION=300

# Monitoring
MCP_LOG_REQUESTS=true
MCP_LOG_LEVEL=warning
MCP_LOG_RETENTION_DAYS=30

# Database Authentication
MCP_USER_MODEL=App\Models\User
MCP_USER_FOREIGN_KEY=user_id
MCP_USER_OWNER_KEY=id
```

### Docker

```dockerfile
# Add to your Dockerfile
COPY config/mcp.php /var/www/config/
RUN php artisan config:cache

# Ensure logs directory is writable
RUN mkdir -p /var/www/storage/logs && \
    chown -R www-data:www-data /var/www/storage

# Add database indexes for performance
COPY database/migrations/add_mcp_indexes.php /var/www/database/migrations/
```

### Database Indexes

Create proper indexes for optimal performance:

```php
// database/migrations/add_mcp_indexes.php
public function up(): void
{
    Schema::table('api_keys', function (Blueprint $table) {
        $table->index(['key', 'is_active']);
        $table->index(['user_id', 'is_active']);
        $table->index(['client_identifier']);
        $table->index(['expires_at']);
    });

    Schema::table('users', function (Blueprint $table) {
        $table->index('mcp_enabled');
    });
}
```

### Load Balancing

MCP servers are stateless and can be load balanced normally. Consider:

- Session affinity not required
- Rate limiting may need shared storage (Redis)
- Resource caching benefits from shared cache
- Log aggregation for monitoring across instances
- Database connection pooling for authentication

### Monitoring Setup

```bash
# Set up log monitoring
tail -f storage/logs/mcp.log | grep ERROR

# Monitor authentication failures
grep "Authentication.*failed" storage/logs/mcp.log

# Track performance metrics
grep "duration_ms" storage/logs/mcp.log | awk '{print $6}' | sort -n

# Monitor rate limiting
grep "rate.*limit.*exceeded" storage/logs/mcp.log
```

## Troubleshooting

### Common Issues

**Middleware Not Found Error**
```bash
# Laravel 11 - Check bootstrap/app.php has middleware aliases
grep -A 10 "withMiddleware" bootstrap/app.php

# Laravel 10 - Ensure service provider is registered
php artisan config:show app.providers

# Verify route middleware
php artisan route:show mcp.handle
```

**Tool/Resource Not Found**
```bash
# Check registration in config/mcp.php
php artisan config:show mcp.custom

# Verify class exists and implements correct interface
php artisan tinker
>>> class_exists(\App\Services\Custom\MCP\Tools\YourTool::class)
>>> class_implements(\App\Services\Custom\MCP\Tools\YourTool::class)

# Clear application cache
php artisan optimize:clear
```

**Database Authentication Issues**
```bash
# Check user model configuration
php artisan config:show mcp.auth.user_model

# Test user has MCP access
php artisan tinker
>>> $user = User::find(1)
>>> $user->hasMCPAccess()
>>> $user->getMCPPermissions()

# Check API key generation
>>> $key = $user->generateMCPApiKey('test')
>>> $user->isMCPApiKeyValid($key->key)
```

**Client ID Validation Errors**
```bash
# MCPAuthenticationException: Invalid client ID format
# Client IDs must be alphanumeric with dots, hyphens, or underscores only

# Valid client IDs:
# ✅ "mobile_app"
# ✅ "api-client"  
# ✅ "client.v1"
# ❌ "client@domain"
# ❌ "client/path"
# ❌ "client with spaces"

# Test client ID validation
php artisan tinker
>>> app(\ChaoticIngenuity\LaravelMCP\Core\ContextFactory::class)->createFromClient('test-client')
```

**Field Access Issues**
```bash
# Field access permissions are now working correctly (v1.0.0 bug fix)
# Test field access in tinker
php artisan tinker
>>> $user = User::find(1)
>>> $user->hasMCPFieldAccess('product', 'price')
>>> $user->getMCPFieldAccess()
```

**Access Denied Errors**
```bash
# Check client permissions in config/mcp.php
php artisan config:show mcp.auth.clients

# Verify field-level access configuration
php artisan tinker
>>> $context = app(\ChaoticIngenuity\LaravelMCP\Core\ContextFactory::class)->createFromClient('your_client')
>>> $context->hasPermission('tools.your_tool')
>>> $context->hasFieldAccess('product', 'price')

# Check authentication headers are correct
curl -v -H "X-MCP-API-Key: your_key" http://localhost/api/mcp
```

**Rate Limit Issues**
```bash
# Check current rate limit settings
php artisan config:show mcp.rate_limit

# Verify client identification is working
grep "mcp_client" storage/logs/mcp.log

# Clear rate limit cache if needed
php artisan cache:forget "mcp_throttle:your_client"

# Check for burst limit hits
grep "burst.*limit" storage/logs/mcp.log
```

### Debug Mode

Enable debug mode for development:

```env
MCP_DEBUG_DETAILED_ERRORS=true
MCP_DEBUG_LOG_REQUESTS=true
MCP_LOG_LEVEL=debug
MCP_DEBUG_EXPOSE_SYSTEM_INFO=true
```

Check logs for detailed information:

```bash
# View all MCP logs
tail -f storage/logs/mcp.log

# Filter by specific client
grep "client_1" storage/logs/mcp.log

# Find slow requests (>1 second)
grep "duration_ms.*[1-9][0-9][0-9][0-9]" storage/logs/mcp.log

# Check authentication flow
grep -E "(Authentication|auth_type)" storage/logs/mcp.log

# Monitor memory usage
grep "memory_mb" storage/logs/mcp.log | sort -k6 -n
```

### Performance Debugging

Monitor and optimize performance:

```bash
# Average request duration
grep "duration_ms" storage/logs/mcp.log | \
  awk '{print $6}' | awk '{sum+=$1; count++} END {print sum/count}'

# Memory usage patterns
grep "memory_mb" storage/logs/mcp.log | \
  awk '{print $8}' | sort -n | tail -10

# Most used tools/resources
grep -E "(tool|resource)" storage/logs/mcp.log | \
  awk '{print $4}' | sort | uniq -c | sort -nr

# Authentication method distribution
grep "auth_type" storage/logs/mcp.log | \
  awk '{print $7}' | sort | uniq -c
```

### Testing Authentication

```php
// Test database authentication in tinker
php artisan tinker

// Create a test user and API key
>>> $user = User::first()
>>> $user->update(['mcp_enabled' => true])
>>> $key = $user->generateMCPApiKey('test_client', ['tools.*'])
>>> echo $key->key

// Test key validation
>>> $user->isMCPApiKeyValid($key->key)
>>> $user->getMCPApiKeyInfo($key->key)

// Test custom authenticator
>>> $auth = app(\App\Services\Custom\MCP\Auth\DatabaseAuthenticator::class)
>>> $result = $auth->authenticate('api_key', ['api_key' => $key->key])
>>> $result->isSuccess()
>>> $result->getClientId()
```

## Laravel Version Compatibility

### Laravel 11
- ✅ Fully supported
- ⚠️ Requires manual middleware registration in `bootstrap/app.php`
- ✅ Uses new application structure
- ✅ Follows Laravel 11 conventions

### Laravel 10
- ✅ Fully supported
- ✅ Automatic middleware registration
- ✅ Traditional `app/Http/Kernel.php` structure

### Migration from Laravel 10 to 11

If upgrading your Laravel application:

1. Update middleware registration in `bootstrap/app.php`
2. Remove any manual middleware registration from `app/Http/Kernel.php`
3. Test all MCP endpoints still work
4. Update any custom middleware following Laravel 11 patterns

## Advanced Configuration

### Custom Context Factory

Create custom client context logic:

```php
// app/Services/Custom/MCP/CustomContextFactory.php
class CustomContextFactory extends ContextFactory
{
    public function createFromClient(string $clientId): ContextInterface
    {
        // Custom logic for determining client permissions
        if (str_starts_with($clientId, 'user_')) {
            $userId = str_replace('user_', '', $clientId);
            $user = User::find($userId);
            
            return new Context(
                clientId: $clientId,
                permissions: $user->getMCPPermissions(),
                fieldAccess: $user->getMCPFieldAccess(),
                metadata: ['user_id' => $userId, 'tier' => $user->subscription_tier]
            );
        }
        
        return parent::createFromClient($clientId);
    }
}

// Register in service provider
$this->app->singleton(ContextFactory::class, CustomContextFactory::class);
```

### Multi-Tenant Support

Configure MCP for multi-tenant applications:

```php
class TenantAwareMCPMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $tenant = $this->resolveTenant($request);
        
        if (!$tenant) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32001, 'message' => 'Invalid tenant']
            ], 401);
        }
        
        // Set tenant context
        $request->merge(['mcp_tenant' => $tenant->id]);
        
        return $next($request);
    }
}
```

### Custom Schema Validation

Add JSON Schema validation for tool inputs:

```php
use JsonSchema\Validator;

public function execute(array $arguments, ContextInterface $context): ResultInterface
{
    // Validate against schema
    $validator = new Validator();
    $validator->validate($arguments, $this->getInputSchema());
    
    if (!$validator->isValid()) {
        $errors = array_map(fn($error) => $error['message'], $validator->getErrors());
        return Result::error('Validation failed: ' . implode(', ', $errors));
    }
    
    // Continue with execution...
}
```

## Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Add tests for new functionality
4. Ensure all tests pass (`composer test`)
5. Follow PSR-12 coding standards (`composer format`)
6. Update documentation as needed
7. Submit a pull request

### Development Setup

```bash
# Clone the repository
git clone https://github.com/chaoticingenuity/laravel-mcp-server.git
cd laravel-mcp-server

# Install dependencies
composer install

# Run tests
composer test

# Run code formatting
composer format

# Run static analysis
composer analyse
```

### Package Structure

```
src/
├── Contracts/           # Interfaces and contracts
├── Core/               # Core functionality (Registry, Context, etc.)
├── Auth/               # Authentication system
├── Http/               # Controllers and middleware
├── Tools/              # Built-in tools
├── Resources/          # Built-in resources
├── Traits/             # Reusable traits
└── Providers/          # Service providers

config/                 # Configuration files
routes/                 # Route definitions
tests/                  # Test suite
examples/               # Usage examples
stubs/                  # Code generation stubs
```

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

## Support

- **Documentation**: [GitHub Wiki](https://github.com/chaoticingenuity/laravel-mcp-server/wiki)
- **Issues**: [GitHub Issues](https://github.com/chaoticingenuity/laravel-mcp-server/issues)
- **Discussions**: [GitHub Discussions](https://github.com/chaoticingenuity/laravel-mcp-server/discussions)
- **Security Issues**: Email security@chaoticingenuity.com

## Credits

- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
- [Laravel Framework](https://laravel.com/)
- [JSON-RPC 2.0 Specification](https://www.jsonrpc.org/specification)
- All contributors who have helped improve this package

## Related Packages

- **Laravel Sanctum**: For API token authentication
- **Laravel Passport**: For OAuth2 authentication
- **Laravel Telescope**: For debugging and monitoring
- **Laravel Horizon**: For queue monitoring
- **Spatie Laravel Permission**: For role-based permissions
