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
      $pattern = preg_quote($template, '/');
      $pattern = str_replace('\\{[^}]+\\}', '([^/]+)', $pattern);
      self::$compiledPatterns[$template] = "/^{$pattern}$/";
    }

    return preg_match(self::$compiledPatterns[$template], $uri) === 1;
  }

  private function autoRegister(): void
  {
    // Register core tools and resources
    $this->registerCoreTools();
    $this->registerCoreResources();

    // Register custom tools and resources from config
    $this->registerCustomTools();
    $this->registerCustomResources();
  }

  private function registerCoreTools(): void
  {
    $coreTools = [
      \ChaoticIngenuity\LaravelMCP\Tools\EchoTool::class,
    ];

    foreach ($coreTools as $toolClass) {
      if (class_exists($toolClass)) {
        $this->registerTool(app($toolClass));
      }
    }
  }

  private function registerCoreResources(): void
  {
    $coreResources = [
      \ChaoticIngenuity\LaravelMCP\Resources\StatusResource::class,
    ];

    foreach ($coreResources as $resourceClass) {
      if (class_exists($resourceClass)) {
        $this->registerResource(app($resourceClass));
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