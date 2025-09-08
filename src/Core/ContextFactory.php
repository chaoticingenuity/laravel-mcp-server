<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;

class ContextFactory
{
  public function createFromClient(string $clientId): ContextInterface
  {
    $clientConfig = config("mcp.clients.{$clientId}");

    if (!$clientConfig) {
      throw new \Exception("Unknown client: {$clientId}");
    }

    return new Context(
      clientId: $clientId,
      permissions: $clientConfig['permissions'] ?? [],
      fieldAccess: $clientConfig['field_access'] ?? [],
      metadata: $clientConfig['metadata'] ?? []
    );
  }
}