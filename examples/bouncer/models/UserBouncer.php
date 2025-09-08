<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use Silber\Bouncer\Database\HasRolesAndAbilities;

class UserBouncer extends Authenticatable implements MCPUserInterface
{
  use HasMCPAuthentication, HasRolesAndAbilities;

  protected $fillable = [
    'name',
    'email',
    'password',
    'mcp_enabled'
  ];

  protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'mcp_enabled' => 'boolean',
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
   * Override: Check MCP permission using Bouncer
   */
  public function hasMCPPermission(string $permission): bool
  {
    // Check if user can perform the specific MCP action
    return $this->can($permission) ||
      $this->can('admin') ||
      $this->can('mcp.*') ||
      $this->isAn('admin');
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