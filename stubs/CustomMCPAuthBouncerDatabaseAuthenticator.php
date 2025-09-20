<?php

namespace App\Services\Custom\MCP\Auth;

use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;
use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BouncerDatabaseAuthenticator implements AuthenticatorInterface
{
    public function handles(string $type): bool
    {
        return in_array($type, ['api_key', 'user_token']);
    }

    public function authenticate(string $type, array $credentials): AuthenticationResult
    {
        return match ($type) {
            'api_key' => $this->authenticateApiKey($credentials),
            'user_token' => $this->authenticateUserToken($credentials),
            default => AuthenticationResult::failure("Unsupported auth type: {$type}")
        };
    }

    public function getClientId(string $type, array $credentials): ?string
    {
        $result = $this->authenticate($type, $credentials);

        return $result->isSuccess() ? $result->getClientId() : null;
    }

    private function authenticateApiKey(array $credentials): AuthenticationResult
    {
        $apiKey = $credentials['api_key'] ?? '';

        if (empty($apiKey)) {
            return AuthenticationResult::failure('API key required');
        }

        $cacheKey = 'mcp.auth.bouncer.api_key.'.hash('sha256', $apiKey);

        $userData = Cache::remember($cacheKey, config('mcp.auth.cache_duration', 300), function () use ($apiKey) {
            $userModel = config('mcp.auth.user_model.class', \App\Models\User::class);

            return $userModel::withMCPAccess()
                ->get()
                ->first(function ($user) use ($apiKey) {
                    return $user->isMCPApiKeyValid($apiKey);
                });
        });

        if (! $userData || ! $userData->hasMCPAccess()) {
            Log::channel('mcp')->warning('Invalid API key attempt', [
                'api_key_prefix' => substr($apiKey, 0, 8).'...',
                'ip' => request()->ip(),
            ]);

            return AuthenticationResult::failure('Invalid or expired API key');
        }

        $keyInfo = $userData->getMCPApiKeyInfo($apiKey);
        $clientIdentifier = $keyInfo['client_identifier'] ?? "user_{$userData->getKey()}";

        // Update last used timestamp (async)
        dispatch(function () use ($userData, $apiKey) {
            if (method_exists($userData, 'updateMCPApiKeyLastUsed')) {
                $userData->updateMCPApiKeyLastUsed($apiKey);
            }
        })->afterResponse();

        // Get Bouncer roles and abilities
        $roles = $userData->getRoles()->pluck('name')->toArray();
        $abilities = $userData->getAbilities()->pluck('name')->toArray();

        return AuthenticationResult::success($clientIdentifier, [
            'auth_type' => 'bouncer_database_api_key',
            'user_id' => $userData->getKey(),
            'user_name' => $userData->name ?? 'Unknown',
            'user_email' => $userData->email ?? null,
            'key_info' => $keyInfo,
            'roles' => $roles,
            'abilities' => $abilities,
            'permissions' => $userData->getMCPPermissions(),
            'field_access' => $userData->getMCPFieldAccess(),
        ]);
    }

    private function authenticateUserToken(array $credentials): AuthenticationResult
    {
        // Similar implementation for user tokens with Bouncer integration
        // ... (implementation similar to above but for user tokens)
    }
}
