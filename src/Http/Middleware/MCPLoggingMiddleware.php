<?php

namespace ChaoticIngenuity\LaravelMCP\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MCPLoggingMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        // Log incoming request if debug enabled
        if (config('mcp.debug.log_all_requests', false)) {
            Log::channel('mcp')->info('MCP Request Started', [
                'client' => $request->input('mcp_client', 'unknown'),
                'method' => $request->input('method'),
                'tool' => $request->input('params.name'),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $response = $next($request);

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        // Always log completed requests
        Log::channel('mcp')->info('MCP Request', [
            'client' => $request->input('mcp_client', 'unknown'),
            'method' => $request->input('method'),
            'tool' => $request->input('params.name'),
            'duration_ms' => $duration,
            'status' => $response->getStatusCode(),
            'ip' => $request->ip(),
            'memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
        ]);

        return $response;
    }
}
