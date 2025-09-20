<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Auth\AuthenticationResult;
use ChaoticIngenuity\LaravelMCP\Auth\AuthenticatorManager;
use ChaoticIngenuity\LaravelMCP\Auth\StaticConfigAuthenticator;
use ChaoticIngenuity\LaravelMCP\Contracts\AuthenticatorInterface;
use ChaoticIngenuity\LaravelMCP\Tests\TestCase;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mcp.auth.api_keys' => ['test-api-key', 'another-key'],
            'mcp.auth.basic_auth' => ['testuser' => 'testpass'],
            'mcp.auth.bearer_tokens' => ['bearer-token-123'],
            'mcp.auth.api_key_clients' => [
                'test-api-key' => 'api_client',
                'another-key' => 'secondary_client',
            ],
            'mcp.auth.token_clients' => [
                'bearer-token-123' => 'bearer_client',
            ],
            'mcp.auth.clients.api_client' => [
                'permissions' => ['tools.*'],
                'field_access' => ['*'],
                'metadata' => ['tier' => 'standard'],
            ],
            'mcp.auth.clients.secondary_client' => [
                'permissions' => ['tools.echo'],
                'field_access' => [],
                'metadata' => ['tier' => 'basic'],
            ],
            'mcp.auth.clients.bearer_client' => [
                'permissions' => ['tools.*', 'resources.*'],
                'field_access' => ['*'],
                'metadata' => ['tier' => 'premium'],
            ],
        ]);
    }

    /** @test */
    public function it_authenticates_with_valid_api_key(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_rejects_invalid_api_key(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => 'invalid-key',
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32001,
                    'message' => 'Invalid API key',
                ],
            ]);
    }

    /** @test */
    public function it_authenticates_with_basic_auth(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Basic '.base64_encode('testuser:testpass'),
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_rejects_invalid_basic_auth(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Basic '.base64_encode('testuser:wrongpass'),
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_authenticates_with_bearer_token(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Bearer bearer-token-123',
        ]);

        $response->assertStatus(200);
    }

    /** @test */
    public function it_rejects_invalid_bearer_token(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Bearer invalid-token',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_requires_authentication(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ]);

        $response->assertStatus(401)
            ->assertJsonFragment([
                'error' => [
                    'code' => -32001,
                    'message' => 'Unauthorized',
                ],
            ]);
    }

    /** @test */
    public function static_config_authenticator_handles_api_keys(): void
    {
        $authenticator = new StaticConfigAuthenticator;

        $this->assertTrue($authenticator->handles('api_key'));
        $this->assertTrue($authenticator->handles('basic_auth'));
        $this->assertTrue($authenticator->handles('bearer_token'));
        $this->assertFalse($authenticator->handles('custom_auth'));
    }

    /** @test */
    public function static_config_authenticator_validates_api_keys(): void
    {
        $authenticator = new StaticConfigAuthenticator;

        $result = $authenticator->authenticate('api_key', ['api_key' => 'test-api-key']);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('api_client', $result->getClientId());

        $result = $authenticator->authenticate('api_key', ['api_key' => 'invalid-key']);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Invalid API key', $result->getError());
    }

    /** @test */
    public function static_config_authenticator_validates_basic_auth(): void
    {
        $authenticator = new StaticConfigAuthenticator;

        $result = $authenticator->authenticate('basic_auth', [
            'username' => 'testuser',
            'password' => 'testpass',
        ]);
        $this->assertTrue($result->isSuccess());

        $result = $authenticator->authenticate('basic_auth', [
            'username' => 'testuser',
            'password' => 'wrongpass',
        ]);
        $this->assertFalse($result->isSuccess());
    }

    /** @test */
    public function static_config_authenticator_validates_bearer_tokens(): void
    {
        $authenticator = new StaticConfigAuthenticator;

        $result = $authenticator->authenticate('bearer_token', ['token' => 'bearer-token-123']);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('bearer_client', $result->getClientId());

        $result = $authenticator->authenticate('bearer_token', ['token' => 'invalid-token']);
        $this->assertFalse($result->isSuccess());
    }

    /** @test */
    public function authenticator_manager_registers_default_authenticators(): void
    {
        $manager = new AuthenticatorManager;

        $result = $manager->authenticate('api_key', ['api_key' => 'test-api-key']);
        $this->assertTrue($result->isSuccess());
    }

    /** @test */
    public function authenticator_manager_handles_unknown_auth_types(): void
    {
        $manager = new AuthenticatorManager;

        $result = $manager->authenticate('unknown_type', []);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('No authenticator found', $result->getError());
    }

    /** @test */
    public function custom_authenticator_can_be_registered(): void
    {
        $customAuth = new class implements AuthenticatorInterface
        {
            public function handles(string $type): bool
            {
                return $type === 'custom';
            }

            public function authenticate(string $type, array $credentials): AuthenticationResult
            {
                if (($credentials['token'] ?? '') === 'custom-token') {
                    return AuthenticationResult::success('custom_client');
                }

                return AuthenticationResult::failure('Invalid custom token');
            }

            public function getClientId(string $type, array $credentials): ?string
            {
                return 'custom_client';
            }
        };

        $manager = new AuthenticatorManager;
        $manager->registerAuthenticator($customAuth);

        $result = $manager->authenticate('custom', ['token' => 'custom-token']);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals('custom_client', $result->getClientId());

        $result = $manager->authenticate('custom', ['token' => 'wrong-token']);
        $this->assertFalse($result->isSuccess());
    }

    /** @test */
    public function different_clients_have_different_permissions(): void
    {
        // Test api_client with tools.* permission
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => 'test-api-key',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $this->assertGreaterThan(0, count($data['result']['tools']));

        // Test secondary_client with limited permissions
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => 'another-key',
        ]);

        $response->assertStatus(200);
        $data = $response->json();
        $toolNames = array_column($data['result']['tools'], 'name');
        $this->assertContains('echo', $toolNames);
        // Should only have echo tool due to limited permissions
    }

    /** @test */
    public function authentication_result_success_methods(): void
    {
        $result = AuthenticationResult::success('test_client');

        $this->assertTrue($result->isSuccess());
        $this->assertEquals('test_client', $result->getClientId());
        $this->assertNull($result->getError());
    }

    /** @test */
    public function authentication_result_failure_methods(): void
    {
        $result = AuthenticationResult::failure('Test error message');

        $this->assertFalse($result->isSuccess());
        $this->assertNull($result->getClientId());
        $this->assertEquals('Test error message', $result->getError());
    }

    /** @test */
    public function it_handles_malformed_authorization_header(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'Authorization' => 'Malformed Header',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function it_handles_empty_credentials(): void
    {
        $response = $this->postJson('/api/mcp', [
            'jsonrpc' => '2.0',
            'method' => 'tools/list',
            'id' => 1,
        ], [
            'X-MCP-API-Key' => '',
        ]);

        $response->assertStatus(401);
    }
}
