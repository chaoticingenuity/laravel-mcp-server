<?php
namespace ChaoticIngenuity\LaravelMCP\Auth;

use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;
use Illuminate\Support\Collection;

class AuthenticatorManager
{
  private Collection $authenticators;

  public function __construct()
  {
    $this->authenticators = collect();
    $this->registerDefaultAuthenticators();
    $this->registerCustomAuthenticators();
  }

  public function registerAuthenticator(AuthenticatorInterface $authenticator): void
  {
    $this->authenticators->push($authenticator);
  }

  public function authenticate(string $type, array $credentials): AuthenticationResult
  {
    foreach ($this->authenticators as $authenticator) {
      if ($authenticator->handles($type)) {
        return $authenticator->authenticate($type, $credentials);
      }
    }

    return AuthenticationResult::failure("No authenticator found for type: {$type}");
  }

  public function getClientId(string $type, array $credentials): ?string
  {
    foreach ($this->authenticators as $authenticator) {
      if ($authenticator->handles($type)) {
        return $authenticator->getClientId($type, $credentials);
      }
    }

    return null;
  }

  private function registerDefaultAuthenticators(): void
  {
    // Register built-in static authenticators
    $this->registerAuthenticator(new StaticConfigAuthenticator());
  }

  private function registerCustomAuthenticators(): void
  {
    $customAuthenticators = config('mcp.auth.custom_authenticators', []);

    foreach ($customAuthenticators as $authenticatorClass) {
      if (class_exists($authenticatorClass)) {
        $this->registerAuthenticator(app($authenticatorClass));
      }
    }
  }
}