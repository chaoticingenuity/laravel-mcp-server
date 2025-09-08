<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;

class DefaultPermissionManager implements PermissionManagerInterface
{
  public function userHasAbility($user, string $ability): bool
  {
    // Use the trait-based permission system to respect user customizations
    return $user->hasMCPPermission($ability);
  }

  public function getUserAbilities($user): array
  {
    return $user->getMCPPermissions();
  }

  public function cacheUserAbilities($user): void
  {
    // Basic caching implementation - could cache $user->getMCPPermissions()
    // for future enhancement
  }
}