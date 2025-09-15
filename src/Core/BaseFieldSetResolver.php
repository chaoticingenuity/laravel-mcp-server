<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\{FieldSetResolverInterface, ResourceRelationshipInterface};

class BaseFieldSetResolver implements FieldSetResolverInterface
{
    protected array $config;
    protected array $relationships;

    public function __construct(array $config = [], array $relationships = [])
    {
        $this->config = $config;
        $this->relationships = $relationships;
    }

    public function getBaseFields(string $resourceType): array
    {
        return $this->config['base_fields'] ?? [];
    }

    public function getAdditionalFields(string $resourceType, array $matchedRelationships): array
    {
        $additional = [];
        
        foreach ($matchedRelationships as $relationship) {
            $relationshipName = $relationship->getName();
            $relationshipConfig = $this->config['relationships'][$relationshipName] ?? [];
            $relationshipFields = $relationshipConfig['additional_fields'] ?? [];
            
            $additional = array_merge($additional, $relationshipFields);
        }
        
        return array_unique($additional);
    }

    public function getMergeStrategy(): string
    {
        return $this->config['merge_strategy'] ?? 'union';
    }

    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function resolveFieldSet(string $resourceType, array $matchedRelationships): array
    {
        $baseFields = $this->getBaseFields($resourceType);
        $additionalFields = $this->getAdditionalFields($resourceType, $matchedRelationships);
        
        return match ($this->getMergeStrategy()) {
            'union' => $this->mergeUnion($baseFields, $additionalFields, $matchedRelationships),
            'intersection' => $this->mergeIntersection($baseFields, $additionalFields, $matchedRelationships),
            'override' => $this->mergeOverride($baseFields, $additionalFields, $matchedRelationships),
            default => array_unique(array_merge($baseFields, $additionalFields))
        };
    }

    protected function mergeUnion(array $baseFields, array $additionalFields, array $matchedRelationships): array
    {
        // Check for wildcard override
        foreach ($matchedRelationships as $relationship) {
            $relationshipName = $relationship->getName();
            $relationshipConfig = $this->config['relationships'][$relationshipName] ?? [];
            $relationshipFields = $relationshipConfig['additional_fields'] ?? [];
            
            if (in_array('*', $relationshipFields)) {
                return ['*'];
            }
        }
        
        return array_unique(array_merge($baseFields, $additionalFields));
    }

    protected function mergeIntersection(array $baseFields, array $additionalFields, array $matchedRelationships): array
    {
        if (empty($matchedRelationships)) {
            return $baseFields;
        }
        
        // Start with base fields
        $result = $baseFields;
        
        // Find intersection of additional fields from all relationships
        $relationshipFieldSets = [];
        foreach ($matchedRelationships as $relationship) {
            $relationshipName = $relationship->getName();
            $relationshipConfig = $this->config['relationships'][$relationshipName] ?? [];
            $relationshipFields = $relationshipConfig['additional_fields'] ?? [];
            $relationshipFieldSets[] = $relationshipFields;
        }
        
        if (!empty($relationshipFieldSets)) {
            $intersectedFields = array_intersect(...$relationshipFieldSets);
            $result = array_unique(array_merge($result, $intersectedFields));
        }
        
        return $result;
    }

    protected function mergeOverride(array $baseFields, array $additionalFields, array $matchedRelationships): array
    {
        // Sort relationships by priority (already done in Context)
        // Highest priority relationship overrides others
        if (empty($matchedRelationships)) {
            return $baseFields;
        }
        
        $highestPriorityRelationship = $matchedRelationships[0];
        $relationshipName = $highestPriorityRelationship->getName();
        $relationshipConfig = $this->config['relationships'][$relationshipName] ?? [];
        $overrideFields = $relationshipConfig['additional_fields'] ?? [];
        
        if (in_array('*', $overrideFields)) {
            return ['*'];
        }
        
        return array_unique(array_merge($baseFields, $overrideFields));
    }
}