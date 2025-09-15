<?php

namespace App\MCP\ResourceRelationships;

use ChaoticIngenuity\LaravelMCP\Contracts\{ContextInterface, ResourceRelationshipInterface};
use Illuminate\Support\Facades\DB;

/**
 * Example: Database-based ownership relationship
 * Checks if user owns the resource by querying the database
 */
class DatabaseOwnershipRelationship implements ResourceRelationshipInterface
{
    public function __construct(
        private string $table = 'listings',
        private string $ownerColumn = 'user_id',
        private string $resourceIdColumn = 'id'
    ) {}

    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        if (!$userId) {
            return false;
        }

        return DB::table($this->table)
            ->where($this->resourceIdColumn, $resourceId)
            ->where($this->ownerColumn, $userId)
            ->exists();
    }

    public function getName(): string
    {
        return 'database_owner';
    }

    public function getPriority(): int
    {
        return 100; // High priority for ownership
    }
}