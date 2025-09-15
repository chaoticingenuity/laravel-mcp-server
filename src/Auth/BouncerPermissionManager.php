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


    // First check the exact ability
    $prefixedAbility = $this->getPrefixedAbility($ability);
    if ($user->can($prefixedAbility)) {
      return true;
    }

    // Check wildcard permissions
    $abilityParts = explode('.', $ability);
    for ($i = count($abilityParts) - 1; $i >= 0; $i--) {
      $wildcardAbility = $this->getPrefixedAbility(
        implode('.', array_slice($abilityParts, 0, $i)) . '.*'
      );
      if ($user->can($wildcardAbility)) {
        return true;
      }
    }

    // Check for global MCP wildcard
    if ($user->can($this->getPrefix() . '*')) {
      return true;
    }

    return false;
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

    if (!$this->isBouncerAvailable()) {
      // Graceful degradation when Bouncer is not available
      return;
    }

    // Use Bouncer's built-in caching
    \Silber\Bouncer\BouncerFacade::refresh();
  }

  private function isBouncerAvailable(): bool
  {
    if (!class_exists(\Silber\Bouncer\BouncerFacade::class)) {
      return false;
    }

    try {
      // Try to access Bouncer facade to ensure it's properly bootstrapped
      \Silber\Bouncer\BouncerFacade::getFacadeRoot();
      return true;
    } catch (\Exception $e) {
      return false;
    }
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