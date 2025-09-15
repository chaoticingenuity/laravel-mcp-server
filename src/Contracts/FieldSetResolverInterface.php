<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface FieldSetResolverInterface
{
    /**
     * Get the base fields that are always accessible for this resource type
     */
    public function getBaseFields(string $resourceType): array;
    
    /**
     * Get additional fields based on matched relationships
     */
    public function getAdditionalFields(string $resourceType, array $matchedRelationships): array;
    
    /**
     * Get the merge strategy for combining field sets
     * Supported strategies: 'union', 'intersection', 'override'
     */
    public function getMergeStrategy(): string;
    
    /**
     * Get all relationships that should be checked for this resource type
     */
    public function getRelationships(): array;
    
    /**
     * Resolve the final field set based on matched relationships and merge strategy
     */
    public function resolveFieldSet(string $resourceType, array $matchedRelationships): array;
}