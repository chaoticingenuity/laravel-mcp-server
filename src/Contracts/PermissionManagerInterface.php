<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PermissionManagerInterface
{
    /**
     * Determine whether user has the specified ability
     *
     * @param  Model  $user
     */
    public function userHasAbility($user, string $ability): bool;

    /**
     * Return user abilities
     *
     * @param  Model  $user
     */
    public function getUserAbilities($user): array;

    /**
     * Cache user abilities
     *
     * @param  Model  $user
     */
    public function cacheUserAbilities($user): void;
}
