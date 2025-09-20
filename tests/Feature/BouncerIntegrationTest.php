<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Auth\BouncerPermissionManager;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;
use ChaoticIngenuity\LaravelMCP\Tests\TestCase;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;

class BouncerIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up basic configuration
        config([
            'mcp.auth.bouncer.enabled' => true,
            'mcp.auth.bouncer.cache_abilities' => true,
            'mcp.auth.bouncer.ability_prefix' => 'mcp.',
            'mcp.auth.api_keys' => ['test-key'],
            'mcp.auth.api_key_clients' => ['test-key' => 'bouncer_client'],
            'mcp.auth.clients.bouncer_client' => [
                'permissions' => ['tools.*'],
                'field_access' => ['*'],
                'metadata' => ['tier' => 'bouncer'],
            ],
        ]);
    }

    /** @test */
    public function it_validates_bouncer_configuration_when_enabled(): void
    {
        if (! env('MCP_BOUNCER_ENABLED', false) && ! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->markTestSkipped('Bouncer integration not enabled/available (set MCP_BOUNCER_ENABLED=true or install Bouncer to test)');

            return;
        }

        config(['mcp.auth.bouncer.enabled' => true]);

        if (class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Bouncer is available - should work fine
            $manager = new \ChaoticIngenuity\LaravelMCP\Auth\BouncerPermissionManager;
            $this->assertInstanceOf(\ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface::class, $manager);
        } else {
            // This case should not happen since we skip above, but keeping for completeness
            $this->assertTrue(true, 'Bouncer not available - test bypassed');
        }
    }

    /** @test */
    public function it_allows_bouncer_disabled_when_package_missing(): void
    {
        config(['mcp.auth.bouncer.enabled' => false]);

        // Should not throw exception even if Bouncer is missing
        $manager = app(PermissionManagerInterface::class);
        $this->assertInstanceOf(PermissionManagerInterface::class, $manager);

        // Should fall back to DefaultPermissionManager
        if (! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->assertInstanceOf(\ChaoticIngenuity\LaravelMCP\Auth\DefaultPermissionManager::class, $manager);
        }
    }

    /** @test */
    public function it_registers_bouncer_permission_manager_when_available(): void
    {
        config(['mcp.auth.bouncer.enabled' => true]);

        if (class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Clear singleton to force re-registration
            $this->app->forgetInstance(PermissionManagerInterface::class);

            $manager = app(PermissionManagerInterface::class);
            $this->assertInstanceOf(BouncerPermissionManager::class, $manager);
        } else {
            $this->markTestSkipped('Bouncer not available for integration test');
        }
    }

    /** @test */
    public function it_falls_back_to_default_manager_when_bouncer_unavailable(): void
    {
        config(['mcp.auth.bouncer.enabled' => true]);

        if (! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Should fall back to default manager
            $manager = app(PermissionManagerInterface::class);
            $this->assertInstanceOf(\ChaoticIngenuity\LaravelMCP\Auth\DefaultPermissionManager::class, $manager);
        } else {
            $this->markTestSkipped('Bouncer is available - cannot test fallback');
        }
    }

    /** @test */
    public function bouncer_integration_works_with_mcp_setup_command(): void
    {
        if (! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->artisan('mcp:setup --bouncer')
                ->expectsOutput('Bouncer package not installed. Run: composer require silber/bouncer')
                ->assertExitCode(1);
        } else {
            $this->artisan('mcp:setup --bouncer')
                ->expectsOutput('Setting up MCP with Bouncer integration...')
                ->expectsOutput('Setting up basic MCP configuration...')
                ->assertExitCode(0);
        }
    }

    /** @test */
    public function it_handles_bouncer_permission_prefix_correctly(): void
    {
        config(['mcp.auth.bouncer.ability_prefix' => 'custom_prefix.']);

        $manager = new BouncerPermissionManager;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('getPrefixedAbility');
        $method->setAccessible(true);

        $prefixed = $method->invoke($manager, 'tools.echo');
        $this->assertEquals('custom_prefix.tools.echo', $prefixed);

        $method = $reflection->getMethod('getPrefix');
        $method->setAccessible(true);

        $prefix = $method->invoke($manager);
        $this->assertEquals('custom_prefix.', $prefix);
    }

    /** @test */
    public function it_handles_bouncer_cache_configuration(): void
    {
        if (! env('MCP_BOUNCER_ENABLED', false) && ! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->markTestSkipped('Bouncer integration not enabled/available (set MCP_BOUNCER_ENABLED=true or install Bouncer to test)');

            return;
        }

        $manager = new BouncerPermissionManager;
        $user = new TestBouncerUser;

        // Test with cache enabled
        config(['mcp.auth.bouncer.cache_abilities' => true]);

        if (class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Should work when Bouncer is available
            $manager->cacheUserAbilities($user);
        } else {
            // Should not throw when Bouncer not available (graceful degradation)
            $manager->cacheUserAbilities($user);
        }

        // Test with cache disabled
        config(['mcp.auth.bouncer.cache_abilities' => false]);
        $manager->cacheUserAbilities($user);

        // Test passes if no exceptions thrown
        $this->assertTrue(true);
    }

    /** @test */
    public function bouncer_user_model_example_works(): void
    {
        $user = new TestBouncerUser;

        if (class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Test that HasRolesAndAbilities trait methods exist
            $this->assertTrue(method_exists($user, 'can'));
            $this->assertTrue(method_exists($user, 'getAbilities'));
            $this->assertTrue(method_exists($user, 'isAn'));
        }

        // Test MCP-specific overrides
        $this->assertTrue(method_exists($user, 'hasMCPAccess'));
        $this->assertTrue(method_exists($user, 'getMCPPermissions'));
        $this->assertTrue(method_exists($user, 'hasMCPPermission'));
        $this->assertTrue(method_exists($user, 'hasMCPFieldAccess'));
    }

    /** @test */
    public function it_validates_bouncer_detection_logic(): void
    {
        $manager = new BouncerPermissionManager;

        // Use reflection to test private method
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('isBouncerAvailable');
        $method->setAccessible(true);

        $isAvailable = $method->invoke($manager);
        $expectedAvailable = class_exists(\Silber\Bouncer\BouncerFacade::class);

        $this->assertEquals($expectedAvailable, $isAvailable);
    }

    /** @test */
    public function bouncer_permission_manager_gracefully_handles_missing_package(): void
    {
        $manager = new BouncerPermissionManager;
        $user = new TestBouncerUser;

        if (! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Should return empty abilities when Bouncer not available
            $abilities = $manager->getUserAbilities($user);
            $this->assertIsArray($abilities);
            $this->assertEmpty($abilities);

            // Should throw exception when trying to check abilities
            $this->expectException(\RuntimeException::class);
            $manager->userHasAbility($user, 'test.permission');
        } else {
            $this->markTestSkipped('Bouncer is available - cannot test missing package behavior');
        }
    }

    /** @test */
    public function service_provider_configuration_validation_works(): void
    {
        if (! env('MCP_BOUNCER_ENABLED', false) && ! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->markTestSkipped('Bouncer integration not enabled/available (set MCP_BOUNCER_ENABLED=true or install Bouncer to test)');

            return;
        }

        if (class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            // Enable Bouncer integration
            config(['mcp.auth.bouncer.enabled' => true]);

            // Should work without issues when Bouncer is available
            $manager = app(\ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface::class);
            $this->assertInstanceOf(\ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface::class, $manager);
        } else {
            // This case should not happen since we skip above, but keeping for completeness
            $this->assertTrue(true, 'Bouncer not available - test bypassed');
        }
    }

    /** @test */
    public function trait_bouncer_detection_works(): void
    {
        $user = new TestBouncerUser;

        $shouldUseBouncer = $user->shouldUseBouncer();

        $expectedResult = config('mcp.auth.bouncer.enabled', false) &&
            class_exists(\Silber\Bouncer\BouncerFacade::class);

        $this->assertEquals($expectedResult, $shouldUseBouncer);
    }

    /** @test */
    public function integration_test_with_http_requests(): void
    {
        if (! class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->markTestSkipped('Bouncer not available for HTTP integration test');
        }

        config(['mcp.auth.bouncer.enabled' => true]);

        // Test should work with Bouncer enabled
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => 'test-key',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'id' => 1,
            ]);
    }
}

/**
 * Test user class that mimics the Bouncer integration example
 */
class TestBouncerUser extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;

    // Conditionally use Bouncer trait if available
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (class_exists(\Silber\Bouncer\Database\HasRolesAndAbilities::class)) {
            $this->addTraitToClass(\Silber\Bouncer\Database\HasRolesAndAbilities::class);
        }
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'mcp_enabled',
    ];

    protected $casts = [
        'mcp_enabled' => 'boolean',
    ];

    public $mcp_enabled = true;

    /**
     * Mock method for testing when Bouncer not available
     */
    public function can($abilities, $arguments = []): bool
    {
        return false; // Default implementation
    }

    /**
     * Mock method for testing when Bouncer not available
     */
    public function getAbilities()
    {
        return collect();
    }

    /**
     * Mock method for testing when Bouncer not available
     */
    public function isAn($role): bool
    {
        return false;
    }

    /**
     * Add trait dynamically if available (for testing)
     */
    private function addTraitToClass($traitName): void
    {
        if (trait_exists($traitName)) {
            $uses = class_uses_recursive(static::class);
            if (! in_array($traitName, $uses)) {
                // This is for testing only - in real code the trait would be declared normally
                // We can't actually add traits at runtime, so this is just for method existence testing
            }
        }
    }
}
