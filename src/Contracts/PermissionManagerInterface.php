<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;
use Illuminate\Database\Eloquent\Model;

interface PermissionManagerInterface
{
  /**
   * Determine whether user has the specified ability
   * 
   * @param Model $user
   * @param string $ability
   * @return bool
   */
  public function userHasAbility($user, string $ability): bool;
  /**
   * Return user abilities
   * 
   * @param Model $user
   * @return array
   */
  public function getUserAbilities($user): array;
  /**
   * Cache user abilities
   * 
   * @param Model $user
   * @return void
   */
  public function cacheUserAbilities($user): void;
}