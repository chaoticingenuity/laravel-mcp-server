<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface ContextInterface
{
  public function getClientId(): string;
  public function getPermissions(): array;
  public function getFieldAccess(): array;
  public function getMetadata(): array;
  public function hasPermission(string $permission): bool;
  public function hasFieldAccess(string $entityType, string $field): bool;
  public function getAccessibleFields(string $entityType): array;
}