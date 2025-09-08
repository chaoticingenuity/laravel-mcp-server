<?php

namespace ChaoticIngenuity\LaravelMCP\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{RateLimiter, Route};
use ChaoticIngenuity\LaravelMCP\{MCPServer};
use ChaoticIngenuity\LaravelMCP\Core\{Registry, ContextFactory};

class MCPServiceProvider extends ServiceProvider
{
  public function register(): void
  {
    $this->mergeConfigFrom(__DIR__ . '/../../config/mcp.php', 'mcp');

    $this->registerCoreServices();
  }

  public function boot(): void
  {
    $this->publishes([
      __DIR__ . '/../../config/mcp.php' => config_path('mcp.php'),
    ], 'mcp-config');

    $this->publishes([
      __DIR__ . '/../Http/Controllers/MCPController.php' => app_path('Http/Controllers/MCPController.php'),
      __DIR__ . '/../Http/Middleware/MCPAuthMiddleware.php' => app_path('Http/Middleware/MCPAuthMiddleware.php'),
      __DIR__ . '/../Http/Middleware/MCPLoggingMiddleware.php' => app_path('Http/Middleware/MCPLoggingMiddleware.php'),
      __DIR__ . '/../Http/Middleware/MCPSecurityMiddleware.php' => app_path('Http/Middleware/MCPSecurityMiddleware.php'),
      __DIR__ . '/../Http/Middleware/MCPThrottleMiddleware.php' => app_path('Http/Middleware/MCPThrottleMiddleware.php'),
    ], 'mcp-controllers');

    $this->publishes([
      __DIR__ . '/../../stubs/CustomMCPTool.php' => app_path('Services/Custom/MCP/Tools/CustomMCPTool.php'),
      __DIR__ . '/../../stubs/CustomMCPResource.php' => app_path('Services/Custom/MCP/Resources/CustomMCPResource.php'),
    ], 'mcp-stubs');

    $this->setupRateLimiting();
    $this->registerRoutes();
    $this->registerMiddleware();
  }

  private function registerCoreServices(): void
  {
    $this->app->singleton(Registry::class, function ($app) {
      return new Registry();
    });

    $this->app->singleton(ContextFactory::class, function ($app) {
      return new ContextFactory();
    });

    $this->app->singleton(MCPServer::class, function ($app) {
      return new MCPServer(
        $app->make(Registry::class),
        $app->make(ContextFactory::class)
      );
    });
  }
  private function setupRateLimiting(): void
  {
    RateLimiter::for('mcp', function (Request $request) {
      $client = $request->input('mcp_client') ?? $request->ip();
      $perMinute = config('mcp.rate_limit.requests_per_minute', 60);

      return Limit::perMinute($perMinute)->by($client);
    });
  }
  private function registerRoutes(): void
  {
    if (config('mcp.routes.enabled', true)) {
      Route::group([
        'prefix' => config('mcp.routes.prefix', 'api'),
        'middleware' => config('mcp.routes.middleware', ['api']),
      ], function () {
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
      });
    }
  }
  private function registerMiddleware(): void
  {
    // Only auto-register middleware aliases with the application for Laravel 10 and below
    if (version_compare($this->app->version(), '11.0', '<')) {
      $router = $this->app['router'];

      $router->aliasMiddleware('mcp.auth', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPAuthMiddleware::class);
      $router->aliasMiddleware('mcp.logging', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPLoggingMiddleware::class);
      $router->aliasMiddleware('mcp.security', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPSecurityMiddleware::class);
      $router->aliasMiddleware('mcp.throttle', \ChaoticIngenuity\LaravelMCP\Http\Middleware\MCPThrottleMiddleware::class);
    }
    // Laravel 11+ users need to manually register in bootstrap/app.php
  }
}