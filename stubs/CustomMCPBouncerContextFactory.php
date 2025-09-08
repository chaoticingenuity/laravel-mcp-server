<?php
namespace App\Services\Custom\MCP;

use ChaoticIngenuity\LaravelMCP\Core\{Context, ContextFactory};
use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;

class BouncerContextFactory extends ContextFactory
{
    public function createFromClient(string $clientId): ContextInterface
    {
        // Try to get user-based context first
        if (str_starts_with($clientId, 'user_')) {
            return $this->createUserContext($clientId);
        }

        // Fall back to static configuration
        return parent::createFromClient($clientId);
    }

    private function createUserContext(string $clientId): ContextInterface
    {
        $userId = str_replace('user_', '', $clientId);
        $userModel = config('mcp.auth.user_model.class', \App\Models\User::class);
        $user = $userModel::find($userId);

        if (!$user || !$user->hasMCPAccess()) {
            throw new \Exception("Invalid user or no MCP access: {$clientId}");
        }

        // Get auth metadata from request if available
        $authMetadata = request()->input('mcp_auth_metadata', []);
        
        return new Context(
            clientId: $clientId,
            permissions: $user->getMCPPermissions(),
            fieldAccess: $user->getMCPFieldAccess(),
            metadata: array_merge([
                'user_id' => $user->getKey(),
                'user_name' => $user->name,
                'roles' => $authMetadata['roles'] ?? [],
                'abilities' => $authMetadata['abilities'] ?? [],
                'bouncer_enabled' => true,
            ], $authMetadata)
        );
    }
}