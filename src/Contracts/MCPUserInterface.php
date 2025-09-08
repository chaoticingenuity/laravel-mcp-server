<?php
namespace ChaoticIngenuity\LaravelMCP\Contracts;

use ChaoticIngenuity\LaravelMCP\Models\ApiKey;
use Illuminate\Database\Eloquent\Relations\HasMany;
interface MCPUserInterface
{
  /**
   * Get the API keys for this user
   */
  public function apiKeys(): HasMany;

  /**
   * Check if user has MCP access enabled
   */
  public function hasMCPAccess(): bool;

  /**
   * Get MCP tokens for this user
   */
  public function getMCPTokens(): array;

  /**
   * Generate a new API key for this user
   */
  public function generateMCPApiKey(string $clientIdentifier, array $scopes = [], ?string $name = null): ApiKey;

  /**
   * Revoke an API key
   */
  public function revokeMCPApiKey(string $key): bool;

  /**
   * Get the user's MCP permissions
   */
  public function getMCPPermissions(): array;

  /**
   * Get the user's field access configuration
   */
  public function getMCPFieldAccess(): array;
}