<?php
namespace App\Services\Custom\MCP\Database\Seeders;

use Illuminate\Database\Seeder;
use Silber\Bouncer\Bouncer;

class MCPBouncerSeeder extends Seeder
{
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
    Bouncer::ability()->firstOrCreate(['name' => 'mcp_access', 'title' => 'Access MCP Server']);
    Bouncer::ability()->firstOrCreate(['name' => 'admin', 'title' => 'Full Administrative Access']);

    // Tool abilities
    Bouncer::ability()->firstOrCreate(['name' => 'tools.*', 'title' => 'Access All Tools']);
    Bouncer::ability()->firstOrCreate(['name' => 'tools.echo', 'title' => 'Access Echo Tool']);
    Bouncer::ability()->firstOrCreate(['name' => 'tools.search_products', 'title' => 'Search Products']);
    Bouncer::ability()->firstOrCreate(['name' => 'tools.get_inventory', 'title' => 'Get Inventory Data']);

    // Resource abilities
    Bouncer::ability()->firstOrCreate(['name' => 'resources.*', 'title' => 'Access All Resources']);
    Bouncer::ability()->firstOrCreate(['name' => 'resources.status', 'title' => 'Access Server Status']);
    Bouncer::ability()->firstOrCreate(['name' => 'resources.catalog', 'title' => 'Access Product Catalog']);

    // Entity-level abilities
    Bouncer::ability()->firstOrCreate(['name' => 'products.read', 'title' => 'Read Product Data']);
    Bouncer::ability()->firstOrCreate(['name' => 'products.write', 'title' => 'Write Product Data']);
    Bouncer::ability()->firstOrCreate(['name' => 'users.read', 'title' => 'Read User Data']);
    Bouncer::ability()->firstOrCreate(['name' => 'orders.read', 'title' => 'Read Order Data']);

    // Field-level abilities
    $this->createFieldAbilities();
  }

  private function createFieldAbilities(): void
  {
    // Product field access
    $productFields = ['name', 'price', 'description', 'category', 'sku', 'inventory', 'cost', 'profit_margin'];
    foreach ($productFields as $field) {
      Bouncer::ability()->firstOrCreate([
        'name' => "access-fields.product.{$field}",
        'title' => "Access Product {$field} Field"
      ]);
    }

    // User field access
    $userFields = ['name', 'email', 'phone', 'address', 'created_at', 'last_login'];
    foreach ($userFields as $field) {
      Bouncer::ability()->firstOrCreate([
        'name' => "access-fields.user.{$field}",
        'title' => "Access User {$field} Field"
      ]);
    }

    // Wildcard field access
    Bouncer::ability()->firstOrCreate(['name' => 'access-fields.product.*', 'title' => 'Access All Product Fields']);
    Bouncer::ability()->firstOrCreate(['name' => 'access-fields.user.*', 'title' => 'Access All User Fields']);
    Bouncer::ability()->firstOrCreate(['name' => 'access-fields.*.*', 'title' => 'Access All Entity Fields']);
  }

  private function createMCPRoles(): void
  {
    // Admin role
    Bouncer::role()->firstOrCreate([
      'name' => 'admin',
      'title' => 'Administrator'
    ]);

    // MCP API User role
    Bouncer::role()->firstOrCreate([
      'name' => 'mcp_user',
      'title' => 'MCP API User'
    ]);

    // Public API role
    Bouncer::role()->firstOrCreate([
      'name' => 'public_api',
      'title' => 'Public API Access'
    ]);

    // Partner API role
    Bouncer::role()->firstOrCreate([
      'name' => 'partner_api',
      'title' => 'Partner API Access'
    ]);

    // Internal tool role
    Bouncer::role()->firstOrCreate([
      'name' => 'internal_tool',
      'title' => 'Internal Tool Access'
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
