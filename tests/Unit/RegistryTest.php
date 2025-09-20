<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Unit;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResourceInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResultInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ToolInterface;
use ChaoticIngenuity\LaravelMCP\Core\Context;
use ChaoticIngenuity\LaravelMCP\Core\Registry;
use ChaoticIngenuity\LaravelMCP\Core\Result;
use ChaoticIngenuity\LaravelMCP\Tests\TestCase;

class RegistryTest extends TestCase
{
    protected Registry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create fresh registry for each test
        $this->registry = new Registry;
    }

    /** @test */
    public function it_registers_and_retrieves_tools(): void
    {
        $tool = new TestTool('test_tool');

        $this->registry->registerTool($tool);

        $retrievedTool = $this->registry->getTool('test_tool');
        $this->assertSame($tool, $retrievedTool);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_tool(): void
    {
        $tool = $this->registry->getTool('nonexistent');
        $this->assertNull($tool);
    }

    /** @test */
    public function it_registers_and_retrieves_resources(): void
    {
        $resource = new TestResource('test://resource');

        $this->registry->registerResource($resource);

        $retrievedResource = $this->registry->getResource('test://resource');
        $this->assertSame($resource, $retrievedResource);
    }

    /** @test */
    public function it_returns_null_for_nonexistent_resource(): void
    {
        $resource = $this->registry->getResource('nonexistent://resource');
        $this->assertNull($resource);
    }

    /** @test */
    public function it_filters_tools_by_access_permissions(): void
    {
        $publicTool = new TestTool('public_tool', ['tools.public']);
        $privateTool = new TestTool('private_tool', ['tools.private']);

        $this->registry->registerTool($publicTool);
        $this->registry->registerTool($privateTool);

        // Context with only public permissions
        $context = new Context('test_client', ['tools.public'], [], []);

        $accessibleTools = $this->registry->getAccessibleTools($context);

        $this->assertCount(1, $accessibleTools);
        $this->assertEquals('public_tool', $accessibleTools->first()->getName());
    }

    /** @test */
    public function it_filters_resources_by_access_permissions(): void
    {
        $publicResource = new TestResource('public://resource', ['resources.public']);
        $privateResource = new TestResource('private://resource', ['resources.private']);

        $this->registry->registerResource($publicResource);
        $this->registry->registerResource($privateResource);

        // Context with only public permissions
        $context = new Context('test_client', ['resources.public'], [], []);

        $accessibleResources = $this->registry->getAccessibleResources($context);

        $this->assertCount(1, $accessibleResources);
        $this->assertEquals('public://resource', $accessibleResources->first()->getUri());
    }

    /** @test */
    public function it_matches_template_uris_correctly(): void
    {
        $templateResource = new TestResource('template://user/{user_id}', ['resources.template'], true);

        $this->registry->registerResource($templateResource);

        // Should match template pattern
        $matchedResource = $this->registry->getResource('template://user/123');
        $this->assertSame($templateResource, $matchedResource);

        // Should not match different pattern
        $nonMatchedResource = $this->registry->getResource('template://product/123');
        $this->assertNull($nonMatchedResource);
    }

    /** @test */
    public function template_matching_performance_test(): void
    {
        // Register multiple template resources
        $templates = [];
        for ($i = 0; $i < 100; $i++) {
            $template = new TestResource("template://type{$i}/{id}", ['resources.template'], true);
            $templates[] = $template;
            $this->registry->registerResource($template);
        }

        $startTime = microtime(true);

        // Test multiple URI matches
        for ($i = 0; $i < 1000; $i++) {
            $templateIndex = $i % 100;
            $uri = "template://type{$templateIndex}/test-id-{$i}";
            $resource = $this->registry->getResource($uri);
            $this->assertSame($templates[$templateIndex], $resource);
        }

        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;

        // Should complete 1000 template matches in under 200ms
        $this->assertLessThan(0.2, $executionTime,
            "Template matching took {$executionTime}s, should be under 0.2s");
    }

    /** @test */
    public function compiled_patterns_are_cached(): void
    {
        $templateResource = new TestResource('cache://test/{id}', ['resources.test'], true);
        $this->registry->registerResource($templateResource);

        // First match - should compile pattern
        $resource1 = $this->registry->getResource('cache://test/123');
        $this->assertSame($templateResource, $resource1);

        // Second match - should use cached pattern
        $resource2 = $this->registry->getResource('cache://test/456');
        $this->assertSame($templateResource, $resource2);

        // Use reflection to check that patterns are cached
        $reflection = new \ReflectionClass($this->registry);
        $property = $reflection->getProperty('compiledPatterns');
        $property->setAccessible(true);
        $compiledPatterns = $property->getValue($this->registry);

        $this->assertArrayHasKey('cache://test/{id}', $compiledPatterns);
    }

    /** @test */
    public function template_matching_handles_complex_patterns(): void
    {
        $complexTemplate = new TestResource(
            'api://v{version}/users/{user_id}/posts/{post_id}',
            ['resources.api'],
            true
        );

        $this->registry->registerResource($complexTemplate);

        // Should match complex nested pattern
        $resource = $this->registry->getResource('api://v1/users/123/posts/456');
        $this->assertSame($complexTemplate, $resource);

        // Should not match partial pattern
        $nonMatch = $this->registry->getResource('api://v1/users/123');
        $this->assertNull($nonMatch);

        // Should not match extra segments
        $nonMatch2 = $this->registry->getResource('api://v1/users/123/posts/456/comments');
        $this->assertNull($nonMatch2);
    }

    /** @test */
    public function template_matching_handles_special_characters(): void
    {
        $specialTemplate = new TestResource(
            'special://path-with-dashes/file.{ext}',
            ['resources.special'],
            true
        );

        $this->registry->registerResource($specialTemplate);

        // Should properly escape special regex characters
        $resource = $this->registry->getResource('special://path-with-dashes/file.txt');
        $this->assertSame($specialTemplate, $resource);

        // Should not match without proper escaping
        $nonMatch = $this->registry->getResource('special://path_with_dashes/file.txt');
        $this->assertNull($nonMatch);
    }

    /** @test */
    public function exact_match_takes_precedence_over_template_match(): void
    {
        $exactResource = new TestResource('exact://test/123', ['resources.exact']);
        $templateResource = new TestResource('exact://test/{id}', ['resources.template'], true);

        $this->registry->registerResource($exactResource);
        $this->registry->registerResource($templateResource);

        // Exact match should take precedence
        $resource = $this->registry->getResource('exact://test/123');
        $this->assertSame($exactResource, $resource);

        // Template should still work for other IDs
        $templateMatch = $this->registry->getResource('exact://test/456');
        $this->assertSame($templateResource, $templateMatch);
    }

    /** @test */
    public function registry_auto_registers_core_components(): void
    {
        // Core tools and resources should be automatically registered
        $tools = $this->registry->getAccessibleTools(
            new Context('admin', ['tools.*'], [], [])
        );

        $resources = $this->registry->getAccessibleResources(
            new Context('admin', ['resources.*'], [], [])
        );

        // Should have at least the echo tool
        $toolNames = $tools->map(fn ($tool) => $tool->getName())->toArray();
        $this->assertContains('echo', $toolNames);

        // Should have at least the status resource
        $resourceUris = $resources->map(fn ($resource) => $resource->getUri())->toArray();
        $this->assertContains('system://status', $resourceUris);
    }

    /** @test */
    public function admin_permissions_grant_access_to_everything(): void
    {
        $restrictedTool = new TestTool('restricted', ['admin.only']);
        $restrictedResource = new TestResource('restricted://resource', ['admin.only']);

        $this->registry->registerTool($restrictedTool);
        $this->registry->registerResource($restrictedResource);

        // Admin context should access everything
        $adminContext = new Context('admin', ['admin'], [], []);

        $tools = $this->registry->getAccessibleTools($adminContext);
        $resources = $this->registry->getAccessibleResources($adminContext);

        $this->assertTrue($tools->contains($restrictedTool));
        $this->assertTrue($resources->contains($restrictedResource));
    }

    /** @test */
    public function wildcard_permissions_work_correctly(): void
    {
        $toolsWildcard = new TestTool('tools_test', ['tools.specific']);
        $resourcesWildcard = new TestResource('resources://test', ['resources.specific']);

        $this->registry->registerTool($toolsWildcard);
        $this->registry->registerResource($resourcesWildcard);

        // Wildcard context should access matching items
        $wildcardContext = new Context('wildcard', ['tools.*', 'resources.*'], [], []);

        $tools = $this->registry->getAccessibleTools($wildcardContext);
        $resources = $this->registry->getAccessibleResources($wildcardContext);

        $this->assertTrue($tools->contains($toolsWildcard));
        $this->assertTrue($resources->contains($resourcesWildcard));
    }

    /** @test */
    public function memory_usage_stays_reasonable_with_many_registrations(): void
    {
        $initialMemory = memory_get_usage();

        // Register many tools and resources
        for ($i = 0; $i < 1000; $i++) {
            $tool = new TestTool("tool_{$i}", ['tools.test']);
            $resource = new TestResource("resource://test/{$i}", ['resources.test']);

            $this->registry->registerTool($tool);
            $this->registry->registerResource($resource);
        }

        $finalMemory = memory_get_usage();
        $memoryIncrease = $finalMemory - $initialMemory;

        // Memory increase should be reasonable (less than 5MB for 1000 registrations)
        $this->assertLessThan(5 * 1024 * 1024, $memoryIncrease,
            'Memory usage increased by '.($memoryIncrease / 1024 / 1024).'MB');
    }
}

/**
 * Test tool implementation
 */
class TestTool implements ToolInterface
{
    public function __construct(
        private string $name,
        private array $requiredPermissions = [],
        private string $description = 'Test tool',
        private array $inputSchema = []
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getInputSchema(): array
    {
        return $this->inputSchema ?: ['type' => 'object', 'properties' => []];
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        if ($context->hasPermission('admin')) {
            return true;
        }

        foreach ($this->requiredPermissions as $permission) {
            if ($context->hasPermission($permission)) {
                return true;
            }
        }

        return empty($this->requiredPermissions);
    }

    public function execute(array $arguments, ContextInterface $context): ResultInterface
    {
        return Result::success(['tool' => $this->name, 'arguments' => $arguments]);
    }
}

/**
 * Test resource implementation
 */
class TestResource implements ResourceInterface
{
    public function __construct(
        private string $uri,
        private array $requiredPermissions = [],
        private bool $isTemplate = false,
        private string $name = 'Test Resource',
        private string $description = 'Test resource',
        private string $mimeType = 'application/json'
    ) {}

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function isTemplate(): bool
    {
        return $this->isTemplate;
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        if ($context->hasPermission('admin')) {
            return true;
        }

        foreach ($this->requiredPermissions as $permission) {
            if ($context->hasPermission($permission)) {
                return true;
            }
        }

        return empty($this->requiredPermissions);
    }

    public function getAccessibleFields(ContextInterface $context): array
    {
        return $context->getAccessibleFields('test');
    }

    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        return Result::success(['resource' => $this->uri, 'requested_uri' => $uri]);
    }
}
