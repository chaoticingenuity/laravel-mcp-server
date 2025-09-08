<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;

class Context implements ContextInterface
{
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
    return in_array($permission, $this->permissions) || in_array('*', $this->permissions);
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
}