<?php

namespace ChaoticIngenuity\LaravelMCP\Tools;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResultInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ToolInterface;
use ChaoticIngenuity\LaravelMCP\Core\Result;

class EchoTool implements ToolInterface
{
    public function getName(): string
    {
        return 'echo';
    }

    public function getDescription(): string
    {
        return 'Echo back the input message (core MCP tool)';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'message' => [
                    'type' => 'string',
                    'description' => 'The message to echo back',
                ],
            ],
            'required' => ['message'],
        ];
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        return config('mcp.package.enable_echo_tool', true) &&
          ($context->hasPermission('tools.echo') ||
            $context->hasPermission('tools.*') ||
            $context->hasPermission('admin'));
    }

    public function execute(array $arguments, ContextInterface $context): ResultInterface
    {
        $message = $arguments['message'] ?? 'No message provided';

        return Result::success("Echo: {$message}", [
            'client_id' => $context->getClientId(),
            'executed_at' => now()->toISOString(),
            'tool_version' => '1.0.0',
        ]);
    }
}
