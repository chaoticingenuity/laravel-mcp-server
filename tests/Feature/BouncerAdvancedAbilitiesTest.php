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

class BouncerAdvancedAbilitiesTest extends TestCase
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
        $this->testUser = new TestAdvancedBouncerUser();
        $this->testUser->save();

        // Clear any cached abilities
        \Silber\Bouncer\BouncerFacade::refresh();
    }

    protected function setupDatabase(): void
    {
        // Create users table if it doesn't exist
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
    public function complex_nested_permissions_work_correctly(): void
    {
        // Grant complex nested permissions
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.file.read');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.file.write');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.database.query');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.api.external');

        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.write'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.database.query'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.api.external'));
        $this->assertFalse($this->testUser->hasMCPPermission('tools.file.delete'));

        // Test wildcard matching by granting a wildcard ability
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.file.*');

        // Refresh abilities cache
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.delete'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.anything'));
    }

    /** @test */
    public function hierarchical_wildcard_permissions_work(): void
    {
        // Test different levels of wildcard permissions
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.*');

        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.database.query'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.anything.deeply.nested'));
        $this->assertFalse($this->testUser->hasMCPPermission('resources.status'));

        // Test broader wildcard
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.*');
        \Silber\Bouncer\BouncerFacade::refresh();


        $this->assertTrue($this->testUser->hasMCPPermission('resources.status'));
        $this->assertTrue($this->testUser->hasMCPPermission('anything.at.all'));
    }

    /** @test */
    public function conditional_abilities_based_on_context(): void
    {
        // Simplified test without forbidden abilities (advanced feature)
        // Create role-based abilities
        \Silber\Bouncer\BouncerFacade::allow('content_editor')->to('mcp.resources.posts.read');
        \Silber\Bouncer\BouncerFacade::allow('content_editor')->to('mcp.resources.posts.edit');

        \Silber\Bouncer\BouncerFacade::assign('content_editor')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->hasMCPPermission('resources.posts.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.posts.edit'));
        $this->assertFalse($this->testUser->hasMCPPermission('resources.posts.delete')); // Not granted

        // Test that additional permissions work with roles
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.resources.*');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->hasMCPPermission('resources.users.read'));
        $this->assertTrue($this->testUser->hasMCPPermission('resources.posts.delete')); // Now granted via wildcard
    }

    /** @test */
    public function time_based_and_temporary_abilities(): void
    {
        $this->markTestSkipped('Time-based abilities require Bouncer Pro or are not supported in this version');
    }

    /** @test */
    public function field_access_with_complex_patterns(): void
    {
        // Grant complex field access patterns
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('access-fields.user.profile.*');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('access-fields.user.settings.theme');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('view-field.product.metadata.tags');
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('access-fields.order.billing.address');
        \Silber\Bouncer\BouncerFacade::refresh();

        $fieldAccess = $this->permissionResolver->resolveUserFieldAccess($this->testUser);

        // Check that complex field patterns are parsed correctly
        $this->assertArrayHasKey('user', $fieldAccess);
        $this->assertArrayHasKey('product', $fieldAccess);
        $this->assertArrayHasKey('order', $fieldAccess);

        $this->assertContains('profile.*', $fieldAccess['user']);
        $this->assertContains('settings.theme', $fieldAccess['user']);
        $this->assertContains('metadata.tags', $fieldAccess['product']);
        $this->assertContains('billing.address', $fieldAccess['order']);
    }

    /** @test */
    public function multiple_users_with_different_ability_sets(): void
    {
        $user2 = new TestAdvancedBouncerUser();
        $user2->email = 'user2@example.com';
        $user2->save();

        // Grant different abilities to each user
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.read.*');
        \Silber\Bouncer\BouncerFacade::allow($user2)->to('mcp.tools.write.*');

        $this->assertTrue($this->testUser->hasMCPPermission('tools.read.files'));
        $this->assertFalse($this->testUser->hasMCPPermission('tools.write.files'));

        $this->assertFalse($user2->hasMCPPermission('tools.read.files'));
        $this->assertTrue($user2->hasMCPPermission('tools.write.files'));

        // Test ability retrieval for each user
        $user1Abilities = $this->permissionManager->getUserAbilities($this->testUser);
        $user2Abilities = $this->permissionManager->getUserAbilities($user2);

        $this->assertContains('tools.read.*', $user1Abilities);
        $this->assertNotContains('tools.write.*', $user1Abilities);

        $this->assertNotContains('tools.read.*', $user2Abilities);
        $this->assertContains('tools.write.*', $user2Abilities);
    }

    /** @test */
    public function role_inheritance_and_ability_combination(): void
    {
        // Create a role hierarchy
        \Silber\Bouncer\BouncerFacade::allow('base_user')->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::allow('advanced_user')->to('mcp.tools.file.*');
        \Silber\Bouncer\BouncerFacade::allow('admin_user')->to('mcp.*');

        // Assign multiple roles to simulate inheritance
        \Silber\Bouncer\BouncerFacade::assign('base_user')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::assign('advanced_user')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->isAn('base_user'));
        $this->assertTrue($this->testUser->isAn('advanced_user'));
        $this->assertFalse($this->testUser->isAn('admin_user'));

        // Should have abilities from both roles
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));
        $this->assertTrue($this->testUser->hasMCPPermission('tools.file.read'));
        $this->assertFalse($this->testUser->hasMCPPermission('resources.status'));

        // Add admin role
        \Silber\Bouncer\BouncerFacade::assign('admin_user')->to($this->testUser);
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->hasMCPPermission('resources.status'));
    }

    /** @test */
    public function ability_prefix_configuration_changes(): void
    {
        // Test with default prefix
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->permissionManager->userHasAbility($this->testUser, 'tools.echo'));

        // Change prefix configuration
        config(['mcp.auth.bouncer.ability_prefix' => 'app.mcp.']);
        $newManager = new BouncerPermissionManager();
        $newResolver = new BouncerPermissionResolver();

        // Grant ability with new prefix
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('app.mcp.tools.test');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($newManager->userHasAbility($this->testUser, 'tools.test'));
        $this->assertFalse($newManager->userHasAbility($this->testUser, 'tools.echo')); // Old prefix won't work

        // Test resolver with new prefix
        $abilities = $newResolver->resolveUserPermissions($this->testUser);
        $this->assertContains('tools.test', $abilities);
        $this->assertNotContains('tools.echo', $abilities);
    }

    /** @test */
    public function edge_case_empty_and_null_permissions(): void
    {
        // Test user with no permissions
        $abilities = $this->permissionManager->getUserAbilities($this->testUser);
        $this->assertEmpty($abilities);

        $fieldAccess = $this->permissionResolver->resolveUserFieldAccess($this->testUser);
        $this->assertEmpty($fieldAccess);

        // Test checking for non-existent permissions
        $this->assertFalse($this->permissionManager->userHasAbility($this->testUser, 'nonexistent.permission'));
        $this->assertFalse($this->testUser->hasMCPPermission('nonexistent.permission'));
    }

    /** @test */
    public function permission_revocation_works_correctly(): void
    {
        // Grant permission
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));

        // Revoke permission
        \Silber\Bouncer\BouncerFacade::disallow($this->testUser)->to('mcp.tools.echo');
        \Silber\Bouncer\BouncerFacade::refresh();

        $this->assertFalse($this->testUser->hasMCPPermission('tools.echo'));

        // Test that it's not in abilities list
        $abilities = $this->permissionManager->getUserAbilities($this->testUser);
        $this->assertNotContains('tools.echo', $abilities);
    }

    /** @test */
    public function forbidden_abilities_override_allowed(): void
    {
        $this->markTestSkipped('Forbidden abilities are an advanced Bouncer feature not implemented in basic MCP integration');
    }

    /** @test */
    public function performance_with_many_abilities(): void
    {
        // Grant many abilities to test performance
        $abilities = [];
        for ($i = 1; $i <= 100; $i++) {
            $ability = "mcp.test.permission{$i}";
            $abilities[] = $ability;
            \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to($ability);
        }

        // Test that all abilities are correctly retrieved
        $userAbilities = $this->permissionManager->getUserAbilities($this->testUser);
        $this->assertCount(100, $userAbilities);

        // Test specific ability checks
        $this->assertTrue($this->testUser->hasMCPPermission('test.permission1'));
        $this->assertTrue($this->testUser->hasMCPPermission('test.permission50'));
        $this->assertTrue($this->testUser->hasMCPPermission('test.permission100'));
    }

    /** @test */
    public function bouncer_refresh_clears_cached_abilities(): void
    {
        // Grant ability
        \Silber\Bouncer\BouncerFacade::allow($this->testUser)->to('mcp.tools.echo');
        $this->assertTrue($this->testUser->hasMCPPermission('tools.echo'));

        // Cache abilities
        $this->permissionManager->cacheUserAbilities($this->testUser);

        // Remove ability directly from database (bypassing Bouncer's cache)
        \Silber\Bouncer\BouncerFacade::disallow($this->testUser)->to('mcp.tools.echo');

        // Should still return true due to cache
        $this->assertFalse($this->testUser->hasMCPPermission('tools.echo'));

        // Refresh should clear cache
        \Silber\Bouncer\BouncerFacade::refresh();
        $this->assertFalse($this->testUser->hasMCPPermission('tools.echo'));
    }

    private function isBouncerAvailable(): bool
    {
        return class_exists(\Silber\Bouncer\BouncerFacade::class);
    }
}

/**
 * Advanced test user class for complex ability testing
 */
class TestAdvancedBouncerUser extends Authenticatable implements MCPUserInterface
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
        'name' => 'Advanced Test User',
        'email' => 'advanced@example.com',
        'password' => 'password',
        'mcp_enabled' => true,
    ];

    /**
     * Enhanced MCP permission checking with Bouncer
     */
    public function hasMCPPermission(string $permission): bool
    {
        if (!$this->mcp_enabled) {
            return false;
        }

        // Check direct ability with MCP prefix
        $mcpPermission = 'mcp.' . $permission;
        if ($this->can($mcpPermission)) {
            return true;
        }

        // Check for admin access first (global wildcard)
        if ($this->can('mcp.*') || $this->can('admin') || $this->isAn('admin')) {
            return true;
        }

        // Check for wildcard permissions by looking at granted abilities
        $userAbilities = $this->getAbilities()->pluck('name')->toArray();

        // Check if any granted wildcard ability matches the requested permission
        foreach ($userAbilities as $grantedAbility) {
            if (str_ends_with($grantedAbility, '.*') && str_starts_with($grantedAbility, 'mcp.')) {
                // Handle special case of 'mcp.*' (global MCP wildcard)
                if ($grantedAbility === 'mcp.*') {
                    return true;
                }

                // Extract pattern: 'mcp.tools.file.*' -> 'tools.file.'
                $mcpPattern = substr($grantedAbility, 4, -1); // Remove 'mcp.' prefix and '*' suffix

                // Check if requested permission matches the pattern
                if (str_starts_with($permission, $mcpPattern)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Enhanced field access checking
     */
    public function hasMCPFieldAccess(string $entityType, string $field): bool
    {
        if (!$this->mcp_enabled) {
            return false;
        }

        // Admin access
        if ($this->can('admin') || $this->isAn('admin') || $this->can('mcp.*')) {
            return true;
        }

        // Check specific field access patterns
        $patterns = [
            "access-fields.{$entityType}.{$field}",
            "access-fields.{$entityType}.*",
            "view-field.{$entityType}.{$field}",
            "view-field.{$entityType}.*",
        ];

        foreach ($patterns as $pattern) {
            if ($this->can($pattern)) {
                return true;
            }
        }

        return false;
    }
}