<?php

namespace ChaoticIngenuity\LaravelMCP;

use ChaoticIngenuity\LaravelMCP\Contracts\ContextInterface;
use ChaoticIngenuity\LaravelMCP\Core\ContextFactory;
use ChaoticIngenuity\LaravelMCP\Core\Registry;

class MCPServer
{
    public function __construct(
        private Registry $registry,
        private ContextFactory $contextFactory
    ) {}

    public function handleRequest(array $request): array
    {
        $method = $request['method'] ?? '';
        $params = $request['params'] ?? [];
        $id = $request['id'] ?? null;

        // Get client context from middleware
        $clientId = request()->input('mcp_client', 'unknown');
        $context = $this->contextFactory->createFromClient($clientId);

        return match ($method) {
            'initialize' => $this->initialize($params, $id),
            'tools/list' => $this->listTools($context, $id),
            'tools/call' => $this->callTool($params, $context, $id),
            'resources/list' => $this->listResources($context, $id),
            'resources/read' => $this->readResource($params, $context, $id),
            'resources/templates/list' => $this->listResourceTemplates($context, $id),
            default => $this->errorResponse(-32601, 'Method not found', $id)
        };
    }

    private function initialize(array $params, $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => [],
                    'resources' => [
                        'subscribe' => false,
                        'listChanged' => false,
                    ],
                ],
                'serverInfo' => [
                    'name' => config('mcp.server.name', 'Laravel MCP Server'),
                    'version' => config('mcp.server.version', '1.0.0'),
                ],
            ],
            'id' => $id,
        ];
    }

    private function listTools(ContextInterface $context, $id): array
    {
        $tools = $this->registry->getAccessibleTools($context)
            ->map(function ($tool) {
                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => $tool->getInputSchema(),
                ];
            })
            ->values();

        return [
            'jsonrpc' => '2.0',
            'result' => ['tools' => $tools->toArray()],
            'id' => $id,
        ];
    }

    private function callTool(array $params, ContextInterface $context, $id): array
    {
        $toolName = $params['name'] ?? '';
        $arguments = $params['arguments'] ?? [];

        $tool = $this->registry->getTool($toolName);

        if (! $tool || ! $tool->isAccessibleTo($context)) {
            return $this->errorResponse(-32602, 'Tool not found or not accessible', $id);
        }

        try {
            $result = $tool->execute($arguments, $context);

            if (! $result->isSuccess()) {
                return $this->errorResponse(-32603, $result->getError(), $id);
            }

            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => is_string($result->getData()) ? $result->getData() : json_encode($result->getData(), JSON_PRETTY_PRINT),
                        ],
                    ],
                    'metadata' => $result->getMetadata(),
                ],
                'id' => $id,
            ];
        } catch (\Exception $e) {
            return $this->errorResponse(-32603, $e->getMessage(), $id);
        }
    }

    private function listResources(ContextInterface $context, $id): array
    {
        $resources = $this->registry->getAccessibleResources($context)
            ->filter(function ($resource) {
                return ! $resource->isTemplate();
            })
            ->map(function ($resource) {
                return [
                    'uri' => $resource->getUri(),
                    'name' => $resource->getName(),
                    'description' => $resource->getDescription(),
                    'mimeType' => $resource->getMimeType(),
                ];
            })
            ->values();

        return [
            'jsonrpc' => '2.0',
            'result' => ['resources' => $resources->toArray()],
            'id' => $id,
        ];
    }

    private function readResource(array $params, ContextInterface $context, $id): array
    {
        $uri = $params['uri'] ?? '';

        if (empty($uri)) {
            return $this->errorResponse(-32602, 'Missing uri parameter', $id);
        }

        $resource = $this->registry->getResource($uri);

        if (! $resource || ! $resource->isAccessibleTo($context)) {
            return $this->errorResponse(-32602, 'Resource not found or not accessible', $id);
        }

        try {
            $result = $resource->getContent($uri, $context);

            if (! $result->isSuccess()) {
                return $this->errorResponse(-32603, $result->getError(), $id);
            }

            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'contents' => [
                        [
                            'uri' => $uri,
                            'mimeType' => $resource->getMimeType(),
                            'text' => is_string($result->getData()) ? $result->getData() : json_encode($result->getData(), JSON_PRETTY_PRINT),
                        ],
                    ],
                    'metadata' => $result->getMetadata(),
                ],
                'id' => $id,
            ];
        } catch (\Exception $e) {
            return $this->errorResponse(-32603, $e->getMessage(), $id);
        }
    }

    private function listResourceTemplates(ContextInterface $context, $id): array
    {
        $templates = $this->registry->getAccessibleResources($context)
            ->filter(function ($resource) {
                return $resource->isTemplate();
            })
            ->map(function ($resource) {
                return [
                    'uriTemplate' => $resource->getUri(),
                    'name' => $resource->getName(),
                    'description' => $resource->getDescription(),
                    'mimeType' => $resource->getMimeType(),
                ];
            })
            ->values();

        return [
            'jsonrpc' => '2.0',
            'result' => ['resourceTemplates' => $templates->toArray()],
            'id' => $id,
        ];
    }

    private function errorResponse(int $code, string $message, $id): array
    {
        return [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'id' => $id,
        ];
    }
}
