<?php

namespace ChaoticIngenuity\LaravelMCP\Core;

use ChaoticIngenuity\LaravelMCP\Contracts\ResultInterface;

class Result implements ResultInterface
{
  public function __construct(
    private readonly mixed $data,
    private readonly bool $success = true,
    private readonly ?string $error = null,
    private readonly array $metadata = []
  ) {
  }

  public static function success(mixed $data, array $metadata = []): self
  {
    return new self($data, true, null, $metadata);
  }

  public static function error(string $error, array $metadata = []): self
  {
    return new self(null, false, $error, $metadata);
  }

  public function getData(): mixed
  {
    return $this->data;
  }

  public function isSuccess(): bool
  {
    return $this->success;
  }

  public function getError(): ?string
  {
    return $this->error;
  }

  public function getMetadata(): array
  {
    return $this->metadata;
  }

  public function toArray(): array
  {
    return [
      'success' => $this->success,
      'data' => $this->data,
      'error' => $this->error,
      'metadata' => $this->metadata
    ];
  }
}