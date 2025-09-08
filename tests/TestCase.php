<?php

namespace ChaoticIngenuity\LaravelMCP\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use ChaoticIngenuity\LaravelMCP\Providers\MCPServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            MCPServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        
        // Set up MCP configuration for tests
        config()->set('mcp.server.name', 'Laravel MCP Server');
        config()->set('mcp.server.version', '1.0.0');
        config()->set('mcp.routes.enabled', true);
        config()->set('mcp.routes.prefix', 'api');
        
        // Configure Bouncer based on environment variable
        $bouncerEnabled = env('MCP_BOUNCER_ENABLED', false);
        config()->set('mcp.auth.bouncer.enabled', $bouncerEnabled);
        
        // Register middleware aliases manually for testing
        $router = $app['router'];
        $router->aliasMiddleware('mcp.auth', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPAuthMiddleware::class);
        $router->aliasMiddleware('mcp.logging', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPLoggingMiddleware::class);
        $router->aliasMiddleware('mcp.security', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPSecurityMiddleware::class);
        $router->aliasMiddleware('mcp.throttle', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPThrottleMiddleware::class);
    }

    protected function defineRoutes($router): void
    {
        // Register the MCP route for testing
        $router->post('/api/mcp', [\ChaoticIngenuity\LaravelMCP\Http\Controllers\MCPController::class, 'handle'])
            ->middleware(['mcp.security', 'mcp.auth', 'mcp.throttle', 'mcp.logging'])
            ->name('mcp.handle');
    }
}