<?php
namespace ChaoticIngenuity\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\RateLimiter;

class MCPThrottleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $client = $request->input('mcp_client') ?? $request->ip();
        $key = "mcp_throttle:{$client}";

        // Get client-specific or default limits
        $clientConfig = config("mcp.auth.clients.{$client}", []);
        $maxRequests = $clientConfig['rate_limit']['requests_per_minute'] ??
            config('mcp.rate_limit.requests_per_minute', 60);
        $burstLimit = $clientConfig['rate_limit']['burst_limit'] ??
            config('mcp.rate_limit.burst_limit', 10);

        $decayMinutes = 1;

        // Check if too many requests
        if (RateLimiter::tooManyAttempts($key, $maxRequests)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32003, // Custom rate limit error code
                    'message' => 'Too many requests',
                    'data' => [
                        'retry_after' => $retryAfter,
                        'limit' => $maxRequests,
                        'window' => 'per minute'
                    ]
                ]
            ], 429)->withHeaders([
                        'Retry-After' => $retryAfter,
                        'X-RateLimit-Limit' => $maxRequests,
                        'X-RateLimit-Remaining' => 0,
                        'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
                    ]);
        }

        // Check burst limit (short-term)
        $burstKey = 'mcp_burst:' . $client;
        if (RateLimiter::tooManyAttempts($burstKey, $burstLimit)) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32003,
                    'message' => 'Burst limit exceeded',
                    'data' => [
                        'retry_after' => 10,
                        'burst_limit' => $burstLimit
                    ]
                ]
            ], 429);
        }

        // Record the attempt
        RateLimiter::hit($key, $decayMinutes * 60);
        RateLimiter::hit($burstKey, 10); // 10 second burst window

        $response = $next($request);

        // Add rate limit headers
        $remaining = max(0, $maxRequests - RateLimiter::attempts($key));
        $response->headers->add([
            'X-RateLimit-Limit' => $maxRequests,
            'X-RateLimit-Remaining' => $remaining,
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
            'X-RateLimit-Client' => $client,
        ]);

        return $response;
    }
}
