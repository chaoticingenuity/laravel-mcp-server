<?php

namespace ChaoticIngenuity\LaravelMCP\Contracts;

interface ResultInterface
{
  public function getData(): mixed;
  public function isSuccess(): bool;
  public function getError(): ?string;
  public function getMetadata(): array;
  public function toArray(): array;
}