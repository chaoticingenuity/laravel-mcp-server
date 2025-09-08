<?php

namespace App\Services\Custom\MCP\Tools;

use ChaoticIngenuity\LaravelMCP\Contracts\{ToolInterface, ContextInterface, ResultInterface};
use ChaoticIngenuity\LaravelMCP\Core\Result;

class CustomMCPTool implements ToolInterface
{
  public function getName(): string
  {
    return 'custom_tool';
  }

  public function getDescription(): string
  {
    return 'Description of your custom tool';
  }

  public function getInputSchema(): array
  {
    return [
      'type' => 'object',
      'properties' => [
        'param' => [
          'type' => 'string',
          'description' => 'Parameter description'
        ]
      ],
      'required' => ['param']
    ];
  }

  public function isAccessibleTo(ContextInterface $context): bool
  {
    return $context->hasPermission('tools.custom_tool') ||
      $context->hasPermission('admin');
  }

  public function execute(array $arguments, ContextInterface $context): ResultInterface
  {
    // Implement your custom logic here
    $param = $arguments['param'] ?? '';

    return Result::success([
      'result' => "Custom tool executed with: {$param}",
      'client_id' => $context->getClientId()
    ]);
  }
}