<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface PermissionResolverInterface
{
    /**
     * Resolve permissions for a user
     */
    public function resolveUserPermissions($user): array;
    
    /**
     * Resolve field access configuration for a user
     */
    public function resolveUserFieldAccess($user): array;
    
    /**
     * Check if this resolver can handle the given user
     */
    public function canResolve($user): bool;
    
    /**
     * Get the priority of this resolver (higher = more priority)
     */
    public function getPriority(): int;
}