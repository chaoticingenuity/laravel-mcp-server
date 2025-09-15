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
  
  /**
   * Get accessible fields for a specific resource instance using relationship-based access
   */
  public function getAccessibleFieldsForResource(string $resourceType, string $resourceId, FieldSetResolverInterface $resolver): array;
  
  /**
   * Check if user has access to a specific field for a specific resource instance
   */
  public function hasFieldAccessForResource(string $resourceType, string $resourceId, string $field, FieldSetResolverInterface $resolver): bool;
  
  /**
   * Get user ID from metadata (helper method for relationship checking)
   */
  public function getUserId(): ?string;
}