<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface ResourceInterface
{
  public function getUri(): string;
  public function getName(): string;
  public function getDescription(): string;
  public function getMimeType(): string;
  public function isTemplate(): bool;
  public function getContent(string $uri, ContextInterface $context): ResultInterface;
  public function isAccessibleTo(ContextInterface $context): bool;
  public function getAccessibleFields(ContextInterface $context): array;
}