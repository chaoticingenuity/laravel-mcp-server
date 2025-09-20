<?php

namespace ChaoticIngenuity\LaravelMCP\Resources;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResourceInterface;
use ChaoticIngenuity\LaravelMCP\Contracts\ResultInterface;
use ChaoticIngenuity\LaravelMCP\Core\Registry;
use ChaoticIngenuity\LaravelMCP\Core\Result;

class StatusResource implements ResourceInterface
{
    public function getUri(): string
    {
        return 'system://status';
    }

    public function getName(): string
    {
        return 'MCP Server Status';
    }

    public function getDescription(): string
    {
        return 'Current status and configuration of the MCP server';
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
        return config('mcp.package.enable_status_resource', true) &&
          ($context->hasPermission('resources.status') ||
            $context->hasPermission('admin'));
    }

    public function getAccessibleFields(ContextInterface $context): array
    {
        return ['*'];
    }

    public function getContent(string $uri, ContextInterface $context): ResultInterface
    {
        $registry = app(Registry::class);

        $status = [
            'server' => [
                'name' => config('mcp.server.name'),
                'version' => config('mcp.server.version'),
                'status' => 'operational',
                'package_version' => '1.0.0',
            ],
            'capabilities' => [
                'tools' => $registry->getAccessibleTools($context)->count(),
                'resources' => $registry->getAccessibleResources($context)->count(),
                'supports_templates' => true,
                'supports_subscriptions' => false,
            ],
            'client_info' => [
                'client_id' => $context->getClientId(),
                'permissions' => $this->getPermissionsSummary($context),
                'access_level' => $this->getAccessLevel($context),
            ],
            'timestamp' => now()->toISOString(),
        ];

        return Result::success($status);
    }

    private function getAccessLevel(ContextInterface $context): string
    {
        if ($context->hasPermission('admin')) {
            return 'admin';
        }
        if (count($context->getPermissions()) > 5) {
            return 'full';
        }

        return 'limited';
    }

    private function getPermissionsSummary(ContextInterface $context): array
    {
        $summary = [
            'tools' => [],
            'resources' => [],
            'entities' => [],
            'admin' => false,
        ];

        foreach ($context->getPermissions() as $permission) {
            if ($permission === 'admin' || $permission === '*') {
                $summary['admin'] = true;
            } elseif (str_starts_with($permission, 'tools.')) {
                $summary['tools'][] = str_replace('tools.', '', $permission);
            } elseif (str_starts_with($permission, 'resources.')) {
                $summary['resources'][] = str_replace('resources.', '', $permission);
            } elseif (str_contains($permission, '.')) {
                [$entity, $action] = explode('.', $permission, 2);
                if (! isset($summary['entities'][$entity])) {
                    $summary['entities'][$entity] = [];
                }
                $summary['entities'][$entity][] = $action;
            }
        }

        return $summary;
    }
}
