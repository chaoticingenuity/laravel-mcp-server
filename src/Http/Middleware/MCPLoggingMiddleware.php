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

        $logChannel = $this->getLogChannel();

        // Log incoming request if debug enabled
        if (config('mcp.debug.log_all_requests', false)) {
            $logChannel->info('MCP Request Started', [
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
        $logChannel->info('MCP Request', [
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

    /**
     * Get log channel, falling back to default if 'mcp' channel not configured
     */
    private function getLogChannel()
    {
        try {
            $channelName = config('mcp.logging.channel', 'mcp');

            // Check if channel exists in logging config
            if (config("logging.channels.{$channelName}")) {
                return Log::channel($channelName);
            }
        } catch (\Exception $e) {
            // Silently fall back to default
        }

        // Fall back to default channel
        return Log::channel(config('logging.default', 'stack'));
    }
}
