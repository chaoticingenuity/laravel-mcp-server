# Contributing to Laravel MCP Server

Thank you for your interest in contributing to Laravel MCP Server! This document provides guidelines and information for contributors.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Setup](#development-setup)
- [Making Changes](#making-changes)
- [Testing](#testing)
- [Code Standards](#code-standards)
- [Pull Request Process](#pull-request-process)
- [Release Process](#release-process)
- [Getting Help](#getting-help)

## Code of Conduct

This project adheres to a code of conduct. By participating, you are expected to uphold this code:

- **Be respectful**: Treat all community members with respect and kindness
- **Be inclusive**: Welcome newcomers and encourage diverse perspectives
- **Be collaborative**: Work together constructively and assume good intentions
- **Be professional**: Keep discussions focused and constructive

## Getting Started

### Prerequisites

- PHP 8.1 or higher
- Composer
- Laravel 10.x or 11.x knowledge
- Git
- Understanding of MCP (Model Context Protocol)

### Types of Contributions

We welcome various types of contributions:

- 🐛 **Bug Reports**: Help us identify issues
- 🚀 **Feature Requests**: Suggest new functionality
- 📝 **Documentation**: Improve guides, examples, and API docs
- 🧪 **Tests**: Add test coverage or improve existing tests
- 🔧 **Code**: Fix bugs, implement features, optimize performance
- 💡 **Ideas**: Participate in discussions about the project direction

## Development Setup

### 1. Fork and Clone

```bash
# Fork the repository on GitHub
# Then clone your fork
git clone https://github.com/YOUR-USERNAME/laravel-mcp-server.git
cd laravel-mcp-server
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Set Up Testing Environment

```bash
# Copy environment file for testing
cp .env.example .env

# Run tests to ensure everything is working
composer test
```

### 4. Optional: Install Bouncer for Testing

```bash
# For testing Bouncer integration
composer require silber/bouncer --dev
```

## Making Changes

### Branch Naming

Use descriptive branch names:

```bash
# For features
git checkout -b feature/add-webhook-support

# For bug fixes
git checkout -b fix/authentication-middleware-issue

# For documentation
git checkout -b docs/update-bouncer-examples

# For refactoring
git checkout -b refactor/optimize-registry-performance
```

### Commit Messages

Follow [Conventional Commits](https://www.conventionalcommits.org/):

```bash
# Format: type(scope): description
feat(auth): add webhook authentication support
fix(middleware): resolve rate limiting edge case
docs(readme): update Bouncer integration examples
test(registry): add performance benchmarks
refactor(core): optimize template URI matching
```

**Types:**
- `feat`: New features
- `fix`: Bug fixes
- `docs`: Documentation changes
- `test`: Adding/updating tests
- `refactor`: Code refactoring
- `perf`: Performance improvements
- `style`: Code style changes (formatting)
- `chore`: Maintenance tasks

## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
vendor/bin/phpunit tests/Feature/
vendor/bin/phpunit tests/Unit/

# Run with coverage
composer test-coverage

# Run specific test file
vendor/bin/phpunit tests/Feature/MCPServerTest.php
```

### Writing Tests

#### Test Structure

```php
<?php

namespace ChaoticIngenuity\LaravelMCP\Tests\Feature;

use ChaoticIngenuity\LaravelMCP\Tests\TestCase;

class YourFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Set up test configuration
        config([
            'mcp.test_setting' => 'test_value'
        ]);
    }

    /** @test */
    public function it_does_something_useful(): void
    {
        // Arrange
        $input = 'test input';
        
        // Act
        $result = $this->doSomething($input);
        
        // Assert
        $this->assertEquals('expected output', $result);
    }
}
```

#### Test Guidelines

- **Use descriptive test names**: `it_returns_error_for_invalid_api_key`
- **Follow AAA pattern**: Arrange, Act, Assert
- **Test edge cases**: Invalid inputs, boundary conditions
- **Mock external dependencies**: Don't rely on external services
- **Test both success and failure paths**
- **Include performance tests** for critical operations

#### Test Categories

1. **Unit Tests** (`tests/Unit/`):
   - Test individual classes/methods in isolation
   - Mock dependencies
   - Fast execution

2. **Feature Tests** (`tests/Feature/`):
   - Test complete workflows
   - Integration testing
   - HTTP requests and responses

3. **Integration Tests**:
   - Test package integration with Laravel
   - Bouncer integration scenarios
   - Database interactions

### Test Requirements

For new features, include:
- ✅ Unit tests for core logic
- ✅ Feature tests for HTTP endpoints
- ✅ Edge case handling
- ✅ Error scenarios
- ✅ Performance considerations (if applicable)

## Code Standards

### PSR-12 Compliance

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards:

```bash
# Format code using Laravel Pint
composer format

# Check formatting
vendor/bin/pint --test
```

### EditorConfig

Use the provided `.editorconfig` file to maintain consistent formatting:
- 4 spaces for PHP indentation
- UTF-8 encoding
- LF line endings
- Trim trailing whitespace

### Code Quality

#### Static Analysis

```bash
# We recommend using PHPStan or Psalm for static analysis
composer analyse  # if configured
```

#### Code Style Guidelines

- **Use type hints** for all method parameters and return types
- **Write docblocks** for public methods and classes
- **Keep methods small** and focused on single responsibility
- **Use meaningful variable and method names**
- **Follow Laravel conventions** for naming and structure

#### Example Code Style

```php
<?php

namespace ChaoticIngenuity\LaravelMCP\Tools;

use ChaoticIngenuity\LaravelMCP\Contracts\{ToolInterface, ContextInterface, ResultInterface};
use ChaoticIngenuity\LaravelMCP\Core\Result;

/**
 * Example tool demonstrating proper code style
 */
class ExampleTool implements ToolInterface
{
    /**
     * Execute the tool with given arguments
     *
     * @param array<string, mixed> $arguments
     */
    public function execute(array $arguments, ContextInterface $context): ResultInterface
    {
        $message = $arguments['message'] ?? '';
        
        if (empty($message)) {
            return Result::error('Message is required');
        }
        
        return Result::success([
            'processed_message' => $this->processMessage($message),
            'timestamp' => now()->toISOString(),
        ]);
    }
    
    /**
     * Process the message according to business logic
     */
    private function processMessage(string $message): string
    {
        return ucfirst(trim($message));
    }
}
```

### Security Guidelines

- **Never commit sensitive data** (API keys, passwords, tokens)
- **Validate all input** in tools and resources
- **Use Laravel's security features** (CSRF, validation, sanitization)
- **Follow principle of least privilege** for permissions
- **Sanitize output** to prevent XSS attacks

## Pull Request Process

### Before Submitting

1. **Ensure tests pass**: `composer test`
2. **Check code formatting**: `composer format`
3. **Update documentation** if needed
4. **Add/update tests** for your changes
5. **Test manually** if applicable

### Pull Request Template

When creating a PR, include:

```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] New tests added for changes
- [ ] Manual testing completed

## Checklist
- [ ] Code follows PSR-12 standards
- [ ] Self-review of code completed
- [ ] Documentation updated
- [ ] No breaking changes (or properly documented)
```

### Review Process

1. **Automated Checks**: Tests and code style must pass
2. **Code Review**: At least one maintainer review required
3. **Manual Testing**: Complex features tested manually
4. **Documentation Review**: Ensure docs are updated
5. **Merge**: Squash and merge preferred for clean history

## Documentation

### Types of Documentation

1. **README.md**: Main package documentation
2. **API Documentation**: Inline docblocks and generated docs
3. **Examples**: Practical usage examples in `/examples/`
4. **Guides**: Step-by-step tutorials
5. **Migration Guides**: Version upgrade instructions

### Documentation Standards

- **Clear and concise**: Easy to understand for developers
- **Code examples**: Include working code snippets
- **Up-to-date**: Keep synchronized with code changes
- **Accessible**: Use clear headings and structure
- **Tested**: Ensure code examples actually work

### Writing Style

- Use active voice
- Write in second person ("you can...")
- Include code examples for complex concepts
- Link to relevant Laravel/PHP documentation
- Use markdown formatting consistently

## Release Process

### Versioning

This project follows [Semantic Versioning](https://semver.org/):

- **MAJOR**: Breaking changes
- **MINOR**: New features (backward compatible)
- **PATCH**: Bug fixes (backward compatible)

### Release Checklist

1. **Update CHANGELOG.md**
2. **Run full test suite**
3. **Update version in composer.json**
4. **Tag release**
5. **Update documentation**
6. **Announce release**

## Getting Help

### Where to Ask Questions

1. **GitHub Discussions**: General questions and ideas
2. **GitHub Issues**: Bug reports and feature requests  
3. **Discord/Slack**: Real-time community chat (if available)

### When Creating Issues

**For Bug Reports:**
- Laravel version
- PHP version  
- Package version
- Steps to reproduce
- Expected vs actual behavior
- Error messages/logs

**For Feature Requests:**
- Clear description of the feature
- Use case/motivation
- Proposed API (if applicable)
- Consider backward compatibility

### Contributing Documentation

Documentation improvements are always welcome:

- Fix typos and grammar
- Add missing examples
- Improve clarity and organization
- Add translations (if applicable)

## Development Tips

### Useful Commands

```bash
# Development workflow
composer test        # Run tests
composer format      # Format code
composer analyse     # Static analysis (if configured)

# Testing specific areas
vendor/bin/phpunit tests/Feature/MCPServerTest.php
vendor/bin/phpunit --filter="authentication"

# Debugging
php artisan mcp:setup --bouncer  # Test setup command
```

### IDE Setup

Recommended IDE settings:
- Enable EditorConfig support
- Install PHP CS Fixer extension
- Configure PSR-12 formatting
- Enable PHPDoc generation

### Common Patterns

#### Creating New Tools

```php
// 1. Create tool class implementing ToolInterface
// 2. Add comprehensive tests
// 3. Update documentation with usage examples
// 4. Consider permission requirements
```

#### Adding Configuration Options

```php
// 1. Add to config/mcp.php with sensible defaults
// 2. Add environment variable support
// 3. Update documentation
// 4. Add validation in service provider
```

## Thank You!

Your contributions make Laravel MCP Server better for everyone. Whether you're fixing bugs, adding features, improving documentation, or helping other users, every contribution is valuable.

Questions about contributing? Open a discussion or reach out to the maintainers.

---

**Happy coding! 🚀**