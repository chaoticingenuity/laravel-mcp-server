<?php

namespace App\Services\Custom\MCP\Database\Seeders;

use Illuminate\Database\Seeder;
use Silber\Bouncer\BouncerFacade as Bouncer;
use Silber\Bouncer\Database\Ability;
use Silber\Bouncer\Database\Role;

class MCPBouncerSeeder extends Seeder
{
    public function __construct(
        private \Silber\Bouncer\Bouncer $bouncer
    ) {}

    public function run(): void
    {
        // Create MCP-related abilities
        $this->createMCPAbilities();

        // Create MCP roles
        $this->createMCPRoles();

        // Assign abilities to roles
        $this->assignAbilitiesToRoles();
    }

    private function createMCPAbilities(): void
    {
        // General MCP abilities
        Ability::firstOrCreate(['name' => 'mcp_access', 'title' => 'Access MCP Server']);
        Ability::firstOrCreate(['name' => 'admin', 'title' => 'Full Administrative Access']);

        // Tool abilities
        Ability::firstOrCreate(['name' => 'tools.*', 'title' => 'Access All Tools']);
        Ability::firstOrCreate(['name' => 'tools.echo', 'title' => 'Access Echo Tool']);
        Ability::firstOrCreate(['name' => 'tools.search_products', 'title' => 'Search Products']);
        Ability::firstOrCreate(['name' => 'tools.get_inventory', 'title' => 'Get Inventory Data']);

        // Resource abilities
        Ability::firstOrCreate(['name' => 'resources.*', 'title' => 'Access All Resources']);
        Ability::firstOrCreate(['name' => 'resources.status', 'title' => 'Access Server Status']);
        Ability::firstOrCreate(['name' => 'resources.catalog', 'title' => 'Access Product Catalog']);

        // Entity-level abilities
        Ability::firstOrCreate(['name' => 'products.read', 'title' => 'Read Product Data']);
        Ability::firstOrCreate(['name' => 'products.write', 'title' => 'Write Product Data']);
        Ability::firstOrCreate(['name' => 'users.read', 'title' => 'Read User Data']);
        Ability::firstOrCreate(['name' => 'orders.read', 'title' => 'Read Order Data']);

        // Field-level abilities
        $this->createFieldAbilities();
    }

    private function createFieldAbilities(): void
    {
        // Product field access
        $productFields = ['name', 'price', 'description', 'category', 'sku', 'inventory', 'cost', 'profit_margin'];
        foreach ($productFields as $field) {
            Ability::firstOrCreate([
                'name' => "access-fields.product.{$field}",
                'title' => "Access Product {$field} Field",
            ]);
        }

        // User field access
        $userFields = ['name', 'email', 'phone', 'address', 'created_at', 'last_login'];
        foreach ($userFields as $field) {
            Ability::firstOrCreate([
                'name' => "access-fields.user.{$field}",
                'title' => "Access User {$field} Field",
            ]);
        }

        // Wildcard field access
        Ability::firstOrCreate(['name' => 'access-fields.product.*', 'title' => 'Access All Product Fields']);
        Ability::firstOrCreate(['name' => 'access-fields.user.*', 'title' => 'Access All User Fields']);
        Ability::firstOrCreate(['name' => 'access-fields.*.*', 'title' => 'Access All Entity Fields']);
    }

    private function createMCPRoles(): void
    {
        // Admin role
        Role::firstOrCreate([
            'name' => 'admin',
            'title' => 'Administrator',
        ]);

        // MCP API User role
        Role::firstOrCreate([
            'name' => 'mcp_user',
            'title' => 'MCP API User',
        ]);

        // Public API role
        Role::firstOrCreate([
            'name' => 'public_api',
            'title' => 'Public API Access',
        ]);

        // Partner API role
        Role::firstOrCreate([
            'name' => 'partner_api',
            'title' => 'Partner API Access',
        ]);

        // Internal tool role
        Role::firstOrCreate([
            'name' => 'internal_tool',
            'title' => 'Internal Tool Access',
        ]);
    }

    private function assignAbilitiesToRoles(): void
    {
        // Admin gets everything
        Bouncer::allow('admin')->to('admin');

        // MCP User role
        Bouncer::allow('mcp_user')->to([
            'mcp_access',
            'tools.*',
            'resources.*',
            'products.read',
            'users.read',
            'access-fields.product.*',
            'access-fields.user.name',
            'access-fields.user.email',
        ]);

        // Public API role
        Bouncer::allow('public_api')->to([
            'mcp_access',
            'tools.echo',
            'resources.status',
            'resources.catalog',
            'products.read',
            'access-fields.product.name',
            'access-fields.product.price',
            'access-fields.product.description',
        ]);

        // Partner API role
        Bouncer::allow('partner_api')->to([
            'mcp_access',
            'tools.search_products',
            'tools.get_inventory',
            'resources.catalog',
            'products.read',
            'orders.read',
            'access-fields.product.*',
            'access-fields.user.name',
            'access-fields.user.email',
            'access-fields.user.phone',
        ]);

        // Internal tool role
        Bouncer::allow('internal_tool')->to([
            'mcp_access',
            'tools.*',
            'resources.*',
            'products.read',
            'products.write',
            'users.read',
            'orders.read',
            'access-fields.*.*',
        ]);
    }
}
