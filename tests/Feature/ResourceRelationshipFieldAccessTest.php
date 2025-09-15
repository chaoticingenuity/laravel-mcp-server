<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;
use ChaoticIngenuity\LaravelMCP\Core\{Context, ContextFactory, BaseFieldSetResolver};
use ChaoticIngenuity\LaravelMCP\Contracts\{
    ResourceRelationshipInterface,
    FieldSetResolverInterface,
    ContextInterface
};

class ResourceRelationshipFieldAccessTest extends TestCase
{
    protected $contextFactory;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->contextFactory = app(ContextFactory::class);
    }

    /** @test */
    public function it_returns_base_fields_when_no_relationships_match()
    {
        $context = $this->createTestContext('user123', []);
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name', 'public_description'],
            'relationships' => []
        ]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertEquals(['id', 'name', 'public_description'], $fields);
    }

    /** @test */
    public function it_adds_owner_fields_when_user_owns_entity()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123]);
        $ownerRelationship = $this->createOwnerRelationship();
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name', 'public_description'],
            'relationships' => [
                'owner' => ['additional_fields' => ['private_notes', 'commission_rate']]
            ]
        ], [$ownerRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertContains('id', $fields);
        $this->assertContains('name', $fields);
        $this->assertContains('public_description', $fields);
        $this->assertContains('private_notes', $fields);
        $this->assertContains('commission_rate', $fields);
    }

    /** @test */
    public function it_handles_multiple_relationships_with_union_merge()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123, 'team_id' => 'team_A']);
        $ownerRelationship = $this->createOwnerRelationship();
        $teamRelationship = $this->createTeamMemberRelationship();
        
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['private_notes', 'commission_rate']],
                'team_member' => ['additional_fields' => ['team_notes', 'internal_status']]
            ],
            'merge_strategy' => 'union'
        ], [$ownerRelationship, $teamRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        // Should contain base + owner + team fields
        $expected = ['id', 'name', 'private_notes', 'commission_rate', 'team_notes', 'internal_status'];
        $this->assertEquals(sort($expected), sort($fields));
    }

    /** @test */
    public function it_handles_multiple_relationships_with_intersection_merge()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123, 'team_id' => 'team_A']);
        $ownerRelationship = $this->createOwnerRelationship();
        $teamRelationship = $this->createTeamMemberRelationship();
        
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['private_notes', 'shared_field']],
                'team_member' => ['additional_fields' => ['shared_field', 'team_notes']]
            ],
            'merge_strategy' => 'intersection'
        ], [$ownerRelationship, $teamRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        // Should contain base + only shared additional fields
        $expected = ['id', 'name', 'shared_field'];
        sort($expected);
        sort($fields);
        $this->assertEquals($expected, $fields);
    }

    /** @test */
    public function it_handles_priority_based_override_merge()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123, 'role' => 'admin']);
        $ownerRelationship = $this->createOwnerRelationship();
        $adminRelationship = $this->createAdminRelationship();
        
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['private_notes'], 'priority' => 10],
                'admin' => ['additional_fields' => ['*'], 'priority' => 100]
            ],
            'merge_strategy' => 'override'
        ], [$ownerRelationship, $adminRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        // Admin should override owner, granting access to all fields
        $this->assertEquals(['*'], $fields);
    }

    /** @test */
    public function it_supports_custom_relationship_logic()
    {
        $context = $this->createTestContext('user123', ['organization_id' => 'org_456']);
        $customRelationship = new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                // Custom logic: user belongs to same organization as the resource
                $userOrgId = $context->getMetadata()['organization_id'] ?? null;
                return $userOrgId === 'org_456' && $resourceId === 'listing_456';
            }
            
            public function getName(): string { return 'organization_member'; }
            public function getPriority(): int { return 50; }
        };

        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'organization_member' => ['additional_fields' => ['org_private_data']]
            ]
        ], [$customRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertContains('org_private_data', $fields);
    }

    /** @test */
    public function it_supports_query_based_relationships()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123]);
        
        // Mock a query-based relationship that checks database
        $queryRelationship = new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                $userId = $context->getMetadata()['user_id'] ?? null;
                // Simulated database check
                return $userId === 123 && $resourceId === 'listing_456';
            }
            
            public function getName(): string { return 'database_owner'; }
            public function getPriority(): int { return 10; }
        };

        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'public_info'],
            'relationships' => [
                'database_owner' => ['additional_fields' => ['private_database_fields']]
            ]
        ], [$queryRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertContains('private_database_fields', $fields);
    }

    /** @test */
    public function it_supports_contextual_field_filtering()
    {
        $context = $this->createTestContext('user123', ['subscription_tier' => 'premium']);
        
        $tierRelationship = new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                return $context->getMetadata()['subscription_tier'] === 'premium';
            }
            
            public function getName(): string { return 'premium_subscriber'; }
            public function getPriority(): int { return 20; }
        };

        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'basic_info'],
            'relationships' => [
                'premium_subscriber' => ['additional_fields' => ['premium_analytics', 'detailed_metrics']]
            ]
        ], [$tierRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertContains('premium_analytics', $fields);
        $this->assertContains('detailed_metrics', $fields);
    }

    /** @test */
    public function it_handles_complex_nested_relationships()
    {
        $context = $this->createTestContext('user123', [
            'user_id' => 123,
            'team_ids' => ['team_A', 'team_B'],
            'organization_id' => 'org_456',
            'role' => 'manager'
        ]);

        $relationships = [
            $this->createOwnerRelationship(),
            $this->createTeamMemberRelationship(),
            $this->createManagerRelationship()
        ];

        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['owner_private'], 'priority' => 10],
                'team_member' => ['additional_fields' => ['team_shared'], 'priority' => 20],
                'manager' => ['additional_fields' => ['management_data'], 'priority' => 30]
            ],
            'merge_strategy' => 'union'
        ], $relationships);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        $this->assertContains('owner_private', $fields);
        $this->assertContains('team_shared', $fields);
        $this->assertContains('management_data', $fields);
    }

    /** @test */
    public function it_handles_performance_with_large_entity_sets()
    {
        $context = $this->createTestContext('user123', ['user_id' => 123]);
        $relationships = [$this->createOwnerRelationship()];
        
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['private_data']]
            ]
        ], $relationships);

        $start = microtime(true);
        
        // Test with multiple resource IDs
        for ($i = 1; $i <= 100; $i++) {
            $fields = $context->getAccessibleFieldsForResource('listing', "listing_{$i}", $resolver);
        }
        
        $duration = microtime(true) - $start;
        
        // Should complete in reasonable time (adjust threshold as needed)
        $this->assertLessThan(1.0, $duration, 'Performance test failed - took too long');
    }

    /** @test */
    public function it_integrates_with_existing_field_access_system()
    {
        // Test that new entity-relationship system works alongside existing static field access
        $context = $this->createTestContext('user123', ['user_id' => 123], [
            'listing' => ['id', 'static_allowed_field'] // Existing static field access
        ]);

        $ownerRelationship = $this->createOwnerRelationship();
        $resolver = $this->createFieldSetResolver([
            'base_fields' => ['id', 'name'],
            'relationships' => [
                'owner' => ['additional_fields' => ['dynamic_owner_field']]
            ]
        ], [$ownerRelationship]);

        $fields = $context->getAccessibleFieldsForResource('listing', 'listing_456', $resolver);

        // Should include both static and dynamic fields
        $this->assertContains('static_allowed_field', $fields);
        $this->assertContains('dynamic_owner_field', $fields);
    }

    // Helper methods for creating test fixtures
    protected function createTestContext(string $clientId, array $metadata, array $staticFieldAccess = []): ContextInterface
    {
        return new Context($clientId, [], $staticFieldAccess, $metadata);
    }

    protected function createFieldSetResolver(array $config, array $relationships = []): FieldSetResolverInterface
    {
        return new BaseFieldSetResolver($config, $relationships);
    }

    protected function createOwnerRelationship(): ResourceRelationshipInterface
    {
        return new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                return $context->getMetadata()['user_id'] === 123;
            }
            
            public function getName(): string { return 'owner'; }
            public function getPriority(): int { return 10; }
        };
    }

    protected function createTeamMemberRelationship(): ResourceRelationshipInterface
    {
        return new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                $teamId = $context->getMetadata()['team_id'] ?? null;
                return $teamId === 'team_A' || in_array('team_A', $context->getMetadata()['team_ids'] ?? []);
            }
            
            public function getName(): string { return 'team_member'; }
            public function getPriority(): int { return 20; }
        };
    }

    protected function createAdminRelationship(): ResourceRelationshipInterface
    {
        return new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                return $context->getMetadata()['role'] === 'admin';
            }
            
            public function getName(): string { return 'admin'; }
            public function getPriority(): int { return 100; }
        };
    }

    protected function createManagerRelationship(): ResourceRelationshipInterface
    {
        return new class implements ResourceRelationshipInterface {
            public function matches(ContextInterface $context, string $resourceType, string $resourceId): bool
            {
                return $context->getMetadata()['role'] === 'manager';
            }
            
            public function getName(): string { return 'manager'; }
            public function getPriority(): int { return 30; }
        };
    }
}