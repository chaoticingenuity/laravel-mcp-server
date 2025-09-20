<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionResolverInterface;
use Illuminate\Support\Collection;

class PermissionResolverManager
{
    private Collection $resolvers;

    public function __construct()
    {
        $this->resolvers = collect();
        $this->registerDefaultResolvers();
        $this->registerCustomResolvers();
    }

    public function registerResolver(PermissionResolverInterface $resolver): void
    {
        $this->resolvers->push($resolver);
        // Sort by priority (highest first)
        $this->resolvers = $this->resolvers->sortByDesc('priority');
    }

    public function resolveUserPermissions($user): array
    {
        $resolver = $this->getResolverForUser($user);

        return $resolver ? $resolver->resolveUserPermissions($user) : [];
    }

    public function resolveUserFieldAccess($user): array
    {
        $resolver = $this->getResolverForUser($user);

        return $resolver ? $resolver->resolveUserFieldAccess($user) : [];
    }

    private function getResolverForUser($user): ?PermissionResolverInterface
    {
        return $this->resolvers->first(function (PermissionResolverInterface $resolver) use ($user) {
            return $resolver->canResolve($user);
        });
    }

    private function registerDefaultResolvers(): void
    {
        $this->registerResolver(new BouncerPermissionResolver);
        $this->registerResolver(new DefaultPermissionResolver);
    }

    private function registerCustomResolvers(): void
    {
        $customResolvers = config('mcp.auth.custom_permission_resolvers', []);

        foreach ($customResolvers as $resolverClass) {
            if (class_exists($resolverClass)) {
                $this->registerResolver(app($resolverClass));
            }
        }
    }
}
