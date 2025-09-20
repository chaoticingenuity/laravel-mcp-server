<?php

namespace ChaoticIngenuity\LaravelMCP\Traits;

use ChaoticIngenuity\LaravelMCP\Contracts\PermissionManagerInterface;
use ChaoticIngenuity\LaravelMCP\Models\ApiKey;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasMCPAuthentication
{
    /**
     * Get the API keys for this user
     *
     * Override this method if you store keys differently (e.g., in user table columns)
     */
    public function apiKeys(): HasMany
    {
        $foreignKey = config('mcp.auth.user_model.foreign_key', 'user_id');
        $localKey = config('mcp.auth.user_model.owner_key', $this->getKeyName());

        return $this->hasMany(ApiKey::class, $foreignKey, $localKey);
    }

    /**
     * Check if user has MCP access enabled
     */
    public function hasMCPAccess(): bool
    {
        return $this->mcp_enabled ?? true;
    }

    /**
     * Get MCP tokens for this user
     */
    public function getMCPTokens(): array
    {
        return $this->mcp_tokens ?? [];
    }

    /**
     * Generate a new API key for this user
     *
     * Override this method for custom key storage (e.g., store in user table columns)
     */
    public function generateMCPApiKey(string $clientIdentifier, array $scopes = [], ?string $name = null): ApiKey
    {
        return ApiKey::generate($this, $clientIdentifier, $scopes, $name);
    }

    /**
     * Revoke an API key
     *
     * Override this method for custom key storage patterns:
     * - Set user table column to null
     * - Mark key as revoked in user metadata
     * - Custom revocation logic
     *
     * @param  string  $key  The key value or key identifier
     */
    public function revokeMCPApiKey(string $key): bool
    {
        return $this->apiKeys()
            ->where('key', $key)
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Validate if an API key is valid for this user
     *
     * This is more secure than exposing key collections.
     * Override for custom key storage patterns.
     *
     * @param  string  $key  The key to validate
     * @return bool True if key is valid and belongs to this user
     */
    public function isMCPApiKeyValid(string $key): bool
    {
        return $this->apiKeys()
            ->where('key', $key)
            ->valid()
            ->exists();
    }

    /**
     * Get API key information without exposing the actual key
     *
     * @param  string  $key  The key to lookup
     * @return array|null Key metadata without the actual key value
     */
    public function getMCPApiKeyInfo(string $key): ?array
    {
        $apiKey = $this->apiKeys()
            ->where('key', $key)
            ->first(['id', 'client_identifier', 'name', 'scopes', 'is_active', 'last_used_at', 'expires_at', 'created_at']);

        if (! $apiKey) {
            return null;
        }

        return [
            'id' => $apiKey->id,
            'client_identifier' => $apiKey->client_identifier,
            'name' => $apiKey->name,
            'scopes' => $apiKey->scopes,
            'is_active' => $apiKey->is_active,
            'is_valid' => $apiKey->isValid(),
            'last_used_at' => $apiKey->last_used_at,
            'expires_at' => $apiKey->expires_at,
            'created_at' => $apiKey->created_at,
        ];
    }

    /**
     * Get summary of user's API keys without exposing actual keys
     *
     * @return array Summary information about user's keys
     */
    public function getMCPApiKeysSummary(): array
    {
        $keys = $this->apiKeys()
            ->select(['id', 'client_identifier', 'name', 'is_active', 'last_used_at', 'expires_at', 'created_at'])
            ->get();

        return [
            'total_keys' => $keys->count(),
            'active_keys' => $keys->where('is_active', true)->count(),
            'expired_keys' => $keys->filter(fn ($key) => $key->expires_at && $key->expires_at->isPast())->count(),
            'keys' => $keys->map(function ($key) {
                return [
                    'id' => $key->id,
                    'client_identifier' => $key->client_identifier,
                    'name' => $key->name,
                    'is_active' => $key->is_active,
                    'is_expired' => $key->expires_at && $key->expires_at->isPast(),
                    'last_used_at' => $key->last_used_at,
                    'expires_at' => $key->expires_at,
                    'created_at' => $key->created_at,
                ];
            })->toArray(),
        ];
    }

    /**
     * Revoke all API keys for this user
     */
    public function revokeAllMCPApiKeys(): int
    {
        return $this->apiKeys()
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * Clean up expired API keys
     */
    public function cleanupExpiredMCPApiKeys(): int
    {
        return $this->apiKeys()
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Get the user's MCP permissions
     * Uses permission resolver system for flexible permission resolution
     */
    public function getMCPPermissions(): array
    {
        if (app()->bound('mcp.permission_resolver_manager')) {
            return app('mcp.permission_resolver_manager')->resolveUserPermissions($this);
        }

        // Fallback to legacy behavior
        if ($this->shouldUseBouncer()) {
            return app(PermissionManagerInterface::class)->getUserAbilities($this);
        }

        return ! empty($this->mcp_permissions) ? $this->mcp_permissions : [
            'tools.echo',
            'resources.status',
        ];
    }

    /**
     * Get the user's field access configuration
     */
    public function getMCPFieldAccess(): array
    {
        if (app()->bound('mcp.permission_resolver_manager')) {
            return app('mcp.permission_resolver_manager')->resolveUserFieldAccess($this);
        }

        return $this->mcp_field_access ?? [];
    }

    /**
     * Scope to only users with MCP access
     */
    public function scopeWithMCPAccess($query)
    {
        return $query->where('mcp_enabled', true);
    }

    /**
     * Check if user has a specific MCP permission
     */
    public function hasMCPPermission(string $permission): bool
    {
        if ($this->shouldUseBouncer()) {
            return app(PermissionManagerInterface::class)->userHasAbility($this, $permission);
        }

        // Fallback to original implementation with wildcard support
        $permissions = $this->getMCPPermissions();

        if (in_array('admin', $permissions) || in_array('*', $permissions)) {
            return true;
        }

        if (in_array($permission, $permissions)) {
            return true;
        }

        // Check for wildcard permissions
        $permissionParts = explode('.', $permission);
        for ($i = count($permissionParts) - 1; $i >= 0; $i--) {
            $wildcardPermission = implode('.', array_slice($permissionParts, 0, $i)).'.*';
            if (in_array($wildcardPermission, $permissions)) {
                return true;
            }
        }

        return false;
    }

    public function shouldUseBouncer(): bool
    {
        return config('mcp.auth.bouncer.enabled', false) &&
            class_exists(\Silber\Bouncer\BouncerFacade::class);
    }

    /**
     * Check if user has access to a specific field
     */
    public function hasMCPFieldAccess(string $entityType, string $field): bool
    {
        if ($this->hasMCPPermission('admin')) {
            return true;
        }

        $fieldAccess = $this->getMCPFieldAccess();
        $entityAccess = $fieldAccess[$entityType] ?? [];

        return in_array($field, $entityAccess) ||
            in_array('*', $entityAccess) ||
            in_array("{$entityType}.*", $fieldAccess['global'] ?? []);
    }

    /**
     * Rotate an API key (disable old, create new)
     */
    public function rotateMCPApiKey(string $oldKey): ?ApiKey
    {
        $apiKey = $this->apiKeys()->where('key', $oldKey)->first();
        if (! $apiKey) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($apiKey) {
            $apiKey->update(['is_active' => false]);

            return $this->generateMCPApiKey(
                $apiKey->client_identifier,
                $apiKey->scopes,
                ($apiKey->name ?? 'API Key').' (rotated)'
            );
        });
    }

    /**
     * Get API key with usage tracking
     */
    public function getMCPApiKeyWithTracking(string $key): ?ApiKey
    {
        $apiKey = $this->apiKeys()
            ->where('key', $key)
            ->valid()
            ->first();

        if ($apiKey) {
            $apiKey->recordUsage([
                'ip_address' => request()->ip(),
                'user_agent' => request()->header('User-Agent'),
                'endpoint' => request()->path(),
            ]);
        }

        return $apiKey;
    }

    /**
     * Check if user has a specific scope
     */
    public function hasMCPScope(string $scope): bool
    {
        $userScopes = $this->mcp_scopes ?? [];

        return in_array($scope, $userScopes) || in_array('*', $userScopes);
    }

    /**
     * Get user's MCP scopes
     */
    public function getMCPScopes(): array
    {
        return $this->mcp_scopes ?? [];
    }

    /**
     * Add scope to user
     */
    public function addMCPScope(string $scope): void
    {
        $scopes = $this->getMCPScopes();
        if (! in_array($scope, $scopes)) {
            $scopes[] = $scope;
            $this->mcp_scopes = $scopes;
            $this->save();
        }
    }

    /**
     * Remove scope from user
     */
    public function removeMCPScope(string $scope): void
    {
        $scopes = $this->getMCPScopes();
        $this->mcp_scopes = array_values(array_diff($scopes, [$scope]));
        $this->save();
    }
}
