<?php

namespace App\MCP\ResourceRelationships;

use ChaoticIngenuity\LaravelMCP\Contracts\{ContextInterface, ResourceRelationshipInterface};
use Illuminate\Database\Eloquent\Model;

/**
 * Example: Eloquent model-based relationship
 * Uses Laravel's Eloquent relationships to determine access
 */
class EloquentModelRelationship implements ResourceRelationshipInterface
{
    public function __construct(
        private string $modelClass,
        private string $relationshipMethod,
        private string $relationshipName,
        private int $priority = 50
    ) {}

    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        if (!$userId) {
            return false;
        }

        /** @var Model $model */
        $model = $this->modelClass::find($resourceId);
        if (!$model) {
            return false;
        }

        // Call the relationship method and check if user matches
        $relationship = $model->{$this->relationshipMethod}();
        
        // Handle different relationship types
        if (method_exists($relationship, 'where')) {
            return $relationship->where('id', $userId)->exists();
        }
        
        // For direct relationships
        $related = $relationship->first();
        return $related && $related->id == $userId;
    }

    public function getName(): string
    {
        return $this->relationshipName;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    /**
     * Create a relationship for checking if user owns a resource
     */
    public static function owner(string $modelClass, string $relationMethod = 'owner'): self
    {
        return new self($modelClass, $relationMethod, 'eloquent_owner', 100);
    }

    /**
     * Create a relationship for checking if user is assigned to a resource
     */
    public static function assignee(string $modelClass, string $relationMethod = 'assignedTo'): self
    {
        return new self($modelClass, $relationMethod, 'eloquent_assignee', 75);
    }

    /**
     * Create a relationship for checking if user is a collaborator
     */
    public static function collaborator(string $modelClass, string $relationMethod = 'collaborators'): self
    {
        return new self($modelClass, $relationMethod, 'eloquent_collaborator', 25);
    }
}