<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface ResourceRelationshipInterface
{
    /**
     * Check if this relationship matches for the given resource and context
     */
    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool;
    
    /**
     * Get the unique name of this relationship type
     */
    public function getName(): string;
    
    /**
     * Get the priority of this relationship (higher numbers = higher priority)
     * Used for conflict resolution when multiple relationships match
     */
    public function getPriority(): int;
}