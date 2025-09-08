<?php
namespace ChaoticIngenuity\LaravelMCP\Models;

use Illuminate\Database\Eloquent\{Model, Relations\BelongsTo};
use ChaoticIngenuity\LaravelMCP\Contracts\MCPUserInterface;

class ApiKey extends Model
{
  protected $fillable = [
    'key',
    'client_identifier',
    'user_id',
    'name',
    'scopes',
    'is_active',
    'expires_at',
    'usage_count',
    'rate_limit_per_minute',
    'rate_limit_burst'
  ];

  protected $casts = [
    'scopes' => 'array',
    'is_active' => 'boolean',
    'last_used_at' => 'datetime',
    'expires_at' => 'datetime',
    'usage_count' => 'integer',
    'rate_limit_per_minute' => 'integer',
    'rate_limit_burst' => 'integer',
  ];

  protected $hidden = [
    'key' // Hide the actual key in JSON responses
  ];

  public function user(): BelongsTo
  {
    $userModel = config('mcp.auth.user_model.class', \App\Models\User::class);
    $foreignKey = config('mcp.auth.user_model.foreign_key', 'user_id');
    $ownerKey = config('mcp.auth.user_model.owner_key', 'id');

    return $this->belongsTo($userModel, $foreignKey, $ownerKey);
  }

  public static function generate(
    MCPUserInterface $user,
    string $clientIdentifier,
    array $scopes = [],
    ?string $name = null
  ): self {
    $foreignKey = config('mcp.auth.user_model.foreign_key', 'user_id');

    return self::create([
      'key' => 'mcp_' . bin2hex(random_bytes(32)),
      'client_identifier' => $clientIdentifier,
      $foreignKey => $user->getKey(),
      'name' => $name,
      'scopes' => $scopes,
      'expires_at' => now()->addYear(),
    ]);
  }

  /**
   * Get the masked key for display purposes
   */
  public function getMaskedKeyAttribute(): string
  {
    return substr($this->key, 0, 8) . str_repeat('*', 24) . substr($this->key, -8);
  }

  /**
   * Check if the key is expired
   */
  public function isExpired(): bool
  {
    return $this->expires_at && $this->expires_at->isPast();
  }

  /**
   * Check if the key is active and not expired
   */
  public function isValid(): bool
  {
    return $this->is_active && !$this->isExpired();
  }

  /**
   * Scope to only active keys
   */
  public function scopeActive($query)
  {
    return $query->where('is_active', true);
  }

  /**
   * Scope to only non-expired keys
   */
  public function scopeNotExpired($query)
  {
    return $query->where(function ($query) {
      $query->whereNull('expires_at')
        ->orWhere('expires_at', '>', now());
    });
  }

  /**
   * Scope to only valid keys (active and not expired)
   */
  public function scopeValid($query)
  {
    return $query->active()->notExpired();
  }

  /**
   * Record API key usage with optional metadata
   */
  public function recordUsage(array $metadata = []): void
  {
    $this->update([
      'last_used_at' => now(),
      'usage_count' => ($this->usage_count ?? 0) + 1
    ]);
    
    // Log detailed usage if enabled
    if (config('mcp.auth.log_api_usage', false)) {
      \Illuminate\Support\Facades\Log::channel(config('mcp.logging.channel', 'mcp'))
        ->info('MCP API Key Usage', [
          'key_id' => $this->id,
          'client_identifier' => $this->client_identifier,
          'user_id' => $this->user_id,
          'usage_count' => $this->usage_count,
          'metadata' => $metadata,
          'timestamp' => now()->toISOString()
        ]);
    }
  }

  /**
   * Check if key is rate limited
   */
  public function isRateLimited(): bool
  {
    if (!$this->rate_limit_per_minute) {
      return false;
    }

    $cacheKey = "mcp.rate_limit.{$this->id}";
    $currentCount = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
    
    return $currentCount >= $this->rate_limit_per_minute;
  }

  /**
   * Increment rate limit counter
   */
  public function incrementRateLimit(): void
  {
    if (!$this->rate_limit_per_minute) {
      return;
    }

    $cacheKey = "mcp.rate_limit.{$this->id}";
    
    // Use add() with TTL if key doesn't exist, otherwise increment
    if (!\Illuminate\Support\Facades\Cache::has($cacheKey)) {
      \Illuminate\Support\Facades\Cache::put($cacheKey, 1, now()->addSeconds(60));
    } else {
      \Illuminate\Support\Facades\Cache::increment($cacheKey);
    }
  }

  /**
   * Get rate limit status
   */
  public function getRateLimitStatus(): array
  {
    if (!$this->rate_limit_per_minute) {
      return ['limited' => false];
    }

    $cacheKey = "mcp.rate_limit.{$this->id}";
    $currentCount = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
    
    return [
      'limited' => $currentCount >= $this->rate_limit_per_minute,
      'current_count' => $currentCount,
      'limit_per_minute' => $this->rate_limit_per_minute,
      'remaining' => max(0, $this->rate_limit_per_minute - $currentCount),
      'reset_at' => now()->addSeconds(\Illuminate\Support\Facades\Cache::ttl($cacheKey) ?: 60)
    ];
  }
}