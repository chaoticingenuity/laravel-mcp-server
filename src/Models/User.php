<?php

namespace ChaoticIngenuity\LaravelMCP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Abstract User Model for MCP Package
 *
 * This is a template/contract for implementing users' User models.
 * DO NOT use this model directly in production. Instead, configure
 * your existing User model in config/mcp.php under auth.user_model.
 *
 * Your User model should implement the MCPUserInterface or include
 * the HasMCPAuthentication trait for MCP functionality.
 */
abstract class User extends Model
{
    /**
     * Get the API keys for this user
     */
    abstract public function apiKeys(): HasMany;

    /**
     * Check if user has MCP access enabled
     */
    abstract public function hasMCPAccess(): bool;

    /**
     * Get MCP tokens for this user
     */
    abstract public function getMCPTokens(): array;

    /**
     * Generate a new API key for this user
     */
    abstract public function generateMCPApiKey(string $clientIdentifier, array $scopes = [], ?string $name = null): ApiKey;

    /**
     * Revoke an API key
     */
    abstract public function revokeMCPApiKey(string $key): bool;

    /**
     * Get the user's MCP permissions
     */
    abstract public function getMCPPermissions(): array;

    /**
     * Get the user's field access configuration
     */
    abstract public function getMCPFieldAccess(): array;
}
