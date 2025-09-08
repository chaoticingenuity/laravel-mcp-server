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
    'expires_at'
  ];

  protected $casts = [
    'scopes' => 'array',
    'is_active' => 'boolean',
    'last_used_at' => 'datetime',
    'expires_at' => 'datetime',
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
}