<?php
namespace ChaoticIngenuity\LaravelMCP\Contracts;
use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;
interface AuthenticatorInterface
{
  /**
   * Authenticate the given credentials
   * 
   * @param string $type The authentication type (api_key, basic_auth, bearer_token)
   * @param array $credentials The credentials to authenticate
   * @return AuthenticationResult
   */
  public function authenticate(string $type, array $credentials): AuthenticationResult;

  /**
   * Get the client identifier for the authenticated credentials
   * 
   * @param string $type
   * @param array $credentials
   * @return string|null
   */
  public function getClientId(string $type, array $credentials): ?string;

  /**
   * Check if this authenticator handles the given type
   * 
   * @param string $type
   * @return bool
   */
  public function handles(string $type): bool;
}