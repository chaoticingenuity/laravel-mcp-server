<?php

namespace App\MCP\FieldResolvers;

use ChaoticIngenuity\LaravelMCP\Core\BaseFieldSetResolver;
use App\MCP\ResourceRelationships\{
    DatabaseOwnershipRelationship,
    TeamMembershipRelationship,
    SubscriptionTierRelationship
};

/**
 * Example: Real estate listing field access resolver
 * Demonstrates different field access levels for listing resources
 */
class ListingFieldSetResolver extends BaseFieldSetResolver
{
    public function __construct()
    {
        $config = [
            'base_fields' => [
                // Public IDX fields - always accessible
                'mls_id',
                'address',
                'price',
                'bedrooms',
                'bathrooms',
                'square_feet',
                'property_type',
                'listing_status',
                'photos_public'
            ],
            'relationships' => [
                'database_owner' => [
                    'additional_fields' => [
                        // Private agent/owner fields
                        'commission_rate',
                        'private_notes',
                        'lead_source',
                        'client_contact_info',
                        'showing_instructions',
                        'pricing_history',
                        'offer_details',
                        'photos_private'
                    ],
                    'priority' => 100
                ],
                'team_member' => [
                    'additional_fields' => [
                        // Team-shared fields
                        'team_notes',
                        'internal_status',
                        'assigned_agent',
                        'team_commission_split'
                    ],
                    'priority' => 50
                ],
                'subscription_premium' => [
                    'additional_fields' => [
                        // Premium analytics fields
                        'market_analytics',
                        'price_recommendations',
                        'competitive_analysis',
                        'detailed_metrics'
                    ],
                    'priority' => 30
                ]
            ],
            'merge_strategy' => 'union'
        ];

        $relationships = [
            new DatabaseOwnershipRelationship('listings', 'agent_id'),
            new TeamMembershipRelationship('listings', 'team_members'),
            SubscriptionTierRelationship::premium()
        ];

        parent::__construct($config, $relationships);
    }

    /**
     * Custom logic for special cases
     */
    public function resolveFieldSet(string $resourceType, array $matchedRelationships): array
    {
        $fields = parent::resolveFieldSet($resourceType, $matchedRelationships);

        // Custom business logic: if user is both owner and premium subscriber,
        // add exclusive premium owner fields
        $hasOwnership = $this->hasRelationshipType($matchedRelationships, 'database_owner');
        $hasPremium = $this->hasRelationshipType($matchedRelationships, 'subscription_premium');

        if ($hasOwnership && $hasPremium) {
            $fields[] = 'premium_owner_analytics';
            $fields[] = 'exclusive_market_data';
        }

        return array_unique($fields);
    }

    private function hasRelationshipType(array $relationships, string $type): bool
    {
        foreach ($relationships as $relationship) {
            if ($relationship->getName() === $type) {
                return true;
            }
        }
        return false;
    }
}