<?php

namespace App\MCP\Examples;

use ChaoticIngenuity\LaravelMCP\Core\Context;
use App\MCP\FieldResolvers\ListingFieldSetResolver;
use App\MCP\ResourceRelationships\{
    DatabaseOwnershipRelationship,
    TeamMembershipRelationship,
    SubscriptionTierRelationship,
    EloquentModelRelationship
};

/**
 * Example usage of resource-relationship field access system
 */
class ExampleUsage
{
    /**
     * Example 1: Basic usage with listing resolver
     */
    public function basicListingExample()
    {
        // Create context for a premium user who owns listing_123
        $context = new Context(
            clientId: 'mobile_app_v2',
            permissions: ['resources.listings'],
            fieldAccess: [], // No static field access
            metadata: [
                'user_id' => '456',
                'subscription_tier' => 'premium',
                'team_id' => 'team_A'
            ]
        );

        $resolver = new ListingFieldSetResolver();
        
        // Get accessible fields for this specific listing
        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_123', $resolver);
        
        // Result includes:
        // - Base IDX fields (always accessible)
        // - Private owner fields (user owns this listing)
        // - Premium analytics fields (user has premium subscription)
        // - Exclusive premium owner fields (combination bonus)
        
        return $fields;
    }

    /**
     * Example 2: Custom relationship with Eloquent models
     */
    public function eloquentModelExample()
    {
        // Assume we have a Listing model with owner() relationship
        $relationships = [
            EloquentModelRelationship::owner(\App\Models\Listing::class, 'owner'),
            EloquentModelRelationship::collaborator(\App\Models\Listing::class, 'collaborators'),
            new TeamMembershipRelationship()
        ];

        $config = [
            'base_fields' => ['id', 'title', 'description'],
            'relationships' => [
                'eloquent_owner' => [
                    'additional_fields' => ['private_data', 'financial_info'],
                    'priority' => 100
                ],
                'eloquent_collaborator' => [
                    'additional_fields' => ['shared_notes', 'status_updates'],
                    'priority' => 50
                ],
                'team_member' => [
                    'additional_fields' => ['team_internal_data'],
                    'priority' => 25
                ]
            ],
            'merge_strategy' => 'union'
        ];

        $resolver = new \ChaoticIngenuity\LaravelMCP\Core\BaseFieldSetResolver($config, $relationships);
        
        return $resolver;
    }

    /**
     * Example 3: Different merge strategies
     */
    public function mergeStrategyExamples()
    {
        $context = new Context('client', [], [], ['user_id' => '123']);

        // Union strategy - combines all fields
        $unionResolver = $this->createResolver('union');
        $unionFields = $context->getAccessibleFieldsForResource('resource', 'res_1', $unionResolver);
        // Result: base + owner + team fields

        // Intersection strategy - only shared additional fields
        $intersectionResolver = $this->createResolver('intersection');
        $intersectionFields = $context->getAccessibleFieldsForResource('resource', 'res_1', $intersectionResolver);
        // Result: base + only fields present in ALL matched relationships

        // Override strategy - highest priority wins
        $overrideResolver = $this->createResolver('override');
        $overrideFields = $context->getAccessibleFieldsForResource('resource', 'res_1', $overrideResolver);
        // Result: base + fields from highest priority relationship only

        return [
            'union' => $unionFields,
            'intersection' => $intersectionFields,
            'override' => $overrideFields
        ];
    }

    /**
     * Example 4: Usage in a Resource implementation
     */
    public function resourceImplementationExample()
    {
        return '
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
        
        if (!$listing) {
            return Result::error("Listing not found");
        }
        
        // Get accessible fields for this specific listing and user
        $accessibleFields = $context->getAccessibleFieldsForResource(
            "listing", 
            $listingId, 
            $this->fieldResolver
        );
        
        // Filter listing data based on accessible fields
        if (in_array("*", $accessibleFields)) {
            $filteredData = $listing->toArray();
        } else {
            $filteredData = array_intersect_key(
                $listing->toArray(), 
                array_flip($accessibleFields)
            );
        }
        
        return Result::success($filteredData);
    }
}
        ';
    }

    /**
     * Example 5: Configuration-based approach
     */
    public function configurationBasedExample()
    {
        // This could be loaded from config files, database, etc.
        $fieldAccessConfig = [
            'listings' => [
                'base_fields' => ['mls_id', 'address', 'price'],
                'relationships' => [
                    'owner' => [
                        'check_type' => 'database',
                        'table' => 'listings',
                        'column' => 'agent_id',
                        'additional_fields' => ['commission_rate', 'private_notes'],
                        'priority' => 100
                    ],
                    'premium_subscriber' => [
                        'check_type' => 'metadata',
                        'metadata_key' => 'subscription_tier',
                        'expected_values' => ['premium', 'enterprise'],
                        'additional_fields' => ['analytics', 'predictions'],
                        'priority' => 50
                    ]
                ],
                'merge_strategy' => 'union'
            ]
        ];

        // Factory could create relationships and resolvers from config
        return $this->createResolverFromConfig($fieldAccessConfig['listings']);
    }

    private function createResolver(string $mergeStrategy)
    {
        $config = [
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'database_owner' => ['additional_fields' => ['private', 'shared']],
                'team_member' => ['additional_fields' => ['team', 'shared']]
            ],
            'merge_strategy' => $mergeStrategy
        ];

        $relationships = [
            new DatabaseOwnershipRelationship(),
            new TeamMembershipRelationship()
        ];

        return new \ChaoticIngenuity\LaravelMCP\Core\BaseFieldSetResolver($config, $relationships);
    }

    private function createResolverFromConfig(array $config)
    {
        // Implementation would parse config and create appropriate relationships
        // This is left as an exercise for users to implement based on their needs
        return new \ChaoticIngenuity\LaravelMCP\Core\BaseFieldSetResolver($config, []);
    }
}