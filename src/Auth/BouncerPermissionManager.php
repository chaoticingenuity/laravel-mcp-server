<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;

class BouncerPermissionManager implements PermissionManagerInterface
{
  public function userHasAbility($user, string $ability): bool
  {
    if (!$this->isBouncerAvailable()) {
      throw new \RuntimeException('Bouncer package is required but not installed');
    }

    $prefixedAbility = $this->getPrefixedAbility($ability);
    return $user->can($prefixedAbility);
  }

  public function getUserAbilities($user): array
  {
    if (!$this->isBouncerAvailable()) {
      return [];
    }

    return $user->getAbilities()
      ->filter(fn($ability) => str_starts_with($ability->name, $this->getPrefix()))
      ->map(fn($ability) => str_replace($this->getPrefix(), '', $ability->name))
      ->values()
      ->toArray();
  }

  public function cacheUserAbilities($user): void
  {
    if (!config('mcp.auth.bouncer.cache_abilities', true)) {
      return;
    }

    // Use Bouncer's built-in caching
    \Bouncer::refresh();
  }

  private function isBouncerAvailable(): bool
  {
    return class_exists(\Silber\Bouncer\BouncerFacade::class);
  }

  private function getPrefixedAbility(string $ability): string
  {
    return $this->getPrefix() . $ability;
  }

  private function getPrefix(): string
  {
    return config('mcp.auth.bouncer.ability_prefix', 'mcp.');
  }
}