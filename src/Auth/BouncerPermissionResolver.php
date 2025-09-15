<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionResolverInterface;

class BouncerPermissionResolver implements PermissionResolverInterface
{
    public function resolveUserPermissions($user): array
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

    public function resolveUserFieldAccess($user): array
    {
        if (!$this->isBouncerAvailable()) {
            return [];
        }

        // Get all abilities that match field access patterns
        $abilities = $user->getAbilities()->pluck('name');
        $fieldAccess = [];

        foreach ($abilities as $abilityName) {
            // Parse abilities like "access-fields.user.profile.*" or "view-field.product.metadata.tags"
            if (preg_match('/^(?:access-fields|view-field)\.([^.]+)\.(.+)$/', $abilityName, $matches)) {
                $entityType = $matches[1];
                $field = $matches[2];

                if (!isset($fieldAccess[$entityType])) {
                    $fieldAccess[$entityType] = [];
                }
                $fieldAccess[$entityType][] = $field;
            }
            // Also handle MCP prefixed field abilities
            elseif (str_starts_with($abilityName, $this->getPrefix() . 'field.')) {
                $fieldAbility = str_replace($this->getPrefix() . 'field.', '', $abilityName);
                $parts = explode('.', $fieldAbility);
                if (count($parts) >= 2) {
                    $entityType = $parts[0];
                    $field = implode('.', array_slice($parts, 1));

                    if (!isset($fieldAccess[$entityType])) {
                        $fieldAccess[$entityType] = [];
                    }
                    $fieldAccess[$entityType][] = $field;
                }
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