# Laravel MCP Server - Enhancement Roadmap

This document tracks planned enhancements based on comprehensive code reviews and performance analysis. Items are prioritized and will be removed as they are implemented.

## 🔥 HIGH Priority (Implement Immediately)

### 1. Batch Permission Checking
**File**: `src/Core/Context.php`
**Benefit**: Reduce overhead when tools need multiple permissions checked
**Implementation**:
```php
public function hasMultiplePermissions(array $permissions): array
{
    if (!isset($this->compiledPermissions)) {
        $this->compilePermissions();
    }
    
    $results = [];
    foreach ($permissions as $permission) {
        $results[$permission] = $this->hasPermission($permission);
    }
    
    return $results;
}

// Usage in tools:
$permissionResults = $context->hasMultiplePermissions(['tools.read', 'tools.write', 'data.export']);
if ($permissionResults['tools.read'] && $permissionResults['data.export']) {
    // Proceed with operation
}
```

### 2. Permission Result Caching
**File**: `src/Core/Context.php`
**Benefit**: Cache frequently checked permissions within same request context
**Implementation**:
```php
private array $permissionCache = [];

public function hasPermission(string $permission): bool
{
    if (isset($this->permissionCache[$permission])) {
        return $this->permissionCache[$permission];
    }
    
    $result = $this->computePermission($permission);
    
    // Cache result if caching is enabled
    if (config('mcp.performance.cache_permission_results', true)) {
        $this->permissionCache[$permission] = $result;
    }
    
    return $result;
}

private function computePermission(string $permission): bool
{
    // Move existing hasPermission logic here
    // ... existing implementation
}
```

### 3. Database Index Optimization
**File**: Create migration `database/migrations/add_mcp_performance_indexes.php`
**Benefit**: Significantly faster API key and user lookups
**Implementation**:
```php
public function up(): void
{
    Schema::table('api_keys', function (Blueprint $table) {
        // Composite index for most common lookup pattern
        $table->index(['key', 'is_active', 'expires_at'], 'api_keys_lookup_idx');
        
        // Separate indexes for other common queries
        $table->index(['user_id', 'is_active'], 'api_keys_user_active_idx');
        $table->index(['client_identifier', 'is_active'], 'api_keys_client_active_idx');
        $table->index('last_used_at', 'api_keys_last_used_idx');
        $table->index('usage_count', 'api_keys_usage_idx');
    });

    Schema::table('users', function (Blueprint $table) {
        $table->index(['mcp_enabled'], 'users_mcp_enabled_idx');
    });
}
```

### 4. Enhanced API Key Validation Query
**File**: `src/Traits/HasMCPAuthentication.php:76-82`
**Benefit**: More efficient database queries with explicit conditions
**Implementation**:
```php
public function isMCPApiKeyValid(string $key): bool
{
    // Cache key validation results for short duration
    $cacheKey = 'mcp.key.valid.' . hash('sha256', $key . $this->getKey());
    
    return Cache::remember($cacheKey, 60, function () use ($key) {
        return $this->apiKeys()
            ->select('id') // Only select what we need for existence check
            ->where('key', $key)
            ->where('is_active', true) // Explicit condition helps with index usage
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->exists();
    });
}
```

## 🚀 MEDIUM Priority (Next Sprint)

### 1. Event-Driven Architecture
**Files**: Create new events in `src/Events/`
**Benefit**: Better extensibility and monitoring capabilities
**Implementation**:
```php
// src/Events/MCPPermissionChecked.php
class MCPPermissionChecked
{
    public function __construct(
        public readonly string $permission,
        public readonly bool $granted,
        public readonly string $clientId,
        public readonly float $checkDuration
    ) {}
}

// src/Events/MCPApiKeyUsed.php  
class MCPApiKeyUsed
{
    public function __construct(
        public readonly string $keyId,
        public readonly string $clientId,
        public readonly array $metadata,
        public readonly string $endpoint
    ) {}
}

// Usage in Context.php
private function trackPermissionCheck(string $permission, bool $granted): void
{
    $startTime = microtime(true);
    
    if (config('mcp.performance.track_permissions', false)) {
        Cache::increment("mcp.perms.{$permission}." . ($granted ? 'granted' : 'denied'));
        
        // Dispatch event for listeners
        event(new MCPPermissionChecked(
            $permission,
            $granted,
            $this->getClientId(),
            (microtime(true) - $startTime) * 1000
        ));
    }
}
```

### 2. Resolver Manager Memory Optimization
**File**: `src/Auth/PermissionResolverManager.php:19-24`
**Benefit**: Avoid unnecessary sorting on every registration
**Implementation**:
```php
class PermissionResolverManager
{
    private Collection $resolvers;
    private bool $isSorted = false;
    private ?PermissionResolverInterface $cachedResolver = null;
    private $lastUserId = null;

    public function registerResolver(PermissionResolverInterface $resolver): void
    {
        $this->resolvers->push($resolver);
        $this->isSorted = false; // Mark as needing sort
        $this->cachedResolver = null; // Clear cache
    }

    private function ensureSorted(): void
    {
        if (!$this->isSorted) {
            $this->resolvers = $this->resolvers->sortByDesc(fn($r) => $r->getPriority());
            $this->isSorted = true;
        }
    }

    private function getResolverForUser($user): ?PermissionResolverInterface
    {
        // Simple caching for same user within request
        $userId = method_exists($user, 'getKey') ? $user->getKey() : spl_object_id($user);
        
        if ($this->lastUserId === $userId && $this->cachedResolver) {
            return $this->cachedResolver;
        }

        $this->ensureSorted();
        
        $resolver = $this->resolvers->first(function (PermissionResolverInterface $resolver) use ($user) {
            return $resolver->canResolve($user);
        });

        // Cache for this user
        $this->lastUserId = $userId;
        $this->cachedResolver = $resolver;
        
        return $resolver;
    }
}
```

### 3. Permission Policy Classes
**Files**: Create `src/Contracts/PermissionPolicyInterface.php` and implementations
**Benefit**: Handle complex permission scenarios with dedicated classes
**Implementation**:
```php
// src/Contracts/PermissionPolicyInterface.php
interface PermissionPolicyInterface
{
    public function evaluate($user, string $permission, array $context = []): bool;
    public function getDescription(): string;
    public function getPriority(): int;
}

// src/Auth/Policies/TimeBasedPermissionPolicy.php
class TimeBasedPermissionPolicy implements PermissionPolicyInterface
{
    public function evaluate($user, string $permission, array $context = []): bool
    {
        $timeRestrictions = $user->getMCPTimeRestrictions() ?? [];
        
        if (empty($timeRestrictions)) {
            return true; // No restrictions
        }
        
        $currentHour = now()->hour;
        return $currentHour >= ($timeRestrictions['start_hour'] ?? 0) &&
               $currentHour <= ($timeRestrictions['end_hour'] ?? 23);
    }

    public function getDescription(): string
    {
        return 'Evaluates time-based access restrictions';
    }

    public function getPriority(): int
    {
        return 10;
    }
}

// src/Auth/Policies/IPBasedPermissionPolicy.php
class IPBasedPermissionPolicy implements PermissionPolicyInterface
{
    public function evaluate($user, string $permission, array $context = []): bool
    {
        $allowedIPs = $user->getMCPAllowedIPs() ?? [];
        
        if (empty($allowedIPs)) {
            return true;
        }
        
        $clientIP = $context['ip'] ?? request()->ip();
        return $this->isIPAllowed($clientIP, $allowedIPs);
    }
    
    private function isIPAllowed(string $ip, array $allowedIPs): bool
    {
        foreach ($allowedIPs as $allowedIP) {
            if (str_contains($allowedIP, '/')) {
                // CIDR notation
                if ($this->ipInCIDR($ip, $allowedIP)) {
                    return true;
                }
            } elseif ($ip === $allowedIP) {
                return true;
            }
        }
        return false;
    }
}
```

## 🔮 LOW Priority (Future Enhancements)

### 1. Permission Middleware Pipeline
**Files**: Create `src/Auth/PermissionMiddlewarePipeline.php`
**Benefit**: Advanced permission evaluation with middleware chain
**Implementation**:
```php
class PermissionMiddlewarePipeline
{
    private array $middleware = [];
    
    public function through(array $middleware): self
    {
        $this->middleware = $middleware;
        return $this;
    }
    
    public function evaluate(ContextInterface $context, string $permission, array $pipelineContext = []): bool
    {
        return array_reduce($this->middleware, function ($carry, $middleware) use ($context, $permission, $pipelineContext) {
            if (!$carry) {
                return false; // Short-circuit on first failure
            }
            
            if (is_string($middleware)) {
                $middleware = app($middleware);
            }
            
            return $middleware->handle($context, $permission, $pipelineContext);
        }, true);
    }
}

// Usage in advanced scenarios:
$pipeline = new PermissionMiddlewarePipeline();
$granted = $pipeline
    ->through([
        TimeBasedPermissionPolicy::class,
        IPBasedPermissionPolicy::class,
        RateLimitPermissionPolicy::class,
    ])
    ->evaluate($context, 'tools.sensitive_operation', [
        'ip' => request()->ip(),
        'user_agent' => request()->userAgent(),
    ]);
```

### 2. Metrics Dashboard Components
**Files**: Create `src/Http/Controllers/MCPMetricsController.php`
**Benefit**: Built-in analytics and monitoring dashboard
**Implementation**:
```php
class MCPMetricsController extends Controller
{
    public function permissionMetrics()
    {
        $metrics = [];
        $permissions = Cache::get('mcp.tracked_permissions', []);
        
        foreach ($permissions as $permission) {
            $granted = Cache::get("mcp.perms.{$permission}.granted", 0);
            $denied = Cache::get("mcp.perms.{$permission}.denied", 0);
            
            $metrics[$permission] = [
                'granted' => $granted,
                'denied' => $denied,
                'total' => $granted + $denied,
                'success_rate' => $granted + $denied > 0 ? ($granted / ($granted + $denied)) * 100 : 0
            ];
        }
        
        return response()->json($metrics);
    }

    public function apiKeyUsage()
    {
        return ApiKey::select('id', 'client_identifier', 'usage_count', 'last_used_at')
            ->where('is_active', true)
            ->orderByDesc('usage_count')
            ->limit(50)
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'client' => $key->client_identifier,
                    'usage_count' => $key->usage_count,
                    'last_used' => $key->last_used_at?->diffForHumans(),
                    'masked_key' => $key->masked_key
                ];
            });
    }
}
```

### 3. A/B Testing Framework for Permissions
**Files**: Create `src/Testing/PermissionVariantManager.php`
**Benefit**: Test different permission strategies with real traffic
**Implementation**:
```php
class PermissionVariantManager
{
    public function evaluateWithVariant(ContextInterface $context, string $permission): array
    {
        $variant = $this->getVariantForUser($context->getClientId());
        
        $results = [
            'control' => $this->evaluateControl($context, $permission),
            'treatment' => $this->evaluateTreatment($context, $permission, $variant),
            'variant' => $variant
        ];
        
        // Log for analysis
        $this->logVariantResult($context, $permission, $results);
        
        // Return control result for now, treatment for analysis
        return $results['control'];
    }
    
    private function getVariantForUser(string $clientId): string
    {
        // Consistent variant assignment based on client ID
        return (crc32($clientId) % 100) < 10 ? 'treatment' : 'control';
    }
}
```

## 📋 Implementation Notes

### Completion Tracking
- [ ] Batch Permission Checking
- [ ] Permission Result Caching  
- [ ] Database Index Optimization
- [ ] Enhanced API Key Validation Query
- [ ] Event-Driven Architecture
- [ ] Resolver Manager Memory Optimization
- [ ] Permission Policy Classes
- [ ] Permission Middleware Pipeline
- [ ] Metrics Dashboard Components
- [ ] A/B Testing Framework

### Testing Requirements
Each enhancement should include:
- Unit tests for new functionality
- Performance benchmarks comparing before/after
- Integration tests with existing features
- Documentation updates in README.md

### Configuration Requirements
New configuration options should be:
- Added to `config/mcp.php` with sensible defaults
- Documented with environment variable examples
- Include performance impact notes

---

**Note**: Remove completed items from this file and update the implementation status as features are added to the package.