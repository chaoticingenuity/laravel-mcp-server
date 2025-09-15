<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;
use ChaoticIngenuity\LaravelMCP\Auth\BouncerPermissionManager;
use ChaoticIngenuity\LaravelMCP\Auth\BouncerPermissionResolver;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class BouncerRolesAndAbilitiesTest extends TestCase
{
    protected $testUser;
    protected $permissionManager;
    protected $permissionResolver;

    protected function getPackageProviders($app): array
    {
        $providers = parent::getPackageProviders($app);

        if (class_exists(\Silber\Bouncer\BouncerServiceProvider::class)) {
            $providers[] = \Silber\Bouncer\BouncerServiceProvider::class;
        }

        return $providers;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->isBouncerAvailable()) {
            $this->markTestSkipped('Bouncer package not available - install silber/bouncer to run these tests');
        }

        // Set up configuration
        config([
            'mcp.auth.bouncer.enabled' => true,
            'mcp.auth.bouncer.cache_abilities' => true,
            'mcp.auth.bouncer.ability_prefix' => 'mcp.',
        ]);

        $this->setupDatabase();
        $this->permissionManager = new BouncerPermissionManager();
        $this->permissionResolver = new BouncerPermissionResolver();
        $this->testUser = new TestBouncerUserWithBouncer();
        $this->testUser->save();

        // Clear any cached abilities
        \Silber\Bouncer\BouncerFacade::refresh();
    }

    protected function setupDatabase(): void
    {
        // Create users table
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('mcp_enabled')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Create Bouncer tables manually since bouncer:install command might not be available in tests
        $this->createBouncerTables();
    }

    protected function createBouncerTables(): void
    {
        if (!Schema::hasTable('abilities')) {
            Schema::create('abilities', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('title')->nullable();
                $table->bigInteger('entity_id')->unsigned()->nullable();
                $table->string('entity_type')->nullable();
                $table->boolean('only_owned')->default(false);
                $table->json('options')->nullable();
                $table->integer('scope')->nullable()->index();
                $table->timestamps();

                $table->unique(['name', 'entity_id', 'entity_type', 'only_owned', 'scope'], 'abilities_unique');
            });
        }

        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('title')->nullable();
                $table->integer('level')->unsigned()->nullable();
                $table->integer('scope')->nullable()->index();
                $table->timestamps();

                $table->unique(['name', 'scope'], 'roles_name_unique');
            });
        }

        if (!Schema::hasTable('assigned_roles')) {
            Schema::create('assigned_roles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('role_id')->unsigned()->index();
                $table->bigInteger('entity_id')->unsigned();
                $table->string('entity_type');
                $table->bigInteger('restricted_to_id')->unsigned()->nullable();
                $table->string('restricted_to_type')->nullable();
                $table->integer('scope')->nullable()->index();
                $table->timestamps();

                $table->index(['entity_id', 'entity_type', 'scope'], 'assigned_roles_entity_index');
                $table->index(['entity_id', 'entity_type', 'role_id', 'scope'], 'assigned_roles_entity_role_index');

                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->bigInteger('ability_id')->unsigned()->index();
                $table->bigInteger('entity_id')->unsigned()->nullable();
                $table->string('entity_type')->nullable();
                $table->boolean('forbidden')->default(false);
                $table->integer('scope')->nullable()->index();
                $table->timestamps();

                $table->index(['entity_id', 'entity_type', 'scope'], 'permissions_entity_index');

                $table->foreign('ability_id')->references('id')->on('abilities')->onDelete('cascade');
            });
        }
    }

    /** @test */
    public function user_with_admin_role_has_full_mcp_access(): void
    {
        // Grant full MCP access to admin role (proper Bouncer pattern)
        \Silber\Bouncer\BouncerFacade::allow('admin')->to('mcp.*');

        // Assign admin role to user
        \Silber\Bouncer\BouncerFacade::assign('admin')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::refresh();

        // Test admin role permissions
        $this->assertTrue($this->testUser->isAn('admin'));
        $this->assertTrue($this->testUser->hasMCPAccess());
        $this->assertTrue($this->testUser->hasMCPPermission('admin'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.status'));
        $this->assertTrue($this->testUser->hasMCPFieldAccess('user', 'email'));
        $this->assertTrue($this->testUser->hasMCPFieldAccess('product', 'price'));

        // Test that admin permissions are in the MCP permissions array
        $permissions = $this->testUser->getMCPPermissions();
        $this->assertContains('admin', $permissions);
    }

    /** @test */
    public function user_with_mcp_user_role_has_basic_access(): void
    {
        // Assign mcp_user role
        \Silber\Bouncer\BouncerFacade::assign('mcp_user')->to($this->testUser);

        $this->assertTrue($this->testUser->isAn('mcp_user'));
        $this->assertTrue($this->testUser->hasMCPAccess());
        $this->assertFalse($this->testUser->hasMCPPermission('admin'));
    }

    /** @test */
    public function user_with_api_user_role_has_api_access(): void
    {
        // Assign api_user role
        \Silber\Bouncer\BouncerFacade::assign('api_user')->to($this->testUser);

        $this->assertTrue($this->testUser->isAn('api_user'));
        $this->assertTrue($this->testUser->hasMCPAccess());
    }

    /** @test */
    public function user_can_have_specific_mcp_abilities(): void
    {
        // Grant specific MCP abilities
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.status');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp_access');

        $this->assertTrue($this->testUser->can('mcp.tools.echo'));
        $this->assertTrue($this->testUser->can('mcp.resources.status'));
        $this->assertTrue($this->testUser->can('mcp_access'));
        $this->assertTrue($this->testUser->hasMCPAccess());

        // Debug the issue
        $permissions = $this->testUser->getMCPPermissions();
        $this->assertIsArray($permissions);

        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.status'));
        $this->assertFalse($this->testUser->hasMCPPermission('tools.nonexistent'));

        // Test permission manager
        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'tools.echo'));
        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'resources.status'));
        $this->assertFalse($this->permissionManager->userHasAbility($this->testUser, 'tools.nonexistent'));
    }

    /** @test */
    public function user_can_have_wildcard_mcp_abilities(): void
    {
        // Grant wildcard abilities
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.*');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.*');

        $this->assertTrue($this->testUser->can('mcp.tools.*'));
        $this->assertTrue($this->testUser->can('mcp.*'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.anything'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.status'));
    }

    /** @test */
    public function user_field_access_works_with_bouncer_abilities(): void
    {
        // Grant specific field access abilities
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('access-fields.user.email');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('access-fields.product.*');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('view-field.order.total');

        $this->assertTrue($this->testUser->hasMCPFieldAccess('user', 'email'));
        $this->assertTrue($this->testUser->hasMCPFieldAccess('product', 'price'));
        $this->assertTrue($this->testUser->hasMCPFieldAccess('product', 'name'));
        $this->assertFalse($this->testUser->hasMCPFieldAccess('user', 'password'));
        $this->assertFalse($this->testUser->hasMCPFieldAccess('order', 'items')); // only total is allowed

        // Test field access through permission resolver
        $fieldAccess = $this->permissionResolver->resolveUserFieldAccess($this->testUser);
        $this->assertArrayHasKey('user', $fieldAccess);
        $this->assertArrayHasKey('product', $fieldAccess);
        $this->assertArrayHasKey('order', $fieldAccess);
        $this->assertContains('email', $fieldAccess['user']);
        $this->assertContains('*', $fieldAccess['product']);
        $this->assertContains('total', $fieldAccess['order']);
    }

    /** @test */
    public function permission_resolver_correctly_filters_mcp_abilities(): void
    {
        // Grant various abilities, some MCP-related, some not
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.status');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('regular.permission');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('non-mcp-ability');

        $permissions = $this->permissionResolver->resolveUserPermissions($this->testUser);

        // Should only include MCP abilities (with prefix removed)
        $this->assertContains('tools.echo', $permissions);
        $this->assertContains('resources.status', $permissions);
        $this->assertNotContains('regular.permission', $permissions);
        $this->assertNotContains('non-mcp-ability', $permissions);
        $this->assertNotContains('mcp.tools.echo', $permissions); // prefix should be removed
    }

    /** @test */
    public function permission_manager_correctly_uses_ability_prefix(): void
    {
        // Test with default prefix
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');

        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'tools.echo'));
        $this->assertFalse($this->permissionManager->userHasAbility($this->testUser, 'mcp.tools.echo'));

        // Test with custom prefix
        config(['mcp.auth.bouncer.ability_prefix' => 'custom.']);
        $customManager = new BouncerPermissionManager();

        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('custom.tools.test');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($customManager->userHasAbility($this->testUser, 'tools.test'));
    }

    /** @test */
    public function user_abilities_are_retrieved_correctly(): void
    {
        // Grant multiple abilities
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.status');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.admin');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('non-mcp.ability');

        $abilities = $this->permissionManager->getUserAbilities($this->testUser);

        // Should return only MCP abilities without prefix
        $this->assertContains('tools.echo', $abilities);
        $this->assertContains('resources.status', $abilities);
        $this->assertContains('admin', $abilities);
        $this->assertNotContains('non-mcp.ability', $abilities);
        $this->assertNotContains('mcp.tools.echo', $abilities);
    }

    /** @test */
    public function role_based_abilities_are_inherited(): void
    {
        // Create a role with abilities
        \Silber\Bouncer\BouncerFacade::allow('developer')->to('mcp.tools.*');
        \Silber\Bouncer\BouncerFacade::allow('developer')->to('mcp.resources.read');

        // Assign role to user
        \Silber\Bouncer\BouncerFacade::assign('developer')->to($this->testUser);

        $this->assertTrue($this->testUser->isAn('developer'));
        $this->assertTrue($this->testUser->can('mcp.tools.*'));
        $this->assertTrue($this->testUser->can('mcp.resources.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.read'));

        // Test that role-based abilities appear in user abilities
        $abilities = $this->permissionManager->getUserAbilities($this->testUser);
        $this->assertContains('tools.*', $abilities);
        $this->assertContains('resources.read', $abilities);
    }

    /** @test */
    public function multiple_roles_combine_abilities(): void
    {
        // Create multiple roles with different abilities
        \Silber\Bouncer\BouncerFacade::allow('api_reader')->to('mcp.resources.read');
        \Silber\Bouncer\BouncerFacade::allow('tool_user')->to('mcp.tools.echo');

        // Assign both roles to user
        \Silber\Bouncer\BouncerFacade::assign('api_reader')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::assign('tool_user')->to($this->testUser);

        $this->assertTrue($this->testUser->isAn('api_reader'));
        $this->assertTrue($this->testUser->isAn('tool_user'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));

        $abilities = $this->permissionManager->getUserAbilities($this->testUser);
        $this->assertContains('resources.read', $abilities);
        $this->assertContains('tools.echo', $abilities);
    }

    /** @test */
    public function permission_caching_works_correctly(): void
    {
        // Grant ability
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');

        // Cache abilities
        $this->permissionManager->cacheUserAbilities($this->testUser);

        // Should still work after caching
        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'tools.echo'));

        // Test cache disabled
        config(['mcp.auth.bouncer.cache_abilities' => false]);
        $this->permissionManager->cacheUserAbilities($this->testUser);

        // Should still work with caching disabled
        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'tools.echo'));
    }

    /** @test */
    public function user_without_mcp_enabled_has_no_access(): void
    {
        $this->testUser->mcp_enabled = false;
        $this->testUser->save();

        // Even with admin role, should not have MCP access if mcp_enabled is false
        \Silber\Bouncer\BouncerFacade::assign('admin')->to($this->testUser);

        $this->assertFalse($this->testUser->hasMCPAccess());
    }

    /** @test */
    public function permission_resolver_returns_empty_when_no_mcp_abilities(): void
    {
        // Grant non-MCP abilities only
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('regular.permission');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('another.ability');

        $permissions = $this->permissionResolver->resolveUserPermissions($this->testUser);
        $fieldAccess = $this->permissionResolver->resolveUserFieldAccess($this->testUser);

        $this->assertEmpty($permissions);
        $this->assertEmpty($fieldAccess);
    }

    /** @test */
    public function permission_resolver_can_resolve_user_correctly(): void
    {
        $this->assertTrue($this->permissionResolver->canResolve($this->testUser));

        // Test priority
        $this->assertEquals(100, $this->permissionResolver->getPriority());
    }

    private function isBouncerAvailable(): bool
    {
        return class_exists(\Silber\Bouncer\BouncerFacade::class);
    }
}

/**
 * Test user class that properly implements Bouncer integration
 */
class TestBouncerUserWithBouncer extends Authenticatable implements MCPUserInterface
{
    use HasMCPAuthentication;
    use \Silber\Bouncer\Database\HasRolesAndAbilities;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'mcp_enabled'
    ];

    protected $casts = [
        'mcp_enabled' => 'boolean',
    ];

    public $timestamps = true;

    // Set default values for testing
    protected $attributes = [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'mcp_enabled' => true,
    ];

    /**
     * Override: Get MCP permissions from Bouncer abilities
     */
    public function getMCPPermissions(): array
    {
        // Get all abilities for this user (includes role-based abilities)
        $abilities = $this->getAbilities()->pluck('name')->toArray();

        // Filter to only MCP-related abilities
        $mcpAbilities = array_filter($abilities, function ($ability) {
            return str_starts_with($ability, 'mcp.') ||
                str_starts_with($ability, 'tools.') ||
                str_starts_with($ability, 'resources.') ||
                in_array($ability, ['admin', 'mcp_access']);
        });

        // If user has admin role, return admin permission
        if ($this->isAn('admin')) {
            return ['admin'];
        }

        // If user has general MCP access ability
        if (in_array('mcp_access', $abilities)) {
            return array_merge(['mcp_access'], $mcpAbilities);
        }

        return $mcpAbilities;
    }

    /**
     * Override: Get field access from Bouncer abilities
     */
    public function getMCPFieldAccess(): array
    {
        $fieldAccess = [];

        // Get all abilities for field access
        $abilities = $this->getAbilities();

        foreach ($abilities as $ability) {
            // Parse abilities like "view-field.product.price" or "access-fields.user.*"
            if (preg_match('/^(?:view-field|access-fields)\.([^.]+)\.(.+)$/', $ability->name, $matches)) {
                $entityType = $matches[1];
                $field = $matches[2];

                if (!isset($fieldAccess[$entityType])) {
                    $fieldAccess[$entityType] = [];
                }

                $fieldAccess[$entityType][] = $field;
            }
        }

        // Admin users get access to all fields
        if ($this->isAn('admin')) {
            return ['*'];
        }

        return $fieldAccess;
    }


    /**
     * Override: Check field access using Bouncer abilities
     */
    public function hasMCPFieldAccess(string $entityType, string $field): bool
    {
        // Admin users have access to everything
        if ($this->isAn('admin') || $this->can('admin')) {
            return true;
        }

        // Check specific field access
        $fieldAbility = "access-fields.{$entityType}.{$field}";
        $wildcardAbility = "access-fields.{$entityType}.*";

        return $this->can($fieldAbility) ||
            $this->can($wildcardAbility) ||
            $this->can('mcp.*');
    }

    /**
     * Check if user has MCP access (could be role or ability based)
     */
    public function hasMCPAccess(): bool
    {
        if (!$this->mcp_enabled) {
            return false;
        }

        // Check if user has MCP role or ability
        return $this->isAn('mcp_user') ||
            $this->isAn('api_user') ||
            $this->isAn('admin') ||
            $this->can('mcp_access') ||
            $this->can('mcp.*');
    }
}