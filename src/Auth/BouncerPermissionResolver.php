<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionResolverInterface;

class BouncerPermissionResolver implements PermissionResolverInterface
{
    public function resolveUserPermissions($user): array
    {
        if (! $this->isBouncerAvailable()) {
            return [];
        }

        return $user->getAbilities()
            ->filter(fn ($ability) => str_starts_with($ability->name, $this->getPrefix()))
            ->map(fn ($ability) => str_replace($this->getPrefix(), '', $ability->name))
            ->values()
            ->toArray();
    }

    public function resolveUserFieldAccess($user): array
    {
        if (! $this->isBouncerAvailable()) {
            return [];
        }

        // Get field access from user abilities with 'field.' prefix
        $fieldAbilities = $user->getAbilities()
            ->filter(fn ($ability) => str_starts_with($ability->name, $this->getPrefix().'field.'))
            ->map(fn ($ability) => str_replace($this->getPrefix().'field.', '', $ability->name));

        $fieldAccess = [];
        foreach ($fieldAbilities as $fieldAbility) {
            // Parse abilities like "users.name", "posts.*", etc.
            $parts = explode('.', $fieldAbility);
            if (count($parts) >= 2) {
                $entityType = $parts[0];
                $field = implode('.', array_slice($parts, 1));

                if (! isset($fieldAccess[$entityType])) {
                    $fieldAccess[$entityType] = [];
                }
                $fieldAccess[$entityType][] = $field;
            }
        }

        return $fieldAccess;
    }

    public function canResolve($user): bool
    {
        return config('mcp.auth.bouncer.enabled', false) &&
               $this->isBouncerAvailable() &&
               method_exists($user, 'getAbilities');
    }

    public function getPriority(): int
    {
        return 100; // High priority
    }

    private function isBouncerAvailable(): bool
    {
        return class_exists(\Silber\Bouncer\BouncerFacade::class);
    }

    private function getPrefix(): string
    {
        return config('mcp.auth.bouncer.ability_prefix', 'mcp.');
    }
}
