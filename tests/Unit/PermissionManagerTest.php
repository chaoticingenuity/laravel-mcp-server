<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Unit;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;
use ChaoticIngenuity\LaravelMCP\Auth\DefaultPermissionManager;
use ChaoticIngenuity\LaravelMCP\Auth\BouncerPermissionManager;
use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;

class PermissionManagerTest extends TestCase
{
    /** @test */
    public function default_permission_manager_implements_interface(): void
    {
        $manager = new DefaultPermissionManager();
        $this->assertInstanceOf(PermissionManagerInterface::class, $manager);
    }

    /** @test */
    public function default_permission_manager_delegates_to_user_trait(): void
    {
        $user = new TestUser();
        $user->mcp_permissions = ['tools.echo', 'resources.status'];
        
        $manager = new DefaultPermissionManager();
        
        $this->assertTrue($manager->userHasAbility($user, 'tools.echo'));
        $this->assertFalse($manager->userHasAbility($user, 'tools.restricted'));
        
        $abilities = $manager->getUserAbilities($user);
        $this->assertContains('tools.echo', $abilities);
        $this->assertContains('resources.status', $abilities);
    }

    /** @test */
    public function default_permission_manager_handles_admin_permissions(): void
    {
        $user = new TestUser();
        $user->mcp_permissions = ['admin'];
        
        $manager = new DefaultPermissionManager();
        
        $this->assertTrue($manager->userHasAbility($user, 'tools.echo'));
        $this->assertTrue($manager->userHasAbility($user, 'resources.restricted'));
        $this->assertTrue($manager->userHasAbility($user, 'any.permission'));
        
        $abilities = $manager->getUserAbilities($user);
        $this->assertContains('admin', $abilities);
    }

    /** @test */
    public function default_permission_manager_handles_wildcard_permissions(): void
    {
        $user = new TestUser();
        $user->mcp_permissions = ['tools.*', 'resources.status'];
        
        $manager = new DefaultPermissionManager();
        
        $this->assertTrue($manager->userHasAbility($user, 'tools.anything'));
        $this->assertTrue($manager->userHasAbility($user, 'resources.status'));
        $this->assertFalse($manager->userHasAbility($user, 'restricted.action'));
    }

    /** @test */
    public function default_permission_manager_falls_back_to_defaults(): void
    {
        $user = new TestUser();
        // No permissions set, should fall back to defaults
        
        $manager = new DefaultPermissionManager();
        
        $abilities = $manager->getUserAbilities($user);
        $this->assertContains('tools.echo', $abilities);
        $this->assertContains('resources.status', $abilities);
    }

    /** @test */
    public function bouncer_permission_manager_implements_interface(): void
    {
        $manager = new BouncerPermissionManager();
        $this->assertInstanceOf(PermissionManagerInterface::class, $manager);
    }

    /** @test */
    public function bouncer_permission_manager_detects_bouncer_availability(): void
    {
        $manager = new BouncerPermissionManager();
        
        // This will test the actual Bouncer availability
        $bouncerAvailable = class_exists(\Silber\Bouncer\BouncerFacade::class);
        
        if ($bouncerAvailable) {
            $this->markTestSkipped('Bouncer is available - need mock user for full test');
        } else {
            // Test with mock user when Bouncer not available
            $user = new TestUser();
            
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Bouncer package is required but not installed');
            
            $manager->userHasAbility($user, 'test.permission');
        }
    }

    /** @test */
    public function bouncer_permission_manager_returns_empty_abilities_when_unavailable(): void
    {
        $manager = new BouncerPermissionManager();
        $user = new TestUser();
        
        if (!class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $abilities = $manager->getUserAbilities($user);
            $this->assertEmpty($abilities);
        } else {
            $this->markTestSkipped('Bouncer is available - test would require mock abilities');
        }
    }

    /** @test */
    public function bouncer_permission_manager_uses_prefix_configuration(): void
    {
        config(['mcp.auth.bouncer.ability_prefix' => 'custom.']);
        
        $manager = new BouncerPermissionManager();
        
        // Test prefix through reflection since getPrefixedAbility is private
        $reflection = new \ReflectionClass($manager);
        $method = $reflection->getMethod('getPrefixedAbility');
        $method->setAccessible(true);
        
        $prefixed = $method->invoke($manager, 'test.permission');
        $this->assertEquals('custom.test.permission', $prefixed);
    }

    /** @test */
    public function bouncer_permission_manager_respects_cache_configuration(): void
    {
        config(['mcp.auth.bouncer.cache_abilities' => false]);
        
        $manager = new BouncerPermissionManager();
        $user = new TestUser();
        
        // Should not throw exception even if Bouncer not available
        // since caching is disabled
        $manager->cacheUserAbilities($user);
        
        $this->assertTrue(true); // Test passes if no exception thrown
    }

    /** @test */
    public function permission_manager_is_registered_in_service_container(): void
    {
        $manager = app(PermissionManagerInterface::class);
        $this->assertInstanceOf(PermissionManagerInterface::class, $manager);
        
        // Should be DefaultPermissionManager unless Bouncer is enabled and available
        if (!config('mcp.auth.bouncer.enabled', false) || 
            !class_exists(\Silber\Bouncer\BouncerFacade::class)) {
            $this->assertInstanceOf(DefaultPermissionManager::class, $manager);
        }
    }

    /** @test */
    public function permission_managers_are_singletons(): void
    {
        $manager1 = app(PermissionManagerInterface::class);
        $manager2 = app(PermissionManagerInterface::class);
        
        $this->assertSame($manager1, $manager2);
    }

    /** @test */
    public function service_provider_switches_permission_managers_based_on_config(): void
    {
        // Test default registration
        config(['mcp.auth.bouncer.enabled' => false]);
        
        // Clear singleton to force re-registration
        app()->forgetInstance(PermissionManagerInterface::class);
        
        $manager = app(PermissionManagerInterface::class);
        $this->assertInstanceOf(DefaultPermissionManager::class, $manager);
    }

    /** @test */
    public function circular_dependency_is_prevented(): void
    {
        $user = new TestUser();
        $user->mcp_permissions = ['test.permission'];
        
        $manager = new DefaultPermissionManager();
        
        // This should not cause infinite recursion
        $hasAbility = $manager->userHasAbility($user, 'test.permission');
        $this->assertTrue($hasAbility);
        
        // Multiple calls should work fine
        $hasAbility2 = $manager->userHasAbility($user, 'test.permission');
        $this->assertTrue($hasAbility2);
    }

    /** @test */
    public function user_customizations_are_respected(): void
    {
        $user = new class extends TestUser {
            public function hasMCPPermission(string $permission): bool
            {
                // Custom logic - always allow 'custom.action'
                if ($permission === 'custom.action') {
                    return true;
                }
                return parent::hasMCPPermission($permission);
            }
            
            public function getMCPPermissions(): array
            {
                return array_merge(parent::getMCPPermissions(), ['custom.action']);
            }
        };
        
        $user->mcp_permissions = ['tools.echo'];
        
        $manager = new DefaultPermissionManager();
        
        // Should respect custom logic
        $this->assertTrue($manager->userHasAbility($user, 'custom.action'));
        $this->assertTrue($manager->userHasAbility($user, 'tools.echo'));
        $this->assertFalse($manager->userHasAbility($user, 'tools.restricted'));
        
        $abilities = $manager->getUserAbilities($user);
        $this->assertContains('custom.action', $abilities);
        $this->assertContains('tools.echo', $abilities);
    }
}

/**
 * Test user class implementing MCPUserInterface
 */
class TestUser extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;
    
    protected $fillable = [
        'mcp_enabled',
        'mcp_permissions',
        'mcp_field_access',
        'mcp_tokens'
    ];
    
    protected $casts = [
        'mcp_enabled' => 'boolean',
        'mcp_permissions' => 'array',
        'mcp_field_access' => 'array',
        'mcp_tokens' => 'array',
    ];
    
    public $mcp_enabled = true;
    public $mcp_permissions = [];
    public $mcp_field_access = [];
    public $mcp_tokens = [];
}