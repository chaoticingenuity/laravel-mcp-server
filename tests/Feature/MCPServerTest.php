<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;

class MCPServerTest extends TestCase
{
  /** @test */
  public function it_can_list_tools(): void
  {
    config([
      'mcp.clients.test_client' => [
        'permissions' => ['tools.*'],
        'field_access' => [],
        'metadata' => []
      ]
    ]);

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
      ]);
  }

  /** @test */
  public function it_can_execute_echo_tool(): void
  {
    config([
      'mcp.clients.test_client' => [
        'permissions' => ['tools.echo'],
        'field_access' => [],
        'metadata' => []
      ]
    ]);

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
      ->assertJsonFragment([
        'text' => 'Echo: Hello World'
      ]);
  }
}