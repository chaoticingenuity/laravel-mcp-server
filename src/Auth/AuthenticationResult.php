<?php

namespace ChaoticIngenuity\LaravelMCP\Auth;

class AuthenticationResult
{
    public function __construct(
        private readonly bool $success,
        private readonly ?string $clientId = null,
        private readonly ?string $errorMessage = null,
        private readonly array $metadata = []
    ) {}

    public static function success(string $clientId, array $metadata = []): self
    {
        return new self(true, $clientId, null, $metadata);
    }

    public static function failure(string $errorMessage = 'Authentication failed'): self
    {
        return new self(false, null, $errorMessage);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getError(): ?string
    {
        return $this->errorMessage;
    }
}
