# Resource Relationship Field Access

The Laravel MCP Server supports advanced field access control based on the user's relationship to specific resource instances. This allows for dynamic field access that goes beyond simple static permissions.

## Overview

Traditional field access is static - a user either has access to a field type for all resources or none. Resource Relationship Field Access allows you to define different field access levels based on the user's relationship to each individual resource.

**Examples:**
- **Real Estate**: Users see IDX fields for all listings, but private fields only for listings they own/manage
- **Project Management**: Users see basic project info for all projects, but sensitive data only for projects they're assigned to
- **E-commerce**: Users see product info, but financial data only for products they manage

## Architecture

The system uses three main interfaces that users implement:

### 1. ResourceRelationshipInterface

Defines a relationship between a user and a resource:

```php
interface ResourceRelationshipInterface
{
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool;
    public function getName(): string;
    public function getPriority(): int;
}
```

### 2. FieldSetResolverInterface

Resolves which fields are accessible based on matched relationships:

```php
interface FieldSetResolverInterface
{
    public function getBaseFields(string $resourceType): array;
    public function getAdditionalFields(string $resourceType, array $matchedRelationships): array;
    public function getMergeStrategy(): string; // 'union', 'intersection', 'override'
    public function getRelationships(): array;
    public function resolveFieldSet(string $resourceType, array $matchedRelationships): array;
}
```

### 3. Enhanced ContextInterface

Extended with resource-specific field access methods:

```php
// New methods in ContextInterface
public function getAccessibleFieldsForResource(string $resourceType, string $resourceId, FieldSetResolverInterface $resolver): array;
public function hasFieldAccessForResource(string $resourceType, string $resourceId, string $field, FieldSetResolverInterface $resolver): bool;
public function getUserId(): ?string;
```

## Implementation Guide

### Step 1: Define Relationships

Create classes that implement `ResourceRelationshipInterface`:

```php
// Database ownership check
class DatabaseOwnershipRelationship implements ResourceRelationshipInterface
{
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        return DB::table('listings')
            ->where('id', $resourceId)
            ->where('agent_id', $userId)
            ->exists();
    }
    
    public function getName(): string { return 'owner'; }
    public function getPriority(): int { return 100; }
}

// Team membership check
class TeamMembershipRelationship implements ResourceRelationshipInterface
{
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        $teamIds = $context->getMetadata()['team_ids'] ?? [];
        
        $resourceTeamId = DB::table('listings')
            ->where('id', $resourceId)
            ->value('team_id');
            
        return in_array($resourceTeamId, $teamIds);
    }
    
    public function getName(): string { return 'team_member'; }
    public function getPriority(): int { return 50; }
}
```

### Step 2: Create Field Set Resolver

Define which fields are available for each relationship:

```php
class ListingFieldSetResolver extends BaseFieldSetResolver
{
    public function __construct()
    {
        $config = [
            'base_fields' => [
                'mls_id', 'address', 'price', 'bedrooms', 'bathrooms'
            ],
            'relationships' => [
                'owner' => [
                    'additional_fields' => [
                        'commission_rate', 'private_notes', 'client_info'
                    ]
                ],
                'team_member' => [
                    'additional_fields' => [
                        'team_notes', 'internal_status'
                    ]
                ]
            ],
            'merge_strategy' => 'union'
        ];

        $relationships = [
            new DatabaseOwnershipRelationship(),
            new TeamMembershipRelationship()
        ];

        parent::__construct($config, $relationships);
    }
}
```

### Step 3: Use in Resources

Apply field access control in your resource implementations:

```php
class ListingResource implements ResourceInterface
{
    private ListingFieldSetResolver $fieldResolver;
    
    public function __construct()
    {
        $this->fieldResolver = new ListingFieldSetResolver();
    }
    
    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        $listingId = $this->extractIdFromUri($uri);
        $listing = Listing::find($listingId);
        
        // Get accessible fields for this specific listing
        $accessibleFields = $context->getAccessibleFieldsForResource(
            'listing', 
            $listingId, 
            $this->fieldResolver
        );
        
        // Filter data based on accessible fields
        if (in_array('*', $accessibleFields)) {
            $data = $listing->toArray();
        } else {
            $data = array_intersect_key(
                $listing->toArray(), 
                array_flip($accessibleFields)
            );
        }
        
        return Result::success($data);
    }
}
```

## Merge Strategies

### Union (Default)
Combines all fields from base + all matched relationships:
- Base: `['id', 'name']`
- Owner: `['private']`  
- Team: `['team_data']`
- **Result**: `['id', 'name', 'private', 'team_data']`

### Intersection
Only includes fields present in ALL matched relationships:
- Owner: `['private', 'shared']`
- Team: `['team_data', 'shared']`
- **Result**: `['id', 'name', 'shared']` (base + intersection)

### Override
Uses highest priority relationship only:
- Owner (priority 100): `['private']`
- Team (priority 50): `['team_data']`
- **Result**: `['id', 'name', 'private']` (base + owner only)

## Advanced Examples

### Custom Relationship Logic

```php
class SubscriptionTierRelationship implements ResourceRelationshipInterface
{
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $tier = $context->getMetadata()['subscription_tier'] ?? 'free';
        return in_array($tier, ['premium', 'enterprise']);
    }
    
    public function getName(): string { return 'premium_subscriber'; }
    public function getPriority(): int { return 25; }
}
```

### Eloquent Model Integration

```php
class EloquentModelRelationship implements ResourceRelationshipInterface
{
    public function __construct(
        private string $modelClass,
        private string $relationshipMethod,
        private string $name
    ) {}

    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        $model = $this->modelClass::find($resourceId);
        
        return $model?->{$this->relationshipMethod}()
            ->where('id', $userId)
            ->exists() ?? false;
    }
    
    // Usage: EloquentModelRelationship::owner(Listing::class, 'owner')
    public static function owner(string $modelClass): self
    {
        return new self($modelClass, 'owner', 'eloquent_owner');
    }
}
```

### Complex Business Logic

```php
class CustomFieldResolver extends BaseFieldSetResolver
{
    public function resolveFieldSet(string $resourceType, array $matchedRelationships): array
    {
        $fields = parent::resolveFieldSet($resourceType, $matchedRelationships);
        
        // Custom logic: premium owners get exclusive fields
        $hasOwnership = $this->hasRelationship($matchedRelationships, 'owner');
        $hasPremium = $this->hasRelationship($matchedRelationships, 'premium_subscriber');
        
        if ($hasOwnership && $hasPremium) {
            $fields[] = 'premium_owner_analytics';
            $fields[] = 'exclusive_market_data';
        }
        
        return array_unique($fields);
    }
}
```

## Performance Considerations

- **Caching**: Implement caching for database-based relationship checks
- **Batch Operations**: For bulk operations, consider batching relationship checks
- **Indexing**: Ensure database tables used in relationships are properly indexed

```php
class CachedOwnershipRelationship implements ResourceRelationshipInterface
{
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $cacheKey = "ownership:{$context->getUserId()}:{$resourceId}";
        
        return Cache::remember($cacheKey, 300, function() use ($context, $resourceId) {
            return DB::table('listings')
                ->where('id', $resourceId)
                ->where('agent_id', $context->getUserId())
                ->exists();
        });
    }
}
```

## Testing

The system includes comprehensive tests covering:
- Basic field access scenarios
- Multiple relationship combinations  
- All merge strategies
- Custom relationship logic
- Performance with large datasets
- Integration with existing field access

Run tests with:
```bash
./vendor/bin/phpunit tests/Feature/ResourceRelationshipFieldAccessTest.php
```

## Configuration-Based Approach

For simpler use cases, you can define relationships through configuration:

```php
// config/mcp_field_access.php
return [
    'listings' => [
        'base_fields' => ['mls_id', 'address', 'price'],
        'relationships' => [
            'owner' => [
                'type' => 'database',
                'table' => 'listings',
                'column' => 'agent_id',
                'additional_fields' => ['commission_rate', 'private_notes'],
                'priority' => 100
            ],
            'premium' => [
                'type' => 'metadata',
                'key' => 'subscription_tier',
                'values' => ['premium', 'enterprise'],
                'additional_fields' => ['analytics', 'predictions'],
                'priority' => 50
            ]
        ],
        'merge_strategy' => 'union'
    ]
];
```

## Migration from Static Field Access

The new system is fully backward compatible:

1. **Existing static field access continues to work**
2. **Resource-relationship fields are merged with static fields**
3. **Admin permissions (`admin`, `*`) still grant full access**
4. **Gradual migration** - implement resource-relationship access incrementally

## Summary

Resource Relationship Field Access provides:

✅ **Maximum Customizability** - Users implement their own relationship logic  
✅ **Performance Optimized** - Efficient relationship checking and caching  
✅ **Test-Driven** - Comprehensive test coverage ensures reliability  
✅ **Backward Compatible** - Works alongside existing field access system  
✅ **Flexible Strategies** - Multiple merge strategies for different use cases  
✅ **Real-World Ready** - Examples cover common business scenarios