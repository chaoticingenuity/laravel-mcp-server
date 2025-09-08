<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;
use ChaoticIngenuity\LaravelMCP\MCPServer;
use ChaoticIngenuity\LaravelMCP\Core\Registry;
use ChaoticIngenuity\LaravelMCP\Core\ContextFactory;

class MCPServerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set up test configuration
        config([
            'mcp.auth.api_keys' => ['test-key', 'limited-key', 'no-access-key'],
            'mcp.auth.api_key_clients' => [
                'test-key' => 'test_client',
                'limited-key' => 'limited_client',
                'no-access-key' => 'no_access_client'
            ],
            'mcp.auth.clients.test_client' => [
                'permissions' => ['tools.*', 'resources.*'],
                'field_access' => ['*'],
                'metadata' => ['tier' => 'test']
            ],
            'mcp.auth.clients.limited_client' => [
                'permissions' => ['tools.echo'],
                'field_access' => [],
                'metadata' => ['tier' => 'limited']
            ],
            'mcp.auth.clients.no_access_client' => [
                'permissions' => [],
                'field_access' => [],
                'metadata' => ['tier' => 'none']
            ]
        ]);
    }

    /** @test */
    public function it_returns_proper_initialize_response(): void
    {
        $server = app(MCPServer::class);

        $response = $server->handleRequest([
            'method' => 'initialize',
            'params' => [],
            'id' => 1
        ]);

        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertEquals('2024-11-05', $response['result']['protocolVersion']);
        $this->assertArrayHasKey('capabilities', $response['result']);
        $this->assertArrayHasKey('serverInfo', $response['result']);
        $this->assertEquals('Laravel MCP Server', $response['result']['serverInfo']['name']);
    }

    /** @test */
    public function it_can_list_tools_with_permissions(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'tools' => [
                        '*' => ['name', 'description', 'inputSchema']
                    ]
                ],
                'id'
            ])
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'id' => 1
            ]);

        // Verify at least echo tool is present
        $data = $response->json();
        $toolNames = array_column($data['result']['tools'], 'name');
        $this->assertContains('echo', $toolNames);
    }

    /** @test */
    public function it_filters_tools_based_on_permissions(): void
    {
        config([
            'mcp.auth.clients.limited_client' => [
                'permissions' => ['tools.echo'], // Only echo tool
                'field_access' => [],
                'metadata' => []
            ],
            'mcp.auth.api_key_clients' => ['limited-key' => 'limited_client']
        ]);

        $this->withSession(['mcp_client' => 'limited_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'limited-key'
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $toolNames = array_column($data['result']['tools'], 'name');
        $this->assertContains('echo', $toolNames);
        $this->assertCount(1, $data['result']['tools']); // Only one tool should be accessible
    }

    /** @test */
    public function it_can_execute_echo_tool(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo',
                'arguments' => ['message' => 'Hello World']
            ],
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'content' => [
                        '*' => ['type', 'text']
                    ],
                    'metadata'
                ],
                'id'
            ])
            ->assertJsonFragment([
                'text' => 'Echo: Hello World'
            ]);
    }

    /** @test */
    public function it_returns_error_for_inaccessible_tool(): void
    {
        config([
            'mcp.auth.clients.no_access_client' => [
                'permissions' => [], // No permissions
                'field_access' => [],
                'metadata' => []
            ],
            'mcp.auth.api_key_clients' => ['no-access-key' => 'no_access_client']
        ]);

        $this->withSession(['mcp_client' => 'no_access_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'echo',
                'arguments' => ['message' => 'Hello World']
            ],
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'no-access-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Tool not found or not accessible'
                ],
                'id' => 1
            ]);
    }

    /** @test */
    public function it_can_list_resources(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/list',
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'resources' => [
                        '*' => ['uri', 'name', 'description', 'mimeType']
                    ]
                ],
                'id'
            ]);

        // Verify status resource is present
        $data = $response->json();
        $resourceUris = array_column($data['result']['resources'], 'uri');
        $this->assertContains('system://status', $resourceUris);
    }

    /** @test */
    public function it_can_read_status_resource(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [
                'uri' => 'system://status'
            ],
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'contents' => [
                        '*' => ['uri', 'mimeType', 'text']
                    ],
                    'metadata'
                ],
                'id'
            ]);

        $data = $response->json();
        $this->assertEquals('system://status', $data['result']['contents'][0]['uri']);
        $this->assertEquals('application/json', $data['result']['contents'][0]['mimeType']);
    }

    /** @test */
    public function it_returns_error_for_missing_resource_uri(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/read',
            'params' => [], // Missing uri parameter
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Missing uri parameter'
                ],
                'id' => 1
            ]);
    }

    /** @test */
    public function it_can_list_resource_templates(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'resources/templates/list',
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'jsonrpc',
                'result' => [
                    'resourceTemplates'
                ],
                'id'
            ]);
    }

    /** @test */
    public function it_returns_error_for_unknown_method(): void
    {
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'unknown/method',
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found'
                ],
                'id' => 1
            ]);
    }

    /** @test */
    public function it_handles_tool_execution_exceptions(): void
    {
        // This test would require a custom tool that throws exceptions
        // For now, test with invalid tool parameters
        $this->withSession(['mcp_client' => 'test_client']);

        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent_tool',
                'arguments' => []
            ],
            'id' => 1
        ], [
            'X-MCP-API-Key' => 'test-key'
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Tool not found or not accessible'
                ],
                'id' => 1
            ]);
    }
}
