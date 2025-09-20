<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;

interface AuthenticatorInterface
{
    /**
     * Check if this authenticator handles the given type
     */
    public function handles(string $type): bool;

    /**
     * Authenticate the given credentials
     *
     * @param  string  $type  The authentication type (api_key, basic_auth, bearer_token)
     * @param  array  $credentials  The credentials to authenticate
     */
    public function authenticate(string $type, array $credentials): AuthenticationResult;

    /**
     * Get the client identifier for the authenticated credentials
     */
    public function getClientId(string $type, array $credentials): ?string;
}
