<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface ToolInterface
{
  public function getName(): string;
  public function getDescription(): string;
  public function getInputSchema(): array;
  public function execute(array $arguments, ContextInterface $context): ResultInterface;
  public function isAccessibleTo(ContextInterface $context): bool;
}