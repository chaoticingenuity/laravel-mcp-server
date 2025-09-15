<?php

return [
    /**
     * |--------------------------------------------------------------------------
     * | Server Information
     * |--------------------------------------------------------------------------
     */
    'server' => [
        'name' => env('MCP_SERVER_NAME', 'Laravel MCP Server'),
        'version' => env('MCP_SERVER_VERSION', '1.1.0'),
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Routes Configuration
     * |--------------------------------------------------------------------------
     */
    'routes' => [
        'enabled' => env('MCP_ROUTES_ENABLED', true),
        'prefix' => env('MCP_ROUTES_PREFIX', 'api'),
        'middleware' => ['api', 'mcp.auth'],
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Authentication Configuration
     * |--------------------------------------------------------------------------
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

        /**
         * |--------------------------------------------------------------------------
         * | Client Configuration
         * |--------------------------------------------------------------------------
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

        // Custom permission resolver classes (evaluated in order of priority)
        'custom_permission_resolvers' => [
            // Add custom permission resolvers here
            // \App\Services\Custom\MCP\Auth\CustomPermissionResolver::class,
        ],

        // API key usage logging
        'log_api_usage' => env('MCP_LOG_API_USAGE', false),

        // Performance settings
        'cache_duration' => (int) env('MCP_AUTH_CACHE_DURATION', 300), // 5 minutes
        'rate_limit_failed_attempts' => (int) env('MCP_AUTH_RATE_LIMIT', 10),
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Package Configuration
     * |--------------------------------------------------------------------------
     */
    'package' => [
        // Legacy component flags (DEPRECATED - use core_tools/core_resources arrays instead)
        // Will be removed in v2.0.0
        'enable_echo_tool' => env('MCP_ENABLE_ECHO_TOOL', true),
        'enable_status_resource' => env('MCP_ENABLE_STATUS_RESOURCE', true),

        // Auto-registration behavior
        'auto_register_core_components' => env('MCP_AUTO_REGISTER_CORE', true),

        // Core tool classes (preferred method - override/extend as needed)
        // Remove tools from array to disable, add custom tools to enable
        'core_tools' => [
            \ChaoticIngenuity\LaravelMCP\Tools\EchoTool::class,
        ],

        // Core resource classes (preferred method - override/extend as needed)
        // Remove resources from array to disable, add custom resources to enable
        'core_resources' => [
            \ChaoticIngenuity\LaravelMCP\Resources\StatusResource::class,
        ],
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Security Configuration
     * |--------------------------------------------------------------------------
     */
    'security' => [
        'require_https' => env('MCP_REQUIRE_HTTPS', true),
        'allowed_ips' => array_filter(explode(',', env('MCP_ALLOWED_IPS', ''))),
        'block_suspicious_user_agents' => env('MCP_BLOCK_SUSPICIOUS_UA', true),
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Rate Limiting
     * |--------------------------------------------------------------------------
     */
    'rate_limit' => [
        'requests_per_minute' => (int) env('MCP_RATE_LIMIT', 60),
        'burst_limit' => (int) env('MCP_BURST_LIMIT', 10),
        'per_client_limits' => env('MCP_PER_CLIENT_LIMITS', true),
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Logging Configuration
     * |--------------------------------------------------------------------------
     */

    'logging' => [
        'channel' => env('MCP_LOG_CHANNEL', 'mcp'),
        'level' => env('MCP_LOG_LEVEL', 'info'),
        'log_requests' => env('MCP_LOG_REQUESTS', true),
        'log_responses' => env('MCP_LOG_RESPONSES', false),
        'log_performance' => env('MCP_LOG_PERFORMANCE', true),
    ],

    /**
     * |--------------------------------------------------------------------------
     * | Custom Tools and Resources
     * |--------------------------------------------------------------------------
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
    | Performance Configuration
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'track_permissions' => env('MCP_TRACK_PERMISSIONS', false),
        'cache_permission_results' => env('MCP_CACHE_PERMISSION_RESULTS', true),
        'permission_cache_ttl' => env('MCP_PERMISSION_CACHE_TTL', 300),
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

    /*
    |--------------------------------------------------------------------------
    | Service Container Configuration
    |--------------------------------------------------------------------------
    */
    'services' => [
        // Custom service implementations (leave null for defaults)
        'registry_class' => null, // \App\Services\Custom\MCP\CustomRegistry::class,
        'context_class' => null,  // \App\Services\Custom\MCP\CustomContext::class,
        'permission_manager_class' => null, // Override default permission manager
        'authenticator_manager_class' => null, // Override authenticator manager

        // Service binding behavior
        'singleton_services' => true, // Whether to bind services as singletons
        'lazy_load_services' => false, // Whether to defer service loading
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'strict_config_validation' => env('MCP_STRICT_CONFIG_VALIDATION', true),
        'require_server_info' => env('MCP_REQUIRE_SERVER_INFO', true),
        'validate_bouncer_setup' => env('MCP_VALIDATE_BOUNCER_SETUP', true),
    ],
];
