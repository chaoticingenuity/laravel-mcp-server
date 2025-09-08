<?php
namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;
use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;

class StaticConfigAuthenticator implements AuthenticatorInterface
{
    public function handles(string $type): bool
    {
        return in_array($type, ['api_key', 'basic_auth', 'bearer_token']);
    }

    public function authenticate(string $type, array $credentials): AuthenticationResult
    {
        return match ($type) {
            'api_key' => $this->authenticateApiKey($credentials),
            'basic_auth' => $this->authenticateBasicAuth($credentials),
            'bearer_token' => $this->authenticateBearerToken($credentials),
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
        $validKeys = config('mcp.auth.api_keys', []);

        if (in_array($apiKey, $validKeys)) {
            $clientId = $this->getClientFromApiKey($apiKey);
            return AuthenticationResult::success($clientId, ['auth_type' => 'api_key']);
        }

        return AuthenticationResult::failure('Invalid API key');
    }

    private function authenticateBasicAuth(array $credentials): AuthenticationResult
    {
        $username = $credentials['username'] ?? '';
        $password = $credentials['password'] ?? '';

        $validCredentials = config('mcp.auth.basic_auth', []);

        if (
            isset($validCredentials[$username]) &&
            hash_equals($validCredentials[$username], $password)
        ) {
            return AuthenticationResult::success($username, ['auth_type' => 'basic_auth']);
        }

        return AuthenticationResult::failure('Invalid credentials');
    }

    private function authenticateBearerToken(array $credentials): AuthenticationResult
    {
        $token = $credentials['token'] ?? '';
        $validTokens = config('mcp.auth.bearer_tokens', []);

        if (in_array($token, $validTokens)) {
            $clientId = $this->getClientFromToken($token);
            return AuthenticationResult::success($clientId, ['auth_type' => 'bearer_token']);
        }

        return AuthenticationResult::failure('Invalid bearer token');
    }

    private function getClientFromApiKey(string $apiKey): string
    {
        $keyMap = config('mcp.auth.api_key_clients', []);
        return $keyMap[$apiKey] ?? 'unknown';
    }

    private function getClientFromToken(string $token): string
    {
        $tokenMap = config('mcp.auth.token_clients', []);
        return $tokenMap[$token] ?? 'unknown';
    }
}
