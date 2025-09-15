<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\{ToolInterface, ResourceInterface, ContextInterface};
use Illuminate\Support\Collection;

class Registry
{
  private Collection $tools;
  private Collection $resources;

  public function __construct()
  {
    $this->tools = collect();
    $this->resources = collect();
    $this->autoRegister();
  }

  public function registerTool(ToolInterface $tool): void
  {
    $this->tools->put($tool->getName(), $tool);
  }

  public function registerResource(ResourceInterface $resource): void
  {
    $this->resources->put($resource->getUri(), $resource);
  }

  public function getAccessibleTools(ContextInterface $context): Collection
  {
    return $this->tools->filter(function (ToolInterface $tool) use ($context) {
      return $tool->isAccessibleTo($context);
    });
  }

  public function getAccessibleResources(ContextInterface $context): Collection
  {
    return $this->resources->filter(function (ResourceInterface $resource) use ($context) {
      return $resource->isAccessibleTo($context);
    });
  }

  public function getTool(string $name): ?ToolInterface
  {
    return $this->tools->get($name);
  }

  public function getResource(string $uri): ?ResourceInterface
  {
    // Try exact match first
    if ($resource = $this->resources->get($uri)) {
      return $resource;
    }

    // Try template matching
    foreach ($this->resources as $templateUri => $resource) {
      if ($resource->isTemplate() && $this->matchesTemplate($templateUri, $uri)) {
        return $resource;
      }
    }

    return null;
  }
  private static array $compiledPatterns = [];
  private function matchesTemplate(string $template, string $uri): bool
  {
    if (!isset(self::$compiledPatterns[$template])) {
      // Simple approach: replace placeholders with regex pattern
      $pattern = str_replace('/', '\\/', $template); // Escape forward slashes
      $pattern = preg_replace('/\{[^}]+\}/', '([^\/]+)', $pattern); // Replace {param} with ([^/]+)
      self::$compiledPatterns[$template] = "/^{$pattern}$/";
    }

    $result = preg_match(self::$compiledPatterns[$template], $uri);
    return $result === 1;
  }

  private function autoRegister(): void
  {
    // Register core components only if enabled
    if (config('mcp.package.auto_register_core_components', true)) {
      $this->registerCoreTools();
      $this->registerCoreResources();
    }

    // Always register custom tools and resources from config
    $this->registerCustomTools();
    $this->registerCustomResources();
  }

  private function registerCoreTools(): void
  {
    // Use configurable core tools
    $coreTools = config('mcp.package.core_tools', [
      \ChaoticIngenuity\LaravelMCP\Tools\EchoTool::class,
    ]);

    foreach ($coreTools as $toolClass) {
      if (class_exists($toolClass)) {
        $tool = app($toolClass);

        // TODO: Remove in v2.0.0 - Legacy individual tool enablement (prefer core_tools array)
        // Check individual tool enablement for backward compatibility
        if ($toolClass === \ChaoticIngenuity\LaravelMCP\Tools\EchoTool::class
            && !config('mcp.package.enable_echo_tool', true)) {
          continue;
        }

        $this->registerTool($tool);
      }
    }
  }

  private function registerCoreResources(): void
  {
    // Use configurable core resources
    $coreResources = config('mcp.package.core_resources', [
      \ChaoticIngenuity\LaravelMCP\Resources\StatusResource::class,
    ]);

    foreach ($coreResources as $resourceClass) {
      if (class_exists($resourceClass)) {
        $resource = app($resourceClass);

        // TODO: Remove in v2.0.0 - Legacy individual resource enablement (prefer core_resources array)
        // Check individual resource enablement for backward compatibility
        if ($resourceClass === \ChaoticIngenuity\LaravelMCP\Resources\StatusResource::class
            && !config('mcp.package.enable_status_resource', true)) {
          continue;
        }

        $this->registerResource($resource);
      }
    }
  }

  private function registerCustomTools(): void
  {
    $customTools = config('mcp.custom.tools', []);

    foreach ($customTools as $toolClass) {
      if (class_exists($toolClass)) {
        $this->registerTool(app($toolClass));
      }
    }
  }

  private function registerCustomResources(): void
  {
    $customResources = config('mcp.custom.resources', []);

    foreach ($customResources as $resourceClass) {
      if (class_exists($resourceClass)) {
        $this->registerResource(app($resourceClass));
      }
    }
  }
}