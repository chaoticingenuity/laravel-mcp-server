<?php

namespace App\MCP\ResourceRelationships;

use ChaoticIngenuity\LaravelMCP\Contracts\{ContextInterface, ResourceRelationshipInterface};
use Illuminate\Support\Facades\DB;

/**
 * Example: Team membership relationship
 * Checks if user is a member of the team that manages the resource
 */
class TeamMembershipRelationship implements ResourceRelationshipInterface
{
    public function __construct(
        private string $resourceTable = 'listings',
        private string $teamMembershipTable = 'team_user',
        private string $teamColumn = 'team_id'
    ) {}

    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userId = $context->getUserId();
        if (!$userId) {
            return false;
        }

        // Get the team ID for this resource
        $teamId = DB::table($this->resourceTable)
            ->where('id', $resourceId)
            ->value($this->teamColumn);

        if (!$teamId) {
            return false;
        }

        // Check if user is member of this team
        return DB::table($this->teamMembershipTable)
            ->where('team_id', $teamId)
            ->where('user_id', $userId)
            ->exists();
    }

    public function getName(): string
    {
        return 'team_member';
    }

    public function getPriority(): int
    {
        return 50; // Medium priority
    }
}