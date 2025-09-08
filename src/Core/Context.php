<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;

class Context implements ContextInterface
{
  private ?array $compiledPermissions = null;

  public function __construct(
    private readonly string $clientId,
    private readonly array $permissions,
    private readonly array $fieldAccess,
    private readonly array $metadata = []
  ) {
  }

  public function getClientId(): string
  {
    return $this->clientId;
  }

  public function getPermissions(): array
  {
    return $this->permissions;
  }

  public function getFieldAccess(): array
  {
    return $this->fieldAccess;
  }

  public function getMetadata(): array
  {
    return $this->metadata;
  }

  public function hasPermission(string $permission): bool
  {
    if (!isset($this->compiledPermissions)) {
      $this->compilePermissions();
    }
    
    // Check admin/wildcard first
    if (in_array('admin', $this->permissions) || in_array('*', $this->permissions)) {
      $this->trackPermissionCheck($permission, true);
      return true;
    }
    
    // Fast exact match
    if (in_array($permission, $this->compiledPermissions['exact'])) {
      $this->trackPermissionCheck($permission, true);
      return true;
    }
    
    // Check scopes if available
    if ($this->hasScope($permission)) {
      $this->trackPermissionCheck($permission, true);
      return true;
    }
    
    // Efficient wildcard check
    $parts = explode('.', $permission);
    for ($i = count($parts) - 1; $i >= 0; $i--) {
      $prefix = implode('.', array_slice($parts, 0, $i));
      if (in_array($prefix, $this->compiledPermissions['wildcards'])) {
        $this->trackPermissionCheck($permission, true);
        return true;
      }
    }
    
    // Track failed permission check
    $this->trackPermissionCheck($permission, false);
    return false;
  }

  public function hasFieldAccess(string $entityType, string $field): bool
  {
    if ($this->hasPermission('admin')) {
      return true;
    }

    $entityAccess = $this->fieldAccess[$entityType] ?? [];

    return in_array($field, $entityAccess) ||
      in_array('*', $entityAccess) ||
      in_array("{$entityType}.*", $this->fieldAccess['global'] ?? []);
  }

  public function getAccessibleFields(string $entityType): array
  {
    if ($this->hasPermission('admin')) {
      return ['*'];
    }

    return $this->fieldAccess[$entityType] ?? [];
  }

  /**
   * Filter data fields based on user's field access permissions
   */
  public function filterFields(string $entityType, array $data): array
  {
    if ($this->hasPermission('admin')) {
      return $data;
    }
    
    $allowedFields = $this->getAccessibleFields($entityType);
    if (in_array('*', $allowedFields)) {
      return $data;
    }
    
    return array_intersect_key($data, array_flip($allowedFields));
  }

  /**
   * Check if user has access to a specific scope
   */
  public function hasScope(string $scope): bool
  {
    $userScopes = $this->metadata['scopes'] ?? [];
    return in_array($scope, $userScopes) || in_array('*', $userScopes);
  }

  /**
   * Get all user scopes
   */
  public function getScopes(): array
  {
    return $this->metadata['scopes'] ?? [];
  }

  /**
   * Track permission check for performance monitoring
   */
  private function trackPermissionCheck(string $permission, bool $granted): void
  {
    if (config('mcp.performance.track_permissions', false)) {
      \Illuminate\Support\Facades\Cache::increment(
        "mcp.perms.{$permission}." . ($granted ? 'granted' : 'denied')
      );
    }
  }

  /**
   * Compile permissions for efficient checking
   */
  private function compilePermissions(): void
  {
    $this->compiledPermissions = [
      'exact' => [],
      'wildcards' => []
    ];
    
    foreach ($this->permissions as $perm) {
      if (str_ends_with($perm, '.*')) {
        $this->compiledPermissions['wildcards'][] = substr($perm, 0, -2);
      } else {
        $this->compiledPermissions['exact'][] = $perm;
      }
    }
  }
}