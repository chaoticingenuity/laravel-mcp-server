<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Exceptions\MCPAuthenticationException;

class ContextFactory
{
    public function createFromClient(string $clientId): ContextInterface
    {
        // Validate client ID input
        if (empty($clientId)) {
            throw new MCPAuthenticationException('Client ID cannot be empty');
        }

        if (strlen($clientId) > 255) {
            throw new MCPAuthenticationException('Client ID too long');
        }

        // Only allow alphanumeric, underscore, hyphen, and dot
        if (! preg_match('/^[a-zA-Z0-9._-]+$/', $clientId)) {
            throw new MCPAuthenticationException('Invalid client ID format');
        }

        $clientConfig = config("mcp.auth.clients.{$clientId}");

        if (! $clientConfig) {
            // In test environment or for unknown clients, provide default context
            if (app()->environment('testing') || config('mcp.allow_unknown_clients', false)) {
                return new Context(
                    clientId: $clientId,
                    permissions: ['*'], // Default permissions for testing
                    fieldAccess: [],
                    metadata: []
                );
            }

            throw new MCPAuthenticationException("Unknown client: {$clientId}");
        }

        return new Context(
            clientId: $clientId,
            permissions: $clientConfig['permissions'] ?? [],
            fieldAccess: $clientConfig['field_access'] ?? [],
            metadata: $clientConfig['metadata'] ?? []
        );
    }
}
