<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use ChaoticIngenuity\LaravelMCP\Traits\HasMCPAuthentication;
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;
use ChaoticIngenuity\LaravelMCP\Models\ApiKey;
use Illuminate\Support\Facades\Hash;

class UserBasic extends Authenticatable implements MCPUserInterface
{
  use HasMCPAuthentication;

  protected $fillable = [
    'name',
    'email',
    'password',
    'mcp_enabled',
    'mcp_permissions',
    'mcp_field_access',
    'mcp_api_key_1',
    'mcp_api_key_2',
    'mcp_api_key_1_meta',
    'mcp_api_key_2_meta'
  ];

  protected $casts = [
    'email_verified_at' => 'datetime',
    'password' => 'hashed',
    'mcp_enabled' => 'boolean',
    'mcp_permissions' => 'array',
    'mcp_field_access' => 'array',
    'mcp_tokens' => 'array',
    'mcp_api_key_1_meta' => 'array',
    'mcp_api_key_2_meta' => 'array',
  ];

  protected $hidden = [
    'password',
    'mcp_api_key_1',
    'mcp_api_key_2'
  ];

  /**
   * Override: Generate API key and store in user table column
   */
  public function generateMCPApiKey(string $clientIdentifier, array $scopes = [], ?string $name = null): ApiKey
  {
    $key = 'mcp_' . bin2hex(random_bytes(32));
    $hashedKey = Hash::make($key);

    $metadata = [
      'client_identifier' => $clientIdentifier,
      'name' => $name,
      'scopes' => $scopes,
      'is_active' => true,
      'created_at' => now()->toISOString(),
      'expires_at' => now()->addYear()->toISOString(),
      'last_used_at' => null,
    ];

    // Store in first available slot
    if (empty($this->mcp_api_key_1)) {
      $this->update([
        'mcp_api_key_1' => $hashedKey,
        'mcp_api_key_1_meta' => $metadata,
      ]);
      $keyId = 'key_1';
    } elseif (empty($this->mcp_api_key_2)) {
      $this->update([
        'mcp_api_key_2' => $hashedKey,
        'mcp_api_key_2_meta' => $metadata,
      ]);
      $keyId = 'key_2';
    } else {
      throw new \Exception('Maximum number of API keys reached');
    }

    // Return a mock ApiKey object for compatibility
    return new class ($key, $keyId, $metadata) {
      public function __construct(
        public readonly string $key,
        public readonly string $keyId,
        public readonly array $metadata
      ) {
      }

      public function __get($name)
      {
        return $this->metadata[$name] ?? null;
      }
    };
  }

  /**
   * Override: Revoke API key by setting column to null
   */
  public function revokeMCPApiKey(string $key): bool
  {
    // Check key_1
    if ($this->mcp_api_key_1 && Hash::check($key, $this->mcp_api_key_1)) {
      $metadata = $this->mcp_api_key_1_meta ?? [];
      $metadata['is_active'] = false;
      $metadata['revoked_at'] = now()->toISOString();

      $this->update([
        'mcp_api_key_1' => null,
        'mcp_api_key_1_meta' => $metadata,
      ]);
      return true;
    }

    // Check key_2
    if ($this->mcp_api_key_2 && Hash::check($key, $this->mcp_api_key_2)) {
      $metadata = $this->mcp_api_key_2_meta ?? [];
      $metadata['is_active'] = false;
      $metadata['revoked_at'] = now()->toISOString();

      $this->update([
        'mcp_api_key_2' => null,
        'mcp_api_key_2_meta' => $metadata,
      ]);
      return true;
    }

    return false;
  }

  /**
   * Override: Validate API key against user table columns
   */
  public function isMCPApiKeyValid(string $key): bool
  {
    // Check if user has MCP access
    if (!$this->hasMCPAccess()) {
      return false;
    }

    // Check key_1
    if ($this->mcp_api_key_1 && Hash::check($key, $this->mcp_api_key_1)) {
      $metadata = $this->mcp_api_key_1_meta ?? [];
      return $this->isKeyMetadataValid($metadata);
    }

    // Check key_2
    if ($this->mcp_api_key_2 && Hash::check($key, $this->mcp_api_key_2)) {
      $metadata = $this->mcp_api_key_2_meta ?? [];
      return $this->isKeyMetadataValid($metadata);
    }

    return false;
  }

  /**
   * Override: Get API key info without exposing the hash
   */
  public function getMCPApiKeyInfo(string $key): ?array
  {
    // Check key_1
    if ($this->mcp_api_key_1 && Hash::check($key, $this->mcp_api_key_1)) {
      $metadata = $this->mcp_api_key_1_meta ?? [];
      return $this->formatKeyInfo('key_1', $metadata);
    }

    // Check key_2
    if ($this->mcp_api_key_2 && Hash::check($key, $this->mcp_api_key_2)) {
      $metadata = $this->mcp_api_key_2_meta ?? [];
      return $this->formatKeyInfo('key_2', $metadata);
    }

    return null;
  }

  /**
   * Override: Get summary without exposing keys
   */
  public function getMCPApiKeysSummary(): array
  {
    $keys = [];
    $activeCount = 0;
    $expiredCount = 0;

    // Process key_1
    if (!empty($this->mcp_api_key_1_meta)) {
      $metadata = $this->mcp_api_key_1_meta;
      $isExpired = isset($metadata['expires_at']) &&
        now()->isAfter($metadata['expires_at']);

      if ($metadata['is_active'] ?? false) {
        $activeCount++;
      }
      if ($isExpired) {
        $expiredCount++;
      }

      $keys[] = $this->formatKeyInfo('key_1', $metadata);
    }

    // Process key_2
    if (!empty($this->mcp_api_key_2_meta)) {
      $metadata = $this->mcp_api_key_2_meta;
      $isExpired = isset($metadata['expires_at']) &&
        now()->isAfter($metadata['expires_at']);

      if ($metadata['is_active'] ?? false) {
        $activeCount++;
      }
      if ($isExpired) {
        $expiredCount++;
      }

      $keys[] = $this->formatKeyInfo('key_2', $metadata);
    }

    return [
      'total_keys' => count($keys),
      'active_keys' => $activeCount,
      'expired_keys' => $expiredCount,
      'keys' => $keys,
    ];
  }

  /**
   * Helper: Check if key metadata indicates valid key
   */
  private function isKeyMetadataValid(array $metadata): bool
  {
    // Check if active
    if (!($metadata['is_active'] ?? false)) {
      return false;
    }

    // Check if expired
    if (
      isset($metadata['expires_at']) &&
      now()->isAfter($metadata['expires_at'])
    ) {
      return false;
    }

    return true;
  }

  /**
   * Helper: Format key info for display
   */
  private function formatKeyInfo(string $keyId, array $metadata): array
  {
    return [
      'id' => $keyId,
      'client_identifier' => $metadata['client_identifier'] ?? null,
      'name' => $metadata['name'] ?? null,
      'scopes' => $metadata['scopes'] ?? [],
      'is_active' => $metadata['is_active'] ?? false,
      'is_valid' => $this->isKeyMetadataValid($metadata),
      'last_used_at' => $metadata['last_used_at'] ?? null,
      'expires_at' => $metadata['expires_at'] ?? null,
      'created_at' => $metadata['created_at'] ?? null,
      'revoked_at' => $metadata['revoked_at'] ?? null,
    ];
  }

  /**
   * Update last used timestamp for API key
   */
  public function updateMCPApiKeyLastUsed(string $key): void
  {
    $now = now()->toISOString();

    // Check key_1
    if ($this->mcp_api_key_1 && Hash::check($key, $this->mcp_api_key_1)) {
      $metadata = $this->mcp_api_key_1_meta ?? [];
      $metadata['last_used_at'] = $now;
      $this->update(['mcp_api_key_1_meta' => $metadata]);
      return;
    }

    // Check key_2
    if ($this->mcp_api_key_2 && Hash::check($key, $this->mcp_api_key_2)) {
      $metadata = $this->mcp_api_key_2_meta ?? [];
      $metadata['last_used_at'] = $now;
      $this->update(['mcp_api_key_2_meta' => $metadata]);
      return;
    }
  }

  /**
   * Get client identifier for a given API key
   */
  public function getMCPClientIdentifier(string $key): ?string
  {
    $keyInfo = $this->getMCPApiKeyInfo($key);
    return $keyInfo['client_identifier'] ?? null;
  }
}