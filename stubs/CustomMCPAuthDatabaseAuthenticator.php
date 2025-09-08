<?php
namespace App\Services\Custom\MCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;
use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;
use Illuminate\Support\Facades\{Cache, Log};

class DatabaseAuthenticator implements AuthenticatorInterface
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

        $cacheKey = 'mcp.auth.api_key.' . hash('sha256', $apiKey);

        // Try to get user from cache first - use efficient query but preserve customizability
        $userData = Cache::remember($cacheKey, config('mcp.auth.cache_duration', 300), function () use ($apiKey) {
            $userModel = config('mcp.auth.user_model.class', \App\Models\User::class);

            // First, get candidate users efficiently with API key relationships
            $candidateUsers = $userModel::withMCPAccess()
                ->whereHas('apiKeys', function ($query) use ($apiKey) {
                    $query->where('key', $apiKey);
                })
                ->get();

            // Then use the customizable validation method
            return $candidateUsers->first(function ($user) use ($apiKey) {
                return $user->isMCPApiKeyValid($apiKey);
            });
        });

        if (!$userData) {
            Log::channel('mcp')->warning('Invalid API key attempt', [
                'api_key_prefix' => substr($apiKey, 0, 8) . '...',
                'ip' => request()->ip()
            ]);
            return AuthenticationResult::failure('Invalid or expired API key');
        }

        // Get key info for client identifier
        $keyInfo = $userData->getMCPApiKeyInfo($apiKey);
        $clientIdentifier = $keyInfo['client_identifier'] ?? "user_{$userData->getKey()}";

        // Update last used timestamp (async)
        dispatch(function () use ($userData, $apiKey) {
            if (method_exists($userData, 'updateMCPApiKeyLastUsed')) {
                $userData->updateMCPApiKeyLastUsed($apiKey);
            }
        })->afterResponse();

        return AuthenticationResult::success($clientIdentifier, [
            'auth_type' => 'database_api_key',
            'user_id' => $userData->getKey(),
            'user_name' => $userData->name ?? 'Unknown',
            'key_info' => $keyInfo,
            'permissions' => $userData->getMCPPermissions(),
            'field_access' => $userData->getMCPFieldAccess(),
        ]);
    }

    private function authenticateUserToken(array $credentials): AuthenticationResult
    {
        $token = $credentials['token'] ?? '';
        $userId = $credentials['user_id'] ?? '';

        if (empty($token) || empty($userId)) {
            return AuthenticationResult::failure('Token and user ID required');
        }

        $userModel = config('mcp.auth.user_model.class', \App\Models\User::class);
        $cacheKey = "mcp.auth.user_token.{$userId}." . hash('sha256', $token);

        $userData = Cache::remember($cacheKey, config('mcp.auth.cache_duration', 300), function () use ($userId, $token, $userModel) {
            $user = $userModel::find($userId);

            if (!$user || !$user->hasMCPAccess()) {
                return null;
            }

            $validTokens = $user->getMCPTokens();
            foreach ($validTokens as $tokenData) {
                if (
                    Hash::check($token, $tokenData['hash']) &&
                    ($tokenData['expires_at'] ?? null) > now()
                ) {
                    return [
                        'user' => $user,
                        'token_data' => $tokenData
                    ];
                }
            }

            return null;
        });

        if (!$userData) {
            return AuthenticationResult::failure('Invalid user token');
        }

        $user = $userData['user'];
        $tokenData = $userData['token_data'];

        return AuthenticationResult::success("user_{$user->getKey()}", [
            'auth_type' => 'user_token',
            'user_id' => $user->getKey(),
            'user_name' => $user->name ?? 'Unknown',
            'user_email' => $user->email ?? null,
            'token_name' => $tokenData['name'] ?? 'default',
            'scopes' => $tokenData['scopes'] ?? [],
            'permissions' => $user->getMCPPermissions(),
            'field_access' => $user->getMCPFieldAccess(),
        ]);
    }
}
