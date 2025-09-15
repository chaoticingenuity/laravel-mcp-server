# Changelog

All notable changes to Laravel MCP Server will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-01-15

### 🚀 New Features

#### Maximum Configurability & Customization
- **🔧 Service Container Overrides**: Replace any core service (Registry, Context, PermissionManager) with custom implementations
- **⚙️ Configuration Validation Controls**: Disable package validation requirements (`MCP_STRICT_CONFIG_VALIDATION=false`)
- **🎯 Core Component Arrays**: New `core_tools` and `core_resources` config arrays for maximum flexibility
- **📦 Minimal Footprint Options**: Disable auto-registration (`MCP_AUTO_REGISTER_CORE=false`) for custom-only setups
- **🚀 Enterprise Features**: Advanced security, monitoring, performance optimization, and high availability features

#### Resource Relationship Field Access
- **🎯 Contextual field permissions**: Field access now supports user relationships to specific resource instances
- **🔄 Dynamic field resolution**: Different fields accessible based on ownership, team membership, subscription tiers, etc.
- **📋 Multiple merge strategies**: Union, intersection, and override strategies for combining field sets
- **⚡ Performance optimized**: Efficient relationship checking with built-in caching support
- **🔧 Fully customizable**: Interface-based architecture allows any relationship logic implementation
- **🔄 Backward compatible**: Works alongside existing static field access system

#### Core Components Added
- `ResourceRelationshipInterface` - Define custom relationship logic
- `FieldSetResolverInterface` - Control field resolution strategies  
- `BaseFieldSetResolver` - Built-in resolver with common functionality
- Enhanced `ContextInterface` with resource-specific field access methods

#### Documentation & Examples
- Comprehensive implementation guide with real-world examples
- Example relationships: database ownership, team membership, subscription tiers
- Performance considerations and caching strategies
- Complete test coverage (11+ test scenarios)

### ✨ Enhancements
- Enhanced `Context` class with resource-relationship field access methods
- Extended test suite with comprehensive resource relationship field access tests
- Added example implementations for common use cases

### ⚠️ Deprecations
- **Individual Component Flags**: `enable_echo_tool` and `enable_status_resource` config flags are deprecated in favor of `core_tools` and `core_resources` arrays
- **Migration Path**: Both methods work in v1.x for backward compatibility, but individual flags will be removed in v2.0
- **Recommended Action**: Update configuration to use the new array-based approach for more flexibility

### 📚 Documentation
- Added [Advanced Customization Guide](docs/ADVANCED_CUSTOMIZATION.md) - Complete guide to maximum configurability
- Added [Feature Coverage Documentation](docs/FEATURE_COVERAGE.md) - Comprehensive overview of all 100+ features
- Added [Resource Relationship Field Access Guide](docs/RESOURCE_RELATIONSHIP_FIELD_ACCESS.md)
- Enhanced README with "Advanced Features" section highlighting configurability philosophy
- Updated examples directory with practical implementations

## [1.0.0] - 2025-09-05

### 🎉 Initial Release

Laravel MCP Server v1.0.0 is the first stable release, providing a comprehensive implementation of the Model Context Protocol (MCP) for Laravel applications.

### ✨ Added

#### Core Features
- **Full MCP 2024-11-05 protocol implementation** with JSON-RPC 2.0 support
- **Tool and Resource system** with auto-discovery and registration
- **Template resources** with parameter extraction and URI pattern matching
- **Fine-grained permission system** with field-level access control
- **Multiple authentication methods**: API keys, Basic Auth, Bearer tokens
- **Custom authentication system** with database integration support

#### Permission Management
- **🆕 Optional Bouncer integration** for advanced role-based permissions
- **🔄 Permission Manager architecture** with pluggable system design
- **🛡️ Enhanced security** with improved authentication flow
- **⚡ Auto-detection** of Bouncer package with graceful fallback

#### Performance & Optimization
- **⚡ Registry optimizations** with compiled pattern caching for template URIs
- **📊 Performance benchmarks** ensuring 1000 template matches complete in <100ms
- **💾 Memory optimization** keeping 1000 tool/resource registrations under 5MB
- **🚀 Authentication caching** for optimal permission lookup performance

#### Developer Experience
- **🚀 MCP Setup Command** (`php artisan mcp:setup --bouncer`) for easy configuration
- **📊 Comprehensive test suite** with 74 tests covering all major functionality
- **🎛️ Flexible authentication** with multiple API key storage patterns
- **📚 Rich examples** for both basic usage and Bouncer integration
- **✨ PSR-12 compliance** with .editorconfig for consistent formatting

#### Security & Middleware
- **Comprehensive security middleware stack**:
  - `MCPSecurityMiddleware`: HTTPS enforcement, IP whitelisting, security headers
  - `MCPAuthMiddleware`: Multi-method authentication with custom authenticators
  - `MCPThrottleMiddleware`: Rate limiting with per-client and burst controls
  - `MCPLoggingMiddleware`: Request/response logging with performance metrics
- **Rate limiting** with per-client controls and burst protection
- **IP whitelisting** with CIDR notation support
- **Security headers** and HTTPS enforcement

#### Testing & Quality
- **📊 Comprehensive test coverage** across 5 test suites:
  - `MCPServerTest`: Core MCP protocol implementation (15 tests)
  - `AuthenticationTest`: All authentication methods and edge cases (20 tests)
  - `PermissionManagerTest`: Permission system and Bouncer integration (12 tests)
  - `BouncerIntegrationTest`: Package detection and fallback behavior (12 tests)
  - `RegistryTest`: Performance, caching, and functionality (15 tests)
- **Performance validation** with automated benchmarks
- **Security testing** for authentication and authorization
- **Integration testing** for HTTP requests and middleware

#### Documentation & Examples
- **📝 Comprehensive documentation** with step-by-step guides
- **🔧 Installation guides** for Laravel 10 and 11
- **💼 Usage examples** for custom tools and resources
- **🏗️ Architecture documentation** explaining the system design
- **🚀 Migration guides** for upgrading between versions

### 🛠️ Technical Implementation

#### Architecture
- **Registry system** for tools and resources with access control filtering
- **Context factory** for client permission management
- **Result system** for standardized response handling
- **Service provider** with auto-registration and configuration validation

#### Laravel Integration
- **Laravel 10+ and 11+ support** with version-specific optimizations
- **Service container integration** with singleton pattern for performance
- **Middleware integration** with automatic registration for Laravel 10
- **Route integration** with customizable prefix and middleware stack
- **Configuration system** with environment variable support

#### MCP Protocol Compliance
- **Initialize method** with server capabilities and information
- **Tools system**: `tools/list` and `tools/call` with permission filtering
- **Resources system**: `resources/list`, `resources/read`, `resources/templates/list`
- **Error handling** with standardized JSON-RPC 2.0 error codes
- **Template resources** with URI parameter extraction

### 📚 Documentation

#### New Documentation Files
- **README.md**: Comprehensive package documentation with examples
- **UPGRADE.md**: Version migration guide with step-by-step instructions
- **CONTRIBUTING.md**: Development guidelines and contribution process
- **CHANGELOG.md**: Version history and change documentation
- **.editorconfig**: PSR-12 compliant formatting configuration

#### Example Files
- **Basic examples**: Traditional Laravel authentication and permissions
- **Bouncer examples**: Advanced role-based permission management
- **Migration examples**: Database schema for different storage patterns
- **Stub files**: Templates for creating custom tools and resources

### 🔧 Configuration

#### Environment Variables
```env
# Server Configuration
MCP_SERVER_NAME="Laravel MCP Server"
MCP_SERVER_VERSION="1.0.0"

# Bouncer Integration (New in v1.0.0)
MCP_BOUNCER_ENABLED=false
MCP_BOUNCER_CACHE_ABILITIES=true
MCP_BOUNCER_ABILITY_PREFIX=mcp.

# Authentication
MCP_API_KEY_1=your-api-key
MCP_CLIENT_1=your-client-id

# Security
MCP_REQUIRE_HTTPS=true
MCP_ALLOWED_IPS=192.168.1.0/24

# Rate Limiting
MCP_RATE_LIMIT=60
MCP_BURST_LIMIT=10

# Logging
MCP_LOG_REQUESTS=true
MCP_LOG_PERFORMANCE=true
```

### 🚀 Performance Metrics

The v1.0.0 release includes validated performance benchmarks:

| Operation | Benchmark | Status |
|-----------|-----------|---------|
| Template URI Matching | 1000 matches < 100ms | ✅ Optimized |
| Memory Usage | 1000 registrations < 5MB | ✅ Efficient |
| Authentication Cache | Sub-millisecond lookups | ✅ Cached |
| Registry Operations | Constant time access | ✅ Optimized |

### 📦 Dependencies

#### Required
- `php`: `^8.1`
- `illuminate/contracts`: `^10.0|^11.0`
- `illuminate/support`: `^10.0|^11.0`
- `illuminate/http`: `^10.0|^11.0`
- `illuminate/database`: `^10.0|^11.0`

#### Suggested
- `silber/bouncer`: `^1.0` - Enhanced role and ability management for MCP permissions

### 🛡️ Security

This release has been designed with security as a primary concern:

- **Input validation** on all tool and resource parameters
- **Permission-based access control** with fine-grained field access
- **Rate limiting** to prevent abuse
- **IP whitelisting** for network-level security
- **Authentication middleware** with multiple validation methods
- **Security headers** for enhanced protection
- **No sensitive data exposure** in error messages

### 🧪 Testing

The package includes comprehensive test coverage:

```bash
# Run all 74 tests
composer test

# Coverage by test suite:
# - Core functionality: 15 tests
# - Authentication: 20 tests  
# - Permission management: 12 tests
# - Bouncer integration: 12 tests
# - Registry performance: 15 tests
```

### 📋 Compatibility

| Environment | Version | Support |
|-------------|---------|---------|
| PHP | 8.1+ | ✅ Full Support |
| Laravel | 10.x | ✅ Full Support |
| Laravel | 11.x | ✅ Full Support |
| Bouncer | 1.x | ✅ Optional Integration |

### 🚀 Getting Started

```bash
# Install the package
composer require chaoticingenuity/laravel-mcp-server

# Basic setup
php artisan vendor:publish --tag=mcp-config
php artisan mcp:setup

# With Bouncer (optional)
composer require silber/bouncer
php artisan mcp:setup --bouncer
```

### 📖 What's Next

Future releases will focus on:
- Additional authentication providers
- Enhanced monitoring and observability
- Performance optimizations
- Extended MCP protocol features
- Community-requested features

---

**Full Changelog**: https://github.com/chaoticingenuity/laravel-mcp-server/commits/v1.0.0
