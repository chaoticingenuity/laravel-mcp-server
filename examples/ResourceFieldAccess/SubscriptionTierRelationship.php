<?php

namespace App\MCP\ResourceRelationships;

use ChaoticIngenuity\LaravelMCP\Contracts\{ContextInterface, ResourceRelationshipInterface};

/**
 * Example: Subscription tier-based access
 * Grants access based on user's subscription level
 */
class SubscriptionTierRelationship implements ResourceRelationshipInterface
{
    public function __construct(
        private string $requiredTier = 'premium',
        private array $allowedTiers = ['premium', 'enterprise']
    ) {}

    public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
    {
        $userTier = $context->getMetadata()['subscription_tier'] ?? 'free';
        
        return in_array($userTier, $this->allowedTiers);
    }

    public function getName(): string
    {
        return 'subscription_' . $this->requiredTier;
    }

    public function getPriority(): int
    {
        return 30; // Lower priority than ownership/team membership
    }

    /**
     * Create a premium tier relationship
     */
    public static function premium(): self
    {
        return new self('premium', ['premium', 'enterprise']);
    }

    /**
     * Create an enterprise tier relationship
     */
    public static function enterprise(): self
    {
        return new self('enterprise', ['enterprise']);
    }
}