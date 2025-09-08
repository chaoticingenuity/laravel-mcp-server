<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionResolverInterface;

class DefaultPermissionResolver implements PermissionResolverInterface
{
    public function resolveUserPermissions($user): array
    {
        // Use user's mcp_permissions property or default permissions
        return !empty($user->mcp_permissions) ? $user->mcp_permissions : [
            'tools.echo',
            'resources.status'
        ];
    }

    public function resolveUserFieldAccess($user): array
    {
        return $user->mcp_field_access ?? [];
    }

    public function canResolve($user): bool
    {
        return true; // Can always resolve as fallback
    }

    public function getPriority(): int
    {
        return 0; // Lowest priority - fallback resolver
    }
}