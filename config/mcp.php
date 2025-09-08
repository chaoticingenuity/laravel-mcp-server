<?php

return [
  /*
  |--------------------------------------------------------------------------
  | Server Information
  |--------------------------------------------------------------------------
  */
  'server' => [
    'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
    'version' => env('MCP_SERVER_VERSION', '1.0.0'),
  ],

  /*
  |--------------------------------------------------------------------------
  | Routes Configuration
  |--------------------------------------------------------------------------
  */
  'routes' => [
    'enabled' => env('MCP_ROUTES_ENABLED', true),
    'prefix' => env('MCP_ROUTES_PREFIX', 'api'),
    'middleware' => ['api', 'mcp.auth'],
  ],

  /*
   |--------------------------------------------------------------------------
   | Authentication Configuration
   |--------------------------------------------------------------------------
   */
  'auth' => [
    'user_model' => [
      'class' => App\Models\User::class,
      'foreign_key' => env('MCP_USER_FOREIGN_KEY', 'user_id'),
      'owner_key' => env('MCP_USER_OWNER_KEY', 'id'),
    ],

    // Legacy static configuration (still supported)
    'api_keys' => array_filter([
      env('MCP_API_KEY_1'),
      env('MCP_API_KEY_2'),
    ]),

    'basic_auth' => array_filter([
      env('MCP_BASIC_USER_1') => env('MCP_BASIC_PASS_1'),
    ]),

    'bearer_tokens' => array_filter([
      env('MCP_BEARER_TOKEN_1'),
    ]),

    // Bouncer integration settings
    'bouncer' => [
      'enabled' => env('MCP_BOUNCER_ENABLED', false),
      'cache_abilities' => env('MCP_BOUNCER_CACHE_ABILITIES', true),
      'ability_prefix' => env('MCP_BOUNCER_ABILITY_PREFIX', 'mcp.'),
    ],

    // New custom authenticator classes
    'custom_authenticators' => [
      // Add custom authenticators here
      // \App\Services\Custom\MCP\Auth\DatabaseAuthenticator::class,
    ],

    // Performance settings
    'cache_duration' => (int) env('MCP_AUTH_CACHE_DURATION', 300), // 5 minutes
    'rate_limit_failed_attempts' => (int) env('MCP_AUTH_RATE_LIMIT', 10),
  ],
  /*
  |--------------------------------------------------------------------------
  | Client Configuration
  |--------------------------------------------------------------------------
  */
  'clients' => [
    'default' => [
      'permissions' => [
        'tools.echo',
        'resources.status',
      ],
      'field_access' => [],
      'metadata' => ['tier' => 'default']
    ],
  ],

  'api_key_clients' => [
    env('MCP_API_KEY_1') => env('MCP_CLIENT_1', 'client_1'),
    env('MCP_API_KEY_2') => env('MCP_CLIENT_2', 'client_2'),
    env('MCP_API_KEY_3') => env('MCP_CLIENT_3', 'client_3'),
  ],

  'token_clients' => [
    env('MCP_BEARER_TOKEN_1') => env('MCP_TOKEN_CLIENT_1', 'token_client_1'),
    env('MCP_BEARER_TOKEN_2') => env('MCP_TOKEN_CLIENT_2', 'token_client_2'),
  ],

  /*
|--------------------------------------------------------------------------
| Package Configuration
|--------------------------------------------------------------------------
*/
  'package' => [
    'enable_echo_tool' => env('MCP_ENABLE_ECHO_TOOL', true),
    'enable_status_resource' => env('MCP_ENABLE_STATUS_RESOURCE', true),
  ],

  /*
  |--------------------------------------------------------------------------
  | Security Configuration
  |--------------------------------------------------------------------------
  */
  'security' => [
    'require_https' => env('MCP_REQUIRE_HTTPS', true),
    'allowed_ips' => array_filter(explode(',', env('MCP_ALLOWED_IPS', ''))),
    'block_suspicious_user_agents' => env('MCP_BLOCK_SUSPICIOUS_UA', true),
  ],

  /*
  |--------------------------------------------------------------------------
  | Rate Limiting
  |--------------------------------------------------------------------------
  */
  'rate_limit' => [
    'requests_per_minute' => (int) env('MCP_RATE_LIMIT', 60),
    'burst_limit' => (int) env('MCP_BURST_LIMIT', 10),
    'per_client_limits' => env('MCP_PER_CLIENT_LIMITS', true),
  ],

  /*
  |--------------------------------------------------------------------------
  | Logging Configuration
  |--------------------------------------------------------------------------
  */

  'logging' => [
    'channel' => env('MCP_LOG_CHANNEL', 'mcp'),
    'level' => env('MCP_LOG_LEVEL', 'info'),
    'log_requests' => env('MCP_LOG_REQUESTS', true),
    'log_responses' => env('MCP_LOG_RESPONSES', false),
    'log_performance' => env('MCP_LOG_PERFORMANCE', true),
  ],

  /*
  |--------------------------------------------------------------------------
  | Custom Tools and Resources
  |--------------------------------------------------------------------------
  */
  'custom' => [
    'tools' => [
      // Add your custom tool classes here
      // \App\Services\Custom\MCP\Tools\YourCustomTool::class,
    ],
    'resources' => [
      // Add your custom resource classes here
      // \App\Services\Custom\MCP\Resources\YourCustomResource::class,
    ],
  ],

  /*
  |--------------------------------------------------------------------------
  | Debug Configuration
  |--------------------------------------------------------------------------
  */
  'debug' => [
    'expose_system_info' => env('MCP_DEBUG_EXPOSE_SYSTEM_INFO', false),
    'detailed_error_messages' => env('MCP_DEBUG_DETAILED_ERRORS', false),
    'log_all_requests' => env('MCP_DEBUG_LOG_REQUESTS', false),
  ],
];