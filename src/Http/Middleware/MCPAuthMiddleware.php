<?php

namespace ChaoticIngenuity\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\{Request, JsonResponse};
use ChaoticIngenuity\LaravelMCP\Auth\AuthenticatorManager;

class MCPAuthMiddleware
{
  public function __construct(
    private AuthenticatorManager $authenticatorManager
  ) {
  }
  public function handle(Request $request, Closure $next)
  {
    // Check for API key in header
    if ($apiKey = $request->header('X-MCP-API-Key')) {
      return $this->authenticate('api_key', ['api_key' => $apiKey], $request, $next);
    }

    // Check for Basic Auth
    if ($authHeader = $request->header('Authorization')) {
      if (str_starts_with($authHeader, 'Basic ')) {
        $credentials = $this->parseBasicAuth($authHeader);
        if ($credentials) {
          return $this->authenticate('basic_auth', [
            'username' => $credentials[0],
            'password' => $credentials[1]
          ], $request, $next);
        }
      } elseif (str_starts_with($authHeader, 'Bearer ')) {
        $token = substr($authHeader, 7);
        return $this->authenticate('bearer_token', ['token' => $token], $request, $next);
      }
    }

    // Custom authentication schemes
    if ($userToken = $request->header('X-User-Token')) {
      $userId = $request->header('X-User-ID');
      return $this->authenticate('user_token', [
        'token' => $userToken,
        'user_id' => $userId
      ], $request, $next);
    }

    return $this->unauthorizedResponse();
  }
  private function authenticate(string $type, array $credentials, Request $request, Closure $next)
  {
    $result = $this->authenticatorManager->authenticate($type, $credentials);

    if (!$result->isSuccess()) {
      return $this->unauthorizedResponse($result->getErrorMessage());
    }

    // Add client info and metadata to request
    $request->merge([
      'mcp_client' => $result->getClientId(),
      'mcp_auth_metadata' => $result->getMetadata()
    ]);

    return $next($request);
  }
  private function parseBasicAuth(string $authHeader): ?array
  {
    if (!str_starts_with($authHeader, 'Basic ')) {
      return null;
    }

    $encoded = substr($authHeader, 6);
    $decoded = base64_decode($encoded);

    if (!$decoded || !str_contains($decoded, ':')) {
      return null;
    }

    return explode(':', $decoded, 2);
  }
  private function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
  {
    return response()->json([
      'jsonrpc' => '2.0',
      'error' => [
        'code' => -32001,
        'message' => $message
      ]
    ], 401);
  }
}