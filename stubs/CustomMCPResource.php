<?php

namespace App\Services\Custom\MCP\Resources;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResourceInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResultInterface;
use ChaoticIngenuity\LaravelMCP\Core\Result;

class CustomMCPResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'custom://resource';
    }

    public function getName(): string
    {
        return 'Custom Resource';
    }

    public function getDescription(): string
    {
        return 'Description of your custom resource';
    }

    public function getMimeType(): string
    {
        return 'application/json';
    }

    public function isTemplate(): bool
    {
        return false;
    }

    public function isAccessibleTo(ContextInterface $context): bool
    {
        return $context->hasPermission('resources.custom') ||
          $context->hasPermission('admin');
    }

    public function getAccessibleFields(ContextInterface $context): array
    {
        return ['*'];
    }

    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        // Implement your custom resource logic here
        $data = [
            'message' => 'Custom resource content',
            'client_id' => $context->getClientId(),
            'timestamp' => now()->toISOString(),
        ];

        return Result::success($data);
    }
}
