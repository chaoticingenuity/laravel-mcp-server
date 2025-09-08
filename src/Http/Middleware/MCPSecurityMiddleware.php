<?php
namespace ChaoticIngenuity\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\{Request, Response};

class MCPSecurityMiddleware
{
  public function handle(Request $request, Closure $next)
  {
    // Check HTTPS requirement
    if (config('mcp.security.require_https', false) && !$request->secure()) {
      return response()->json([
        'jsonrpc' => '2.0',
        'error' => [
          'code' => -32001,
          'message' => 'HTTPS required'
        ]
      ], 400);
    }

    // Check IP whitelist
    if (!$this->isIPAllowed($request)) {
      return response()->json([
        'jsonrpc' => '2.0',
        'error' => [
          'code' => -32001,
          'message' => 'IP not allowed'
        ]
      ], 403);
    }

    $response = $next($request);

    // Add security headers
    return $this->addSecurityHeaders($response);
  }

  private function isIPAllowed(Request $request): bool
  {
    $allowedIPs = config('mcp.security.allowed_ips', []);

    if (empty($allowedIPs)) {
      return true; // No restrictions
    }

    $clientIP = $request->ip();

    foreach ($allowedIPs as $allowedIP) {
      if ($this->ipMatches($clientIP, $allowedIP)) {
        return true;
      }
    }

    return false;
  }

  private function ipMatches(string $clientIP, string $allowedIP): bool
  {
    // Handle CIDR notation
    if (str_contains($allowedIP, '/')) {
      return $this->ipInRange($clientIP, $allowedIP);
    }

    // Direct IP match
    return $clientIP === $allowedIP;
  }

  private function ipInRange(string $ip, string $range): bool
  {
    [$subnet, $bits] = explode('/', $range);

    $ip = ip2long($ip);
    $subnet = ip2long($subnet);
    $mask = -1 << (32 - $bits);

    return ($ip & $mask) === ($subnet & $mask);
  }

  private function addSecurityHeaders($response)
  {
    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'DENY');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Content-Security-Policy', "default-src 'none'");

    // Remove server identification headers
    $response->headers->remove('Server');
    $response->headers->remove('X-Powered-By');

    return $response;
  }
}